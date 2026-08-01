<?php

namespace Tests\Feature;

use App\Models\ImpactCell;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;    /**
     * Phase 18 — One-credential-per-Impact-Cell invariant.
 *
 * 4 sub-assertions mirror tests/Feature/AdminUserCellAssignmentTest +
 * tests/Feature/Phase17ImpactCellEditTest conventions. Each test
 * isolates the invariant under a different scenario:
 *
 *   1. Signup blocks duplicate (cell is occupied by a live Impact_Leaders).
 *   2. Signup allowed for an unoccupied cell.
 *   3. Admin can edit an existing Impact_Leaders user's profile
 *      (rule's ignore($id) excludes the editing row itself).
 *   4. Soft-delete of a leader frees the slot — a fresh signup succeeds.
 *
 * Conventions (mirror AdminUserCellAssignmentTest)
 *   - Explicit `use RefreshDatabase;` here on top of the parent's
 *     RefreshDatabaseWithSeed trait — composes via Laravel's
 *     `setUpTraits()` detection of `RefreshDatabase` in
 *     `class_uses_recursive()`. The trait's `afterRefreshingDatabase()`
 *     hook clears Spatie's permission cache so User::hasRole() sees
 *     freshly-migrated rows, not the stale snapshot from a prior test.
 *   - CSRF bypass inherited from `tests/TestCase.php` via the
 *     `protected $withoutMiddleware = [ValidateCsrfToken::class]`
 *     property (Phase 20 — centralisation). Per-test setUp no longer
 *     needs to know about CSRF plumbing. Historical note: prior phases
 *     carried a per-test `$this->withoutMiddleware(ValidateCsrfToken::class)`
 *     call here; that line was the silent root cause of test 4's hidden
 *     TokenMismatchException in Phase 18 when the import was case-mismatched
 *     against the framework's actual middleware FQN.
 *   - RolesAndPermissionsSeeder seeded per test.
 *   - ImpactCell::create with deterministic UUID + is_primary=true so
 *     the explicit validation rules pass.
 */
class Phase18OneCredentialPerCellTest extends TestCase
{
    // Explicit `use RefreshDatabase;` keeps Laravel's `setUpTraits()` happy
    // WITHOUT shadowing tests/TestCase.php → RefreshDatabaseWithSeed's
    // `afterRefreshingDatabase()` hook. The trait's hook already calls
    // `app(PermissionRegistrar::class)->forgetCachedPermissions()` to clear
    // Spatie's in-memory permission cache, but we also re-clear it after
    // seeding so syncRoles(['Impact_Leaders']) in the signup POST finds
    // the freshly-seeded role by exact-name lookup (not by stale-id
    // memoised from a previous test in the same process).
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        // Defensive: belt-and-suspenders cache invalidation. The instance
        // form (NOT `::forgetCachedPermissions()` static) is required —
        // modern Spatie exposes this as an instance method only.
        if (app()->bound(\Spatie\Permission\PermissionRegistrar::class)) {
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    private function makeImpactCell(string $name = 'Test Cell'): ImpactCell
    {
        return ImpactCell::create([
            'id'         => (string) Str::uuid(),
            'name'       => $name,
            'is_primary' => true,
        ]);
    }

    private function makeLeaderForCell(ImpactCell $cell, string $email = 'leader@impact.test'): User
    {
        $u = User::factory()->create([
            'name'           => 'Test Leader',
            'email'          => $email,
            'password'       => Hash::make('whatever'),
            'impact_cell_id' => $cell->id,
            'active_role'    => 'Impact_Leaders',
        ]);
        $u->assignRole('Impact_Leaders');
        return $u;
    }

    private function makeAdmin(): User
    {
        $u = User::factory()->create([
            'name'     => 'Test Admin',
            'email'    => 'testadmin@impact.test',
            'password' => Hash::make('TestAdminPass2026!'),
        ]);
        $u->assignRole('Administrator');
        return $u;
    }

    private function signupPayload(string $email, string $cellId, array $extra = []): array
    {
        return array_merge([
            'name'                  => 'New Leader',
            'email'                 => $email,
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'roles'                 => ['Impact_Leaders'],
            'active_role'           => 'Impact_Leaders',
            'impact_cell_id'        => $cellId,
            // Phase 23 — leader_name + leader_phone are now mandatory on
            // Impact_Leaders signup (RegisterInertiaRequest rules). Carry
            // sensible defaults here so signup-focused tests hit the happy
            // path; tests that need the missing-required-key 422 path can
            // pass `'leader_name' => ''` (or similar) via $extra.
            'leader_name'           => 'Test Leader Name',
            'leader_phone'          => '0803-111-2222',
        ], $extra);
    }

    /**
     * 1. Signup with an OCCUPIED cell must surface the friendly error and
     *    NOT create the user row.
     */
    public function test_signup_blocks_duplicate_impact_leader_for_occupied_cell(): void
    {
        $cell = $this->makeImpactCell('Occupied Cell');
        $this->makeLeaderForCell($cell, 'existing@impact.test');

        $response = $this->post('/register', $this->signupPayload('newleader@impact.test', $cell->id));

        // Laravel's `assertSessionHasErrors([key => message_fragment])` walks
        // the ViewErrorBag → MessageBag → messages[] chain correctly. The
        // previous `(array) session('errors')` cast yielded `$errs['bags']['default']`
        // rather than `$errs['impact_cell_id']`, which made
        // `assertNotEmpty($errs['impact_cell_id'] ?? [])` always pass-through
        // to an empty array and fail downstream. The closed-form signature
        // below asserts BOTH the field AND a substring of its message in
        // one call, eliminating the bag-walking dance.
        $response->assertSessionHasErrors([
            // Exact copy of the message thrown from
            // \App\Rules\ImpactCellHasNoLiveLeader::validate(). Avoid
            // truncating into a "substring" — Laravel's
            // `assertSessionHasErrors` semantics for the [key => message]
            // form have varied across versions; passing the full phrase
            // makes the assertion robust to the Str::contains / exact-
            // match split and matches what the FormRequest stores in the
            // session error bag.
            'impact_cell_id' => 'This Impact Cell Already has a Login Credentials, reset the password or ask the Admin for login details.',
        ]);
        $this->assertDatabaseMissing('users', ['email' => 'newleader@impact.test']);
    }

    /**
     * 2. Signup with an UNOCCUPIED cell must succeed.
     */
    public function test_signup_allowed_for_unoccupied_cell(): void
    {
        $cell = $this->makeImpactCell('Fresh Cell');

        $response = $this->post('/register', $this->signupPayload('brandnew@impact.test', $cell->id));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email'          => 'brandnew@impact.test',
            'impact_cell_id' => $cell->id,
        ]);
    }

    /**
     * 3. Admin editing an EXISTING leader's profile (without changing the
     *    cell binding) must NOT trip the invariant. The Rule's ignore($id)
     *    excludes the editing row itself, the controller's transaction
     *    recheck also short-circuits when `user->impact_cell_id === newCellId`.
     */
    public function test_admin_can_edit_existing_leader_without_tripping_invariant(): void
    {
        $cell = $this->makeImpactCell('Edit Me');
        $admin = $this->makeAdmin();
        $existing = $this->makeLeaderForCell($cell, 'editme@impact.test');

        $response = $this->actingAs($admin)
            ->put(route('admin.users.update', $existing), [
                'name'           => 'Edit Renamed',
                'email'          => 'editme@impact.test',
                'roles'          => ['Impact_Leaders'],
                'active_role'    => 'Impact_Leaders',
                'impact_cell_id' => $cell->id, // unchanged
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'id'   => $existing->id,
            'name' => 'Edit Renamed',
        ]);
    }

    /**
     * 4. Soft-delete of an existing leader must FREE the slot — a fresh
     *    signup against the same cell must succeed.
     */
    public function test_soft_delete_frees_the_slot_for_new_signup(): void
    {
        $cell = $this->makeImpactCell('Soft Deleted Lead');
        $former = $this->makeLeaderForCell($cell, 'former@impact.test');
        $former->delete(); // SoftDeletes; deleted_at stamped

        $response = $this->post('/register', $this->signupPayload('newafter@impact.test', $cell->id));

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', [
            'email'          => 'newafter@impact.test',
            'impact_cell_id' => $cell->id,
        ]);
    }
}
