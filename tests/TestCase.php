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
 * Phase 27 — Test-time DB isolation (RESOLVED)
 * ──────────────────────────────────────────────────────────────────────────────
 *
 * Phase 26 (`phpunit.xml`) hardens the env var guarantees via
 * `force="true"` on every `<env>` tag and points tests at a separate
 * `impact_test` MySQL database. Configuration-layer-only isolation.
 *
 * Phase 27 originally attempted a defensive rebind in `setUp()` after
 * `parent::setUp()` and hit TWO blockers — BOTH are now resolved:
 *
 *   (1) PRIVILEGES (blocked → fixed via root): `ipcDBurs22` (the .env-
 *       configured DB user) only had access to `impact_guest`.
 *       `CREATE DATABASE impact_test` failed with `Access denied` (1044).
 *       Fixed by creating `impact_test` and granting
 *       `GRANT ALL PRIVILEGES ON impact_test.* TO 'ipcDBurs22'@'localhost'`
 *       via root MySQL access.
 *
 *   (2) TIMING (blocked → fixed via the right hook): `parent::setUp()`
 *       fires RefreshDatabase → migrate:fresh BEFORE a setUp()-rebind
 *       could execute. The rebind now lives in
 *       `RefreshDatabaseWithSeed::beforeRefreshingDatabase()` — Laravel's
 *       documented hook that fires BEFORE `migrate:fresh` — so the
 *       rebind takes effect in time.
 *
 * Root cause of why the config rebind was needed at all: PHPUnit's
 * `<env force="true">` only writes `putenv()` + `$_ENV`, NOT `$_SERVER`.
 * Laravel's `env()` reads `$_SERVER` FIRST, and `.env` loads
 * `DB_DATABASE=impact_guest` into `$_SERVER`, so `env('DB_DATABASE')`
 * always won and every `php artisan test` wiped the live dev DB.
 *
 * Guard: `tests/Feature/DbIsolationGuardTest.php` asserts the suite runs
 * against `impact_test` AND that `impact_guest` still holds its rows.
 * Recovery if anything regresses: `bash scripts/restart_dev_server.sh`.
 *
 * ─────────────────────────────────────────────────────────────────────
 * Phase 31 — post-test reseed safety net (belt-and-braces)
 * ─────────────────────────────────────────────────────────────────────
 *
 * Even with impact_test isolation, the PHPUnit extension
 * `Tests\Support\ReseedAfterTestRun` (registered in phpunit.xml) fires
 * `php artisan seed:canonical` on `TestRunner\Finished` after ANY test
 * run, so seeded credentials (sbcadmin@impact.test / //Chris##101,
 * officer1@impact.test, etc.) are re-verified against the live dev DB
 * as a final safety net. It spawns a child `php artisan` process with
 * the `.env` dev DB values re-asserted (the parent process has
 * `DB_DATABASE=impact_test` forced via putenv, which a child would
 * otherwise inherit). Skips in CI; failures warn on STDERR without
 * failing the suite. `composer test-local` also calls seed:canonical
 * explicitly as a second belt-and-braces layer.
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
