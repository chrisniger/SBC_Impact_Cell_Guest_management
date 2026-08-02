<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 06e+2 — regression coverage for `GateStubPagesByEnvironment`.
 *
 * Behaviour matrix the middleware must satisfy:
 *
 *   env  | route name                            | outcome
 *   -----+---------------------------------------+----------
 *   X    | not in GATED_ROUTES (or null)         | 200 passthrough
 *   local/staging/testing | gated route          | 200 passthrough
 *   production            | gated route          | 404
 *
 * This test ships the minimal matrix to lock the contract; a future
 * edit that accidentally drops `'testing'` from `REVEAL_ENVS` or swaps
 * `abort(404)` for a redirect-to-dashboard should fail here, not in
 * a production 4xx-storm.
 *
 * Two roles exercised: Administrator (positive path on every test) and
 * a FollowUpOfficer (sanity check that POST /admin/users stays blocked
 * by UserPolicy even with the gate deliberately left off for writes).
 */
class StubGateTest extends TestCase
{
    use RefreshDatabase;

    /** Routes that the GateStubPagesByEnvironment middleware lists in its GATED_ROUTES const. */
    private const GATED_ROUTES = [
        'admin.users.index',
        'admin.roles-permissions.index',
        'admin.messages.index',
        'admin.analytics.index',
    ];

    /** Routes that are NEVER gated (live in every env). */
    private const NOT_GATED_ROUTES = [
        'admin.submissions.index', // partial stub with a real link to /impact-submissions
        // Sanity check the auth-gated CRUD write endpoints too — POST/PATCH/DELETE
        // for admin.users.* stay live even in production (intentional, per
        // GateStubPagesByEnvironment docblock).
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Convenience: act as a freshly-seeded Administrator.
     *
     * Env override uses `$this->app->detectEnvironment()` rather than
     * `config(['app.env' => ...])` because Laravel captures the env
     * in the Application container at boot time — `app()->environment()`
     * reads from `$this['env']`, not from the config repository, so
     * `config('app.env')` swaps don't propagate into the middleware
     * chain. `detectEnvironment()` is the official Laravel 11/12 way
     * to force a per-test env.
     */
    private function actingAsAdmin(?string $env = null): self
    {
        if ($env !== null) {
            $this->app->detectEnvironment(fn () => $env);
        }

        $admin = User::factory()->create([
            'name'        => 'Test Admin',
            // uniqid suffix — the fixed base email collided across the 5 env-loop
            // tests in one run (users.users_email_unique) because this env runs
            // against the dev DB without per-test rollback (see MessagesAdminTest
            // for the same convention).
            'email'       => 'gate-test-admin.' . uniqid() . '@impact.test',
            'password'    => 'GateTest##101',           // hashed via cast
            'active_role' => 'Administrator',
        ])->assignRole('Administrator');

        return $this->actingAs($admin);
    }

    public function test_local_environment_reveals_gated_routes(): void
    {
        foreach (self::GATED_ROUTES as $name) {
            $uri = $this->uriFromRouteName($name);
            $this->actingAsAdmin('local')
                ->get($uri)
                ->assertOk(); // 200 — Inertia render delivered, no gate
        }
    }

    public function test_staging_environment_reveals_gated_routes(): void
    {
        foreach (self::GATED_ROUTES as $name) {
            $uri = $this->uriFromRouteName($name);
            $this->actingAsAdmin('staging')
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_testing_environment_reveals_gated_routes(): void
    {
        // Locks the contract that PHPUnit CI runs (APP_ENV=testing) can
        // hit the stubs without seeing a 404 page.
        foreach (self::GATED_ROUTES as $name) {
            $uri = $this->uriFromRouteName($name);
            $this->actingAsAdmin('testing')
                ->get($uri)
                ->assertOk();
        }
    }

    public function test_production_environment_returns_404_for_gated_routes(): void
    {
        foreach (self::GATED_ROUTES as $name) {
            $uri = $this->uriFromRouteName($name);
            $this->actingAsAdmin('production')
                ->get($uri)
                ->assertNotFound(); // 404 — clean "route doesn't exist" semantic
        }
    }

    public function test_non_gated_routes_are_live_in_every_environment(): void
    {
        foreach (self::NOT_GATED_ROUTES as $name) {
            $uri = $this->uriFromRouteName($name);
            foreach (['local', 'staging', 'testing', 'production'] as $env) {
                $this->actingAsAdmin($env)
                    ->get($uri)
                    ->assertOk();
            }
        }
    }

    public function test_write_endpoints_for_users_stay_unauthorized_for_non_admin(): void
    {
        // Per GateStubPagesByEnvironment docblock: POST/PATCH/DELETE on
        // admin.users.* are deliberately NOT gated so future migrations
        // can pre-create users. Sanity-check the policy still blocks
        // a regular follow-up officer from hitting them.
        $officer = User::factory()->create([
            'name'        => 'Test Officer',
            'email'       => 'gate-test-officer@impact.test',
            'password'    => 'GateOfficer##101',
            'active_role' => 'FollowUpOfficer',
        ])->assignRole('FollowUpOfficer');

        $this->actingAs($officer)
            ->post(route('admin.users.store'), [
                'name'     => 'Smoke',
                'email'    => 'smoke@impact.test',
                'password' => 'SmokePass##101',
                'password_confirmation' => 'SmokePass##101',
                'roles'       => ['FollowUpOfficer'],
                'active_role' => 'FollowUpOfficer',
            ])
            ->assertForbidden(); // UserPolicy blocks non-admin → 403
    }

    private function uriFromRouteName(string $name): string
    {
        $route = route($name, [], false);
        if (! is_string($route)) {
            $this->fail("Route '{$name}' did not resolve to a URI.");
        }
        return '/' . ltrim($route, '/');
    }
}
