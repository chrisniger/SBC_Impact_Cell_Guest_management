<?php

namespace Tests\Feature\Console;

use App\Support\RoleHelper;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Phase 14 follow-up — verifies `Tests\Concerns\RefreshDatabaseWithSeed`
 * works correctly under LOCAL (non-CI) mode.
 *
 * Contract under LOCAL (CI env unset):
 *   1) `runningInCI()` returns FALSE.
 *   2) Spatie's `forgetCachedPermissions()` clears the cache key
 *      (the trait's `afterRefreshingDatabase()` hook calls this on
 *      every refresh; we exercise the helper directly here since the
 *      hook itself is fired by the framework's setUp chain that
 *      already ran before this test body's first line).
 *   3) The trait does NOT auto-seed (deleted in Phase 14 follow-up;
 *      intentionally offloaded to `composer test-local` so seed logic
 *      stays out of the test framework).
 *   4) `RoleHelper::ROLE_NAMES` remains non-empty (regression guard).
 */
class RefreshDatabaseWithSeedLocalTest extends TestCase
{
    public function test_runningInCI_returns_false_when_CI_env_is_unset(): void
    {
        // Defend against env bleed from a sibling test class (e.g.
        // CITest sets CI=true at file-load; if its tearDown misfires,
        // our env could inherit the value here).
        putenv('CI');

        $this->assertFalse(
            \Tests\Concerns\RefreshDatabaseWithSeed::runningInCI(),
            'runningInCI() should report FALSE when CI env is unset',
        );
    }

    public function test_trait_does_not_auto_seed_so_role_table_starts_empty(): void
    {
        // Contract lock: this assertion WILL break the moment anyone
        // tries to re-add auto-seeding inside the trait. Good. It
        // forces a deliberate design conversation about the
        // (intentionally rejected) auto-seed path. No off-by-one
        // escape hatch.
        $this->assertSame(
            0,
            Role::count(),
            'simplified trait contract: no auto-seed. Use `composer test-local` for the post-test reseed workflow.',
        );

        // Regression guard: RoleHelper::ROLE_NAMES is the canonical
        // 10-role list that the sign-up picker + PermissionsSeeder
        // both depend on. Drift here would surface as the
        // Phase-14-style "RoleDoesNotExist on Impact_Zonal_Coordinator"
        // again.
        $this->assertGreaterThan(
            0,
            count(RoleHelper::ROLE_NAMES),
            'RoleHelper::ROLE_NAMES must remain non-empty',
        );
    }

    public function test_forgetCachedPermissions_clears_spatie_cache_key(): void
    {
        // Read the cache key from the registrar's public property
        // instead of hardcoding 'spatie.permissions.cache' -- the
        // latter would silently break if Spatie changed the default
        // via `config('permission.cache.key')`. See PermissionRegistrar
        // source: `public string $cacheKey`.
        $registrar = app(PermissionRegistrar::class);
        $cacheKey  = $registrar->cacheKey;

        // Prime the cache, prove the precondition, then call the
        // helper the trait itself invokes. If this clears, the trait's
        // hook (which calls the same helper) clears the cache on every
        // test method.
        \Illuminate\Support\Facades\Cache::put($cacheKey, 'STALE-SENTINEL');
        $this->assertSame(
            'STALE-SENTINEL',
            \Illuminate\Support\Facades\Cache::get($cacheKey),
            'precondition: sentinel writeable to Spatie cache key',
        );

        $registrar->forgetCachedPermissions();

        $this->assertNull(
            \Illuminate\Support\Facades\Cache::get($cacheKey),
            'forgetCachedPermissions() should clear the Spatie cache key (this is what the trait\'s afterRefreshingDatabase() hook fires per test method)',
        );
    }
}
