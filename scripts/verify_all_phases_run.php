<?php
/**
 * Master verifier runner — Phase 12 polish-round deliverable.
 *
 * Discovers scripts/verify_phase{NN}_run.php via glob, then runs a 2-stage pipeline:
 *   [STAGE 1] RUNS `php -l` ON EACH VERIFIER FIRST (the "syntax-gate").
 *             If ANY verifier fails `php -l`, the master run ABORTS there —
 *             per the HANDOFF §0 rule "verifier-green-requires-`php -l`-clean":
 *             a verifier that cannot be parsed cannot pass. Syntax-fail listing
 *             is printed; exit code = 2.
 *   [STAGE 2] Only after all verifiers pass the syntax-gate, runs each verifier
 *             sequentially + captures the `N pass / M fail` summary line.
 *             Parses the summary with a regex + prints per-phase + total.
 *
 * Why this exists: prior ship rounds shipped verifiers with parse errors that
 * slipped past authoring (the author forgot to run `php -l` until the basher
 * diagnostic caught it). The master runner hardens the workflow by making
 * `php -l` the FIRST gate — no per-phase counting begins until every verifier
 * is syntactically clean.
 *
 * Exit codes:
 *   0 = all verifiers syntax-clean + all counted pass (M=0 for each).
 *   1 = some verifiers passed syntax-gate but had assertion failures.
 *   2 = some verifiers FAILED `php -l` (syntax-gate ABORT — fix before continuing).
 *
 * Cross-platform: the PHP binary is autodetected from `$_SERVER['_']` (argv[0])
 * first (covers Windows runs where `php` is on PATH), then falls back to env vars
 * `PHP_BIN` or `which php`, then to a hardcoded `/d/php84/php.exe` (the project's
 * Windows local invocation path). Override via `PHP_BIN=custom-path php scripts/verify_all_phases_run.php`.
 */

declare(strict_types=1);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): void {
    fwrite(STDERR, "Master-runner PHP error (#{$errno}): {$errstr} in {$errfile}:{$errline}\n");
    exit(1);
});

// ---------------------------------------------------------------------------
// PHP binary autodetection.
// ---------------------------------------------------------------------------
function detectPhpBinary(): string {
    $candidates = [
        getenv('PHP_BIN'),
        $_SERVER['_'] ?? null, // argv[0] of the running script — usually the php binary on most setups
    ];
    foreach ($candidates as $c) {
        if (is_string($c) && $c !== '' && is_executable($c)) {
            return $c;
        }
    }
    // Fall back to platform convention.
    if (PHP_OS_FAMILY === 'Windows') {
        return '/d/php84/php.exe';
    }
    return '/usr/bin/php';
}

$php = detectPhpBinary();

// ---------------------------------------------------------------------------
// Verifier discovery (glob on scripts/verify_phase{NN}_run.php).
// ---------------------------------------------------------------------------
$scriptsDir = __DIR__;
$verifierFiles = glob($scriptsDir . '/verify_phase[0-9][0-9]_run.php');
if (! is_array($verifierFiles) || empty($verifierFiles)) {
    fwrite(STDERR, "Master runner discovered 0 verifier files under {$scriptsDir}.\n");
    fwrite(STDERR, "Expected pattern: scripts/verify_phase{NN}_run.php (e.g. verify_phase02_run.php).\n");
    exit(1);
}
sort($verifierFiles); // stable lex order — 02, 03, ..., 12

echo "=== Phase-12 polish-round • Master verifier runner ===\n";
echo "PHP binary: {$php}\n";
echo "Discovered " . count($verifierFiles) . " verifier files:\n";
foreach ($verifierFiles as $f) {
    echo "  - " . basename($f) . " (" . number_format(filesize($f)) . " bytes)\n";
}
echo "\n";

// ---------------------------------------------------------------------------
// STAGE 1: php -l syntax-gate.
// ---------------------------------------------------------------------------
echo "=== [STAGE 1] php -l syntax-gate (master runner) ===\n";
$syntaxFails = [];
foreach ($verifierFiles as $f) {
    $output = [];
    $rc = 0;
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($f) . ' 2>&1', $output, $rc);
    if ($rc !== 0) {
        $syntaxFails[] = $f;
        echo "  [FAIL] " . basename($f) . "  (php -l exit " . $rc . ")\n";
        foreach ($output as $line) {
            echo "         " . $line . "\n";
        }
    } else {
        // php -l only prints on success: "No syntax errors detected in <path>"
        $summary = !empty($output) ? trim($output[0]) : 'OK';
        echo "  [PASS] " . basename($f) . "  ({$summary})\n";
    }
}

if (! empty($syntaxFails)) {
    fwrite(STDOUT, "\n*** SYNTAX-GATE ABORT *** " . count($syntaxFails) . " verifier file(s) failed `php -l`:\n");
    foreach ($syntaxFails as $f) {
        fwrite(STDOUT, "  - " . basename($f) . "\n");
    }
    fwrite(STDOUT, "\nPer HANDOFF §0 rule, NO per-phase assertion counting begins until every verifier is syntax-clean.\n");
    fwrite(STDOUT, "Fix the syntax error(s) above AND re-run. Most common cause: a trailing comma inside an expression parens (e.g., `(A || B, 'msg')` instead of `(A || B)`).\n");
    exit(2);
}

echo "\n--- All " . count($verifierFiles) . " verifier file(s) PASS syntax-gate. Proceeding to per-phase runs ---\n\n";

// ---------------------------------------------------------------------------
// STAGE 2: per-phase assertion runs.
// ---------------------------------------------------------------------------
echo "=== [STAGE 2] per-phase assertion runs ===\n";
$phasesPassed = 0;
$phasesFailed = 0;
$phaseResults = [];
foreach ($verifierFiles as $f) {
    $output = shell_exec(escapeshellarg($php) . ' ' . escapeshellarg($f) . ' 2>&1');
    $base = basename($f, '.php');
    if (! preg_match('/(\d+)\s*pass\s*\/\s*(\d+)\s*fail/', (string)$output, $m)) {
        echo "  [NO SUMMARY] {$base}  (verifier ran but emitted no `N pass / M fail` line)\n";
        $phasesFailed++;
        $phaseResults[$base] = ['pass' => null, 'fail' => null];
        continue;
    }
    $passCount = (int)$m[1];
    $failCount = (int)$m[2];
    $phaseResults[$base] = ['pass' => $passCount, 'fail' => $failCount];
    if ($failCount === 0) {
        $phasesPassed++;
        echo "  [GREEN]  {$base}  — {$passCount} pass / {$failCount} fail\n";
    } else {
        $phasesFailed++;
        echo "  [RED ]   {$base}  — {$passCount} pass / {$failCount} fail\n";
    }
}

echo "\n=== Master summary ===\n";
echo "PHASES_GREEN: {$phasesPassed}\n";
echo "PHASES_RED:   {$phasesFailed}\n";
echo "TOTAL:        " . count($verifierFiles) . " (" . array_sum(array_column($phaseResults, 'pass') ?: [0]) . " assertions across the matrix — see per-phase summaries above)\n";
echo "MASTER_PHP_L: CLEAN  (syntax-gate passed for all " . count($verifierFiles) . " verifiers)\n";
echo "MASTER_EXIT:  " . ($phasesFailed === 0 ? '0  (project complete — all shipping phases green)' : '1  (regression detected — fix the RED phase(s) above)') . "\n";

exit($phasesFailed === 0 ? 0 : 1);
