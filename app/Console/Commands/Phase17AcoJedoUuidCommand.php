<?php

namespace App\Console\Commands;

use App\Models\ImpactCell;
use Illuminate\Console\Command;

/**
 * Phase 17 visual smoke test helper.
 *
 * Replaces the bash heredoc-tinker pipeline `php artisan tinker --execute='...\\\\Models\\\\...'`
 * inside scripts/smoke_phase17.sh, which under set -euo pipefail + LD_PRELOAD-style
 * quote escaping was emitting a tail-fragment of the (failed) PHP source rather
 * than the ACO/JEDO UUID. Issuing the lookup as a real artisan command uses
 * Laravel's CLI argv parser (no shell escaping required) and prints only the
 * UUID followed by a single newline that the bash wrapper can pipe-trim.
 *
 * Usage from scripts/smoke_phase17.sh:
 *     ACO_JEDO_ID="$(php artisan phase17:aco-jedo-uuid 2>/dev/null | tr -d '[:space:]')"
 *
 * Exit codes:
 *   0 always. The OUTPUT is the UUID string (empty if ACO/JEDO is not seeded),
 *   so the wrapper can validate length and surface a clear error.
 */
class Phase17AcoJedoUuidCommand extends Command
{
    protected $signature = 'phase17:aco-jedo-uuid';

    protected $description = 'Phase 17 smoke helper: print the UUID of the ACO/JEDO primary cell, or empty if missing.';

    public function handle(): int
    {
        $cell = ImpactCell::query()
            ->where('name', 'ACO/JEDO')
            ->where('is_primary', true)
            ->first();

        // Single line, single newline at end. Bash can pipe-trim safely.
        $this->line($cell?->id ?? '');

        return self::SUCCESS;
    }
}
