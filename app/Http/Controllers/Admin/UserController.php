<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Http\Resources\UserResource;
use App\Models\ImpactCell;
use App\Models\User;
use App\Rules\ImpactCellHasNoLiveLeader;
use App\Support\RoleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 06e+1 + 06e+2 + 06e+3 — Admin UserController.
 *
 * Endpoints (all admin-only via UserPolicy; the 4 routes that render
 * Inertia output are additionally gated by `gate.stubs`):
 *
 *   GET    /admin/users                → index     (paginated table; ?filter=trashed)
 *   POST   /admin/users                → store     (Add User modal)
 *   GET    /admin/users/{user}/edit    → edit      (Edit page)
 *   PUT    /admin/users/{user}         → update    (Edit form submit; incl. assertSelfCannotDemote)
 *   PATCH  /admin/users/{user}/role    → updateRole (inline role switch, PATCH-targeted)
 *   PATCH  /admin/users/{user}/zonal-cells → updateZonalCells (inline cell assignment
 *                                        for Impact_Zonal_Coordinator, PATCH-targeted)
 *   PATCH  /admin/users/{user}/restore → restore   (un-delete)
 *   DELETE /admin/users/{user}         → destroy   (soft-delete; self-delete 403)
 *
 * Active-role semantics
 *   - `updateRole()` mirrors `Auth\RoleSwitchController` for the
 *     inline `<select>` quick-toggle — the new role MUST already be
 *     one of the target user's Spatie roles ($user->canSwitchTo()).
 *   - `update()` is the comprehensive editor and DOES change the
 *     Spatie roleset via `$user->syncRoles($data['roles'])`.
 *
 * Self-protection invariants
 *   - DELETE: cannot delete self (hard 403 in destroy()).
 *   - PUT/PATCH update: cannot remove own Administrator Spatie role
 *     (AdminUserRequest::assertSelfCannotDemote → 422 with errors.roles).
 *   - GET edit + update: any admin can edit any user (policy gates).
 */
class UserController extends Controller
{
    private const PAGE_SIZE = 20;

    /**
     * GET /admin/users — paginated user list, role-badge dataset, and
     * the union of roles that can be added (for the Add User modal's
     * role-picker: same 10 roles as the seeder).
     *
     * Phase 06e+3 — `?filter=trashed` toggles soft-delete visibility.
     * When the filter is set, `onlyTrashed()` is applied so admins can
     * locate deleted accounts and trigger Restore.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', User::class);

        $filter = $request->string('filter')->toString();
        $showTrashed = $filter === 'trashed';

        $query = User::query()
            ->with('zonalImpactCells:id')
            ->orderBy('name');

        if ($showTrashed) {
            $query->onlyTrashed();
        }

        if ($search = $request->string('search')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate(self::PAGE_SIZE)->withQueryString();

        return Inertia::render('Admin/Users/Index', [
            'users'         => UserResource::collection($users),
            'canCreate'     => true,
            'activeRole'    => $request->user()?->activeRole(),
            'rolesForNew'   => self::addableRoles(),
            'cellsList'     => self::cellsForAssignment(),
            'showTrashed'   => $showTrashed,
            'trashedCount'  => User::onlyTrashed()->count(),
        ]);
    }

    /**
     * POST /admin/users — admin creates a new user.
     *
     * Auto-login, email verification flow, and Spatie role assignment
     * would all be wrong if we used `Auth\\RegisteredUserController::store`
     * (which assumes the request comes from a guest user form). The
     * dedicated controller method is the cleanest boundary.
     */
    public function store(AdminUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validated();
        $this->assertRolesHaveCells($data);

        $user = DB::transaction(function () use ($data) {
            // Phase 18 — one-credential-per-cell invariant (race-condition
            // belt-and-suspenders). The AdminUserRequest::rules() already
            // surface the friendly error via FormRequest; lockForUpdate +
            // re-check inside this transaction serialises simultaneous
            // POSTs so two admins can't both assign themselves to the
            // same Impact Cell as Impact_Leaders.
            $cellId = in_array('Impact_Leaders', $data['roles'], true)
                ? ($data['impact_cell_id'] ?? null)
                : null;
            if ($cellId) {
                ImpactCell::where('id', $cellId)->lockForUpdate()->first();
                if (ImpactCellHasNoLiveLeader::hasLiveLeader((string) $cellId)) {
                    throw ValidationException::withMessages([
                        'impact_cell_id' => ImpactCellHasNoLiveLeader::OCCUPIED_MESSAGE,
                    ]);
                }
            }

            $u = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => $data['password'], // hashed via cast
                'impact_cell_id' => $cellId,
            ]);
            $u->syncRoles($data['roles']);
            $u->forceFill(['active_role' => $data['active_role']])->save();
            $u->zonalImpactCells()->sync($data['zonal_impact_cell_ids'] ?? []);
            return $u;
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} created.");
    }

    /**
     * GET /admin/users/{user}/edit — render the Edit page.
     *
     * Single source of truth: the page is pre-filled with the user's
     * REAL persisted state (including `deleted_at` for the soft-delete
     * banner), the role list shared with Add modal, and the boolean
     * `isSelf` so the Admin checkbox can be locked when the actor
     * edits their own record.
     */
    public function edit(Request $request, User $user): Response
    {
        $this->authorize('view', $user);

        return Inertia::render('Admin/Users/Edit', [
            'user'         => (new UserResource($user->load('zonalImpactCells:id')))->resolve($request),
            'rolesForNew'  => self::addableRoles(),
            // Phase 13+ follow-up — filter to primary cells only. The
            // public signup form already enforces this via the same query
            // inside Auth\RegisteredUserController::create(); reusing
            // the filter here keeps the admin Edit page consistent.
            'cellsList'    => self::cellsForAssignment(),
            'isSelf'       => (int) $user->id === (int) $request->user()->id,
            'isTrashed'    => $user->trashed(),
            'deletedAt'    => optional($user->deleted_at)?->toIso8601String(),
            'activeRole'   => $request->user()?->activeRole(),
            'currentUserId'=> $request->user()->id,
        ]);
    }

    /**
     * PUT /admin/users/{user} — comprehensive update from the Edit page.
     *
     * Update path applies name, email, role set, active role, and
     * (optionally) a new password. Self-edit is gated by
     * AdminUserRequest::assertSelfCannotDemote (throws ValidationException
     * if the actor is removing their own Administrator role).
     */
    public function update(AdminUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        // Self-demote invariant: throws ValidationException → 422 form re-render.
        $request->assertSelfCannotDemote();

        $data = $request->validated();
        $this->assertRolesHaveCells($data);

        DB::transaction(function () use ($user, $data) {
            $newCellId = in_array('Impact_Leaders', $data['roles'], true)
                ? ($data['impact_cell_id'] ?? null)
                : null;

            // Phase 18 — only lock + recheck when the cell binding is
            // CHANGING. Profile-only edits on the same cell don't
            // re-trip the invariant; AdminUserRequest::rules() with
            // ignore($userId) excludes the editing user themselves.
            if ($newCellId && $user->impact_cell_id !== $newCellId) {
                ImpactCell::where('id', $newCellId)->lockForUpdate()->first();
                if (ImpactCellHasNoLiveLeader::hasLiveLeader((string) $newCellId, (int) $user->id)) {
                    throw ValidationException::withMessages([
                        'impact_cell_id' => ImpactCellHasNoLiveLeader::OCCUPIED_MESSAGE,
                    ]);
                }
            }

            $user->update([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'active_role'    => $data['active_role'],
                // A cell assignment is meaningful on the User row only for
                // Impact_Leaders. Clear stale leader data when an admin removes
                // that role during an edit.
                'impact_cell_id' => $newCellId,
            ]);

            $user->syncRoles($data['roles']);
            $user->zonalImpactCells()->sync($data['zonal_impact_cell_ids'] ?? []);

            // Password is optional on edit — only update if explicitly set.
            // Empty string + trimmed empty input both fall through to a no-op.
            if (! empty($data['password'] ?? null)) {
                $user->update(['password' => $data['password']]); // hashed via cast
            }
        });

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} updated.");
    }

    /**
     * PATCH /admin/users/{user}/role — inline "Edit Active Role" dropdown.
     *
     * Mirrors `Auth\RoleSwitchController::store` semantics. Server-side
     * restrict: the new role MUST already be one of the target user's
     * Spatie roles ($user->canSwitchTo($role)). Provisioning new
     * Spatie roles is the dedicated edit form's job, not the inline.
     */
    public function updateRole(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'role' => ['required', 'string', 'max:64'],
        ]);

        if (! $user->canSwitchTo($data['role'])) {
            abort(422, 'The target user does not hold that Spatie role. Use the edit form to assign new roles.');
        }

        $user->forceFill(['active_role' => $data['role']])->save();

        return response()->json([
            'success'     => true,
            'active_role' => $user->fresh()->active_role,
        ]);
    }

    /**
     * PATCH /admin/users/{user}/zonal-cells — inline "quick assign" from
     * the Users table row (next to the role dropdown).
     *
     * Mirrors updateRole()'s PATCH-targeted shape but for the
     * `impact_cell_user` pivot: replaces the user's zonal cell set with
     * the submitted ids. Server-side restrictions:
     *   - target user MUST hold Impact_Zonal_Coordinator (422 otherwise),
     *   - every id must be a primary Impact Cell (same Rule::exists
     *     predicate as AdminUserRequest::rules(), so the inline picker
     *     and the full edit form can never drift).
     *
     * Returns a redirect (not JSON) so Inertia re-fetches the table with
     * fresh props — keeps the picker + the Impact Cells column in sync.
     */
    public function updateZonalCells(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if (! $user->hasRole('Impact_Zonal_Coordinator')) {
            abort(422, 'Only Impact Zonal Coordinators can be assigned cells.');
        }

        $data = $request->validate([
            'zonal_impact_cell_ids' => ['nullable', 'array'],
            'zonal_impact_cell_ids.*' => [
                'required',
                'string',
                'uuid',
                Rule::exists('impact_cells', 'id')
                    ->where(fn ($query) => $query->where('is_primary', true)),
            ],
        ]);

        $user->zonalImpactCells()->sync($data['zonal_impact_cell_ids'] ?? []);

        // No flash banner here on purpose: this is a rapid multi-toggle
        // interaction and the checked checkboxes are the feedback. A
        // `with('success', ...)` banner would flicker on every toggle.
        return redirect()->back();
    }

    /**
     * PATCH /admin/users/{user}/restore — un-delete a soft-deleted user.
     *
     * Roles + active_role are preserved (SoftDeletes only sets deleted_at;
     * all other columns are untouched). For password-security hygiene,
     * any stale password_reset_tokens row tied to the user's email is
     * also purged — a reset link dispatched before the user's deletion
     * would otherwise remain valid.
     */
    public function restore(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        if (! $user->trashed()) {
            abort(422, 'User is not deleted.');
        }

        $user->restore();

        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$user->name} restored.");
    }

    /**
     * DELETE /admin/users/{user} — soft-delete. Cannot delete self.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        if ((int) $user->id === (int) $request->user()->id) {
            abort(403, 'You cannot delete your own account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', "User {$name} removed.");
    }

    /**
     * 10 seeded role names — surfaced to the Add User modal + the Edit
     * page. Sourced from RoleHelper::ROLE_NAMES so the picker, the
     * Form Request validator, and the seeder can never drift.
     */
    public static function addableRoles(): array
    {
        return RoleHelper::ROLE_NAMES;
    }

    /** Primary cells are the assignable units after the Phase 13 flattening. */
    private static function cellsForAssignment(): \Illuminate\Database\Eloquent\Collection
    {
        return ImpactCell::where('is_primary', true)
            ->ordered()
            ->get(['id', 'name', 'is_primary']);
    }

    /**
     * Phase 15 — role-specific cell assignment invariant.
     *
     * Mirrors AdminUserRequest::assertSelfCannotDemote's shape: a
     * controller-side business rule, fired AFTER the standard rules pass,
     * that raises a ValidationException with errors.impact_cell_id so the
     * Inertia form re-renders with an inline error pointing at the
     * dropdown.
     *
     * Why controller-side and not in the Form Request
     * ------------------------------------------------
     * The Form Request can express the same rule with
     * `Rule::requiredIf(fn() => in_array('Impact_Leaders', $roles, true))`,
     * which we DO in RegisterInertiaRequest (no impact_cell_id needed
     * for FollowUpOfficer signup). On the admin path the same rule was
     * tried but the resulting 422 message was confusing because the
     * `exists` validator reported "doesn't exist" for null when the
     * real failure is "you need to pick one". Doing the post-rules check
     * here lets us throw a friendlier message keyed to the dropdown.
     */
    private function assertRolesHaveCells(array $data): void
    {
        $roles = (array) ($data['roles'] ?? []);

        if (in_array('Impact_Leaders', $roles, true)
            && trim((string) ($data['impact_cell_id'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'impact_cell_id' => 'Assign an Impact Cell before saving an Impact Leaders user.',
            ]);
        }

        if (in_array('Impact_Zonal_Coordinator', $roles, true)
            && count(array_filter((array) ($data['zonal_impact_cell_ids'] ?? []))) === 0) {
            throw ValidationException::withMessages([
                'zonal_impact_cell_ids' => 'Assign at least one Impact Cell before saving a Zonal Coordinator.',
            ]);
        }
    }
}
