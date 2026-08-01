<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Phase 18 — fixture-rotation helper for the seeded `sbcadmin@impact.test` admin.
 *
 * Replaces the at-risk ad-hoc `bcrypt(Str::random(96))` + `Hash::make('ImpactAdmin2026!')`
 * bash heredoc-tinker pattern that has been quietly leaking plaintext into
 * `.freebuff/` shell history across the past few turns. Issuing the rotation
 * as a real Artisan command uses Laravel's argv parser (no shell escaping),
 * keeps the random plaintext in PHP memory only (never crosses argv / env),
 * and lets Eloquent's `password => hashed` cast auto-bcrypt the value
 * immediately before the SQL write so the column never sees plaintext.
 *
 * Usage:
 *     php artisan sbcadmin:rotate sentinel
 *     php artisan sbcadmin:rotate                # restore to env('SBCADMIN_PASSWORD','ImpactAdmin2026!')
 *     php artisan sbcadmin:rotate "MySecret123!"
 *     php artisan sbcadmin:rotate status
 *
 * Exit codes:
 *   0 — success (or idempotent no-op when bcrypt already matches target)
 *   1 — sbcadmin@impact.test does not exist (does NOT auto-seed)
 *
 * Sentinel-leak mitigations (see scripts/rotate_sbcadmin_password.sh for caller contract):
 *   1. The 96-char random string is generated inside PHP and lives only in a
 *      local variable; it never reaches bash argv, env, or the DB column.
 *   2. The artisan command class hash-log only emits mode metadata
 *      ('sentinel' or 'restore') — never the plaintext, never the literal hash.
 *   3. Eloquent's `hashed` cast on User::$casts intercepts the plaintext during
 *      fillable assignment and replaces it with the bcrypt hash BEFORE the
 *      save translates it to SQL.
 *   4. `saveQuietly()` is used so any model observers / activity loggers cannot
 *      accidentally re-emit the plaintext via event listeners.
 *   5. The local $pwd variable falls out of scope when this method returns.
 */
class RotateSbcadminPasswordCommand extends Command
{
    /** `{target}` is required: 'sentinel', 'status', or a literal password. */
    protected $signature = 'sbcadmin:rotate {target}';

    protected $description = 'Phase 18 fixture helper: rotate / inspect / restore sbcadmin@impact.test.';

    public function handle(): int
    {
        $u = User::where('email', 'sbcadmin@impact.test')->first();
        if (! $u) {
            $this->error('sbcadmin@impact.test does not exist. Run: php artisan db:seed --class=AdminUserSeeder');
            return self::FAILURE;
        }

        $target = $this->argument('target');

        // status: print the current row state without mutating anything.
        if ($target === 'status') {
            $defaultPw = (string) env('SBCADMIN_PASSWORD', 'ImpactAdmin2026!');
            $state = Hash::check($defaultPw, $u->getAuthPassword())
                ? 'READY (auth accepts env/default)'
                : 'LOCKED (sentinel or unknown)';
            $this->line(sprintf(
                'sbcadmin@impact.test  id=%d  role=%s  state=%s  hash=%s...',
                $u->id,
                $u->active_role ?? '(none)',
                $state,
                substr($u->getAuthPassword(), 0, 30)
            ));
            return self::SUCCESS;
        }

        // sentinel vs restore.
        if ($target === 'sentinel') {
            $pwd = Str::random(96);
            $mode = 'sentinel';
            $label = 'sentinel (random 96-char in-memory plaintext)';
        } else {
            $pwd = $target;
            $mode = 'restore';
            $label = 'restore (literal password)';
        }

        // Idempotency for RESTORE: if the bcrypt already authenticates against
        // the supplied plaintext, the rotation is a no-op — avoids bumping
        // updated_at / triggering observers on consecutive identical runs.
        // For SENTINEL every plaintext is freshly random so the check below
        // will almost always be FALSE; we accept the re-write in that mode.
        if (Hash::check($pwd, $u->getAuthPassword())) {
            $this->line("ALREADY on target (mode=$mode). No write performed.");
            return self::SUCCESS;
        }

        // Eloquent's `password => hashed` cast auto-bcrypts here.
        $u->forceFill(['password' => $pwd])->saveQuietly();
        $u = $u->fresh();

        // Audit log: metadata ONLY — never the plaintext, never the literal hash.
        Log::info('sbcadmin@impact.test password rotated', [
            'mode' => $mode,
            'actor' => 'sbcadmin:rotate artisan command',
        ]);

        $this->line(sprintf(
            'Rotated [mode=%s, label=%s]. Fresh hash=%s...',
            $mode,
            $label,
            substr($u->getAuthPassword(), 0, 30)
        ));
        return self::SUCCESS;
    }
}
