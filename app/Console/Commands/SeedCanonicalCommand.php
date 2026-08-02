<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FollowUpOfficerSeeder;
use Database\Seeders\FollowUpTeamSeeder;
use Database\Seeders\ImpactCellSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ZonalCoordinatorSeeder;
use Illuminate\Console\Command;

/**
 * Phase 31 — `php artisan seed:canonical` (post-test safety net / recovery).
 *
 * Re-runs the canonical idempotent fixture seeders — the SAME set and
 * ordering as `scripts/restart_dev_server.sh §3` — so seeded credentials
 * always survive a test cycle. It is the single command invoked by:
 *
 *   1. The PHPUnit extension `Tests\Support\ReseedAfterTestRun`
 *      (registered in phpunit.xml) — fires on `TestRunner\Finished`
 *      after ANY `php artisan test` / `composer test` / bare `phpunit`
 *      run, spawning this command as a child process that reads the
 *      real `.env` dev DB (see the extension for why the env must be
 *      re-asserted).
 *
 *   2. `composer test-local` (composer.json) — explicit belt-and-braces
 *      reseed for the documented local workflow, so the guarantee holds
 *      even if a teammate strips the phpunit extension.
 *
 *   3. Manual recovery — `php artisan seed:canonical` is the concise
 *      form of restart_dev_server.sh's six `db:seed --class=...` calls.
 *
 * Ordering (must mirror restart_dev_server.sh §3): roles BEFORE users
 * (assignRole/syncRoles need real rows), ImpactCellSeeder second (cell
 * dropdown + leader signups), then the fixture-user seeders (no
 * cross-deps), and FollowUpTeamSeeder LAST because it reads
 * `officer1@impact.test` from FollowUpOfficerSeeder to attach its team
 * guest fixtures.
 */
class SeedCanonicalCommand extends Command
{
    protected $signature = 'seed:canonical';

    protected $description = 'Re-run the canonical idempotent fixture seeders (roles, cells, users) so seeded credentials always survive test runs.';

    /** Canonical fixture set — keep in sync with restart_dev_server.sh §3. */
    private const CANONICAL_SEEDERS = [
        RolesAndPermissionsSeeder::class,
        ImpactCellSeeder::class,
        AdminUserSeeder::class,
        ZonalCoordinatorSeeder::class,
        FollowUpOfficerSeeder::class,
        FollowUpTeamSeeder::class,
    ];

    public function handle(): int
    {
        foreach (self::CANONICAL_SEEDERS as $seeder) {
            $this->components->task('Seeding ' . class_basename($seeder), function () use ($seeder): bool {
                // db:seed --class with --force: idempotent firstOrCreate
                // seeders; --force suppresses the production prompt.
                return $this->callSilently('db:seed', [
                    '--class' => $seeder,
                    '--force' => true,
                ]) === 0;
            });
        }

        // Core promise: the admin login survives. (Also implicitly proves
        // the roles leg — AdminUserSeeder::assignRole throws if missing.)
        $admin = User::where('email', 'sbcadmin@impact.test')->first();

        if (! $admin) {
            $this->error('Admin fixture sbcadmin@impact.test MISSING after canonical reseed.');
            $this->error('Run: php artisan db:seed --class=AdminUserSeeder --force -vvv');

            return self::FAILURE;
        }

        $this->components->info('Canonical reseed OK — Admin fixture present.');

        return self::SUCCESS;
    }
}
