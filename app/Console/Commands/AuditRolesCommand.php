<?php

namespace App\Console\Commands;

use App\Support\RoleHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/**
 * Phase 14 — `php artisan roles:audit` (deploy-misconfig smoke gate).
 *
 * Audits three things every deploy:
 *
 *   (1) MISSING ROLES — every name in `RoleHelper::ROLE_NAMES` is
 *       present in the `roles` table on `guard=web`. (Catches
 *       `migrate` without `db:seed`, truncated `roles` table, etc.)
 *
 *   (2) GUARD-NAME MISMATCH — a role may have a row on a non-`web`
 *       guard (e.g. someone manually INSERTed with `guard_name='api'`).
 *       Spatie's `syncRoles()` will not find such rows for the
 *       public signup path (`guard=web`), so the row effectively
 *       doesn't exist for our purposes.
 *
 *   (3) SIGNUP-VISIBLE / ROLE_NAMES DRIFT — the public signup surface
 *       uses `RoleHelper::SIGNUP_VISIBLE_ROLES` (a stricter subset
 *       of `ROLE_NAMES`). If a future PR adds a role to
 *       `SIGNUP_VISIBLE_ROLES` without also adding it to `ROLE_NAMES`,
 *       the seeder never creates the row → the Phase-14 controller
 *       guard fires at runtime instead of being caught at deploy time.
 *       This command compares the two lists and fails on any drift.
 *
 * Today's incident (the original motivation for the audit):
 *
 *   `User::syncRoles(['Impact_Zonal_Coordinator'])` was throwing
 *   `Spatie\Permission\Exceptions\RoleDoesNotExist` on production
 *   because `RoleHelper::ROLE_NAMES[9]` was spelled
 *   'Impact_Zonal_Coordinator' (missing one 'o') while
 *   `RoleHelper::SIGNUP_VISIBLE_ROLES[1]` was correctly spelled
 *   'Impact_Zonal_Coordinator'. (Root-cause analysis: a one-character
 *   spelling drift between two constants in the same file — not a
 *   missing-seeder scenario, as we originally hypothesized.) The
 *   typo was corrected in `RoleHelper.php` AND a data migration
 *   (`2026_07_31_150000_fix_impact_zonal_typo_in_roles_table.php`)
 *   brings existing rows in line. This command's check (3) above is
 *   the defense against recurrence — if a future PR typos or drifts
 *   the two constants again, deploy-misconfig is caught at the
 *   deploy step, not at /register.
 *
 * Other design choices:
 *
 *   - Reads the DB DIRECTLY via `Role::query()`, not via Spatie's
 *     `Role::findByName()` / PermissionRegistrar cache, so a stale
 *     cache cannot silently report "all good" when the DB is empty.
 *   - Default = strict: exit 1 on any of (1), (2), (3). The deploy
 *     pipeline wants a clear pass/fail; there's no value in papering
 *     over a soft anomaly when the production hazard is exactly
 *     that kind of misconfig.
 *   - JSON output mode (`--json`) is for log-pipeline parsers; the
 *     human-readable table mode is the default for an operator
 *     running `php artisan roles:audit` interactively.
 *
 * Usage:
 *   php artisan roles:audit           # default — table output, strict
 *   php artisan roles:audit --json    # JSON output (for log-pipeline grep)
 */
class AuditRolesCommand extends Command
{
    /**
     * Laravel 11/12 auto-discovers command classes in `app/Console/Commands/`,
     * so no `withCommands(...)` call is needed in `bootstrap/app.php`.
     *
     * The name `roles:audit` slots cleanly into Spatie-aware ops mental
     * models (`roles:list` / `roles:create` / `roles:repair`).
     */
    protected $signature = 'roles:audit
        {--json : Emit a JSON payload instead of the human-readable table}';

    protected $description = 'Audit Spatie roles against the canonical RoleHelper::ROLE_NAMES list AND verify SIGNUP_VISIBLE_ROLES ⊆ ROLE_NAMES; log + exit non-zero on any anomaly.';

    public function handle(): int
    {
        // Defeat Spatie's permission/role cache so a stale cashed "all good"
        // state cannot mask an empty `roles` table on a deploy.
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $expected       = RoleHelper::ROLE_NAMES;
        $signupVisible  = RoleHelper::SIGNUP_VISIBLE_ROLES;

        $presentOnWeb = Role::query()
            ->whereIn('name', $expected)
            ->where('guard_name', 'web')
            ->pluck('name')
            ->all();

        // Guard distribution — surfaces hypothesis H3 from the deploy-
        // misconfig investigation (role present but under a different
        // guard_name, e.g. someone manually INSERTed with `guard_name='api'`).
        $distribution = Role::query()
            ->whereIn('name', $expected)
            ->selectRaw('guard_name, COUNT(*) AS c')
            ->groupBy('guard_name')
            ->pluck('c', 'guard_name')
            ->toArray();

        $payload = self::computePayload(
            expected:          $expected,
            presentOnWeb:      $presentOnWeb,
            distribution:      $distribution,
            signupVisibleRoles: $signupVisible,
        );

        // Log at info when healthy, WARNING when unhealthy — so monitoring
        // pipelines filtering `>= warning` automatically pick up the bad
        // case (deploy-misconfig = deploy should NOT be declared green).
        $logMethod = $payload['healthy'] ? 'info' : 'warning';
        Log::$logMethod('ROLES_AUDIT', $payload);

        if ($this->option('json')) {
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $payload['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        // --- Human-readable mode ---
        $this->info('Spreadsheet roles audit (Phase 14 deploy-misconfig gate)');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Expected total (ROLE_NAMES)',       (string) $payload['expected_count']],
                ['Found on guard=web',                (string) $payload['present_on_web']],
                ['Missing names',                     $payload['missing'] ? implode(', ', $payload['missing']) : '(none)'],
                ['Guard distribution',                $payload['guard_distribution'] === [] ? '(empty)' : json_encode($payload['guard_distribution'])],
                ['Guard mismatch',                    $payload['guard_mismatch'] ? 'YES — some expected roles live on guard != web' : 'no'],
                ['Signup-visible (SIGNUP_VISIBLE_ROLES)', $payload['signup_visible_roles'] ? implode(', ', $payload['signup_visible_roles']) : '(empty)'],
                ['Signup/ROLE_NAMES drift',           $this->driftLabel($payload)],
                ['Healthy',                           $payload['healthy'] ? 'YES' : 'NO'],
            ]
        );

        if (! empty($payload['missing'])) {
            $this->error(sprintf(
                "  %d role(s) missing: %s\n  Remediation: php artisan db:seed --class=RolesAndPermissionsSeeder",
                $payload['missing_count'],
                implode(', ', $payload['missing']),
            ));
        }

        if ($payload['guard_mismatch']) {
            $this->warn("  Guard mismatch detected — expected roles live under a non-'web' guard_name. SyncRoles() will throw RoleDoesNotExist on the public /register path. Re-seed or update the offending rows to guard_name='web'.");
        }

        if (! empty($payload['signup_drift'])) {
            $this->error(sprintf(
                "  %d role(s) leaked into SIGNUP_VISIBLE_ROLES that are NOT in ROLE_NAMES: %s\n  Remediation: add them to RoleHelper::ROLE_NAMES AND run db:seed --class=RolesAndPermissionsSeeder so the seeder creates the row. Today's incident was exactly this kind of drift.",
                count($payload['signup_drift']),
                implode(', ', $payload['signup_drift']),
            ));
        }

        return $payload['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Helper for the human-readable table: render the drift row's status.
     * May be `'no'`, `'yes (with names)'`, or `'n/a — empty signup list'`.
     */
    private function driftLabel(array $payload): string
    {
        if (empty($payload['signup_visible_roles'])) {
            return 'n/a — SIGNUP_VISIBLE_ROLES is empty';
        }
        if ($payload['signup_consistent']) {
            return 'no';
        }
        return 'YES — drift list: ' . implode(', ', $payload['signup_drift']);
    }

    /**
     * Phase 14 — pure payload-computation helper. Public + static so the
     * feature test drives it directly with synthetic inputs (no DB state
     * mutation needed).
     *
     * Why factored out:
     *   The audit command's `handle()` does 3 things: read DB rows →
     *   compute payload → log + render. The middle 75% is pure logic
     *   and is the only thing that has unit-test value. Driving it via
     *   synthetic inputs sidesteps a Spatie `:memory:`-SQLite oddity
     *   observed in this project's test environment.
     *
     * Inputs:
 *   - $expected:          array of expected role names (canonical
 *                         from RoleHelper::ROLE_NAMES).
 *   - $presentOnWeb:      array of role names ACTUALLY present on
 *                         guard='web' (subset of $expected).
 *   - $distribution:      map of guard-name → count of expected-named
 *                         rows on that guard. E.g. ['web' => 10] or
 *                         ['web' => 9, 'api' => 1].
 *   - $signupVisibleRoles: REQUIRED. Array of role names exposed on
 *                         the public /register form (RoleHelper::
 *                         SIGNUP_VISIBLE_ROLES). Used to detect drift
 *                         vs $expected (today's 2026-07-31 incident:
 *                         SIGNUP_VISIBLE_ROLES contained a name that
 *                         ROLE_NAMES did not → Spatie's syncRoles threw
 *                         RoleDoesNotExist on /register). Pass `[]`
 *                         ONLY if the project has no public-signup
 *                         surface (admin-only provisioning); an empty
 *                         list is the canonical "no drift possible"
 *                         answer and `signup_consistent` will be true.
 *                         NOTE: this arg has NO default — callers MUST
 *                         supply it explicitly so the drift check can
 *                         never be silently skipped.
     *
     * Output: array{expected_count, present_count, present_on_web,
     *                missing, missing_count, guard_distribution,
     *                guard_mismatch, signup_visible_roles,
     *                signup_drift, signup_drift_count, signup_consistent,
     *                healthy}.
     */
    public static function computePayload(
        array $expected,
        array $presentOnWeb,
        array $distribution,
        array $signupVisibleRoles,
    ): array {
        $expectedTotal = count($expected);
        $missing = array_values(array_diff($expected, $presentOnWeb));

        // Guard mismatch: at least one expected-name row lives on a non-web guard.
        // The second clause (`expectedTotal === array_sum($distribution)`)
        // is a sanity floor — if SOME expected role is already missing, the
        // command already reports it through `$missing` and the uniform
        // `empty($missing)` check below classifies the run as unhealthy.
        // We keep the clause explicit so the bool self-documents.
        $totalAcrossGuards = array_sum($distribution);
        $mismatchedGuard = ($totalAcrossGuards > ($distribution['web'] ?? 0))
            && ($expectedTotal === $totalAcrossGuards);

        // Signup-visible / ROLE_NAMES drift — the today's-incident
        // scenario. SIGNUP_VISIBLE_ROLES must be a strict subset of
        // ROLE_NAMES; otherwise the seeder creates no row for a name
        // that the signup form ships, and Spatie's syncRoles throws
        // RoleDoesNotExist at /register time.
        $signupDrift = array_values(array_diff($signupVisibleRoles, $expected));
        $signupConsistent = empty($signupDrift);

        $healthy = empty($missing)
            && ! $mismatchedGuard
            && $signupConsistent;

        return [
            'expected_count'     => $expectedTotal,
            'present_count'      => count($presentOnWeb),
            'present_on_web'     => $distribution['web'] ?? 0,
            'missing'            => $missing,
            'missing_count'      => count($missing),
            'guard_distribution' => $distribution,
            'guard_mismatch'     => $mismatchedGuard,
            'signup_visible_roles' => $signupVisibleRoles,
            'signup_drift'       => $signupDrift,
            'signup_drift_count' => count($signupDrift),
            'signup_consistent'  => $signupConsistent,
            'healthy'            => $healthy,
        ];
    }
}
