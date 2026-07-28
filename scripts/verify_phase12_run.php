<?php
/**
 * Phase 12 — Deployment to Hostinger (v2 Laravel) verifier.
 *
 * Phase 12 is a documentation-only phase (Implementation/Phase_12_Deployment.md):
 * a 7-section Laravel 12 + Vite deploy sequence + Rollback + Final go/no-go that
 * bakes the Hostinger handoff into a runnable sequence. No runtime code changes
 * land — so the verifier is a doc-coverage + HANDOFF-state check.
 *
 * 19 sub-assertions across: self-syntax (1) → spec-doc coverage (15) → HANDOFF
 * state (3). The Phase 12 spec was rewritten from v1 Node narrative to v2
 * Laravel narrative in this round (composer install --optimize-autoloader
 * --no-dev + npm run build + Vite public/build/ + php artisan migrate
 * --force + DocumentRoot → public/ + .env prod creds + chmod 775 + 3-user-
 * group verifications + Audit-log 4-write smoke + Rollback + Final go/no-go).
 *
 *   spec-doc coverage (2-16):
 *     [2]   active production URL https://app.summitdata.one/
 *     [3]   composer install --optimize-autoloader --no-dev (production composer)
 *     [4]   npm run build (Vite emit)
 *     [5]   Vite output path public/build/ (NOT dist/client v1)
 *     [6]   php artisan migrate --force (Laravel prod migration)
 *     [7]   php artisan storage:link (storage symlink)
 *     [8]   Hostinger upload target /home/u188660189/domains/app.summitdata.one/
 *     [9]   .env production credentials (APP_KEY + APP_URL + APP_DEBUG=false)
 *     [10]  DocumentRoot pointing at public/ (Laravel front-controller)
 *     [11]  storage permission check (chmod 775 storage + bootstrap/cache)
 *     [12]  3 user-group verify logins (sbcAdmin + officer1 + Impact_Leaders)
 *     [13]  /api/health + /login + /dashboard no-500 verify endpoints
 *     [14]  Audit log 4-write coverage (guest create + guest update + member submission + weekly report)
 *     [15]  Rollback procedure (mv . .broken- + cp -r .previous + no tmp/restart.txt needed)
 *     [16]  Final go/no-go checklist (12 phases + 3 user groups + Leadership Board + Audit log)
 *
 *   HANDOFF state (17-19):
 *     [17]  HANDOFF §1 Phase 12 row labelled ✅ Done with today's date
 *     [18]  HANDOFF §0 last-verified-green line mentions Phase 12
 *     [19]  HANDOFF §0 trailer `Next: Phase 12` pointer has been retired
 */

declare(strict_types=1);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): void {
    fwrite(STDERR, "PHP error (#{$errno}): {$errstr} in {$errfile}:{$errline}\n");
    exit(1);
});

$pass = 0;
$fail = 0;
$failed = [];

function check(int $n, string $label, bool $cond, string $expected): void {
    global $pass, $fail, $failed;
    if ($cond) {
        $pass++;
        echo "  [{$n}] pass — {$label}\n";
    } else {
        $fail++;
        $failed[] = "[{$n}] {$label} — expected: {$expected}";
        echo "  [{$n}] FAIL — {$label} (expected: {$expected})\n";
    }
}

$base = __DIR__ . '/..';

$specPath = $base . '/Implementation/Phase_12_Deployment.md';
$handoffPath = $base . '/HANDOFF.md';

// ---------------------------------------------------------------------------
// [1] self-syntax — verifier file must be PHP-parseable (no fatal).
// ---------------------------------------------------------------------------
check(1, 'verify_phase12_run.php is PHP-parseable (no fatal)', true, 'self');

// ---------------------------------------------------------------------------
// [2] Phase 12 spec exists + names the active production URL.
// ---------------------------------------------------------------------------
$specExists = is_file($specPath);
$specText = $specExists ? file_get_contents($specPath) : '';
check(2, 'Phase 12 spec doc exists + names the active production URL `https://app.summitdata.one/`',
    $specExists
    && str_contains($specText, 'https://app.summitdata.one')
    && str_contains($specText, 'app.summitdata.one'),
    'spec must name `https://app.summitdata.one` as the active preferred deploy URL'
);

// ---------------------------------------------------------------------------
// [3] composer install --optimize-autoloader --no-dev (Laravel prod composer).
// ---------------------------------------------------------------------------
check(3, 'Phase 12 spec documents `composer install --optimize-autoloader --no-dev` (Laravel prod composer install)',
    str_contains($specText, 'composer install --optimize-autoloader --no-dev'),
    'must include the production-mode composer install command'
);

// ---------------------------------------------------------------------------
// [4] npm run build (Vite emit trigger).
// ---------------------------------------------------------------------------
check(4, 'Phase 12 spec documents `npm run build` (Vite emit trigger — bundles React/Inertia to public/build/)',
    str_contains($specText, 'npm run build'),
    'must include `npm run build` under Build locally'
);

// ---------------------------------------------------------------------------
// [5] Vite output path public/build/ (v2 — NOT v1 dist/client).
// ---------------------------------------------------------------------------
check(5, 'Phase 12 spec cites `public/build/` as the Vite bundle-output path (Laravel v2; replaces v1 `dist/client` narrative)',
    str_contains($specText, 'public/build/'),
    'spec must mention `public/build/` as the Vite-emitted bundle path (Laravel-native; NOT `dist/client` which was v1 Node narrative)'
);

// ---------------------------------------------------------------------------
// [6] php artisan migrate --force (Laravel prod migration gate).
// ---------------------------------------------------------------------------
check(6, 'Phase 12 spec documents `php artisan migrate --force` (Laravel prod migration gate — required for production deployment)',
    preg_match('/php\s+artisan\s+migrate\s+--force/', $specText) === 1,
    'must include `php artisan migrate --force` as a deployment step'
);

// ---------------------------------------------------------------------------
// [7] php artisan storage:link (Laravel public/storage symlink).
// ---------------------------------------------------------------------------
check(7, 'Phase 12 spec documents `php artisan storage:link` (Laravel symlink to expose public/uploads)',
    preg_match('/php\s+artisan\s+storage:link/', $specText) === 1,
    'must include `php artisan storage:link` (creates `public/storage` → `../storage/app/public`)'
);

// ---------------------------------------------------------------------------
// [8] Hostinger upload target /home/u188660189/domains/app.summitdata.one/public_html/.
// ---------------------------------------------------------------------------
check(8, 'Phase 12 spec details the Hostinger upload target `/home/u188660189/domains/app.summitdata.one/public_html/laravel/`',
    str_contains($specText, '/home/u188660189/domains/app.summitdata.one/public_html/laravel')
    || str_contains($specText, '/home/u188660189/domains/app.summitdata.one/public_html/'),
    'must include Hostinger account path `/home/u188660189/domains/app.summitdata.one/public_html/` (account + laravel subdir is the v2 manifest target)'
);

// ---------------------------------------------------------------------------
// [9] .env production credentials (APP_KEY + APP_URL + APP_DEBUG=false).
// ---------------------------------------------------------------------------
check(9, 'Phase 12 spec covers `.env` production credentials (`APP_KEY` + `APP_URL=https://app.summitdata.one` + `APP_DEBUG=false`)',
    str_contains($specText, 'APP_KEY')
    && str_contains($specText, 'APP_URL=https://app.summitdata.one')
    && str_contains($specText, 'APP_DEBUG=false'),
    'must include `APP_KEY`, `APP_URL=https://app.summitdata.one`, `APP_DEBUG=false` as `.env` production credentials'
);

// ---------------------------------------------------------------------------
// [10] DocumentRoot pointing at public/ (Laravel front-controller requirement).
// ---------------------------------------------------------------------------
check(10, 'Phase 12 spec documents `DocumentRoot` pointing at `laravel/public/` (Laravel 12 front-controller routing; avoids the v1 Passenger Node narrative)',
    str_contains($specText, 'DocumentRoot')
    && (str_contains($specText, 'laravel/public') || str_contains($specText, 'public/')),
    'must include `DocumentRoot` directive + Laravel-specific `public/` path'
);

// ---------------------------------------------------------------------------
// [11] storage permission check (chmod 775 storage + bootstrap/cache).
// ---------------------------------------------------------------------------
check(11, 'Phase 12 spec covers storage/ + bootstrap/cache permission check (`chmod 775`)',
    preg_match('/chmod\s+-R\s+775\s+storage\s+bootstrap\/cache/', $specText) === 1,
    'must include `chmod -R 775 storage bootstrap/cache` (Laravel write-permission gate)'
);

// ---------------------------------------------------------------------------
// [12] 3 user-group verify logins.
// ---------------------------------------------------------------------------
check(12, 'Phase 12 spec covers 3 user-group verify logins (sbcadmin@impact.test Administrator + officer1@impact.test FollowUpOfficer + phase04-test-Impact_Leaders@impact.test Impact_Leaders)',
    str_contains($specText, 'sbcadmin@impact.test')
    && (str_contains($specText, 'officer1@impact.test') && str_contains($specText, 'FollowUpOfficer'))
    && (str_contains($specText, 'Impact_Leaders') || str_contains($specText, 'Impact Cell Leader')),
    'spec must require login + UI verify for all 3 user groups (Admin + Officer + Impact Leader)'
);

// ---------------------------------------------------------------------------
// [13] /api/health + /login + /dashboard no-500 verify endpoints.
// ---------------------------------------------------------------------------
check(13, 'Phase 12 spec covers /api/health + /login + /dashboard no-500 verify endpoints (all 3 user groups)',
    str_contains($specText, '/api/health')
    && str_contains($specText, '/login')
    && str_contains($specText, '/dashboard'),
    'must include `/api/health`, `/login`, `/dashboard` as no-500 verify endpoints for all 3 user groups'
);

// ---------------------------------------------------------------------------
// [14] Audit log 4-write coverage.
// ---------------------------------------------------------------------------
check(14, 'Phase 12 spec covers Audit log per-write coverage (GUEST_CREATED + GUEST_UPDATED + member_submission + weekly_report_submitted)',
    str_contains($specText, 'GUEST_CREATED')
    && str_contains($specText, 'GUEST_UPDATED')
    && (str_contains($specText, 'member_submission') || str_contains($specText, 'Member submission'))
    && (str_contains($specText, 'weekly_report_submitted') || str_contains($specText, 'WEEKLY_REPORT_SUBMITTED')),
    'must include all 4 audit-write types: GUEST_CREATED + GUEST_UPDATED + member_submission + weekly_report_submitted'
);

// ---------------------------------------------------------------------------
// [15] Rollback procedure (mv + cp -r .previous + no Passenger needed).
// ---------------------------------------------------------------------------
check(15, 'Phase 12 spec covers Rollback procedure (`mv . .broken-` + `cp -r .previous .` + DocumentRoot invariant)',
    (str_contains($specText, 'mv . .broken') || str_contains($specText, 'mv . current.broken') || str_contains($specText, "mv . '.broken"))
    && str_contains($specText, 'cp -r .previous .')
    && str_contains($specText, 'DocumentRoot'),
    'spec `Rollback` section must list the 3 rollback steps: mv current → mv . .broken-$timestamp + cp -r .previous . + DocumentRoot invariant verification (NO tmp/restart.txt — Laravel Apache picks up new index.php on next request)'
);

// ---------------------------------------------------------------------------
// [16] Final go/no-go checklist (all 12 phases + 3 user groups + Leadership Board + Audit log).
// ---------------------------------------------------------------------------
check(16, 'Phase 12 spec covers Final go/no-go checklist (all 12 phases + Leadership Board + Audit log capture 4-write + storage symlink)',
    str_contains($specText, 'Final go/no-go')
    && str_contains($specText, 'Leadership Board')
    && str_contains($specText, 'Audit log captures')
    && str_contains($specText, '/storage/laravel-logo.png'),
    'spec must include the `Final go/no-go checklist` section with 12 phases + Leadership Board + Audit log 4-write + storage symlink resolution'
);

// ---------------------------------------------------------------------------
// [17] HANDOFF.md §1 Phase 12 row labelled ✅ Done (today's date 2026-07-27).
// ---------------------------------------------------------------------------
$handoffExists = is_file($handoffPath);
$handoffText = $handoffExists ? file_get_contents($handoffPath) : '';
check(17, 'HANDOFF.md §1 has Phase 12 row labelled `✅ **Done**` with 2026-07-27 date',
    $handoffExists
    && str_contains($handoffText, '| 12 Deployment ')
    && str_contains($handoffText, 'Done')
    && str_contains($handoffText, '2026-07-27'),
    '`| 12 Deployment |` row in §1 must be labelled `Done` (2026-07-27)'
);

// ---------------------------------------------------------------------------
// [18] HANDOFF.md §0 last-verified-green line includes Phase 12 (anchored count).
// ---------------------------------------------------------------------------
check(18, 'HANDOFF.md `Last verified green:` listing mentions Phase 12 (19/19) — anchored on literal count',
    $handoffExists
    && str_contains($handoffText, 'Last verified green')
    && preg_match('/Phase\s*12\s*\(19\/19\)/', $handoffText) === 1,
    'HANDOFF `Last verified green:` line must contain `Phase 12 (19/19)` (anchored count)'
);

// ---------------------------------------------------------------------------
// [19] HANDOFF.md §0 trailer `Next: Phase 12` pointer is retired (no longer present).
// ---------------------------------------------------------------------------
check(19, 'HANDOFF.md §0 trailer `Next: Phase 12` pointer is retired AND new `Project feature-complete` marker present (dual-prong)',
    $handoffExists
    && (preg_match('/feature-complete|ship[ -]+complete|all\s*\d*\s*shipping|all\s*shipped|build[ -]+complete|v2[ -]+complete|all 12 shipping|deployment complete/i', $handoffText) === 1)
    && ! preg_match('/\*\*Next:\*\*\s+Phase\s+12\b/', $handoffText),
    'HANDOFF §0 must contain a project-completion phrase matching the multi-anchor regex AND must NOT contain `**Next:** Phase 12` (defensive-negative)'
);

// ---------------------------------------------------------------------------

echo "\nPhase 12 (v2) verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
