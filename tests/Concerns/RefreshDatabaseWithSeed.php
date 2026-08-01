<?php

namespace Tests\Concerns;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

/**
 * Tests\Concerns\RefreshDatabaseWithSeed
 *
 * Wraps Laravel's `RefreshDatabase` trait to additionally clear Spatie's
 * in-memory permission cache on every refresh cycle.
 *
 * Why this exists
 * ---------------
 * Phase 14 surfaced a `RoleDoesNotExist on Impact_Zonal_Coordinator` error
 * that fired in tests that never queried by role name -- the throw came
 * from Spatie's permission-cache lookup path, not from the test body.
 *
 * Root cause:
 *   1) Spatie's `PermissionRegistrar` keeps an in-memory permission
 *      snapshot via Laravel's `Cache` facade (configured to `array` in
 *      `phpunit.xml` -- per-process, NOT per-DB-connection).
 *   2) `RefreshDatabase` rebuilds the in-memory SQLite schema on every
 *      test method (`migrate:fresh` first method per class,
 *      `beginTransaction` every method, `rollBack` at `tearDown`).
 *   3) The `array` cache survives across PHPUnit test methods in the
 *      same process. The DB rows do NOT (transaction rolled back).
 *   4) When the next test method's setup runs and any Spatie code calls
 *      `loadPermissions()`, the cache returns the OLD snapshot which
 *      lacks the just-migrated roles. Spatie's `Role::findByName()` /
 *      `Permission::findByName()` throws `RoleDoesNotExist` /
 *      `PermissionDoesNotExist` for the seemingly-missing role, even
 *      though it IS present in the freshly-migrated DB.
 *
 * Fix: clear the cache in `afterRefreshingDatabase()`. Laravel's
 * `RefreshDatabase::refreshDatabase()` chain is:
 *   beforeRefreshingDatabase() → restoreInMemoryDatabase() →
 *     refreshTestDatabase() (= migrate:fresh + beginTransaction) →
 *     afterRefreshingDatabase() ← our hook fires here
 * `afterRefreshingDatabase()` fires ONCE per test method, with the schema
 * migrated and the transaction open.
 *
 * Why `afterRefreshingDatabase()` (not `refreshTestDatabase()`)
 * --------------------------------------------------------------
 * Earlier attempt overrode `refreshTestDatabase(): void` -- a PHP-fatal:
 * `Declaration of Illuminate\Foundation\Testing\RefreshDatabase::
 *  refreshTestDatabase() must be compatible with Tests\TestCase::
 *  refreshTestDatabase(): void`. `RefreshDatabase::refreshTestDatabase`
 * is declared WITHOUT a `: void` return type, and PHP strict-signature
 * rules reject a child adding `void` to a parent's no-type method.
 * `afterRefreshingDatabase()` is also no-type-declared (matching) and
 * is Laravel's documented seam for "do post-refresh work" -- the correct
 * layer for cache invalidation.
 *
 * What this trait does NOT do (auto-seed abandonment)
 * ---------------------------------------------------
 * Earlier Phase 14 iterations attempted to also run `DatabaseSeeder`
 * here so local test runs would leave the persistent DB seeded enough
 * for `/register` to keep working. That approach hit interaction
 * symptoms between the seed chain (which itself reads through Spatie:
 * `AdminUserSeeder`'s `$admin->hasRole('Administrator')` triggered
 * Spatie's permission cache reload during the seed) and the cache
 * clear -- the `RoleDoesNotExist` symptom resurfaced inside Spatie's
 * internal `findByName()` calls during seed chain. The maintainable
 * answer: don't auto-seed from the test framework. If you want the
 * persistent DB seeded locally, run one of:
 *     php artisan db:seed
 *     php artisan migrate:fresh --seed
 *     composer test-local    (provided by composer.json -- runs
 *                             `php artisan test $@` then `php artisan
 *                             db:seed` so the workflow is one command)
 *
 * CI detection (`runningInCI()`)
 * -------------------------------
 * Currently unused by the trait itself (the trait does not branch on
 * CI mode anymore). Retained as `public static` so the integration
 * tests (`RefreshDatabaseWithSeedCITest`,
 * `RefreshDatabaseWithSeedLocalTest`) can poll it deterministically
 * without needing a test instance.
 */
trait RefreshDatabaseWithSeed
{
    use RefreshDatabase;

    /**
     * Laravel native lifecycle hook. Fires inside `refreshDatabase()`
     * AFTER `refreshTestDatabase()` has migrated fresh (+ first method
     * per class only) and `beginDatabaseTransaction()` has opened the
     * per-method transaction. This is the documented seam for "do
     * post-refresh work".
     *
     * SIGNATURE NOTE: NO `: void` return type. Parent's
     * `protected function afterRefreshingDatabase()` is untyped.
     * PHP strict-signature rules reject a child method adding `void`
     * to a parent's no-type-declared method; doing so produces a
     * fatal `Declaration must be compatible` at class-load.
     * See class docblock for the failed experiment this comment is
     * memorialising.
     */
    protected function afterRefreshingDatabase()
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Static detector. Used by test fixtures (CITest, LocalTest) to
     * probe CI mode without needing a test instance.
     *
     * Returns `true` when either:
     *   - env `CI` is one of: true/1/yes (case-insensitive)
     *   - env `GITHUB_ACTIONS` is one of: true/1/yes (case-insensitive)
     *
     * Both `false` (env var unset) and `''` (env var present but empty)
     * are treated as NOT-in-CI so a locally-empty var behaves honestly.
     */
    public static function runningInCI(): bool
    {
        $truthy = static function (string $value): bool {
            return in_array(
                strtolower(trim($value)),
                ['true', '1', 'yes'],
                true,
            );
        };

        $ci  = getenv('CI');
        $gha = getenv('GITHUB_ACTIONS');

        if ($ci !== false && $truthy($ci)) {
            return true;
        }
        if ($gha !== false && $truthy($gha)) {
            return true;
        }
        return false;
    }
}
