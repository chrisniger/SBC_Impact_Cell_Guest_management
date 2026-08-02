<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RoleHelper;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 34 — Admin Roles & Permissions page feature test.
 *
 * Locks the user-visible contract delivered in Phase 34 (the build that
 * replaced the Phase 06d.0 "Coming soon" stub with a real editor):
 *
 *   1. Page is Administrator-only (403 for other roles).
 *   2. Canonical roles (RoleHelper::ROLE_NAMES) cannot be renamed or
 *      deleted — only their permissions can change.
 *   3. Custom roles: create / rename / delete freely; delete blocked
 *      while members are assigned.
 *   4. Permission catalog: seeded from RoleHelper::PERMISSIONS,
 *      Administrator granted the full catalog; new permissions can be
 *      added via the endpoint.
 *   5. The listing route stays behind `gate.stubs` (production → 404).
 *
 * Inheritance: extends Tests\TestCase (RefreshDatabaseWithSeed → the
 * beforeRefreshingDatabase() rebind keeps tests on the isolated
 * `impact_test` DB). Deliberately does NOT re-use RefreshDatabase at the
 * class level — the double-use shadows the parent's DB isolation rebind.
 */
class RolesPermissionsAdminTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─── Sub-assertion 1 — admin can view the page with roles + permissions ──
    public function test_admin_can_view_roles_permissions_page(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('admin.roles-permissions.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/RolesPermissions/Index')
            ->has('roles', 10)
            ->has('permissions', count(RoleHelper::PERMISSIONS))
            ->where('canonical', RoleHelper::ROLE_NAMES)
        );
    }

    // ─── Sub-assertion 2 — non-admin gets 403 ──────────────────────────
    public function test_non_admin_cannot_view_or_mutate(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');

        $this->actingAs($leader)->get(route('admin.roles-permissions.index'))->assertForbidden();
        $this->actingAs($leader)->post(route('admin.roles-permissions.store'), ['name' => 'Hacker'])->assertForbidden();
        $this->actingAs($leader)->put(route('admin.roles-permissions.update', Role::first()), ['name' => 'Hacker'])->assertForbidden();
        $this->actingAs($leader)->delete(route('admin.roles-permissions.destroy', Role::first()))->assertForbidden();
        $this->actingAs($leader)->post(route('admin.roles-permissions.permissions.store'), ['name' => 'x.hack'])->assertForbidden();
    }

    // ─── Sub-assertion 3 — create a custom role with permissions ───────
    public function test_admin_can_create_custom_role_with_permissions(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->post(route('admin.roles-permissions.store'), [
            'name'        => 'Regional_Reporter',
            'permissions' => ['reports.view', 'audit.view'],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $role = Role::where('name', 'Regional_Reporter')->where('guard_name', 'web')->first();
        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(['reports.view', 'audit.view'], $role->permissions->pluck('name')->all());
    }

    // ─── Sub-assertion 4 — duplicate role name rejected ────────────────
    public function test_duplicate_role_name_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        Role::create(['name' => 'Existing_Role', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->post(route('admin.roles-permissions.store'), [
            'name' => 'Existing_Role',
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertSame(11, Role::count());
    }

    // ─── Sub-assertion 5 — custom role rename + permission sync ────────
    public function test_admin_can_rename_custom_role_and_sync_permissions(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $role  = Role::create(['name' => 'Old_Name', 'guard_name' => 'web']);
        $role->syncPermissions(['reports.view']);

        $response = $this->actingAs($admin)->put(route('admin.roles-permissions.update', $role), [
            'name'        => 'New_Name',
            'permissions' => ['reports.view', 'cells.manage'],
        ]);

        $response->assertRedirect();
        $this->assertSame('New_Name', $role->fresh()->name);
        $this->assertEqualsCanonicalizing(['reports.view', 'cells.manage'], $role->fresh()->permissions->pluck('name')->all());
    }

    // ─── Sub-assertion 6 — canonical role name is IMMUTABLE ────────────
    public function test_canonical_role_cannot_be_renamed_but_permissions_update(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $role  = Role::where('name', 'Impact_Leaders')->where('guard_name', 'web')->firstOrFail();

        $response = $this->actingAs($admin)->put(route('admin.roles-permissions.update', $role), [
            'name'        => 'Renamed_Leaders',
            'permissions' => ['cells.manage'],
        ]);

        $response->assertRedirect();
        // Name unchanged — only permissions applied.
        $this->assertSame('Impact_Leaders', $role->fresh()->name);
        $this->assertEqualsCanonicalizing(['cells.manage'], $role->fresh()->permissions->pluck('name')->all());
    }

    // ─── Sub-assertion 7 — canonical role cannot be deleted ────────────
    public function test_canonical_role_cannot_be_deleted(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $role  = Role::where('name', 'Supervisor')->where('guard_name', 'web')->firstOrFail();

        $response = $this->actingAs($admin)->delete(route('admin.roles-permissions.destroy', $role));

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['name' => 'Supervisor', 'guard_name' => 'web']);
    }

    // ─── Sub-assertion 8 — role with members cannot be deleted ─────────
    public function test_role_with_members_cannot_be_deleted(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $role  = Role::create(['name' => 'Occupied_Role', 'guard_name' => 'web']);
        $member = $this->makeUserWithRole('FollowUpOfficer');
        $member->assignRole($role);

        $response = $this->actingAs($admin)->delete(route('admin.roles-permissions.destroy', $role));

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseHas('roles', ['name' => 'Occupied_Role']);
    }

    // ─── Sub-assertion 9 — empty custom role CAN be deleted ────────────
    public function test_empty_custom_role_can_be_deleted(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $role  = Role::create(['name' => 'Lonely_Role', 'guard_name' => 'web']);

        $response = $this->actingAs($admin)->delete(route('admin.roles-permissions.destroy', $role));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('roles', ['name' => 'Lonely_Role']);
    }

    // ─── Sub-assertion 10 — permission catalog seeded + Administrator has all ──
    public function test_permission_catalog_seeded_and_admin_gets_full_catalog(): void
    {
        foreach (RoleHelper::PERMISSIONS as $p) {
            $this->assertDatabaseHas('permissions', ['name' => $p, 'guard_name' => 'web']);
        }
        $admin = Role::where('name', 'Administrator')->where('guard_name', 'web')->firstOrFail();
        $this->assertEqualsCanonicalizing(RoleHelper::PERMISSIONS, $admin->permissions->pluck('name')->all());
    }

    // ─── Sub-assertion 11 — add a new permission to the catalog ────────
    public function test_admin_can_add_new_permission(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->post(route('admin.roles-permissions.permissions.store'), [
            'name' => 'reports.export',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('permissions', ['name' => 'reports.export', 'guard_name' => 'web']);
    }

    // ─── Sub-assertion 12 — production env hides the listing (gate.stubs) ──
    public function test_listing_is_hidden_in_production(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        // Env override uses detectEnvironment() — NOT config(['app.env' => ...]) —
        // because Laravel captures the env in the Application container at boot
        // and GateStubPagesByEnvironment reads app()->environment(). config()
        // swaps never propagate into the middleware chain (see StubGateTest).
        $this->app->detectEnvironment(fn () => 'production');

        $this->actingAs($admin)->get(route('admin.roles-permissions.index'))->assertNotFound();
    }

    // ─────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'RolesPerm ' . $role,
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }
}
