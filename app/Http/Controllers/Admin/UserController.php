<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdminUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $query = User::query()->orderBy('name');

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

        $user = User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => $data['password'], // hashed via cast
        ]);

        $user->syncRoles($data['roles']);
        $user->forceFill(['active_role' => $data['active_role']])->save();

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
            'user'         => (new UserResource($user))->resolve($request),
            'rolesForNew'  => self::addableRoles(),
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

        $user->update([
            'name'        => $data['name'],
            'email'       => $data['email'],
            'active_role' => $data['active_role'],
        ]);

        $user->syncRoles($data['roles']);

        // Password is optional on edit — only update if explicitly set.
        // Empty string + trimmed empty input both fall through to a no-op.
        if (! empty($data['password'] ?? null)) {
            $user->update(['password' => $data['password']]); // hashed via cast
        }

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
}
