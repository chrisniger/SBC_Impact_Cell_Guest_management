<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 17 — Admin Impact Cell edit surface feature test.
 *
 * Locks the user-visible contract delivered in Phase 17 (the build that
 * added /impact-cells/create, /impact-cells/{id} three inline editor
 * cards, and the /attach-sub-cell + /detach-sub-cell re-parenting
 * endpoints). All 9 sub-assertions anchor the happy-path admin edit
 * flow + the failure paths that admin or Impact_Cell_Admin can hit.
 *
 * Inheritance: this test extends Tests\TestCase which uses
 * RefreshDatabaseWithSeed — that trait composes Laravel's
 * RefreshDatabase + forgets Spatie's permission cache after every
 * refresh cycle (Phase 14 follow-up). Deliberately does NOT re-`use
 * RefreshDatabase` at the class level: the double-use would shadow
 * RefreshDatabaseWithSeed::beforeRefreshingDatabase() (PHP trait
 * precedence) and silently drop the impact_test isolation rebind —
 * tests would then run against the LIVE impact_guest dev DB.
 *
 * Auth plumbing: CSRF bypass inherited from `tests\TestCase.php`
 * (Phase 20); setUp seeds RolesAndPermissionsSeeder (so the 10 Spatie
 * role rows exist before any user is created), then explicitly calls
 * `app(PermissionRegistrar::class)->forgetCachedPermissions()` to
 * invalidate the in-process permission cache so User::activeRole()
 * resolves correctly and Phase 09 controllers don't mis-dispatch.
 */
class Phase17ImpactCellEditTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Phase 14 — forget Spatie's permission cache after every refresh
        // so User::hasRole() sees freshly-migrated rows, not the previous
        // process-level snapshot.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─── Sub-assertion 1 — primary happy path ──────────────────────────
    public function test_admin_can_update_aco_jedo_phone_and_address_via_put(): void
    {
        $admin = $this->admin();
        $cell  = $this->cell('ACO/JEDO');

        $response = $this->actingAs($admin)->put(
            route('impact-cells.update', $cell),
            $this->validDetailsPayload($cell, phone: '+1-555-ACO-JEDO', address: '123 Test Street')
        );

        // Phase 32 — update() now redirects back to the SHOW page so a
        // successful details save is immediately visible on the edited cell.
        $response->assertRedirect(route('impact-cells.show', $cell));
        $fresh = $cell->fresh();
        $this->assertSame('+1-555-ACO-JEDO', $fresh->phone);
        $this->assertSame('123 Test Street', $fresh->address);
        // Sanity: the cell stays primary (we didn't toggle is_primary=false).
        $this->assertTrue($fresh->is_primary);
        $this->assertNull($fresh->parent_cell_id);
    }

    // ─── Sub-assertion 2 — leadership team happy path ───────────────────
    public function test_admin_can_update_leadership_team_via_put(): void
    {
        $admin = $this->admin();
        $cell  = $this->cell('ACO/JEDO');

        $response = $this->actingAs($admin)->put(
            route('impact-cells.update', $cell),
            $this->validDetailsPayload($cell, leaderName: 'John Doe', leaderPhone: '0803-111-2222')
        );

        $response->assertRedirect(route('impact-cells.show', $cell));
        $fresh = $cell->fresh();
        $this->assertSame('John Doe', $fresh->leader_name);
        $this->assertSame('0803-111-2222', $fresh->leader_phone);
        // Other Leadership Team fields stay null/empty when only leader was sent.
        $this->assertNull($fresh->assistant_name);
        $this->assertNull($fresh->welfare_officer_name);
    }

    // ─── Sub-assertion 3 — Impact_Cell_Admin is READ-ONLY (Phase 35) ─────
    public function test_impact_cell_admin_cannot_update_aco_jedo_via_put(): void
    {
        $admin = $this->makeUserWithRole('Impact_Cell_Admin');
        $cell  = $this->cell('ACO/JEDO');

        $response = $this->actingAs($admin)->put(
            route('impact-cells.update', $cell),
            $this->validDetailsPayload($cell, phone: '+1-555-ICA')
        );

        $response->assertForbidden();
        $this->assertNull($cell->fresh()->phone);
    }

    // ─── Sub-assertion 4 — non-admin gets 403 ───────────────────────────
    public function test_non_admin_cannot_update_aco_jedo_via_put(): void
    {
        $leader = $this->makeUserWithRole('Impact_Leaders');
        $cell   = $this->cell('ACO/JEDO');

        $response = $this->actingAs($leader)->put(
            route('impact-cells.update', $cell),
            $this->validDetailsPayload($cell, phone: 'injected-by-leader')
        );

        $response->assertForbidden();
        // DB must NOT have changed.
        $this->assertNull($cell->fresh()->phone);
    }

    // ─── Sub-assertion 5 — required_if guard for sub-cell parent ────────
    public function test_sub_cell_creation_without_parent_cell_id_is_rejected(): void
    {
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->post('/impact-cells', [
            'name'    => 'Orphan Sub Attempt',
            'phone'   => '',
            'address' => '',
            // is_primary=false but no parent_cell_id — required_if should fire.
            'is_primary'     => false,
            'parent_cell_id' => '',
            'order'          => 0,
            // Leadership fields are nullable so they're omitted here.
        ]);

        $response->assertSessionHasErrors('parent_cell_id');
        $this->assertDatabaseMissing('impact_cells', ['name' => 'Orphan Sub Attempt']);
    }

    // ─── Sub-assertion 6 — sub-cell attach happy path ────────────────────
    public function test_sub_cell_attach_reparents_candidate_under_this_primary(): void
    {
        $admin     = $this->admin();
        $parent    = $this->cell('ACO/JEDO');
        $candidate = $this->cell('APO MECHANIC'); // seeded-id pattern, but a fresh row.

        $response = $this->actingAs($admin)->post(
            route('impact-cells.attach-sub-cell', $parent),
            ['child_id' => $candidate->id]
        );

        $response->assertRedirect(); // back() used in the controller
        $fresh = $candidate->fresh();
        $this->assertFalse($fresh->is_primary);
        $this->assertSame($parent->id, $fresh->parent_cell_id);
    }

    // ─── Sub-assertion 7 — sub-cell detach promotes back to primary ─────
    public function test_sub_cell_detach_promotes_child_back_to_primary(): void
    {
        $admin  = $this->admin();
        $parent = $this->cell('ACO/JEDO');
        $child  = $this->cell('APO MECHANIC');
        $child->update(['parent_cell_id' => $parent->id, 'is_primary' => false]);

        $response = $this->actingAs($admin)->post(
            route('impact-cells.detach-sub-cell', $parent),
            ['child_id' => $child->id]
        );

        $response->assertRedirect();
        $fresh = $child->fresh();
        $this->assertTrue($fresh->is_primary);
        $this->assertNull($fresh->parent_cell_id);
    }

    // ─── Sub-assertion 8 — grandparent-trap guard ──────────────────────
    public function test_demote_with_active_subcells_is_rejected(): void
    {
        $admin = $this->admin();
        $grandparent = $this->cell('GRANDPARENT');
        $child       = $this->cell('CHILD');
        // Set up an existing primary → sub-cell relationship.
        $child->update(['parent_cell_id' => $grandparent->id, 'is_primary' => false]);
        $anotherParent = $this->cell('OTHER PARENT');

        // Attempt to demote the grandparent to a sub-cell of OTHER PARENT.
        // This would create a 3-level hierarchy — must be rejected by the
        // existing-subcells guard inside hierarchyRulesOrThrow.
        $response = $this->actingAs($admin)->put(
            route('impact-cells.update', $grandparent),
            $this->validDetailsPayload($grandparent, isPrimary: '0', parentCellId: $anotherParent->id)
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('hierarchy');
        // Cell stays primary AND keeps its own child.
        $freshGrand = $grandparent->fresh();
        $freshChild = $child->fresh();
        $this->assertTrue($freshGrand->is_primary);
        $this->assertNull($freshGrand->parent_cell_id);
        $this->assertSame($grandparent->id, $freshChild->parent_cell_id);
    }

    // ─── Sub-assertion 9 — /create route resolves BEFORE /{id} ──────────
    public function test_create_route_resolves_for_admin(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/impact-cells/create');

        $response->assertOk();
        // Confirms first-match-wins routing: if /create were registered AFTER
        // /{id}, this URL would 404 (findOrFail("create") on a UUID column).
        $response->assertInertia(fn ($page) => $page
            ->component('ImpactCells/Create')
        );
    }

    // ───────────────────────────────────────────────────────────────────
    // Test helpers
    // ───────────────────────────────────────────────────────────────────

    private function admin(): User
    {
        return $this->makeUserWithRole('Administrator');
    }

    private function makeUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'name'        => 'Phase 17 ' . $role,
            'active_role' => $role,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function cell(string $name): ImpactCell
    {
        return ImpactCell::create([
            'id'         => (string) Str::uuid(),
            'name'       => $name,
            'is_primary' => true,
            'order'      => 0,
        ]);
    }

    /**
     * Build a valid `validateCell` payload that PUT /impact-cells/{id}
     * accepts. Tests override only the fields they care about so the
     * shape stays predictable across the suite.
     *
     * Phase 17 — `parent_cell_id` is `required_if:is_primary,false` so
     * callers that flip is_primary MUST also pass a non-empty parent.
     */
    private function validDetailsPayload(
        ImpactCell $cell,
        ?string $phone = null,
        ?string $address = null,
        ?string $leaderName = null,
        ?string $leaderPhone = null,
        string|bool $isPrimary = true,
        ?string $parentCellId = null,
    ): array {
        // Controller expects a real boolean, but tests pass either '0' or 1.
        // Phase 17 validateCell coerced via (bool) cast — same on the test side.
        $isPrimaryBool = $isPrimary === '0' ? false : (bool) $isPrimary;

        // Phase 17 required_if guard: only require parent when demoting.
        $parentCellId = $parentCellId ?? ($isPrimaryBool ? '' : $cell->id);
        if (! $isPrimaryBool && $parentCellId === '') {
            $parentCellId = $cell->id; // self-loop guard would reject; tests that flip this MUST pass an explicit parent.
        }

        return array_filter([
            'name'                 => $cell->name,
            'phone'                => $phone ?? '',
            'address'              => $address ?? '',
            'parent_cell_id'       => $parentCellId,
            'is_primary'           => $isPrimaryBool,
            'order'                => 0,
            'leader_name'          => $leaderName,
            'leader_phone'         => $leaderPhone,
            'assistant_name'       => null,
            'assistant_phone'      => null,
            'welfare_officer_name' => null,
            'welfare_officer_phone'=> null,
        ], fn ($v) => $v !== null);
    }
}
