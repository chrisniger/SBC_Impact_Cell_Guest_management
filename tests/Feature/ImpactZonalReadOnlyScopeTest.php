<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 36 — Impact_Zonal_Coordinator read-only scope.
 *
 * The zonal coordinator contract:
 *   - `/impact-cells` lists ONLY the cells assigned to them (admin assigns
 *     1+ cells via the impact_cell_user pivot).
 *   - The cell detail page is reachable only for assigned cells.
 *   - They CANNOT submit reports / submissions (read-only).
 *   - They see the full activity feed for their assigned cells.
 *   - The Leadership Board is scoped to their assigned cells.
 */
class ImpactZonalReadOnlyScopeTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_zonal_impact_cells_index_shows_only_assigned_cells(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $response = $this->actingAs($zonal)->get(route('impact-cells.index'));

        $response->assertOk();
        $ids = collect($response->inertiaProps('cells'))->pluck('id')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_zonal_with_no_assigned_cells_sees_empty_cells_list(): void
    {
        $zonal = $this->zonal();
        $this->cell('Unrelated Cell');

        $response = $this->actingAs($zonal)->get(route('impact-cells.index'));

        $response->assertOk();
        $this->assertSame([], collect($response->inertiaProps('cells'))->pluck('id')->all());
    }

    public function test_zonal_can_view_assigned_cell_but_not_unassigned_cell_detail(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $this->actingAs($zonal)->get(route('impact-cells.show', $assigned->id))->assertOk();
        $this->actingAs($zonal)->get(route('impact-cells.show', $other->id))->assertForbidden();
    }

    public function test_zonal_cannot_open_the_submission_form(): void
    {
        $zonal = $this->zonal();

        $this->actingAs($zonal)->get(route('impact-submissions.create'))->assertForbidden();
    }

    public function test_zonal_cannot_view_submission_detail_outside_assigned_cells(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $leader = $this->leader($assigned);
        $inAssigned = ImpactSubmission::create([
            'impact_cell_id' => $assigned->id,
            'user_id'        => $leader->id,
            'type'           => 'member',
            'data'           => ['name' => 'Assigned Member'],
        ]);
        $inOther = ImpactSubmission::create([
            'impact_cell_id' => $other->id,
            'user_id'        => $leader->id,
            'type'           => 'member',
            'data'           => ['name' => 'Other Member'],
        ]);

        $this->actingAs($zonal)->get(route('impact-submissions.show', $inAssigned->id))->assertOk();
        $this->actingAs($zonal)->get(route('impact-submissions.show', $inOther->id))->assertForbidden();
    }

    public function test_zonal_soul_search_is_scoped_to_assigned_cells(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $leader = $this->leader($assigned);
        ImpactSubmission::create([
            'impact_cell_id' => $assigned->id,
            'user_id'        => $leader->id,
            'type'           => 'soul',
            'data'           => ['full_name' => 'Zonal Soul', 'phone' => '0801'],
        ]);
        ImpactSubmission::create([
            'impact_cell_id' => $other->id,
            'user_id'        => $leader->id,
            'type'           => 'soul',
            'data'           => ['full_name' => 'Leaked Soul', 'phone' => '0802'],
        ]);

        $response = $this->actingAs($zonal)
            ->getJson(route('impact-submissions.search', ['q' => 'Soul']))
            ->assertOk()
            ->json();

        $names = collect($response)->pluck('name')->all();
        $this->assertContains('Zonal Soul', $names);
        $this->assertNotContains('Leaked Soul', $names);
    }

    public function test_zonal_cannot_submit_a_report(): void
    {
        $zonal = $this->zonal();
        $cell = $this->cell('Cell');
        $zonal->zonalImpactCells()->sync([$cell->id]);

        $this->actingAs($zonal)
            ->post(route('impact-submissions.store'), [
                'impact_cell_id' => $cell->id,
                'type'           => 'report',
                'data'           => ['members' => 3],
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('impact_submissions', 0);
    }

    public function test_zonal_submissions_index_is_scoped_to_assigned_cells_and_read_only(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $leader = $this->leader($assigned);
        $inAssigned = ImpactSubmission::create([
            'impact_cell_id' => $assigned->id,
            'user_id'        => $leader->id,
            'type'           => 'report',
            'data'           => ['members' => 5],
        ]);
        $inOther = ImpactSubmission::create([
            'impact_cell_id' => $other->id,
            'user_id'        => $leader->id,
            'type'           => 'member',
            'data'           => ['name' => 'Other Member'],
        ]);

        $response = $this->actingAs($zonal)->get(route('impact-submissions.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('ImpactSubmissions/Index')
            ->where('canCreate', false)
        );

        $ids = collect($response->inertiaProps('submissions.data'))->pluck('id')->all();
        $this->assertContains($inAssigned->id, $ids);
        $this->assertNotContains($inOther->id, $ids);
    }

    public function test_zonal_leadership_board_is_scoped_to_assigned_cells(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $response = $this->actingAs($zonal)->get(route('leadership.index'));

        $response->assertOk();
        $ids = collect($response->inertiaProps('boards'))->pluck('cellId')->all();
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_zonal_cannot_fetch_unassigned_leadership_board_json(): void
    {
        $zonal = $this->zonal();
        $assigned = $this->cell('Assigned Cell');
        $other = $this->cell('Other Cell');
        $zonal->zonalImpactCells()->sync([$assigned->id]);

        $this->actingAs($zonal)->getJson(route('leadership-board.show', $assigned->id))->assertOk();
        $this->actingAs($zonal)->getJson(route('leadership-board.show', $other->id))->assertForbidden();
    }

    public function test_non_zonal_impact_cell_users_still_see_all_cells(): void
    {
        $leader = $this->leader($this->cell('Leader Cell'));
        $unassigned = $this->cell('Unrelated Cell');

        $response = $this->actingAs($leader)->get(route('impact-cells.index'));

        $response->assertOk();
        $ids = collect($response->inertiaProps('cells'))->pluck('id')->all();
        $this->assertContains($unassigned->id, $ids);
    }

    private function zonal(): User
    {
        $user = User::factory()->create([
            'active_role'     => 'Impact_Zonal_Coordinator',
            'impact_cell_id'  => null,
        ]);
        $user->assignRole('Impact_Zonal_Coordinator');

        return $user;
    }

    private function leader(ImpactCell $cell): User
    {
        $user = User::factory()->create([
            'active_role'     => 'Impact_Leaders',
            'impact_cell_id'  => $cell->id,
        ]);
        $user->assignRole('Impact_Leaders');

        return $user;
    }

    private function cell(string $name): ImpactCell
    {
        return ImpactCell::create([
            'id'          => (string) Str::uuid(),
            'name'        => $name,
            'is_primary'  => true,
            'order'       => 0,
        ]);
    }
}
