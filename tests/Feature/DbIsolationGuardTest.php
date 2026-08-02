<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOException;
use Tests\TestCase;

/**
 * Phase 27 — permanent guard for test-time DB isolation.
 *
 * The test suite must run against the dedicated `impact_test` database
 * and must NEVER `migrate:fresh` the live dev `impact_guest` DB.
 *
 * Regression history (empirically diagnosed 2026-08-01):
 *   - phpunit.xml declares `<env name="DB_DATABASE" value="impact_test"
 *     force="true"/>`, but PHPUnit's `<env>` handler only writes
 *     `putenv()` + `$_ENV` — it does NOT touch `$_SERVER`.
 *   - Laravel's `env()` helper reads `$_SERVER` FIRST, and `.env` loads
 *     `DB_DATABASE=impact_guest` into `$_SERVER`. Result:
 *     `env('DB_DATABASE')` ALWAYS returned `impact_guest`, so every
 *     `php artisan test` run wiped the LIVE dev database (Phase 25
 *     incident, recurring).
 *   - Fix: `RefreshDatabaseWithSeed::beforeRefreshingDatabase()` rebinds
 *     the resolved config (and `DB::purge('mysql')`) BEFORE
 *     `migrate:fresh` runs. This test locks that behaviour in.
 *
 * If either test fails, STOP — the suite is about to (or just did) wipe
 * dev data. Recovery: `bash scripts/restart_dev_server.sh`.
 */
class DbIsolationGuardTest extends TestCase
{
    public function test_test_connection_targets_impact_test_not_dev_db(): void
    {
        $actual = DB::connection()->getDatabaseName();

        // Keep 'impact_test' in sync with phpunit.xml's DB_DATABASE env
        // value and the `getenv('DB_DATABASE') ?: 'impact_test'` fallback
        // in RefreshDatabaseWithSeed::beforeRefreshingDatabase().
        $this->assertSame(
            'impact_test',
            $actual,
            'Test suite must run against impact_test, got: ' . $actual
        );
    }

    public function test_dev_impact_guest_database_has_not_been_wiped(): void
    {
        $cfg = config('database.connections.mysql');

        try {
            $pdo = new PDO(
                'mysql:host=' . $cfg['host']
                    . ';port=' . $cfg['port']
                    . ';dbname=impact_guest'
                    . ';charset=' . $cfg['charset'],
                $cfg['username'],
                $cfg['password']
            );
            $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        } catch (PDOException $e) {
            // Skip ONLY when the dev DB genuinely doesn't exist (MySQL
            // driver code 1049 = unknown database, e.g. fresh CI). Auth
            // /privilege failures (1045/1044) must still FAIL loudly — a
            // wrong .env password is a real problem, not a skip.
            if ((int) ($e->errorInfo[1] ?? 0) === 1049) {
                $this->markTestSkipped(
                    'impact_guest dev DB not present in this environment: ' . $e->getMessage()
                );
                return;
            }
            throw $e;
        }

        $this->assertGreaterThan(
            0,
            $count,
            'impact_guest (live dev DB) was wiped by a test run — isolation is broken.'
        );
    }
}
