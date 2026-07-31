<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterInertiaRequest;
use App\Models\ImpactCell;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
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
}
