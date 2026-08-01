<?php

namespace Tests\Feature\Auth;

use App\Support\RoleHelper;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 14 — deploy-misconfig guard coverage for
 * `RegisteredUserController::ensureSignupRolesSeeded()`.
 *
 * Contract:
 *   1) When every role in `RoleHelper::SIGNUP_VISIBLE_ROLES` is
 *      present in the `roles` table (guard `web`), GET /register
 *      renders the form (200) and POST /register returns 302 (the
 *      happy redirect-to-dashboard on success).
 *   2) When ANY role in SIGNUP_VISIBLE_ROLES is missing from the
 *      `roles` table (typical deploy-misconfig: `migrate` without
 *      `db:seed`, or a `roles` table truncate-reset), BOTH
 *      GET /register AND POST /register respond with 503 Service
 *      Unavailable.
 *
 * A future bug that accidentally drops the guard call from
 * `store()` (the critical surface — `create()` is purely UX) MUST
 * fail this test — the silent regression back to
 * "RoleDoesNotExist 500 from inside syncRoles()" is exactly what
 * this guard prevents.
 *
 * Note on ordering (corrected per Phase-14 code review):
 *   In Laravel, the framework resolves a FormRequest via DI BEFORE
 *   the controller method body executes — `FormRequest::__construct`
 *   calls `validateResolved()` which fires prepareForValidation +
 *   authorize + rules. So `store()`'s guard call runs AFTER the
 *   FormRequest validation has completed, NOT before. The guard
 *   still serves its PRIMARY purpose: short-circuiting BEFORE
 *   `$user->syncRoles(...)` does (which is where the unsustainable
 *   `RoleDoesNotExist` exception is raised). For a true pre-
 *   validation abort, that guard would need to be route-middleware
 *   (`Route::post('register', [...])->middleware('ensure.signup-roles')`),
 *   out of scope for this change.
 */
class SignupRoleGuardTest extends TestCase
{
    // RefreshDatabase is now inherited from Tests\TestCase (via RefreshDatabaseWithSeed).

    /**
     * A wire-shape-valid signup payload — passes every FormRequest
     * validator on `RegisterInertiaRequest` independently of the
     * guard. `Impact_Zonal_Coordinator` is the smallest valid signup
     * shape because it requires no impact_cell_id binding (the cell
     * picker is gated behind `requiredIf(roles, Impact_Leaders)`).
     */
    private const VALID_POST = [
        'name'     => 'Smoke User',
        'email'    => 'smoke-roles-guard@impact.test',
        'password' => 'RolesGuard##101',
        'password_confirmation' => 'RolesGuard##101',
        'roles'    => ['Impact_Zonal_Coordinator'],
        'active_role' => 'Impact_Zonal_Coordinator',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        // Direct role insertion — bypasses RolesAndPermissionsSeeder
        // to isolate the test from seeder correctness (the focus here
        // is the controller-level guard contract, not the seeder).
        // Inserts ALL 10 ROLE_NAMES so Authz checks in UserPolicy
        // aren't collateral damage during this test class's run.
        foreach (RoleHelper::SIGNUP_VISIBLE_ROLES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }

    public function test_register_form_renders_when_all_signup_roles_are_seeded(): void
    {
        // setUp() inserts both SIGNUP_VISIBLE_ROLES rows — happy path.
        $this->get('/register')->assertOk();
    }

    public function test_register_form_returns_503_when_a_signup_role_is_missing(): void
    {
        // Simulate "migrate ran, db:seed did not": one of the two
        // SIGNUP_VISIBLE_ROLES rows is removed; the guard must fire.
        Role::where('name', 'Impact_Zonal_Coordinator')->delete();

        $this->get('/register')->assertStatus(503);
    }

    public function test_register_post_returns_503_when_a_signup_role_is_missing(): void
    {
        // Same missing-role scenario but exercised through the POST
        // path — this is the critical contract: the 503 must fire
        // BEFORE `User::syncRoles()` is called (which is where the
        // unsustainable `RoleDoesNotExist` exception is raised).
        Role::where('name', 'Impact_Zonal_Coordinator')->delete();

        $this->post('/register', self::VALID_POST)->assertStatus(503);
    }

    public function test_register_post_succeeds_when_all_signup_roles_are_seeded(): void
    {
        // Sanity-check the happy path after seeding — the guard MUST
        // not interfere with valid submissions.
        $response = $this->post('/register', self::VALID_POST);

        $response->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticated();
    }
}
