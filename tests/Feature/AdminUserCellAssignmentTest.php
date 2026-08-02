<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUserCellAssignmentTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_impact_leader_with_one_impact_cell(): void
    {
        $admin = $this->admin();
        $cell = $this->cell('Leader Cell');

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $this->payload([
            'name' => 'Cell Leader',
            'email' => 'cell-leader@impact.test',
            'roles' => ['Impact_Leaders'],
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $cell->id,
        ]));

        $response->assertRedirect(route('admin.users.index'));

        $leader = User::where('email', 'cell-leader@impact.test')->firstOrFail();
        $this->assertSame($cell->id, $leader->impact_cell_id);
        $this->assertSame([], $leader->zonalImpactCells()->pluck('impact_cells.id')->all());
    }

    public function test_admin_can_create_zonal_coordinator_with_multiple_impact_cells(): void
    {
        $admin = $this->admin();
        $cells = [
            $this->cell('Zonal Cell A'),
            $this->cell('Zonal Cell B'),
        ];

        $response = $this->actingAs($admin)->post(route('admin.users.store'), $this->payload([
            'name' => 'Zonal Coordinator',
            'email' => 'zonal-coordinator@impact.test',
            'roles' => ['Impact_Zonal_Coordinator'],
            'active_role' => 'Impact_Zonal_Coordinator',
            'zonal_impact_cell_ids' => collect($cells)->pluck('id')->all(),
        ]));

        $response->assertRedirect(route('admin.users.index'));

        $zonal = User::where('email', 'zonal-coordinator@impact.test')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            collect($cells)->pluck('id')->all(),
            $zonal->zonalImpactCells()->pluck('impact_cells.id')->all(),
        );
    }

    public function test_admin_must_assign_required_cells_for_role(): void
    {
        $admin = $this->admin();

        $leaderResponse = $this->actingAs($admin)->post(route('admin.users.store'), $this->payload([
            'name' => 'Unassigned Leader',
            'email' => 'unassigned-leader@impact.test',
            'roles' => ['Impact_Leaders'],
            'active_role' => 'Impact_Leaders',
        ]));

        $leaderResponse->assertSessionHasErrors('impact_cell_id');
        $this->assertDatabaseMissing('users', ['email' => 'unassigned-leader@impact.test']);

        $zonalResponse = $this->actingAs($admin)->post(route('admin.users.store'), $this->payload([
            'name' => 'Unassigned Zonal',
            'email' => 'unassigned-zonal@impact.test',
            'roles' => ['Impact_Zonal_Coordinator'],
            'active_role' => 'Impact_Zonal_Coordinator',
        ]));

        $zonalResponse->assertSessionHasErrors('zonal_impact_cell_ids');
        $this->assertDatabaseMissing('users', ['email' => 'unassigned-zonal@impact.test']);
    }

    public function test_admin_add_user_page_exposes_primary_cells_for_role_selectors(): void
    {
        $admin = $this->admin();
        $primary = $this->cell('Add Primary Cell');
        $nonPrimary = ImpactCell::create([
            'id' => (string) Str::uuid(),
            'name' => 'Add Non-primary Cell',
            'parent_cell_id' => $primary->id,
            'is_primary' => false,
            'order' => 1,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.users.index'));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Index')
            ->has('cellsList', 1)
            ->where('cellsList.0.id', $primary->id)
            ->where('cellsList.0.name', $primary->name)
            ->where('rolesForNew', fn ($roles) => $roles->contains('Impact_Leaders')
                && $roles->contains('Impact_Zonal_Coordinator'))
        );

        $this->assertSame(
            [$primary->id],
            collect($response->inertiaProps('cellsList'))->pluck('id')->all(),
        );
        $this->assertNotContains(
            $nonPrimary->id,
            collect($response->inertiaProps('cellsList'))->pluck('id')->all(),
        );
    }

    public function test_admin_edit_page_prefills_single_leader_cell_and_no_zonal_cells(): void
    {
        $admin = $this->admin();
        $cell = $this->cell('Edit Leader Cell');
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $cell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $leader));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->where('cellsList.0.id', $cell->id)
            ->where('user.impact_cell_id', $cell->id)
            ->where('user.zonal_impact_cell_ids', [])
        );

        $userProps = $response->inertiaProps('user');
        $this->assertSame($cell->id, $userProps['impact_cell_id']);
        $this->assertSame([], $userProps['zonal_impact_cell_ids']);
    }

    public function test_admin_edit_page_prefills_multiple_zonal_cells_and_no_leader_cell(): void
    {
        $admin = $this->admin();
        $cells = [
            $this->cell('Edit Zonal Cell A'),
            $this->cell('Edit Zonal Cell B'),
        ];
        $zonal = User::factory()->create([
            'active_role' => 'Impact_Zonal_Coordinator',
            'impact_cell_id' => null,
        ]);
        $zonal->assignRole('Impact_Zonal_Coordinator');
        $zonal->zonalImpactCells()->sync(collect($cells)->pluck('id')->all());

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $zonal));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.impact_cell_id', null)
            ->where('user.zonal_impact_cell_ids', fn ($ids) => $ids->count() === 2)
        );

        $this->assertEqualsCanonicalizing(
            collect($cells)->pluck('id')->all(),
            $response->inertiaProps('user.zonal_impact_cell_ids'),
        );
    }

    public function test_admin_edit_page_does_not_prefill_cell_selectors_for_unrelated_role(): void
    {
        $admin = $this->admin();
        $officer = User::factory()->create([
            'active_role' => 'FollowUpOfficer',
        ]);
        $officer->assignRole('FollowUpOfficer');

        $response = $this->actingAs($admin)->get(route('admin.users.edit', $officer));

        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Users/Edit')
            ->where('user.impact_cell_id', null)
            ->where('user.zonal_impact_cell_ids', [])
        );
    }

    public function test_admin_users_index_includes_assignments_for_the_cells_column(): void
    {
        $admin = $this->admin();
        $leaderCell = $this->cell('Table Leader Cell');
        $zonalCells = [
            $this->cell('Table Zonal Cell A'),
            $this->cell('Table Zonal Cell B'),
        ];

        $leader = User::factory()->create([
            'name' => 'Table Leader',
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $leaderCell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        $zonal = User::factory()->create([
            'name' => 'Table Zonal',
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);
        $zonal->assignRole('Impact_Zonal_Coordinator');
        $zonal->zonalImpactCells()->sync(collect($zonalCells)->pluck('id')->all());

        $response = $this->actingAs($admin)->get(route('admin.users.index'));
        $rows = collect($response->inertiaProps('users.data'))->keyBy('id');

        $this->assertSame($leaderCell->id, $rows[$leader->id]['impact_cell_id']);
        $this->assertSame([], $rows[$leader->id]['zonal_impact_cell_ids']);
        $this->assertEqualsCanonicalizing(
            collect($zonalCells)->pluck('id')->all(),
            $rows[$zonal->id]['zonal_impact_cell_ids'],
        );
    }

    public function test_admin_update_replaces_zonal_coordinator_cell_assignments(): void
    {
        $admin = $this->admin();
        $first = $this->cell('Initial Zonal Cell');
        $second = $this->cell('Replacement Zonal Cell');
        $zonal = User::factory()->create([
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);
        $zonal->assignRole('Impact_Zonal_Coordinator');
        $zonal->zonalImpactCells()->sync([$first->id]);

        $response = $this->actingAs($admin)->put(route('admin.users.update', $zonal), $this->payload([
            'name' => $zonal->name,
            'email' => $zonal->email,
            'roles' => ['Impact_Zonal_Coordinator'],
            'active_role' => 'Impact_Zonal_Coordinator',
            'zonal_impact_cell_ids' => [$second->id],
            'password' => '',
            'password_confirmation' => '',
        ]));

        $response->assertRedirect(route('admin.users.index'));
        $this->assertSame(
            [$second->id],
            $zonal->fresh()->zonalImpactCells()->pluck('impact_cells.id')->all(),
        );
    }

    public function test_admin_can_inline_update_zonal_cell_assignments_from_table_row(): void
    {
        $admin = $this->admin();
        $first = $this->cell('Inline Zonal Cell A');
        $second = $this->cell('Inline Zonal Cell B');
        $zonal = User::factory()->create([
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);
        $zonal->assignRole('Impact_Zonal_Coordinator');
        $zonal->zonalImpactCells()->sync([$first->id]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update-zonal-cells', $zonal), [
                'zonal_impact_cell_ids' => [$first->id, $second->id],
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $zonal->fresh()->zonalImpactCells()->pluck('impact_cells.id')->all(),
        );
    }

    public function test_inline_zonal_cell_update_rejects_non_primary_cell(): void
    {
        $admin = $this->admin();
        $primary = $this->cell('Inline Primary Cell');
        $nonPrimary = ImpactCell::create([
            'id' => (string) Str::uuid(),
            'name' => 'Inline Non-primary Cell',
            'parent_cell_id' => $primary->id,
            'is_primary' => false,
            'order' => 1,
        ]);
        $zonal = User::factory()->create([
            'active_role' => 'Impact_Zonal_Coordinator',
        ]);
        $zonal->assignRole('Impact_Zonal_Coordinator');
        $zonal->zonalImpactCells()->sync([$primary->id]);

        $response = $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.update-zonal-cells', $zonal), [
                'zonal_impact_cell_ids' => [$nonPrimary->id],
            ]);

        // Validation errors arrive per-index (same wire shape as the Add
        // modal's errorsForArr handling on the frontend).
        $response->assertSessionHasErrors('zonal_impact_cell_ids.0');
        $this->assertSame(
            [$primary->id],
            $zonal->fresh()->zonalImpactCells()->pluck('impact_cells.id')->all(),
        );
    }

    public function test_inline_zonal_cell_update_rejects_non_zonal_user(): void
    {
        $admin = $this->admin();
        $cell = $this->cell('Inline Leader Cell');
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $cell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update-zonal-cells', $leader), [
                'zonal_impact_cell_ids' => [$cell->id],
            ]);

        $response->assertStatus(422);
        $this->assertSame([], $leader->fresh()->zonalImpactCells()->pluck('impact_cells.id')->all());
    }

    private function admin(): User
    {
        $admin = User::factory()->create([
            'name' => 'Test Administrator',
            'email' => 'assignment-admin@impact.test',
            'active_role' => 'Administrator',
        ]);
        $admin->assignRole('Administrator');

        return $admin;
    }

    private function cell(string $name): ImpactCell
    {
        return ImpactCell::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'is_primary' => true,
            'order' => 0,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'password' => 'Assignment##101',
            'password_confirmation' => 'Assignment##101',
            'roles' => ['FollowUpOfficer'],
            'active_role' => 'FollowUpOfficer',
        ], $overrides);
    }
}
