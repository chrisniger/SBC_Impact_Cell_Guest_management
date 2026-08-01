<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\RefreshDatabaseWithSeed;

/**
 * Base TestCase for the project.
 *
 * Phase 14 follow-up: now uses `Tests\Concerns\RefreshDatabaseWithSeed`
 * so every test class gets seed-on-non-CI behavior automatically.
 * Tests needing a custom `setUp()` override can still do so — their
 * override shadows the trait's setUp() but Laravel's `setUpTraits()`
 * keeps seeing `RefreshDatabase` in the recursive `class_uses_recursive()`
 * chain, so the framework wiring stays intact.
 *
 * Read tests/Concerns/RefreshDatabaseWithSeed.php for trait-class
 * composition rationale and the CI-detection contract.
 *
 * ───────────────────────────────────────────────────────────────────────
 * Phase 20 — Centralised CSRF middleware bypass
 * ───────────────────────────────────────────────────────────────────────
 *
 * The Laravel middleware pipeline registers
 * `Illuminate\Foundation\Http\Middleware\ValidateCsrfToken` on the
 * `web` group. Without disabling it, every `$this->post()`/`$this->put()`
 * from a Feature test returns 419 "CSRF token mismatch". Centralising
 * the disable in the `setUp()` override below means EVERY child test
 * inherits it and needs no per-file FQN-import or per-test setUp() call.
 *
 * The load-bearing lessons — the PSR-4 FQN case-sensitivity trap
 * (`VerifyCsrfToken::class` vs `ValidateCsrfToken::class` silently
 * no-ops on PSR-4 case-sensitive Linux), the method-call-vs-class-property
 * wiring detail (Laravel's `MakesHttpRequests::call()` only honours
 * `$this->withoutMiddleware` AFTER `$this->withoutMiddleware(...)`
 * has been invoked at least once), and the parent::setUp()-first
 * ordering rationale — live on the `setUp()` method's docblock below
 * for proximity to the actual `withoutMiddleware(ValidateCsrfToken::class)`
 * call. Read the `setUp()` method docblock (in this file, immediately
 * below) before editing the disable mechanism.
 *
 * Child classes needing additional middleware disabled MAY override
 * `setUp()` and append `$this->withoutMiddleware(OtherMiddleware::class)`
 * AFTER `parent::setUp()`. Do NOT introduce a
 * `protected $withoutMiddleware = [...]` property as the entire bypass
 * mechanism — the property-only path silently returns 419 status. Use
 * the setUp() override pattern exclusively.
 *
 * ──────────────────────────────────────────────────────────────────────────────
 * Phase 27 — Test-time DB isolation reverted (DBA-blocked)
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * Phase 26 (`phpunit.xml`) hardens the env var guarantees via
 * `force="true"` on every `<env>` tag and points tests at a separate
 * `impact_test` MySQL database. Configuration-layer-only isolation.
 *
 * Phase 27 was a defensive rebind in `setUp()` after `parent::setUp()`
 * (rebind config + DB::purge('mysql')), intended as a belt-and-braces
 * against the .env-bleed we observed empirically. The attempt hit TWO
 * unresolved blockers that surfaced during verification:
 *
 *   (1) TIMING: `parent::setUp()` fires RefreshDatabase → migrate:fresh
 *       BEFORE the rebind executes, so the migrate:fresh still targets
 *       `impact_guest` (the env-loaded DB). The reviewer's correct
 *       recommendation is to move the rebind to the
 *       `beforeRefreshingDatabase()` hook in `RefreshDatabaseWithSeed.php`
 *       so the rebind takes effect BEFORE `migrate:fresh` runs. Pending
 *       experimental confirmation + the Privileges blocker below.
 *
 *   (2) PRIVILEGES: `ipcDBurs22` (the .env-configured DB user) only has
 *       access to `impact_guest`, `information_schema`, `performance_schema`.
 *       `CREATE DATABASE impact_test` fails with `Access denied for user
 *       'ipcDBurs22'@'localhost' to database 'impact_test'` (1044), and
 *       GRANT-self attempts fail the same way. Without a DBA granting
 *       impact_test.* to ipcDBurs22, even a perfect TIMING-side fix
 *       cannot put isolation into effect.
 *
 * Decision for now: REVERT the code-level rebind in setUp() to keep tests
 * green against the current .env DB. Recurring consequence: `php artisan
 * test` will wipe `impact_guest` between runs (the original Phase 25
 * incident). Recovery is owned by `scripts/restart_dev_server.sh`, which
 * is the durable guard today.
 *
 * To re-enable, the path is:
 *   (a) DBA grants `GRANT ALL PRIVILEGES ON impact_test.* TO 'ipcDBurs22'@'localhost'`.
 *   (b) Move the rebind from this setUp() into
 *       RefreshDatabaseWithSeed::beforeRefreshingDatabase() (pre-migrate:fresh).
 *   (c) Verify with 3 consecutive reruns. Until then, /register can flip to
 *       503 mid-test-session; bash scripts/restart_dev_server.sh recovers.
 */
abstract class TestCase extends BaseTestCase
{
    use RefreshDatabaseWithSeed;

    /**
     * Phase 20 — centralised CSRF middleware bypass (the only override in here now).
     *
     * See class docblock for the load-bearing lessons behind ordering,
     * PSR-4 case-sensitivity, and the method-call vs class-property
     * disable mechanics.
     *
     * Order -- parent::setUp() first:
     * 1. `parent::setUp()` fires `setUpTraits()` → `RefreshDatabase` →
     *    `beginTransaction` + `afterRefreshingDatabase()` (which is the
     *    `forgetCachedPermissions()` hook inside `RefreshDatabaseWithSeed`).
     * 2. We disable CSRF middleware last so the disable is the final
     *    override on top of a freshly-rolled-back DB.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
