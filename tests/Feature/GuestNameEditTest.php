<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regression test for the guest-name-not-saving bug.
 *
 * Root cause: `GuestRequest::rules()` validated `follow_officer_id` with the
 * `uuid` rule, but `users.id` is a BIGINT auto-increment PK (not UUID). Every
 * admin edit submitted `follow_officer_id` (an integer like 9), validation
 * failed, Laravel 303-redirected back to the edit form, and the guest name
 * never persisted.
 *
 * Conventions (mirror Phase18OneCredentialPerCellTest):
 *   - Explicit `use RefreshDatabase;` on top of the parent's
 *     RefreshDatabaseWithSeed trait (composes via setUpTraits()).
 *   - RolesAndPermissionsSeeder seeded in setUp() so assignRole resolves.
 *   - CSRF bypass inherited from tests/TestCase.php.
 */
class GuestNameEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_save_a_guest_name_change(): void
    {
        $admin = User::factory()->create([
            'name'     => 'QA Admin',
            'email'    => 'qaadmin@impact.test',
            'password' => Hash::make('QaAdminPass2026!'),
        ]);
        $admin->assignRole('Administrator');

        $officer = User::factory()->create([
            'name'  => 'QA Officer',
            'email' => 'qaofficer@impact.test',
        ]);

        // No GuestFactory exists — create directly. HasUuids auto-generates
        // the string PK. follow_officer_id is an INTEGER (the exact shape
        // the bug depends on: users.id is bigint, not uuid).
        $guest = Guest::create([
            'guest_name'        => 'Original QA Guest',
            'follow_officer_id' => $officer->id,
        ]);

        $newName = 'QA Regression Rename';

        $this->actingAs($admin)
            ->put(route('guests.update', $guest->id), [
                'guest_name'        => $newName,
                'follow_officer_id' => $guest->follow_officer_id,
            ])
            ->assertRedirect(route('guests.show', $guest->id))
            // Explicit: validation must NOT fail — the pre-fix behaviour was
            // a 303 back to the edit form with a follow_officer_id error.
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('guests', [
            'id'         => $guest->id,
            'guest_name' => $newName,
        ]);
    }
}
