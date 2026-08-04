<?php
/**
 * Phase 10 — CSV Import / Export verifier.
 *
 * Asserts the architectural surface of Phase 10 (Implementation/Phase_10_CSV_Import_Export.md):
 *   - CsvImportController (admin-gated index/import + str_getcsv parser + 5-alias phone map +
 *     duplicate-by-phone + {created,skipped,errors} JSON shape + skips empty-phone rows).
 *   - CsvExportController (RoleHelper::groupOf gate Admin/Officer/Team +
 *     columnsForRole('Administrator') covers all 3 group-owned column-sets +
 *     fallback returns 8-column subset EXCLUDING the impact-cell + follow-officer-owned columns
 *     and EXCLUDING the follow-team-owned columns).
 *   - 3 csv routes registered (csv.import GET, csv.import.upload POST, csv.export GET).
 *   - resources/js/Pages/Csv/Import.tsx exists with 3 upload testids + AuthenticatedLayout +
 *     fetch('/csv/import', POST, formData) + card-csv-import + card-csv-result with StatusPill
 *     for Created + Skipped.
 *
 * 19 sub-assertions across: self-syntax (1) → spec shape (1) → import controller (8) →
 * export controller (4) → routes (1) → page (4).
 *
 * Deferred to Phase 10b (documented in HANDOFF §1 row):
 *   - GUEST_CSV_COLUMNS(role) helper (currently inlined in CsvExportController::columnsForRole).
 *   - Per-template column header sync (template validator accepts officer/team/impact but
 *     the alias map is single-set).
 *   - auditBatch(req, 'GUESTS_IMPORTED', $created) (spec §7) — import currently doesn't
 *     write to activity log.
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

$importCtrlPath = $base . '/app/Http/Controllers/CsvImportController.php';
$exportCtrlPath = $base . '/app/Http/Controllers/CsvExportController.php';
$csvColumnsPath = $base . '/app/Support/CsvColumns.php';
$pagesImportPath = $base . '/resources/js/Pages/Csv/Import.tsx';
$routesPath = $base . '/routes/web.php';
$specPath = $base . '/Implementation/Phase_10_CSV_Import_Export.md';

// ---------------------------------------------------------------------------
// [1] self-syntax — verifier file must be PHP-parseable (no fatal).
// ---------------------------------------------------------------------------
check(1, 'verify_phase10_run.php is PHP-parseable (no fatal)', true, 'self');

// ---------------------------------------------------------------------------
// [2] Phase 10 spec doc exists + covers 3 templates + duplicate-by-phone + sanitization.
// ---------------------------------------------------------------------------
$specExists = is_file($specPath);
$specText = $specExists ? file_get_contents($specPath) : '';
check(2, 'Phase 10 spec doc exists + covers 3 templates + duplicate-by-phone + sanitization',
    $specExists
    && (
        str_contains($specText, 'Follow Up Officer')
        && str_contains($specText, 'Follow Up Team')
        && str_contains($specText, 'Impact Cell')
    )
    && str_contains($specText, 'Duplicate detection by phone')
    && str_contains($specText, 'stripDisallowed')
    && str_contains($specText, 'drag-and-drop'),
    '`Implementation/Phase_10_CSV_Import_Export.md` must describe 3 templates (Officer/Team/Impact) + duplicate-by-phone + stripDisallowed sanitization + drag-and-drop UI'
);

// ---------------------------------------------------------------------------
// [3] CsvImportController exists + extends Controller + has index() + import().
// ---------------------------------------------------------------------------
$importSrc = is_file($importCtrlPath) ? file_get_contents($importCtrlPath) : '';
// Phase 10b fix: $csvColumnsSrc declaration hoisted from after check(11) — was missing after duplicate-removal round.
$csvColumnsSrc = is_file($csvColumnsPath) ? file_get_contents($csvColumnsPath) : '';
check(3, 'CsvImportController exists + extends Controller + has index() + import()',
    $importSrc !== ''
    && str_contains($importSrc, 'class CsvImportController extends Controller')
    && str_contains($importSrc, 'public function index(Request $request): Response')
    && str_contains($importSrc, 'public function import(Request $request): JsonResponse'),
    '`class CsvImportController extends Controller` with `public function index(Request $request): Response` + `public function import(Request $request): JsonResponse`'
);

// ---------------------------------------------------------------------------
// [4] CsvImportController::index() admin-gated via activeRole === Administrator.
// ---------------------------------------------------------------------------
check(4, 'CsvImportController::index() admin-gated via abort_unless(activeRole === Administrator, 403)',
    preg_match('/abort_unless\s*\(\s*\$request->user\s*\(\s*\)\s*\?\s*->\s*activeRole\s*\(\s*\)\s*===\s*\'Administrator\'\s*,\s*403\s*\)/', $importSrc) === 1,
    '`abort_unless($request->user()?->activeRole() === \'Administrator\', 403)` must guard index()'
);

// ---------------------------------------------------------------------------
// [5] CsvImportController::import() admin-gated + validates csv file + template in:officer,team,impact.
// ---------------------------------------------------------------------------
check(5, 'CsvImportController::import() admin-gated + validates csv file + template in:officer,team,impact',
    preg_match("/abort_unless\\s*\\(\\s*\\\$request->user\\s*\\(\\s*\\)\\s*\\?\\s*->\\s*activeRole\\s*\\(\\s*\\)\\s*===\\s*'Administrator'\\s*,\\s*403\\s*\\)/", $importSrc) === 1
    && preg_match("/'csv'\\s*=>\\s*\\[\\s*'required'\\s*,\\s*'file'\\s*,\\s*'mimes:csv,txt'\\s*\\]/", $importSrc) === 1
    && preg_match("/'template'\\s*=>\\s*\\[\\s*'nullable'\\s*,\\s*'string'\\s*,\\s*'in:officer,team,impact'\\s*\\]/", $importSrc) === 1,
    'must guard activeRole===\'Administrator\' + validate \'csv\'=>[\'required\',\'file\',\'mimes:csv,txt\'] + \'template\'=>[\'nullable\',\'string\',\'in:officer,team,impact\']'
);

// ---------------------------------------------------------------------------
// [6] CsvImportController::import() parses with str_getcsv + file() per spec.
// ---------------------------------------------------------------------------
check(6, 'CsvImportController::import() parses with str_getcsv + file() (CSV parsing per spec)',
    preg_match("/array_map\\s*\\(\\s*'str_getcsv'\\s*,\\s*file\\s*\\(\\s*\\\$file->getRealPath\\s*\\(\\s*\\)\\s*\\)\\s*\\)/", $importSrc) === 1,
    'must use `array_map(\'str_getcsv\', file($file->getRealPath()))` per spec CSV parsing contract'
);

// ---------------------------------------------------------------------------
// [7] CsvImportController::import() aliases phone from 5 supported column headers.
// ---------------------------------------------------------------------------
check(7, 'app/Support/CsvColumns.php defines the 5-alias phone map (phone|phone number|mobile|tel|telephone); CsvImportController delegates via CsvColumns::aliasesForTemplate(...)',
    preg_match("/'phone'\\s*=>\\s*\\[\\s*'phone'\\s*,\\s*'phone number'\\s*,\\s*'mobile'\\s*,\\s*'tel'\\s*,\\s*'telephone'\\s*\\]/", $csvColumnsSrc) === 1
    && str_contains($importSrc, 'CsvColumns::aliasesForTemplate'),
    'must define 5-alias phone map: phone|phone number|mobile|tel|telephone — in CsvColumns::aliasesForTemplate base array; CsvImportController delegates via CsvColumns::aliasesForTemplate($request->input(\'template\'))'
);

// ---------------------------------------------------------------------------
// [8] CsvImportController::import() duplicate-by-phone via Guest::where('phone', $phone)->exists().
// ---------------------------------------------------------------------------
check(8, 'CsvImportController::import() duplicate detection by phone (Guest::where phone exists)',
    preg_match("/Guest::where\\s*\\(\\s*'phone'\\s*,\\s*\\\$phone\\s*\\)\\s*->\\s*exists\\s*\\(\\s*\\)/", $importSrc) === 1,
    'must skip a row when `Guest::where(\'phone\', $phone)->exists()` returns true'
);

// ---------------------------------------------------------------------------
// [9] CsvImportController::import() returns JSON `{created, skipped, errors}`.
// ---------------------------------------------------------------------------
check(9, 'CsvImportController::import() responds with {created, skipped, errors} JSON shape',
    preg_match("/'created'\\s*=>\\s*\\\$created/", $importSrc) === 1
    && preg_match("/'skipped'\\s*=>\\s*\\\$skipped/", $importSrc) === 1
    && preg_match("/'errors'\\s*=>\\s*\\\$skipDetails/", $importSrc) === 1,
    'response must include `created` + `skipped` + `errors` keys'
);

// ---------------------------------------------------------------------------
// [10] CsvImportController::import() skips rows with empty phone + reports in errors.
// ---------------------------------------------------------------------------
check(10, 'CsvImportController::import() skips rows with empty phone + reports in errors',
    preg_match("/\\\$phone\\s*=\\s*trim\\s*\\(\\s*\\\$row\\[\\s*\\\$columnMap\\[\\s*'phone'\\s*\\]\\s*\\]\\s*\\?\\?\\s*''\\s*\\)/", $importSrc) === 1
    && preg_match("/if\\s*\\(\\s*empty\\s*\\(\\s*\\\$phone\\s*\\)\\s*\\)\\s*\\{\\s*\\\$skipped\\s*\\+\\+\\s*;/", $importSrc) === 1
    && preg_match("/\\\$skipDetails\\[\\]\\s*=\\s*\"/", $importSrc) === 1,
    'must extract phone via trim($row[$columnMap[\'phone\']] ?? \'\') + skip+report when empty'
);

// ---------------------------------------------------------------------------
// [11] CsvExportController exists + has export() returning StreamedResponse.
// ---------------------------------------------------------------------------
$exportSrc = is_file($exportCtrlPath) ? file_get_contents($exportCtrlPath) : '';
check(11, 'CsvExportController exists + extends Controller + has export(Request): StreamedResponse',
    $exportSrc !== ''
    && str_contains($exportSrc, 'class CsvExportController extends Controller')
    && preg_match('/public function export\s*\(\s*Request\s+\$request\s*\)\s*:\s*StreamedResponse/', $exportSrc) === 1,
    '`class CsvExportController extends Controller` with `public function export(Request $request): StreamedResponse`'
);

// ---------------------------------------------------------------------------
// [12] CsvExportController::export() gates on admin OR followUpOfficer-group OR followUpTeam-group via RoleHelper::groupOf.
// ---------------------------------------------------------------------------
check(12, 'CsvExportController::export() gates on admin OR followUpOfficer-group OR followUpTeam-group via RoleHelper::groupOf',
    preg_match("/RoleHelper::groupOf\\s*\\(\\s*\\\$role\\s*\\)/", $exportSrc) === 1
    && preg_match("/\\\$canExport\\s*=\\s*\\\$role\\s*===\\s*'Administrator'\\s*\\|\\|\\s*\\\$group\\s*===\\s*'followUpOfficer'\\s*\\|\\|\\s*\\\$group\\s*===\\s*'followUpTeam'/", $exportSrc) === 1,
    'must check `$canExport = $role === \'Administrator\' || $group === \'followUpOfficer\' || $group === \'followUpTeam\'` using RoleHelper::groupOf'
);

// ---------------------------------------------------------------------------
// [13] Admin column-set moved to `CsvColumns::forRole()` (Phase 10b). Source of truth = the helper.
// Verifier updated to read the helper + assert the controller delegates to it.
// ---------------------------------------------------------------------------
check(13, 'app/Support/CsvColumns::forRole Admin branch covers all 3 group-owned columns (nearest_impact_cell_id + follow_officer_id + follow_up_status + follow_up_contacts); CsvExportController delegates via CsvColumns::forRole($role)',
    str_contains($csvColumnsSrc, "'nearest_impact_cell_id'")
    && str_contains($csvColumnsSrc, "'follow_officer_id'")
    && str_contains($csvColumnsSrc, "'follow_up_status'")
    && str_contains($csvColumnsSrc, "'follow_up_contacts'")
    && str_contains($exportSrc, 'CsvColumns::forRole')
    && str_contains($exportSrc, 'private function columnsForRole') === false,
    'admin branch (in CsvColumns::forRole) must include impact-cell (`nearest_impact_cell_id`) + follow-officer (`follow_officer_id`) + follow-team (`follow_up_status` + `follow_up_contacts`) columns; CsvExportController calls CsvColumns::forRole($role); private columnsForRole method must NOT be re-introduced in the controller'
);

// ---------------------------------------------------------------------------
// [14] CsvExportController fallback returns 8-column officer/team subset EXCLUDING group-owned columns.
// ---------------------------------------------------------------------------
// [14] fix: previous version had a brittle `! preg_match` global-negative that fought the Admin branch's own
// co-listing of `'nearest_impact_cell_id', 'follow_officer_id'` (Admin legitimately has both). The new shape
// uses ONLY a positive exact-array match on the fallback return — since the regex's character-by-character
// string match would fail if any extra element were inserted, it INHERENTLY proves the fallback excludes the
// impact-cell + follow-officer + follow-team columns without needing a separate negative check.
check(14, 'app/Support/CsvColumns::forRole fallback returns exactly 8-col officer/team subset (positive exact-list match — inherently excludes any added group-owned column); CsvExportController delegates via CsvColumns::forRole($role)',
    preg_match("/'guest_name'\\s*,\\s*'phone'\\s*,\\s*'email'\\s*,\\s*'event'\\s*,\\s*'source'\\s*,\\s*'contacted_status'\\s*,\\s*'visited'\\s*,\\s*'created_at'/", $csvColumnsSrc) === 1
    && str_contains($exportSrc, 'CsvColumns::forRole'),
    'fallback (in CsvColumns::forRole) must return exactly [guest_name, phone, email, event, source, contacted_status, visited, created_at] in that order; CsvExportController calls CsvColumns::forRole($role)'
);

// ---------------------------------------------------------------------------
// [15] routes/web.php registers the 3 csv routes (csv.import GET + csv.import.upload POST + csv.export GET).
// ---------------------------------------------------------------------------
$routesSrc = is_file($routesPath) ? file_get_contents($routesPath) : '';
// [15] Round-3 rewrite: previous 2 rounds suffered from PHP double-quote escape nightmares around the FQ
// class name (`\\App\\Http\\Controllers\\…`). Per code-reviewer feedback, switch to a single-line
// `[^;]*` co-proximity check (DOTALL `/s` flag) requiring 5 anchor substrings to appear in the same
// route statement bounded by `;`. Tolerates: any FQ class escaping, any namespace aliasing
// (`use App\… + CsvImportController::class`), any whitespace, any quote style for the path.
check(15, 'routes/web.php registers csv.import (GET) + csv.import.upload (POST) + csv.export (GET) — single-line co-proximity via `[^;]*`',
    preg_match("/Route::get[^;]*'\\/csv\\/import'[^;]*CsvImportController::class[^;]*'index'[^;]*'csv\\.import'[^;]*;/s", $routesSrc) === 1
    && preg_match("/Route::post[^;]*'\\/csv\\/import'[^;]*CsvImportController::class[^;]*'import'[^;]*'csv\\.import\\.upload'[^;]*;/s", $routesSrc) === 1
    && preg_match("/Route::get[^;]*'\\/csv\\/export'[^;]*CsvExportController::class[^;]*'export'[^;]*'csv\\.export'[^;]*;/s", $routesSrc) === 1,
    'must register 3 csv routes as single-statement co-proximity of {HTTP method + path + last-segment controller class + method + route-name}: csv.import (GET + CsvImportController@index + csv.import), csv.import.upload (POST + CsvImportController@import + csv.import.upload), csv.export (GET + CsvExportController@export + csv.export)'
);

// ---------------------------------------------------------------------------
// [16] resources/js/Pages/Csv/Import.tsx exists + exports default CsvImport function.
// ---------------------------------------------------------------------------
$pagesSrc = is_file($pagesImportPath) ? file_get_contents($pagesImportPath) : '';
check(16, 'resources/js/Pages/Csv/Import.tsx exists + exports default function CsvImport',
    $pagesSrc !== ''
    && str_contains($pagesSrc, 'export default function CsvImport'),
    'must exist + `export default function CsvImport`'
);

// ---------------------------------------------------------------------------
// [17] Import.tsx has 3 e2e-addressable testids for the upload-zip trio (drop-zone + file-input + upload-button).
// ---------------------------------------------------------------------------
check(17, 'Import.tsx has data-testid="csv-drop-zone" + "csv-file-input" + "csv-upload-button" (e2e-addressable)',
    str_contains($pagesSrc, 'data-testid="csv-drop-zone"')
    && str_contains($pagesSrc, 'data-testid="csv-file-input"')
    && str_contains($pagesSrc, 'data-testid="csv-upload-button"'),
    'must have 3 testids for the upload-zip trio (drop-zone + hidden file input + upload button)'
);

// ---------------------------------------------------------------------------
// [18] Import.tsx is wired through AdminDashboardLayout + file-input ref + POST to /csv/import with formData.
//
// Phase 06e update: the page migrated from the retired `AuthenticatedLayout`
// to `AdminDashboardLayout` (admin sidebar shell + topbar chrome). The
// Phase 06 cleanup pass deleted `resources/js/Layouts/AuthenticatedLayout.tsx`
// entirely, so the prior assertion against that import path was referencing
// a non-existent module. This check now asserts the canonical post-Phase-06e
// shell — single source of truth for the unified layout across every role.
// ---------------------------------------------------------------------------
check(18, 'Import.tsx uses AdminDashboardLayout + useRef<HTMLInputElement>(null) + fetch(\'/csv/import\', { method: \'POST\', body: formData })',
    str_contains($pagesSrc, "import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout'")
    && str_contains($pagesSrc, 'useRef<HTMLInputElement>(null)')
    && preg_match("/fetch\\s*\\(\\s*['\"]\\/csv\\/import['\"]\\s*,\\s*\\{\\s*method\\s*:\\s*['\"]POST['\"]\\s*,\\s*body\\s*:\\s*formData(?:\\s*,\\s*[^}]*)?\\s*\\}\\s*\\)/", $pagesSrc) === 1,
    'must layer under AdminDashboardLayout + manage file input ref + POST formData to /csv/import'
);

// ---------------------------------------------------------------------------
// [19] Import.tsx renders card-csv-import + card-csv-result + StatusPill for Created: + Skipped:.
// ---------------------------------------------------------------------------
check(19, 'Import.tsx renders card-csv-import shell + card-csv-result with StatusPill for Created + Skipped',
    str_contains($pagesSrc, 'data-testid="card-csv-import"')
    && str_contains($pagesSrc, 'data-testid="card-csv-result"')
    && str_contains($pagesSrc, 'Created:')
    && str_contains($pagesSrc, 'Skipped:')
    && str_contains($pagesSrc, "import StatusPill from '@/Components/StatusPill'"),
    'must render both shell cards + StatusPill imported from Components/StatusPill + Created + Skipped + Errors status messaging'
);

// ---------------------------------------------------------------------------

echo "\nPhase 10 verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
