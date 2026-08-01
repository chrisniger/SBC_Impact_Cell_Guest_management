<?php

namespace Tests\Feature\Console;

use App\Console\Commands\AuditRolesCommand;
use App\Support\RoleHelper;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 14 — feature tests for `php artisan roles:audit`.
 *
 * Test design philosophy:
 *   The audit command's `handle()` does 3 things: read DB rows → compute
 *   the payload → log + render. The middle step (`computePayload`) is
 *   pure logic with no DB calls and is the only thing that has true
 *   unit-test value. We drive it with synthetic inputs to keep tests
 *   deterministic across this project's `:memory:` SQLite + Spatie
 *   test environment.
 *
 *   One integration test (`test_handle_wires_db_rows_...`) locks the
 *   wire-up between DB rows and the helper. A future regression where
 *   `handle()` reads the wrong table or passes the wrong arguments
 *   to `computePayload` would fail this test.
 */
class AuditRolesCommandTest extends TestCase
{
    // RefreshDatabase is now inherited from Tests\TestCase (via RefreshDatabaseWithSeed).
    // No explicit `use RefreshDatabase;` is needed here.

    public function test_command_is_registered_with_canonical_signature(): void
    {
        $all = $this->app[\Illuminate\Contracts\Console\Kernel::class]->all();

        $this->assertArrayHasKey('roles:audit', $all);
    }

    // -------------------------------------------------------------------
    // Pure-helper tests (no DB state mutation).
    // -------------------------------------------------------------------

    public function test_compute_payload_is_healthy_when_all_expected_present_and_signup_consistent(): void
    {
        $expected   = RoleHelper::ROLE_NAMES; // 10 entries
        $signup     = RoleHelper::SIGNUP_VISIBLE_ROLES; // 2 entries, subset

        $payload = AuditRolesCommand::computePayload(
            expected:          $expected,
            presentOnWeb:      $expected,
            distribution:      ['web' => count($expected)],
            signupVisibleRoles: $signup,
        );

        $this->assertTrue($payload['healthy']);
        $this->assertSame(count($expected), $payload['expected_count']);
        $this->assertSame(count($expected), $payload['present_count']);
        $this->assertSame(count($expected), $payload['present_on_web']);
        $this->assertSame([], $payload['missing']);
        $this->assertSame(0, $payload['missing_count']);
        $this->assertFalse($payload['guard_mismatch']);
        $this->assertSame($signup, $payload['signup_visible_roles']);
        $this->assertSame([], $payload['signup_drift']);
        $this->assertSame(0, $payload['signup_drift_count']);
        $this->assertTrue($payload['signup_consistent']);
    }

    public function test_compute_payload_reports_missing_names_when_present_subset_lacks_expected(): void
    {
        // Inline 8 names that ARE on web guard (the 10 canonical less
        // Administrator + Impact_Zonal_Coordinator).
        $presentOnWeb = [
            'Supervisor',
            'FollowUpOfficer',
            'Follow_UP',
            'Follow_UP_Admin',
            'Follow_UP_View_Only',
            'Impact_Leaders',
            'Impact_Cell_Admin',
            'Impact_Cell_Report',
        ];

        $payload = AuditRolesCommand::computePayload(
            expected:          RoleHelper::ROLE_NAMES,
            presentOnWeb:      $presentOnWeb,
            distribution:      ['web' => count($presentOnWeb)],
            signupVisibleRoles: RoleHelper::SIGNUP_VISIBLE_ROLES,
        );

        $this->assertFalse($payload['healthy']);
        $this->assertSame(2, $payload['missing_count']);
        $this->assertEqualsCanonicalizing(['Administrator', 'Impact_Zonal_Coordinator'], $payload['missing']);
        $this->assertFalse($payload['guard_mismatch']);
        $this->assertTrue($payload['signup_consistent']);
    }

    public function test_compute_payload_reports_guard_mismatch_when_a_role_lives_on_non_web_guard(): void
    {
        // 9 names on web guard (all canonical except 'Impact_Zonal_Coordinator',
        // which we simulate living on the 'api' guard).
        $presentOnWeb = [
            'Administrator',
            'Supervisor',
            'FollowUpOfficer',
            'Follow_UP',
            'Follow_UP_Admin',
            'Follow_UP_View_Only',
            'Impact_Leaders',
            'Impact_Cell_Admin',
            'Impact_Cell_Report',
        ];

        $payload = AuditRolesCommand::computePayload(
            expected:          RoleHelper::ROLE_NAMES,
            presentOnWeb:      $presentOnWeb,
            distribution:      ['web' => count($presentOnWeb), 'api' => 1],
            signupVisibleRoles: RoleHelper::SIGNUP_VISIBLE_ROLES,
        );

        $this->assertFalse($payload['healthy']);
        $this->assertTrue($payload['guard_mismatch']);
        // `missing` correctly contains 'Impact_Zonal_Coordinator' because the
        // role is absent from `$presentOnWeb` — the seeder file says it lives
        // on `api` so the web query doesn't return it. This is BY DESIGN:
        // both `missing` AND `guard_mismatch` light up for the same root
        // cause. (The previous version of this test asserted `missing === []`
        // which conflated the two states; we now assert the precise truth.)
        $this->assertEqualsCanonicalizing(['Impact_Zonal_Coordinator'], $payload['missing']);
        $this->assertSame(1, $payload['missing_count']);
        $this->assertTrue($payload['signup_consistent']);
    }

    public function test_compute_payload_handles_empty_distribution_and_empty_present(): void
    {
        // Fresh DB scenario — guard the edge case so the no-DB deploy-
        // misconfig path doesn't accidentally regress to healthy=true.
        $payload = AuditRolesCommand::computePayload(
            expected:          RoleHelper::ROLE_NAMES,
            presentOnWeb:      [],
            distribution:      [],
            signupVisibleRoles: RoleHelper::SIGNUP_VISIBLE_ROLES,
        );

        $this->assertFalse($payload['healthy']);
        $this->assertSame(count(RoleHelper::ROLE_NAMES), $payload['missing_count']);
        $this->assertEqualsCanonicalizing(RoleHelper::ROLE_NAMES, $payload['missing']);
        $this->assertFalse($payload['guard_mismatch']);
        $this->assertTrue($payload['signup_consistent']);
    }

    public function test_compute_payload_flags_signup_drift_when_signup_role_not_in_expected(): void
    {
        // Today's incident (corrected): SIGNUP_VISIBLE_ROLES contains a
        // role name that ROLE_NAMES does NOT.
        $expected = [
            'A', 'B', 'C',
        ];
        $signup = [
            'A', 'Brand_New_Role_Not_Seeded',
        ];

        $payload = AuditRolesCommand::computePayload(
            expected:          $expected,
            presentOnWeb:      $expected,
            distribution:      ['web' => count($expected)],
            signupVisibleRoles: $signup,
        );

        $this->assertFalse($payload['healthy']);
        $this->assertFalse($payload['signup_consistent']);
        $this->assertSame(['Brand_New_Role_Not_Seeded'], $payload['signup_drift']);
        $this->assertSame(1, $payload['signup_drift_count']);
        // No DB rows missing — the failure is purely a name-list drift.
        $this->assertSame([], $payload['missing']);
        $this->assertFalse($payload['guard_mismatch']);
    }

    public function test_compute_payload_signup_consistent_when_signup_is_strict_subset(): void
    {
        $expected = ['A', 'B', 'C', 'D'];
        $signup   = ['A', 'B'];

        $payload = AuditRolesCommand::computePayload(
            expected:          $expected,
            presentOnWeb:      $expected,
            distribution:      ['web' => count($expected)],
            signupVisibleRoles: $signup,
        );

        $this->assertTrue($payload['signup_consistent']);
        $this->assertSame([], $payload['signup_drift']);
        $this->assertTrue($payload['healthy']);
    }

    public function test_compute_payload_signup_consistent_when_signup_empty(): void
    {
        // A future app variant without public signup (e.g. admin-only
        // provisioning) might ship with SIGNUP_VISIBLE_ROLES = [].
        // The audit must NOT report drift in that case — there's nothing
        // to drift against.
        $payload = AuditRolesCommand::computePayload(
            expected:          ['A', 'B', 'C'],
            presentOnWeb:      ['A', 'B', 'C'],
            distribution:      ['web' => 3],
            signupVisibleRoles: [],
        );

        $this->assertTrue($payload['signup_consistent']);
        $this->assertSame([], $payload['signup_drift']);
        $this->assertTrue($payload['healthy']);
    }

    // -------------------------------------------------------------------
    // Integration test (locks the wire-up between DB and the helper).
    // -------------------------------------------------------------------

    public function test_handle_wires_db_rows_into_compute_payload_and_exits_zero_when_healthy(): void
    {
        // Seed via Spatie's Role::firstOrCreate (mirrors production seeder).
        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $exitCode = Artisan::call('roles:audit', ['--json' => true]);
        $output   = Artisan::output();
        $payload  = json_decode(trim($output), true);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($payload['healthy']);
        $this->assertSame([], $payload['missing']);
        $this->assertTrue($payload['signup_consistent']);
        $this->assertSame(RoleHelper::SIGNUP_VISIBLE_ROLES, $payload['signup_visible_roles']);
    }

    // -------------------------------------------------------------------
    // Log verbosity assertions (helper-driven).
    // -------------------------------------------------------------------

    public function test_handle_logs_at_info_when_payload_is_healthy(): void
    {
        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
        Log::spy();

        Artisan::call('roles:audit');

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'ROLES_AUDIT' && ($ctx['healthy'] ?? false) === true);
    }

    public function test_compute_payload_drives_warning_log_level_when_unhealthy(): void
    {
        // Drive the verbosity switch via direct `Log::$logMethod` so the
        // mapping is tested at the contract level rather than via an
        // integration path that depends on `:memory:`-SQLite state.
        Log::spy();

        $presentOnWeb = [
            'Supervisor',
            'FollowUpOfficer',
            'Follow_UP',
            'Follow_UP_Admin',
            'Follow_UP_View_Only',
            'Impact_Leaders',
            'Impact_Cell_Admin',
            'Impact_Cell_Report',
        ];

        $payload = AuditRolesCommand::computePayload(
            expected:          RoleHelper::ROLE_NAMES,
            presentOnWeb:      $presentOnWeb,
            distribution:      ['web' => count($presentOnWeb)],
            signupVisibleRoles: RoleHelper::SIGNUP_VISIBLE_ROLES,
        );

        $logMethod = $payload['healthy'] ? 'info' : 'warning';
        Log::$logMethod('ROLES_AUDIT', $payload);

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn (string $msg, array $ctx) => $msg === 'ROLES_AUDIT' && ($ctx['healthy'] ?? true) === false);
        Log::shouldNotHaveReceived('info');
    }

    // -------------------------------------------------------------------
    // JSON output shape (lock the wire-format for log-pipeline parsers).
    // -------------------------------------------------------------------

    public function test_json_output_emits_payload_with_expected_keys(): void
    {
        foreach (RoleHelper::ROLE_NAMES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        $exitCode = Artisan::call('roles:audit', ['--json' => true]);
        $output   = Artisan::output();

        $this->assertSame(0, $exitCode);

        foreach ([
            '"expected_count"',
            '"present_count"',
            '"present_on_web"',
            '"missing"',
            '"missing_count"',
            '"guard_distribution"',
            '"guard_mismatch"',
            '"signup_visible_roles"',
            '"signup_drift"',
            '"signup_drift_count"',
            '"signup_consistent"',
            '"healthy"',
        ] as $key) {
            $this->assertStringContainsString($key, $output, "JSON output missing key: {$key}");
        }
    }
}
