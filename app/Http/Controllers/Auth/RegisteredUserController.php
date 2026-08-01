<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterInertiaRequest;
use App\Models\ImpactCell;
use App\Models\User;
use App\Rules\ImpactCellHasNoLiveLeader;
use App\Support\RoleHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

/**
 * Phase 0 + Phase 13 — public signup controller.
 *
 * Phase 13 changes:
 *   - `create()` now ships `rolesForSignup` (the 3-role SELECT list from
 *     `RoleHelper::SIGNUP_VISIBLE_ROLES`) + `cellsList` (lightweight
 *     id/name/is_primary projection of ImpactCell) so the React page can
 *     render the role checkbox grid + the Impact Cell dropdown without
 *     a follow-up fetch.
 *   - `store()` now uses the dedicated `RegisterInertiaRequest` Form
 *     Request (was an inline `$request->validate()` call previously).
 *   - When `Impact_Leaders` is chosen AND `impact_cell_id` is present,
 *     the controller seeds the selected cell's
 *     leader_name / leader_phone / assistant_name / assistant_phone /
 *     welfare_officer_name / welfare_officer_phone columns. Overwriting
 *     an existing cell's leadership team data is intentional — the cell
 *     is "owned" by the cell, and the first Impact_Leaders assigned to
 *     it sets the roster. Admin can correct via /impact-cells/{id} PUT.
 *   - Phase 13 follow-up: the multi-write signup (User::create +
 *     syncRoles + forceFill(active_role) + ImpactCell::where()->update)
 *     is wrapped in DB::transaction so a partial-failure leaves no
 *     half-baked rows. Password is no longer pre-hashed here — User's
 *     `password` => 'hashed' cast auto-hashes on assignment.
 *
 * Signup-flow invariants (enforced server-side, mirror the controller
 * in Admin/UserController so the admin path's behaviour should diverge
 * only in the "who acts" axis, not the "what data" axis):
 *
 *   - Multi-role allowed.
 *   - Administrator / Supervisor / cross-cell-admin roles are NOT in
 *     SIGNUP_VISIBLE_ROLES — the Form Request's Rule::in rejects them
 *     server-side even if a hand-crafted client sends them.
 *   - Administrator cannot be reached via this controller at all
 *     (no public role grants it, and the request's role allowlist keeps
 *     it out).
 */
class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     *
     * Phase 13 payload surface (consumed by `resources/js/Pages/Auth/Register.tsx`):
     *   - rolesForSignup: string[] selectable roles (3 entries today).
     *   - cellsList:      { id, name, is_primary }[] for the cell dropdown.
     * `activeRole` is global-shared by HandleInertiaRequests::share() whenever
     * the visitor is auth'd, but the public form is anonymous so the page
     * itself doesn't read it.
     */
    public function create(): Response
    {
        // Phase 14 — guard rail. See ensureSignupRolesSeeded() for rationale.
        $this->ensureSignupRolesSeeded();

        return Inertia::render('Auth/Register', [
            'rolesForSignup' => RoleHelper::signupVisibleRoles(),
            // Phase 13+ follow-up — public signup displays ONLY primary
            // cells (no sub-cell rendering in scope as of 2026-07-31).
            'cellsList'      => ImpactCell::where('is_primary', true)->ordered()->get(['id', 'name', 'is_primary']),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * Uses `RegisterInertiaRequest` for the validation surface (so the
     * messages + prepareForValidation live in one place and Feature
     * tests target a single class). Behind that surface:
     *   1) DB transaction wrapping the user create + role sync +
     *      active_role set + cell leader-team seed so a partial-failure
     *      rolls back cleanly. No orphan users, no half-FKs.
     *   2) User::create with `password` plain — the model cast
     *      'password' => 'hashed' auto-hashes on assignment.
     *   3) syncRoles($roles)   — multi-role at signup is permitted.
     *   4) IF `Impact_Leaders` is in roles AND impact_cell_id is set,
     *      seed the chosen cell's leader_team fields. Silent overwrite
     *      is acceptable for v1 (no pre-check on existing leadership);
     *      admin can correct via the Show page's inline-edit.
     *   5) fire `Registered` event (so email verification hooks attach).
     *   6) Auth::login($user) + redirect to /dashboard.
     *
     * @throws \Illuminate\Validation\ValidationException on bad input (via FormRequest).
     */
    public function store(RegisterInertiaRequest $request): RedirectResponse
    {
        // Phase 14 — guard rail. Note on ordering: in Laravel, the
        // FormRequest is resolved by the IoC container BEFORE the
        // controller body executes (FormRequest::__construct
        // triggers validateResolved → authorize + prepareForValidation
        // + rules). So this guard call runs AFTER FormRequest
        // validation has already completed — NOT before. The guard's
        // primary purpose still holds: it short-circuits BEFORE
        // `$user->syncRoles($data['roles'])` does, which is where
        // the unsustainable `RoleDoesNotExist` exception is raised.
        // A truly pre-validation abort would require route middleware
        // (Route::post('register', ...)->middleware('ensure.signup-roles'))
        // — out of scope here.
        $this->ensureSignupRolesSeeded();

        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            // Phase 18 — race-condition guard for the one-credential-per-cell
            // invariant. The FormRequest rule has already run, but two
            // simultaneous POSTs can both pass validation at T0 and both
            // try to create a leader for the same cell at T1. Lock the
            // candidate ImpactCell row inside the transaction so the
            // check below is serialised, then re-check with the rule's
            // query and throw the same friendly message on conflict.
            if (! empty($data['impact_cell_id'] ?? null)
                && in_array('Impact_Leaders', $data['roles'], true)) {
                \App\Models\ImpactCell::where('id', $data['impact_cell_id'])
                    ->lockForUpdate()
                    ->first();

                if (ImpactCellHasNoLiveLeader::hasLiveLeader((string) $data['impact_cell_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'impact_cell_id' => ImpactCellHasNoLiveLeader::OCCUPIED_MESSAGE,
                    ]);
                }
            }

            $user = User::create([
                'name'           => $data['name'],
                'email'          => $data['email'],
                'password'       => $data['password'], // 'hashed' cast auto-hashes
                'impact_cell_id' => $data['impact_cell_id'] ?? null,
            ]);

            $user->syncRoles($data['roles']);

            // prepareForValidation defaulted active_role, but Laravel Users
            // column still needs a write at this point.
            $user->forceFill(['active_role' => $data['active_role']])->save();

            // Phase 13 — if this signup binds an Impact Leader to a cell, also
            // write the cell's leader_team seed. The cells table is canonical
            // for leadership ownership; this is just the FIRST card on the
            // roster. Admin can correct via /impact-cells/{id} PUT later.
            if (in_array('Impact_Leaders', $data['roles'], true) && ! empty($data['impact_cell_id'] ?? null)) {
                ImpactCell::where('id', $data['impact_cell_id'])->update([
                    'leader_name'            => $data['leader_name'] ?? null,
                    'leader_phone'           => $data['leader_phone'] ?? null,
                    'assistant_name'         => $data['assistant_name'] ?? null,
                    'assistant_phone'        => $data['assistant_phone'] ?? null,
                    'welfare_officer_name'   => $data['welfare_officer_name'] ?? null,
                    'welfare_officer_phone'  => $data['welfare_officer_phone'] ?? null,
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }

    /**
     * Phase 14 — deploy-misconfig guard rail.
     *
     * Without this guard, an operator who runs `migrate` without
     * `db:seed` (or someone who truncates the `roles` table for a
     * test reset / fresh DB / partial restore) sees a 500 trace like
     * `Spatie\Permission\Exceptions\RoleDoesNotExist` deep inside
     * `User::syncRoles()` instead of a clean 503 with remediation
     * guidance. Failing loudly here turns a deploy mistake into an
     * obvious on-call page rather than a confusing user-facing 500.
     *
     * Called from BOTH `create()` AND `store()` — both surface this
     * failure mode independently:
     *   - `create()` — UX nicety so the form is never rendered in a
     *     state where the role grid is silently empty / broken.
     *   - `store()` — the critical guard, since a missing role would
     *     otherwise throw inside the DB transaction and roll back
     *     the user row invisibly. The guard ensures the 503 fires
     *     BEFORE we even attempt `$user->syncRoles()`.
     *
     * Failure mode:
     *   503 Service Unavailable
     *   body: "Signup is temporarily unavailable: required roles are
     *          not seeded: <missing>. Run `php artisan db:seed
     *          --class=RolesAndPermissionsSeeder`."
     *
     * Why no in-memory cache? `Role::whereIn(...)` is one indexed
     * lookup with a 2-element `IN` clause — sub-millisecond on
     * MySQL/Postgres. Caching the result for N seconds would defeat
     * the "fail loudly when seed is broken" intent if the cache is
     * warm from before the deploy (the bug needs to surface AT the
     * deploy, not be hidden).
     */
    private function ensureSignupRolesSeeded(): void
    {
        $present = Role::whereIn('name', RoleHelper::SIGNUP_VISIBLE_ROLES)
            ->pluck('name')
            ->all();

        $missing = array_diff(RoleHelper::SIGNUP_VISIBLE_ROLES, $present);

        abort_if(
            ! empty($missing),
            503,
            'Signup is temporarily unavailable: required roles are not seeded: '
                . implode(', ', $missing)
                . '. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`.'
        );
    }
}
