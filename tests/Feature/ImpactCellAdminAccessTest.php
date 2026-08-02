<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 09 — Impact_Cell_Admin (cross-cell + cross-zonal supervisor) feature test.
 *
 * Locks the user-visible contract delivered in Phase 09:
 *
 *   1. Impact_Cell_Admin can reach /dashboard and is served the dedicated
 *      supervisor surface (asserted via the Inertia `variant` prop =
 *      'impactCellAdmin', NOT the leader variant which has a single-cell
 *      scope). The "Cross-cell & cross-zonal overview" h2 itself is
 *      client-rendered JSX, so the assertion keys off the variant prop
 *      instead of assertSee on server HTML (see the test body).
 *
 *   2. ImpactCellPolicy::create() is CLOSED to Impact_Cell_Admin
 *      (Phase 35 — Impact_Cell_Admin is read-only on the Impact Cells
 *      surface: view yes, add/edit no. Hierarchy writes are
 *      Administrator-only, matching delete()'s blast-radius gate).
 *
 *   3. ImpactCellPolicy::delete() stays admin-only.
 *      Cell deletion teardown touches global system state (sub-cell
 *      cascades, leadership tree recompute, submission history) — a
 *      blast radius we keep behind the top-level admin role.
 *
 *   4. ImpactSubmissionController::index() scoping is correct:
 *      Impact_Cell_Admin sees submissions authored by users whose
 *      active_role ∈ GROUP_IMPACT_CELL and does NOT see submissions
 *      authored by Follow-UP officer/team users.
 *
 * PHPUnit config (phpunit.xml) uses sqlite :memory: + APP_ENV=testing.
 * The `gate.stubs` middleware reads `app()->environment()` and reveal-tests
 * for `testing`, so admin/leadership routes are NOT gated during these
 * tests (no need to fake the env).
 */
class ImpactCellAdminAccessTest extends TestCase
{

    public function test_impact_cell_admin_sees_cross_cell_dashboard(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole(RoleHelper::ROLE_IMPACT_CELL_ADMIN);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        // Phase 35 follow-up — assert the Inertia props instead of assertSee
        // on client-rendered JSX. The "Cross-cell & cross-zonal overview" h2
        // (ICA surface) and the leader variant's "Cell Snapshot" section are
        // rendered by React AFTER hydration, so they never appear in the
        // server HTML and assertSee could never match them. The `variant`
        // prop is the single source of truth for which surface rendered:
        //   - variant='impactCellAdmin' → the ICA supervisor surface (proves
        //     the "Cross-cell" header is what renders AND that the leader
        //     variant's "Cell Snapshot" did NOT leak through).
        //   - activeGroup='impactCell'  → the impactCell-group surface.
        //   - activeRole='Impact_Cell_Admin' → the correct actor dispatched.
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('variant', 'impactCellAdmin')
            ->where('activeGroup', 'impactCell')
            ->where('activeRole', 'Impact_Cell_Admin')
        );
    }

    public function test_impact_cell_admin_cannot_create_impact_cell(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole(RoleHelper::ROLE_IMPACT_CELL_ADMIN);

        $response = $this->actingAs($admin)->post('/impact-cells', [
            'name'       => 'New Test Cell',
            'is_primary' => true,
            'order'      => 0,
        ]);

        // Phase 35 — Impact_Cell_Admin is read-only: create() now 403s.
        $response->assertForbidden();
        $this->assertDatabaseMissing('impact_cells', [
            'name'       => 'New Test Cell',
            'is_primary' => true,
        ]);
    }

    public function test_impact_cell_admin_cannot_delete_impact_cell(): void
    {
        $this->seedRoles();
        $admin = $this->makeUserWithRole(RoleHelper::ROLE_IMPACT_CELL_ADMIN);
        $cell = $this->makePrimaryCell('Subordinate Cell');

        $response = $this->actingAs($admin)->delete("/impact-cells/{$cell->id}");

        $response->assertForbidden();
        // Cell still exists — policy gate prevented the delete.
        $this->assertDatabaseHas('impact_cells', ['id' => $cell->id]);
    }

    public function test_impact_cell_admin_sees_only_cross_group_submissions(): void
    {
        $this->seedRoles();
        $cellAdmin       = $this->makeUserWithRole(RoleHelper::ROLE_IMPACT_CELL_ADMIN);
        $cellLeader      = $this->makeUserWithRole('Impact_Leaders');
        $followUpOfficer = $this->makeUserWithRole('FollowUpOfficer');

        $cell = $this->makePrimaryCell('Test Primary');

        // In-scope submission — author is in GROUP_IMPACT_CELL.
        // `fellowship_date_key` MUST be unique per (impact_cell_id) per the
        // migration's `submission_cell_date_unique` index; the controller
        // enforces this for user-facing POSTs, but our direct model insert
        // must respect it too or the second insert QueryExceptions out.
        ImpactSubmission::create([
            'impact_cell_id'      => $cell->id,
            'user_id'             => $cellLeader->id,
            'type'                => 'report',
            'fellowship_date_key' => '2026-01-05',
            'data'                => ['full_name' => 'Alpha Leader Report'],
        ]);

        // Out-of-scope submission — author is in Follow-Up officer bucket.
        // Distinct fellowship_date_key to avoid the unique-index collision.
        ImpactSubmission::create([
            'impact_cell_id'      => $cell->id,
            'user_id'             => $followUpOfficer->id,
            'type'                => 'report',
            'fellowship_date_key' => '2026-01-12',
            'data'                => ['full_name' => 'Officer Out-Of-Scope Report'],
        ]);

        $response = $this->actingAs($cellAdmin)->get('/impact-submissions');

        $response->assertOk();
        // The cross-group scope includes Impact_Leaders submissions.
        $response->assertSee('Alpha Leader Report');
        // The cross-group scope excludes Follow-Up officer submissions.
        $response->assertDontSee('Officer Out-Of-Scope Report');
    }

    // ───────────────────────────────────────────────────────────────────
    // Test helpers
    // ───────────────────────────────────────────────────────────────────

    /**
     * Bootstrap the 10 Spatie role rows (mirror of RolesAndPermissionsSeeder).
     *
     * REQUIRED because User::activeRole() in app/Models/User.php validates the
     * `active_role` column against the user's Spatie-role set via hasRole():
     *
     *     if ($this->active_role !== null && $this->hasRole($this->active_role)) {
     *         return $this->active_role;
     *     }
     *     return $this->getRoleNames()->first();
     *
     * Without seedRoles() + assignRole() in makeUserWithRole(), hasRole() returns
     * false (no matching role row), activeRole() falls through to the first-
     * Spatie-role lookup, which is `null`, and Phase 09 controllers see the user
     * as role-less → fall through to adminDashboard instead of the supervisor
     * variant. All 4 tests would silently fail.
     *
     * forgetCachedPermissions() before AND after seeding guards against Spatie
     * caching stale role names from a prior test in the same phpunit run.
     */
    private function seedRoles(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Create a User with `active_role` set AND Spatie role assigned.
     *
     * BOTH writes required because User::activeRole() validates the column
     * against `$user->hasRole($this->active_role)` (see User.php docblock
     * around the activeRole() accessor). Without the Spatie `assignRole()`
     * wire-up, the fallback path returns null and Phase 09 controllers
     * mis-dispatch.
     */
    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'active_role' => $role,
        ]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Create a primary ImpactCell with a deterministic UUID so the
     * routes below can reference it deterministically.
     */
    private function makePrimaryCell(string $name): ImpactCell
    {
        return ImpactCell::create([
            'id'         => (string) Str::uuid(),
            'name'       => $name,
            'is_primary' => true,
            'order'      => 0,
        ]);
    }
}
