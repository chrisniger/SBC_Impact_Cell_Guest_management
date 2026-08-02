<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 32 — dedicated Leadership-Team edit endpoint.
 *
 * Regression contract for the "leadership team / details are not saving"
 * bug: the old React leadership form PUTted ONLY the 6 free-text leadership
 * fields to /impact-cells/{id}, but ImpactCellController::update() ->
 * validateCell() requires `name` + `is_primary`, so every leadership save
 * 303'd back with unreachable validation errors and nothing persisted.
 *
 * The fix splits a dedicated PUT /impact-cells/{id}/leadership endpoint:
 *   - validates ONLY the 6 nullable leadership columns,
 *   - authorizes via ImpactCellPolicy::updateLeadership,
 *   - never accepts `name` / hierarchy fields, so an assigned
 *     Impact_Leaders can edit their own team WITHOUT being able to rename
 *     the cell (the name/hierarchy edit stays behind ImpactCellPolicy::update
 *     = admin/ICA only).
 */
class ImpactCellLeadershipEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─── Admin happy path — leadership fields save via the NEW route ────
    public function test_admin_can_update_leadership_team_via_dedicated_route(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $cell  = $this->cell('ACO/JEDO');

        $response = $this->actingAs($admin)->put(
            route('impact-cells.update-leadership', $cell),
            ['leader_name' => 'John Doe', 'leader_phone' => '0803-111-2222']
        );

        $response->assertRedirect();
        $fresh = $cell->fresh();
        $this->assertSame('John Doe', $fresh->leader_name);
        $this->assertSame('0803-111-2222', $fresh->leader_phone);
        // Other leadership fields untouched.
        $this->assertNull($fresh->assistant_name);
        // Name + hierarchy physically unreachable via this route.
        $this->assertSame('ACO/JEDO', $fresh->name);
        $this->assertTrue($fresh->is_primary);
    }

    // ─── Assigned Impact_Leaders may edit their OWN cell's team ────────
    public function test_assigned_impact_leader_can_edit_own_cell_leadership(): void
    {
        $cell   = $this->cell('ACO/JEDO');
        $leader = $this->makeUserWithRole('Impact_Leaders', $cell->id);

        $response = $this->actingAs($leader)->put(
            route('impact-cells.update-leadership', $cell),
            ['welfare_officer_name' => 'Grace', 'welfare_officer_phone' => '0805-000-0000']
        );

        $response->assertRedirect();
        $fresh = $cell->fresh();
        $this->assertSame('Grace', $fresh->welfare_officer_name);
        $this->assertSame('0805-000-0000', $fresh->welfare_officer_phone);
    }

    // ─── Assigned leader CANNOT rename the cell (name unreachable) ──────
    public function test_assigned_leader_cannot_rename_cell_through_leadership_route(): void
    {
        $cell   = $this->cell('ACO/JEDO');
        $leader = $this->makeUserWithRole('Impact_Leaders', $cell->id);

        $this->actingAs($leader)->put(
            route('impact-cells.update-leadership', $cell),
            ['leader_name' => 'Renamed By Leader', 'name' => 'HACKED NAME']
        );

        $this->assertSame('ACO/JEDO', $cell->fresh()->name);
    }

    // ─── Assigned leader cannot use the full update() route (403) ───────
    public function test_assigned_leader_cannot_edit_cell_details_via_update_route(): void
    {
        $cell   = $this->cell('ACO/JEDO');
        $leader = $this->makeUserWithRole('Impact_Leaders', $cell->id);

        $response = $this->actingAs($leader)->put(
            route('impact-cells.update', $cell),
            ['name' => 'Leader Rename Attempt', 'phone' => '', 'address' => '', 'is_primary' => true, 'order' => 0]
        );

        $response->assertForbidden();
        $this->assertSame('ACO/JEDO', $cell->fresh()->name);
    }

    // ─── Non-assigned leader gets 403 on another cell ───────────────────
    public function test_leader_cannot_edit_a_cell_they_are_not_assigned_to(): void
    {
        $myCell   = $this->cell('MY CELL');
        $otherCell = $this->cell('OTHER CELL');
        $leader   = $this->makeUserWithRole('Impact_Leaders', $myCell->id);

        $response = $this->actingAs($leader)->put(
            route('impact-cells.update-leadership', $otherCell),
            ['leader_name' => 'Intruder']
        );

        $response->assertForbidden();
        $this->assertNull($otherCell->fresh()->leader_name);
    }

    // ─── Non-leader / non-admin role gets 403 ───────────────────────────
    public function test_follow_up_officer_cannot_edit_any_leadership_team(): void
    {
        $cell  = $this->cell('ACO/JEDO');
        $officer = $this->makeUserWithRole('FollowUpOfficer');

        $response = $this->actingAs($officer)->put(
            route('impact-cells.update-leadership', $cell),
            ['leader_name' => 'Intruder']
        );

        $response->assertForbidden();
    }

    // ─── Phase 35 — Impact_Cell_Admin is READ-ONLY on leadership too ────
    public function test_impact_cell_admin_cannot_update_any_leadership_team(): void
    {
        $ica  = $this->makeUserWithRole('Impact_Cell_Admin');
        $cell = $this->cell('ACO/JEDO');

        $response = $this->actingAs($ica)->put(
            route('impact-cells.update-leadership', $cell),
            ['assistant_name' => 'Assistant', 'assistant_phone' => '0801-222-3333']
        );

        $response->assertForbidden();
        $this->assertNull($cell->fresh()->assistant_name);
    }

    // ─── Validation: leadership route rejects nothing unexpected (nullable) ──
    public function test_leadership_route_accepts_partial_empty_submission(): void
    {
        $admin = $this->makeUserWithRole('Administrator');
        $cell  = $this->cell('ACO/JEDO');
        $cell->update(['leader_name' => 'Old Leader']);

        $response = $this->actingAs($admin)->put(
            route('impact-cells.update-leadership', $cell),
            ['leader_name' => '']
        );

        $response->assertRedirect();
        $this->assertNull($cell->fresh()->leader_name);
    }

    // ─── Show-page gate contract (locks the React button visibility) ─────
    public function test_show_page_grants_assigned_leader_only_leadership_edit(): void
    {
        $cell   = $this->cell('ACO/JEDO');
        $leader = $this->makeUserWithRole('Impact_Leaders', $cell->id);

        $response = $this->actingAs($leader)->get(route('impact-cells.show', $cell));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('ImpactCells/Show')
            ->where('canEditLeadership', true)
            ->where('canEditDetails', false)
        );
    }

    public function test_show_page_hides_both_editors_from_non_assigned_leader(): void
    {
        $myCell    = $this->cell('MY CELL');
        $otherCell = $this->cell('OTHER CELL');
        $leader    = $this->makeUserWithRole('Impact_Leaders', $myCell->id);

        $response = $this->actingAs($leader)->get(route('impact-cells.show', $otherCell));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('ImpactCells/Show')
            ->where('canEditLeadership', false)
            ->where('canEditDetails', false)
        );
    }

    public function test_show_page_grants_admin_both_editors(): void
    {
        $cell  = $this->cell('ACO/JEDO');
        $admin = $this->makeUserWithRole('Administrator');

        $response = $this->actingAs($admin)->get(route('impact-cells.show', $cell));

        $response->assertOk()->assertInertia(fn ($page) => $page
            ->component('ImpactCells/Show')
            ->where('canEditLeadership', true)
            ->where('canEditDetails', true)
        );
    }

    // ───────────────────────────────────────────────────────────────────
    // Test helpers
    // ───────────────────────────────────────────────────────────────────

    private function makeUserWithRole(string $role, ?string $impactCellId = null): User
    {
        $user = User::factory()->create([
            'name'           => 'Phase 32 ' . $role,
            'active_role'    => $role,
            'impact_cell_id' => $impactCellId,
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
}
