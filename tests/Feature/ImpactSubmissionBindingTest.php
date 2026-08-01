<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ImpactSubmissionBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_all_submission_forms_expose_registered_cell_for_impact_leader(): void
    {
        $registeredCell = $this->makeCell('Registered Cell');
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $registeredCell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        foreach (['member', 'report', 'childbirth', 'soul'] as $type) {
            $response = $this->actingAs($leader)->get("/impact-submissions/create?type={$type}");

            $response->assertOk();
            $this->assertSame([
                'id' => $registeredCell->id,
                'name' => 'Registered Cell',
            ], $response->inertiaProps('assignedCell'));
        }
    }

    public function test_report_form_does_not_bind_cell_for_other_impact_roles(): void
    {
        $cellAdmin = User::factory()->create([
            'active_role' => 'Impact_Cell_Admin',
        ]);
        $cellAdmin->assignRole('Impact_Cell_Admin');

        $response = $this->actingAs($cellAdmin)->get('/impact-submissions/create?type=report');

        $response->assertOk();
        $this->assertNull($response->inertiaProps('assignedCell'));
    }

    public function test_impact_leader_without_registered_cell_gets_exact_validation_error(): void
    {
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => null,
        ]);
        $leader->assignRole('Impact_Leaders');

        $response = $this->actingAs($leader)->post('/impact-submissions', [
            // Even if the client sends a cell, the controller replaces it
            // with the user's registered value (null) before validation.
            'impact_cell_id' => (string) Str::uuid(),
            'type' => 'report',
            'fellowship_date_key' => '2026-08-03',
            'data' => [
                'fellowship_date' => '2026-08-03',
                'adults' => 0,
            ],
        ]);

        $response
            ->assertSessionHasErrors([
                'impact_cell_id' => 'The impact cell id field is required.',
            ]);

        $this->assertDatabaseCount('impact_submissions', 0);
    }

    public function test_impact_leader_non_report_submissions_are_bound_to_registered_cell(): void
    {
        $registeredCell = $this->makeCell('Registered Cell');
        $otherCell = $this->makeCell('Other Cell');
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $registeredCell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        foreach (['member', 'childbirth', 'soul'] as $index => $type) {
            $response = $this->actingAs($leader)->post('/impact-submissions', [
                'impact_cell_id' => $otherCell->id,
                'type' => $type,
                'data' => ['fixture' => $type],
            ]);

            $submission = ImpactSubmission::query()
                ->where('user_id', $leader->id)
                ->where('type', $type)
                ->first();

            $this->assertNotNull($submission);
            $response->assertRedirect(route('impact-submissions.show', $submission->id));
            $this->assertSame($type, $submission->type);
            $this->assertSame($registeredCell->id, $submission->impact_cell_id);
            $this->assertDatabaseMissing('impact_submissions', [
                'type' => $type,
                'impact_cell_id' => $otherCell->id,
                'user_id' => $leader->id,
            ]);
        }

        $this->assertDatabaseCount('impact_submissions', 3);
    }

    public function test_impact_leader_without_registered_cell_gets_exact_validation_error_for_non_report_submissions(): void
    {
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => null,
        ]);
        $leader->assignRole('Impact_Leaders');

        foreach (['member', 'childbirth', 'soul'] as $type) {
            $response = $this->actingAs($leader)->post('/impact-submissions', [
                'impact_cell_id' => (string) Str::uuid(),
                'type' => $type,
                'data' => ['fixture' => $type],
            ]);

            $response->assertSessionHasErrors([
                'impact_cell_id' => 'The impact cell id field is required.',
            ]);
        }

        $this->assertDatabaseCount('impact_submissions', 0);
    }

    public function test_impact_leader_report_is_bound_to_registered_cell(): void
    {
        $registeredCell = $this->makeCell('Registered Cell');
        $otherCell = $this->makeCell('Other Cell');
        $leader = User::factory()->create([
            'active_role' => 'Impact_Leaders',
            'impact_cell_id' => $registeredCell->id,
        ]);
        $leader->assignRole('Impact_Leaders');

        $response = $this->actingAs($leader)->post('/impact-submissions', [
            // Simulate a forged or stale selector value. The controller must
            // replace this with the cell assigned during registration.
            'impact_cell_id' => $otherCell->id,
            'type' => 'report',
            'fellowship_date_key' => '2026-08-01',
            'data' => [
                'fellowship_date' => '2026-08-01',
                'adults' => 12,
            ],
        ]);

        $submission = ImpactSubmission::query()->latest('created_at')->first();

        $this->assertNotNull($submission);
        $response->assertRedirect(route('impact-submissions.show', $submission->id));
        $this->assertSame($registeredCell->id, $submission->impact_cell_id);
        $this->assertDatabaseHas('impact_submissions', [
            'id' => $submission->id,
            'impact_cell_id' => $registeredCell->id,
            'user_id' => $leader->id,
            'type' => 'report',
        ]);
        $this->assertDatabaseMissing('impact_submissions', [
            'impact_cell_id' => $otherCell->id,
            'user_id' => $leader->id,
            'type' => 'report',
        ]);
    }

    public function test_other_impact_cell_roles_can_select_cell_for_all_submission_types(): void
    {
        $selectedCell = $this->makeCell('Selected Cell');
        $cellAdmin = User::factory()->create([
            'active_role' => 'Impact_Cell_Admin',
        ]);
        $cellAdmin->assignRole('Impact_Cell_Admin');

        foreach (['member', 'report', 'childbirth', 'soul'] as $type) {
            $response = $this->actingAs($cellAdmin)->post('/impact-submissions', [
                'impact_cell_id' => $selectedCell->id,
                'type' => $type,
                'data' => ['fixture' => $type],
            ]);

            $submission = ImpactSubmission::query()
                ->where('user_id', $cellAdmin->id)
                ->where('type', $type)
                ->first();

            $this->assertNotNull($submission);
            $response->assertRedirect(route('impact-submissions.show', $submission->id));
            $this->assertSame($selectedCell->id, $submission->impact_cell_id);
        }
    }

    public function test_other_impact_cell_roles_can_select_report_cell(): void
    {
        $selectedCell = $this->makeCell('Selected Cell');
        $otherCell = $this->makeCell('Other Cell');
        $cellAdmin = User::factory()->create([
            'active_role' => 'Impact_Cell_Admin',
        ]);
        $cellAdmin->assignRole('Impact_Cell_Admin');

        $response = $this->actingAs($cellAdmin)->post('/impact-submissions', [
            'impact_cell_id' => $selectedCell->id,
            'type' => 'report',
            'fellowship_date_key' => '2026-08-02',
            'data' => [
                'fellowship_date' => '2026-08-02',
                'adults' => 8,
            ],
        ]);

        $submission = ImpactSubmission::query()->latest('created_at')->first();

        $this->assertNotNull($submission);
        $response->assertRedirect(route('impact-submissions.show', $submission->id));
        $this->assertSame($selectedCell->id, $submission->impact_cell_id);
        $this->assertNotSame($otherCell->id, $submission->impact_cell_id);
    }

    private function makeCell(string $name): ImpactCell
    {
        return ImpactCell::create([
            'id' => (string) Str::uuid(),
            'name' => $name,
            'is_primary' => true,
            'order' => 0,
        ]);
    }
}
