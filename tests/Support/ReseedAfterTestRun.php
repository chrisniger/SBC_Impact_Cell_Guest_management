<?php

declare(strict_types=1);

namespace Tests\Support;

use PHPUnit\Event\TestRunner\Finished;
use PHPUnit\Event\TestRunner\FinishedSubscriber;
use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;
use Symfony\Component\Process\Process;
use Tests\Concerns\RefreshDatabaseWithSeed;

/**
 * Phase 31 — post-test reseed safety net (PHPUnit extension).
 *
 * Fires `php artisan seed:canonical` AFTER the test suite finishes, so
 * seeded credentials always survive ANY test run (`php artisan test`,
 * `composer test`, bare `vendor/bin/phpunit`, IDE test runners).
 *
 * Why a child process (not in-process Artisan::call)?
 * ----------------------------------------------------
 * The parent PHPUnit process has phpunit.xml's `<env force="true">`
 * values in ITS environment (`DB_DATABASE=impact_test`, `APP_ENV=testing`,
 * etc. — set via putenv + $_ENV). In-process seeding would re-read those
 * and seed the TEST database. Spawning a fresh `php artisan` child lets
 * Laravel re-bootstrap from `.env` (dev DB), with the caveat below.
 *
 * Why re-assert the env explicitly?
 * ---------------------------------
 * Symfony Process inherits the parent's environment by default, so the
 * child would STILL see `DB_DATABASE=impact_test` from putenv and seed
 * the wrong DB. We therefore pass an explicit env array (parent env
 * merged with the real `.env` values) so the child boots against the
 * development `impact_guest` database — the DB whose credentials the
 * safety net exists to protect.
 *
 * Skip conditions:
 *   - CI (`RefreshDatabaseWithSeed::runningInCI()`): no dev DB to
 *     protect; the child would 503/fail pointlessly.
 *   - No `.env` file: nothing to read the dev DB name from.
 *   - Child exits non-zero: warn on STDERR, but NEVER fail the suite —
 *     a reseed failure must not mask the actual test results.
 */
final class ReseedAfterTestRun implements Extension
{
    private const SEED_COMMAND = 'seed:canonical';

    public function bootstrap(
        Configuration $configuration,
        Facade $facade,
        ParameterCollection $parameters,
    ): void {
        $facade->registerSubscriber(new class implements FinishedSubscriber {
            public function notify(Finished $event): void
            {
                ReseedAfterTestRun::run();
            }
        });
    }

    /**
     * Invoked by the subscriber on `TestRunner\Finished`. Public so it can
     * also be driven directly from tests if needed.
     */
    public static function run(): void
    {
        if (RefreshDatabaseWithSeed::runningInCI()) {
            fwrite(STDERR, "[reseed] skipped: running in CI (no dev DB to protect)\n");
            return;
        }

        $projectRoot = dirname(__DIR__, 2); // tests/Support → project root
        $envFile = $projectRoot . '/.env';
        $artisan = $projectRoot . '/artisan';

        if (! is_file($envFile) || ! is_file($artisan)) {
            fwrite(STDERR, "[reseed] skipped: .env or artisan not found\n");
            return;
        }

        $devEnv = self::readDotEnv($envFile);
        $devDatabase = $devEnv['DB_DATABASE'] ?? 'impact_guest';
        $devConnection = $devEnv['DB_CONNECTION'] ?? 'mysql';
        $devAppEnv = $devEnv['APP_ENV'] ?? 'local';

        // Parent env (includes phpunit.xml's forced impact_test) merged
        // with the real .env values — the latter MUST win. DB_URL is
        // forced to '' by phpunit.xml; re-assert the .env value (or empty)
        // so a future .env DB_URL can never be shadowed by the leak.
        $childEnv = array_merge(getenv(), [
            'DB_DATABASE'   => $devDatabase,
            'DB_CONNECTION' => $devConnection,
            'DB_URL'        => $devEnv['DB_URL'] ?? '',
            'APP_ENV'       => $devAppEnv,
        ]);

        $process = new Process(
            [PHP_BINARY, $artisan, self::SEED_COMMAND, '--no-interaction'],
            $projectRoot,
            $childEnv,
            timeout: 300,
        );

        $process->run();

        if (! $process->isSuccessful()) {
            fwrite(STDERR, '[reseed] WARN: seed:canonical exited ' . $process->getExitCode()
                . " (suite results unaffected):\n" . $process->getOutput() . $process->getErrorOutput() . "\n");
            return;
        }

        fwrite(STDERR, "[reseed] dev DB {$devDatabase} reseeded (seed:canonical OK)\n");
    }

    /**
     * Minimal .env reader — only the keys the reseed needs. Returns an
     * associative array; missing keys are absent (callers default).
     */
    private static function readDotEnv(string $envFile): array
    {
        $result = [];
        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            return $result;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            $value = trim($value);
            $wasQuoted = false;

            // Strip surrounding quotes (Laravel .env convention).
            if (strlen($value) >= 2
                && (($value[0] === '"' && $value[strlen($value) - 1] === '"')
                    || ($value[0] === "'" && $value[strlen($value) - 1] === "'"))) {
                $value = substr($value, 1, -1);
                $wasQuoted = true;
            }

            // Strip trailing inline comments (only for unquoted values, so
            // a quoted value containing ' #' is never clipped).
            if (! $wasQuoted && $value !== '') {
                $hashPos = strpos($value, ' #');
                if ($hashPos !== false) {
                    $value = rtrim(substr($value, 0, $hashPos));
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
