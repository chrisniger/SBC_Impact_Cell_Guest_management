<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\TestCase;

/**
 * Phase 02b — role switcher feature test (multi-role dashboard switching).
 *
 * Locks the contract behind the top-bar RoleSwitcher dropdown:
 *
 *   1. A multi-role user can switch their persisted `active_role` via
 *      POST /auth/switch-role.
 *   2. The switch is validated against the user's ACTUAL Spatie roles —
 *      a role they don't hold is rejected (403).
 *   3. Guests are rejected (401).
 *   4. `HandleInertiaRequests` shares the role data the dropdown reads
 *      (`auth.user.roles` / `hasMultipleRoles` / `activeRole`).
 *
 * Inheritance: extends Tests\TestCase (RefreshDatabaseWithSeed), which
 * rebinds the connection to the isolated `impact_test` DB. Deliberately
 * does NOT re-`use RefreshDatabase` at the class level — the double-use
 * would shadow the isolation rebind (see RolesPermissionsAdminTest).
 */
class RoleSwitchTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // ─── Sub-assertion 1 — multi-role user switches active_role ───────
    public function test_multi_role_user_can_switch_active_role(): void
    {
        $user = $this->makeUserWithRoles('Impact_Leaders', 'FollowUpOfficer');
        $this->assertSame('Impact_Leaders', $user->activeRole());

        $response = $this->actingAs($user)->post(route('role.switch'), [
            'role' => 'FollowUpOfficer',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('FollowUpOfficer', $user->fresh()->active_role);
    }

    // ─── Sub-assertion 2 — role the user doesn't hold → 403 ───────────
    public function test_role_not_held_is_rejected(): void
    {
        $user = $this->makeUserWithRoles('Impact_Leaders');

        $this->actingAs($user)
            ->post(route('role.switch'), ['role' => 'Administrator'])
            ->assertForbidden();

        $this->assertSame('Impact_Leaders', $user->fresh()->active_role);
    }

    // ─── Sub-assertion 3 — unauthenticated → redirect to login ───────
    public function test_guest_cannot_switch_role(): void
    {
        // POST /auth/switch-role sits inside the `auth` middleware group, so a
        // guest is 302-redirected to /login before the controller's abort(401)
        // (defense-in-depth) is ever reached.
        $this->post(route('role.switch'), ['role' => 'Impact_Leaders'])
            ->assertRedirect(route('login'));
    }

    // ─── Sub-assertion 4 — shared props carry the dropdown's data ─────
    public function test_inertia_shared_props_expose_roles_for_switcher(): void
    {
        $user = $this->makeUserWithRoles('Impact_Leaders', 'Follow_UP');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.activeRole', 'Impact_Leaders')
                ->where('auth.user.hasMultipleRoles', true)
                ->has('auth.user.roles', 2)
            );
    }

    // ─── Sub-assertion 5 — single-role user shares hasMultipleRoles=false ──
    public function test_single_role_user_has_multiple_roles_false(): void
    {
        $user = $this->makeUserWithRoles('FollowUpOfficer');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('auth.user.hasMultipleRoles', false)
                ->where('auth.user.activeRole', 'FollowUpOfficer')
            );
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeUserWithRoles(string ...$roles): User
    {
        $user = User::factory()->create([
            'name'        => 'RoleSwitch ' . implode('_', $roles),
            // uniqid suffix — role-derived base emails would collide across
            // tests in one run (see MessagesAdminTest for the same pattern).
            'email'       => 'roleswitch.' . strtolower(implode('.', $roles)) . '.' . uniqid() . '@impact.test',
            'active_role' => $roles[0],
        ]);
        $user->assignRole($roles);
        return $user;
    }
}
