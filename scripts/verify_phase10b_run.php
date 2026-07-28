<?php
/**
 * Phase 10b verifier — CSV polish round:
 *   (a) `app/Support/CsvColumns` helper extracted from CsvExportController
 *   (b) per-template alias-map sync (officer / team / impact / default)
 *   (c) Spatie-Activitylog `auditBatch`-style import summary
 *   (d) `resources/js/Pages/Csv/Import.tsx` template-select UX
 *
 * Mirrors the Phase 09b verifier shape — 15 source-pattern sub-assertions.
 *
 * Run: php scripts/verify_phase10b_run.php
 * Expected: 15 pass / 0 fail.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app  = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = 0; $fail = 0; $results = [];

function check(int $n, string $desc, bool $cond, string $expected = ''): void
{
    global $pass, $fail, $results;
    if ($cond) {
        $pass++;
        $results[] = "[{$n}] PASS: {$desc}";
    } else {
        $fail++;
        $msg = "[{$n}] FAIL: {$desc}";
        if ($expected !== '') {
            $msg .= " — expected: {$expected}";
        }
        $results[] = $msg;
    }
}

$csvColumnsFile = $root . '/app/Support/CsvColumns.php';
$exportCtrlFile = $root . '/app/Http/Controllers/CsvExportController.php';
$importCtrlFile = $root . '/app/Http/Controllers/CsvImportController.php';
$routesFile     = $root . '/routes/web.php';
$importTsxFile  = $root . '/resources/js/Pages/Csv/Import.tsx';

$csvColumnsSrc = is_file($csvColumnsFile) ? file_get_contents($csvColumnsFile) : '';
$exportSrc     = is_file($exportCtrlFile) ? file_get_contents($exportCtrlFile) : '';
$importSrc     = is_file($importCtrlFile) ? file_get_contents($importCtrlFile) : '';
$routesSrc     = is_file($routesFile)     ? file_get_contents($routesFile)     : '';
$importTsxSrc  = is_file($importTsxFile)  ? file_get_contents($importTsxFile)  : '';

// [1] Self-syntax — `php -l` on CsvColumns.php must report no syntax errors.
$tmp = tempnam(sys_get_temp_dir(), 'p10b_lint_');
file_put_contents($tmp, $csvColumnsSrc);
$lintOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1');
unlink($tmp);
check(1, 'app/Support/CsvColumns.php parses cleanly (php -l)',
    is_file($csvColumnsFile) && str_contains((string) $lintOut, 'No syntax errors detected'),
    'php -l reports no syntax errors');

// [2] CsvColumns class exists in App\Support namespace.
check(2, 'app/Support/CsvColumns.php declares class CsvColumns in App\\Support namespace',
    str_contains($csvColumnsSrc, 'namespace App\\Support')
        && str_contains($csvColumnsSrc, 'final class CsvColumns'),
    'namespace App\\Support + final class CsvColumns');

// [3] CsvColumns has public static forRole method.
check(3, 'CsvColumns has public static function forRole(?string $role): array',
    (bool) preg_match('/public\s+static\s+function\s+forRole\s*\(\s*\?string\s+\$role\s*\)\s*:\s*array/', $csvColumnsSrc),
    'public static function forRole(?string $role): array');

// [4] CsvColumns has public static aliasesForTemplate method.
check(4, 'CsvColumns has public static function aliasesForTemplate(?string $template): array',
    (bool) preg_match('/public\s+static\s+function\s+aliasesForTemplate\s*\(\s*\?string\s+\$template\s*\)\s*:\s*array/', $csvColumnsSrc),
    'public static function aliasesForTemplate(?string $template): array');

// [5] CsvColumns::forRole Admin-branch contains all 4 group-owned columns.
check(5, 'CsvColumns::forRole Admin branch lists nearest_impact_cell_id + follow_officer_id + follow_up_status + follow_up_contacts',
    str_contains($csvColumnsSrc, "'nearest_impact_cell_id'")
        && str_contains($csvColumnsSrc, "'follow_officer_id'")
        && str_contains($csvColumnsSrc, "'follow_up_status'")
        && str_contains($csvColumnsSrc, "'follow_up_contacts'"),
    'Admin branch lists all 4 group-owned columns');

// [6] CsvColumns::forRole fallback returns the 8-col officer+team subset.
// Phase 10b Round-2 fix: relax regex — array literal is split across multiple lines
// (column, on own line) so the close-bracket + semicolon aren't on the same line as 'created_at'.
check(6, 'CsvColumns::forRole fallback returns the 8-col officer+team subset',
    (bool) preg_match('/\'guest_name\'\s*,\s*\'phone\'\s*,\s*\'email\'\s*,\s*\'event\'\s*,\s*\'source\'\s*,\s*\'contacted_status\'\s*,\s*\'visited\'\s*,\s*\'created_at\'/', $csvColumnsSrc),
    "fallback returns [guest_name, phone, email, event, source, contacted_status, visited, created_at]");

// [7] aliasesForTemplate('officer') branch enriches with contacted_status + visited.
check(7, 'aliasesForTemplate officer case enriches with contacted_status + visited aliases',
    (bool) preg_match('/\'officer\'\s*=>\s*\$base\s*\+\s*\[\s*\'contacted_status\'/', $csvColumnsSrc)
        && (bool) preg_match('/\'officer\'\s*=>\s*\$base[\s\S]*?\'visited\'/', $csvColumnsSrc),
    "'officer' => \$base + ['contacted_status' => ..., 'visited' => ...]");

// [8] aliasesForTemplate('team') branch enriches with follow_up_status.
check(8, 'aliasesForTemplate team case enriches with follow_up_status alias',
    (bool) preg_match('/\'team\'\s*=>\s*\$base\s*\+\s*\[\s*\'follow_up_status\'/', $csvColumnsSrc),
    "'team' => \$base + ['follow_up_status' => ...]");

// [9] aliasesForTemplate('impact') branch enriches with impact_status + nearest_impact_cell_id.
check(9, 'aliasesForTemplate impact case enriches with impact_status + nearest_impact_cell_id aliases',
    (bool) preg_match('/\'impact\'\s*=>\s*\$base\s*\+\s*\[\s*\'impact_status\'/', $csvColumnsSrc)
        && (bool) preg_match('/\'impact\'\s*=>\s*\$base[\s\S]*?\'nearest_impact_cell_id\'/', $csvColumnsSrc),
    "'impact' => \$base + ['impact_status' => ..., 'nearest_impact_cell_id' => ...]");

// [10] CsvExportController imports + calls CsvColumns::forRole($role).
check(10, 'CsvExportController imports App\\Support\\CsvColumns + calls CsvColumns::forRole($role)',
    str_contains($exportSrc, 'use App\\Support\\CsvColumns')
        && (bool) preg_match('/\$columns\s*=\s*CsvColumns::forRole\s*\(\s*\$role\s*\)\s*;/', $exportSrc),
    'use App\\Support\\CsvColumns + CsvColumns::forRole($role)');

// [11] CsvExportController private columnsForRole method REMOVED.
check(11, 'CsvExportController no longer declares private columnsForRole method',
    ! str_contains($exportSrc, 'private function columnsForRole'),
    'private function columnsForRole absent');

// [12] CsvImportController uses CsvColumns::aliasesForTemplate per-template sync.
check(12, "CsvImportController uses CsvColumns::aliasesForTemplate(\$request->string('template')->toString()) per-template sync",
    str_contains($importSrc, 'use App\\Support\\CsvColumns')
        && (bool) preg_match('/\$aliasMap\s*=\s*CsvColumns::aliasesForTemplate\s*\(\s*\$request->string\s*\(\s*\'template\'\s*\)\s*->toString\s*\(\s*\)\s*\)\s*;/', $importSrc),
    "CsvColumns::aliasesForTemplate(\$request->string('template')->toString())");

// [13] CsvImportController wires Spatie-Activitylog auditBatch via activity('csv-import')->log('GUESTS_IMPORTED').
check(13, "CsvImportController wires Spatie-Activitylog auditBatch via activity('csv-import')->log('GUESTS_IMPORTED')",
    str_contains($importSrc, "activity('csv-import')")
        && str_contains($importSrc, '->causedBy($request->user())')
        && str_contains($importSrc, '->withProperties(')
        && str_contains($importSrc, "->log('GUESTS_IMPORTED')"),
    "activity('csv-import')->causedBy(\$request->user())->withProperties(...)->log('GUESTS_IMPORTED')");

// [14] routes/web.php has all 3 csv routes intact.
check(14, 'routes/web.php has all 3 csv routes intact (csv.import + csv.import.upload + csv.export)',
    str_contains($routesSrc, "->name('csv.import')")
        && str_contains($routesSrc, "->name('csv.import.upload')")
        && str_contains($routesSrc, "->name('csv.export')"),
    'all 3 csv route names present');

// [15] resources/js/Pages/Csv/Import.tsx has template <select> + formData.append('template', ...).
check(15, "resources/js/Pages/Csv/Import.tsx has template <select> + formData.append('template', ...)",
    str_contains($importTsxSrc, '<select')
        && str_contains($importTsxSrc, 'data-testid="csv-template-select"')
        && str_contains($importTsxSrc, "formData.append('template'"),
    '<select data-testid="csv-template-select"> + formData.append(\'template\', ...)');

// Output + RC.
echo "=== Phase 10b verification ===\n";
foreach ($results as $r) {
    echo $r . "\n";
}
echo "\nFinal: {$pass} pass / {$fail} fail\n";
exit($fail === 0 ? 0 : 1);
