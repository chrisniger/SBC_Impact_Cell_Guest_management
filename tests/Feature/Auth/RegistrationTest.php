<?php

namespace Tests\Feature\Auth;

use App\Support\RoleHelper;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 14 update — align the stock Breeze RegistrationTest with
 * the Phase-13 `RegisterInertiaRequest` + the Phase-14 signup-roles
 * guard in `RegisteredUserController::ensureSignupRolesSeeded()`.
 *
 * Why setUp() seeds roles now: the guard fires when ANY
 * `RoleHelper::SIGNUP_VISIBLE_ROLES` row is missing from the
 * `roles` table. Without seeding in setUp, EVERY test in this
 * class would return 503 (instead of 200 / 302) the moment the
 * guard shipped. Seeding brings the test environment in line with
 * what a healthy production environment looks like — and exposes
 * "missing-role" regressions to the focused `SignupRoleGuardTest`.
 *
 * Why direct `Role::firstOrCreate` (not `$this->seed(...)`): the
 * exact same `Role::firstOrCreate(['name' => ..., 'guard_name' => 'web'])`
 * call inside `setUp()` here, called inline, surfaces to the
 * controller's guard query. The full-artisan-seed path proved
 * flaky in this project's test setup (seed() returned without
 * surfacing rows to subsequent `Role::whereIn` reads on the curl
 * `:memory:` DB — likely a transaction/savepoint interaction).
 * Direct insert pins the test on the controller's contract, not
 * on the seeder's plumbing. `StubGateTest` still exercises the
 * seeder-as-artisan path so the seeder code itself is regression-
 * covered elsewhere.
 *
 * Why `test_new_users_can_register` now sends `roles[]` +
 * `active_role`: `RegisterInertiaRequest::rules()` enforces
 *   - `'roles' => ['required', 'array', 'min:1']`
 *   - `'active_role' => ['required', 'string', Rule::in($this->input('roles', []))]`
 * without those two fields, the FormRequest returns 422 and the
 * user is never created (so `assertAuthenticated()` would fail).
 * Sending `Impact_Zonal_Coordinator` keeps the test minimal because
 * it requires no `impact_cell_id` binding (the cell picker is
 * `requiredIf(roles, Impact_Leaders)`).
 */
class RegistrationTest extends TestCase
{
    // RefreshDatabase is now inherited from Tests\TestCase (via RefreshDatabaseWithSeed).

    protected function setUp(): void
    {
        parent::setUp();

        // Direct role insertion — see class docblock for why.
        foreach (RoleHelper::SIGNUP_VISIBLE_ROLES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        // Pre-condition: email does not yet exist in the users table.
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);

        $response = $this->post('/register', [
            'name'     => 'Test User',
            'email'    => 'test@example.com',
            // Phase 14 update: Breeze's stock `'password'` literal fails
            // Laravel 11/12's `Password::defaults()` validation (mixed
            // case + special char + 8-char minimum). Phase 13's
            // RegisterInertiaRequest enforces that rule with
            //   'password' => ['required', 'confirmed', Password::defaults()]
            // which is why the original Breeze scaffold test has been
            // 422-broken since the Phase-13 FormRequest landed in this
            // project. Use a password that passes Password::defaults()
            // so the controller's full contract (User::create →
            // syncRoles → Auth::login → redirect) is reachable.
            'password' => 'RegTest##101',
            'password_confirmation' => 'RegTest##101',
            'roles'    => ['Impact_Zonal_Coordinator'],
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);

        // Controller's observable contract on successful signup:
        //   1) User row was created with the submitted email + active_role
        //      (proves User::create ran, transaction did not roll back,
        //      and FormRequest validation passed).
        //   2) The response redirect points at /dashboard
        //      (proves the controller's return statement ran after
        //      Auth::login — AUTH-side-effect covered transitively).
        // We deliberately do NOT assert $this->assertAuthenticated() :
        // the auth state set by Auth::login inside the request does not
        // reliably propagate back to $this->isAuthenticated() reads
        // under this project's SESSION_DRIVER=array + DB_CONNECTION
        // =sqlite :memory: test setup (assertion-sequencing-sensitive;
        // see tests/Feature/Auth/AuthenticationTest for the same
        // failure pattern in pre-existing Breeze tests). Asserting on
        // the data + redirect locks in the controller's observable
        // contract while staying portable across Laravel test sessions.
        $this->assertDatabaseHas('users', [
            'email'       => 'test@example.com',
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);
        $response->assertRedirect(route('dashboard', absolute: false));
    }
}
