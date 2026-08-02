<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

/**
 * Phase 34 — Admin Analytics page feature test.
 *
 * Locks the user-visible contract delivered in Phase 34 (the build that
 * replaced the Phase 06d.0 "Coming soon" stub with a real page):
 *
 *   1. Page is Administrator-only (403 for other roles).
 *   2. Payload carries KPI deltas, range config, chart series, and
 *      system overview (reuses the shared AnalyticsService).
 *   3. ?range= is honored (week default; year → 12 buckets).
 *   4. The listing route stays behind `gate.stubs` (production → 404).
 *   5. The shared AnalyticsService still powers the Admin Dashboard
 *      (regression guard for the DashboardController delegation).
 *
 * Inheritance: extends Tests\TestCase (RefreshDatabaseWithSeed), which
 * rebinds the connection to the isolated `impact_test` DB. Deliberately
 * does NOT re-`use RefreshDatabase` at the class level — the double-use
 * would shadow the isolation rebind (see RolesPermissionsAdminTest).
 */
class AnalyticsAdminTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─── Sub-assertion 1 — admin can view the analytics page ──────────
    public function test_admin_can_view_analytics_page(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.analytics.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Analytics/Index')
            ->has('kpis', 7)
            ->has('kpiDeltas')
            ->has('kpiSeries')
            ->where('rangeKey', 'week')
            ->has('rangeLabels')
            ->has('chartSeries')
            ->has('systemOverview')
        );
    }

    // ─── Sub-assertion 2 — non-admin gets 403 ─────────────────────────
    public function test_non_admin_cannot_view_analytics(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');

        $this->actingAs($leader)->get(route('admin.analytics.index'))->assertForbidden();
    }

    // ─── Sub-assertion 3 — ?range=year produces 12 monthly buckets ────
    public function test_range_query_param_is_honored(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.analytics.index', ['range' => 'year']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('rangeKey', 'year')
            ->has('rangeLabels', 12)
        );
    }

    // ─── Sub-assertion 4 — production env hides the listing (gate.stubs) ──
    public function test_listing_is_hidden_in_production(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        // Env override uses detectEnvironment() — NOT config(['app.env' => ...]) —
        // because GateStubPagesByEnvironment reads app()->environment() (see
        // StubGateTest for the same convention).
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs($admin)->get(route('admin.analytics.index'))->assertNotFound();
    }

    // ─── Sub-assertion 5 — dashboard still renders with the delegation ──
    public function test_admin_dashboard_still_renders_after_service_delegation(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('variant', 'admin')
                ->has('chartSeries')
                ->has('kpiSeries')
                ->has('systemOverview')
            );
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'Analytics ' . $role,
            // uniqid suffix — role-derived base emails would collide across
            // tests in one run (see MessagesAdminTest for the same pattern).
            'email'       => 'analytics.' . strtolower(str_replace('_', '.', $role)) . '.' . uniqid() . '@impact.test',
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }
}
