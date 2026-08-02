<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 31 — regression test for `php artisan seed:canonical`.
 *
 * Verifies the shared reseed command (used by the post-test PHPUnit
 * extension AND `composer test-local`) actually restores the canonical
 * fixture set on a freshly-migrated database — roles + admin fixture —
 * and exits 0. Runs against the isolated `impact_test` DB (Phase 27),
 * so it never touches the live dev DB.
 */
class SeedCanonicalCommandTest extends TestCase
{
    public function test_seed_canonical_restores_roles_and_admin_fixture(): void
    {
        $this->artisan('seed:canonical')
            ->expectsOutputToContain('Admin fixture present')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['email' => 'sbcadmin@impact.test']);
        $this->assertTrue(
            Role::where('name', 'Administrator')->exists(),
            'RolesAndPermissionsSeeder should have created the Administrator role',
        );
    }

    public function test_seed_canonical_is_idempotent_on_second_run(): void
    {
        $this->artisan('seed:canonical')->assertExitCode(0);
        $this->artisan('seed:canonical')->assertExitCode(0);

        $this->assertSame(
            1,
            User::where('email', 'sbcadmin@impact.test')->count(),
            'Second run must not duplicate the admin fixture (firstOrCreate contract)',
        );
    }
}
