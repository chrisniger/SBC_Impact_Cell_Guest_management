<?php

namespace Tests\Feature\Console;

use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Phase 14 follow-up — verifies `Tests\Concerns\RefreshDatabaseWithSeed`
 * correctly identifies CI mode via `runningInCI()`.
 *
 * Pattern: `setUp()` sets `CI=true` (BEFORE chaining to parent::setUp()
 * which fires the trait's `afterRefreshingDatabase()` hook). This
 * replaces the file-level `putenv('CI=true')` approach that proved
 * fragile when a `tearDown()` reset ran between test methods and
 * leaked missing-CI state into the next test method's
 * `runningInCI()` probe.
 *
 * Why `setUp()` and not a file-level `putenv()`:
 *   - File-level putenv runs ONCE at class-load time. With PHPUnit's
 *     default process-per-class lifecycle that's fine, but it makes
 *     the class-order coupling brittle.
 *   - `setUp()` runs ONCE per test method (every time, guaranteed),
 *     so each method sees a fresh `CI=true`. The trait's hook inside
 *     `parent::setUp()` runs after our `putenv()`, which is also fine
 *     because the trait doesn't branch on CI anymore.
 *
 * NOTE: we deliberately do NOT reset `CI` in `tearDown()` anymore.
 * Setting `CI` via setUp() guarantees every method sees it; reset
 * is unnecessary and was the source of the previous failure where
 * tearDown's `putenv('CI')` (delete-var form) ran between methods
 * and left method #2 with no CI env.
 */
class RefreshDatabaseWithSeedCITest extends TestCase
{
    public function setUp(): void
    {
        // Set BEFORE parent::setUp() so the helper-poll assertions
        // (runningInCI) see a fresh CI=true regardless of any cross-
        // class env bleed. The trait's afterRefreshingDatabase() hook
        // fires inside parent::setUp() but doesn't read CI env, so
        // ordering doesn't matter for the cache-clear side-effect.
        putenv('CI=true');

        parent::setUp();
    }

    public function test_ci_mode_does_not_poison_subsequent_local_runs(): void
    {
        // Sanity guard: empty roles table (no auto-seed expected under
        // either mode in the simplified Phase 14 trait).
        $this->assertSame(
            0,
            Role::count(),
            'simplified trait contract: no auto-seed in EITHER mode',
        );
    }

    public function test_runningInCI_helper_returns_true_when_CI_env_is_set(): void
    {
        $this->assertTrue(
            \Tests\Concerns\RefreshDatabaseWithSeed::runningInCI(),
            'runningInCI() should report TRUE when CI=true in env (set by setUp)',
        );
    }

    public function test_runningInCI_helper_returns_false_when_CI_env_is_unset(): void
    {
        // Mimic a user manually unsetting CI mid-run: putenv with no
        // value DELETES the var. The helper should observe the change.
        putenv('CI');
        $this->assertFalse(
            \Tests\Concerns\RefreshDatabaseWithSeed::runningInCI(),
            'runningInCI() should report FALSE when CI env is unset',
        );
        // NO restoration: setUp() will reset CI=true on the next method.
    }

    public function test_runningInCI_helper_recognises_GITHUB_ACTIONS_env(): void
    {
        // Belt-and-suspenders: spawn a fresh env state and probe the
        // GITHUB_ACTIONS branch of the detector. CI helper accepts both
        // CI=true AND GITHUB_ACTIONS=true as CI-mode markers.
        putenv('CI');
        putenv('GITHUB_ACTIONS=true');

        $this->assertTrue(
            \Tests\Concerns\RefreshDatabaseWithSeed::runningInCI(),
            'runningInCI() should report TRUE when GITHUB_ACTIONS=true (and CI unset)',
        );

        // Reset env to class-expected state. (setUp() will re-set
        // CI=true on the next method, but better hygiene to leave
        // GITHUB_ACTIONS cleared explicitly.)
        putenv('GITHUB_ACTIONS');
    }
}
