<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Support\CsvColumns;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvImportController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);
        return Inertia::render('Csv/Import');
    }

    public function import(Request $request): JsonResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $request->validate([
            'csv'  => ['required', 'file', 'mimes:csv,txt'],
            'template' => ['nullable', 'string', 'in:officer,team,impact'],
        ]);

        $file = $request->file('csv');

        // Strip a UTF-8 BOM if present (Excel / Windows-saved CSVs commonly
        // prepend one). `trim()` does NOT remove the invisible \xEF\xBB\xBF
        // marker, so an unchecked BOM would ride along on the first header
        // cell ("\u{FEFF}guest name") and fail every alias match below — the
        // importer would then report "missing guest name" on every row even
        // though the CSV is perfectly valid.
        $content = file_get_contents($file->getRealPath()) ?: '';
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Split on any line-ending convention (\r\n, \n, \r) and str_getcsv
        // each line — same per-line parsing contract as file() + str_getcsv,
        // but over the BOM-stripped content so the header alias map sees
        // clean bytes. PREG_SPLIT_NO_EMPTY drops fully-blank separator rows
        // (a blank line is not a data row) while `,,,` rows still survive
        // to be reported as missing-phone.
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', $content, -1, PREG_SPLIT_NO_EMPTY));

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['created' => 0, 'skipped' => 0, 'errors' => ['No data rows found.']]);
        }

        // Belt-and-braces: also ltrim any BOM bytes off each header cell so
        // the alias match is robust even if the content-level strip above
        // ever runs on a file that was mutated between read and parse.
        $headers = array_map(
            static fn (string $header) => ltrim(strtolower(trim($header)), "\xEF\xBB\xBF"),
            $rows[0],
        );
        $dataRows = array_slice($rows, 1);

        $aliasMap = CsvColumns::aliasesForTemplate($request->string('template')->toString());

        $columnMap = [];
        foreach ($aliasMap as $field => $aliases) {
            foreach ($headers as $i => $header) {
                if (in_array($header, $aliases, true)) {
                    $columnMap[$field] = $i;
                    break;
                }
            }
        }

        $created = 0;
        $skipped = 0;
        $skipDetails = [];

        // One-time lookup tables for impact-cell resolution, built ONCE per
        // import instead of a DB query per row with a cell value (bulk CSVs
        // are thousands of rows). Keys are lowercased so both cell names and
        // UUIDs resolve case-insensitively (mirroring the MySQL ci collation
        // the whereKey()/whereRaw() lookups used to rely on).
        $cellIdByLower = [];
        $cellIdByName = [];
        foreach (ImpactCell::all(['id', 'name']) as $cell) {
            $cellIdByLower[mb_strtolower($cell->id)] = $cell->id;
            $cellIdByName[mb_strtolower(trim($cell->name))] = $cell->id;
        }

        foreach ($dataRows as $rowIndex => $row) {
            $phone = self::stripFormulaGuard(self::cell($columnMap, $row, 'phone'));

            if (empty($phone)) {
                $skipped++;
                $skipDetails[] = "Row " . ($rowIndex + 2) . ": missing phone";
                continue;
            }

            $existing = Guest::where('phone', $phone)->exists();
            if ($existing) {
                $skipped++;
                $skipDetails[] = "Row " . ($rowIndex + 2) . ": duplicate phone {$phone}";
                continue;
            }

            $guestName = self::stripFormulaGuard(self::cell($columnMap, $row, 'guest_name'));

            // Skip rows with empty guest name — required field, same contract
            // as missing phone (the guest record is useless without a name).
            if ($guestName === '') {
                $skipped++;
                $skipDetails[] = "Row " . ($rowIndex + 2) . ": missing guest name";
                continue;
            }

            $data = [
                'guest_name' => $guestName,
                'phone'      => $phone,
                'event'      => self::stripFormulaGuard(self::cell($columnMap, $row, 'event')),
            ];

            // Phase 10c — persist the template-specific columns too. Phase 10
            // acceptance: "rows with Impact Status are saved". Previously the
            // importer parsed these headers into $columnMap but silently
            // dropped them, so officer/team/impact sample files lost their
            // extended fields on re-import. Only present columns are written;
            // a plain (default-template) CSV keeps its legacy behaviour.
            $skipRow = false;

            foreach (['contacted_status', 'visited', 'follow_up_status', 'follow_up_contacts', 'impact_status', 'nearest_impact_cell_id'] as $field) {
                if (! isset($columnMap[$field])) {
                    continue;
                }

                $value = self::stripFormulaGuard(trim($row[$columnMap[$field]] ?? ''));
                if ($value === '') {
                    continue;
                }

                if ($field === 'visited') {
                    // `visited` is a tinyint(1) column. Eloquent's primitive
                    // `boolean` cast is get-only — the raw CSV string would
                    // reach MySQL uncast (and PHP's `(bool) 'false'` is `true`
                    // anyway). Normalize the common truthy spellings here.
                    $data[$field] = in_array(strtolower($value), ['1', 'true', 'yes', 'y'], true);
                    continue;
                }

                if ($field === 'nearest_impact_cell_id') {
                    // The column is a UUID FK to impact_cells.id. Real-world
                    // CSVs put a cell NAME here ("EFAB WARU") or a cell UUID.
                    // Resolve either to the real UUID before writing — a raw
                    // name string would either 500 the whole batch on the FK
                    // constraint or be unmappable to a display name later.
                    // Unresolvable values skip the row with a clear error
                    // (same contract as missing phone / duplicate phone /
                    // invalid email above).
                    $resolved = self::resolveImpactCellId($value, $cellIdByLower, $cellIdByName);
                    if ($resolved === null) {
                        $skipped++;
                        $skipDetails[] = "Row " . ($rowIndex + 2) . ": unknown impact cell '{$value}'";
                        $skipRow = true;
                        break;
                    }
                    $data[$field] = $resolved;
                    continue;
                }

                $data[$field] = $value;
            }

            if ($skipRow) {
                continue;
            }

            Guest::create($data);
            $created++;
        }

        // Phase 10b — Spatie-Activitylog `auditBatch`-style summary of the import.
        activity('csv-import')
            ->causedBy($request->user())
            ->withProperties([
                'created'  => $created,
                'skipped'  => $skipped,
                'template' => $request->string('template')->toString(),
            ])
            ->log('GUESTS_IMPORTED');

        return response()->json([
            'created'    => $created,
            'skipped'    => $skipped,
            'errors'     => $skipDetails,
        ]);
    }

    /**
     * Read a CSV cell by canonical field, safe when the header row didn't
     * map the field at all (returns ''). Mirrors the `$row[$i] ?? ''`
     * tolerance for short rows, and fixes the pre-existing 500 when a CSV
     * omits a base header (e.g. `email`) — `$columnMap[$field]` used to be
     * read unguarded, which threw "Undefined array key" and aborted the
     * whole batch.
     */
    private static function cell(array $columnMap, array $row, string $field): string
    {
        $index = $columnMap[$field] ?? null;

        return $index === null ? '' : trim($row[$index] ?? '');
    }

    /**
     * Resolve a CSV-provided `nearest_impact_cell_id` value to a real impact
     * cell UUID. Accepts either:
     *   - the cell's UUID (passed through when the row exists), or
     *   - the cell NAME (case-insensitive exact match — "EFAB WARU" and
     *     "efab waru" both resolve to the same cell).
     * Returns null for empty values and values that match no cell; the
     * caller decides the row-level consequence (skip + error detail).
     *
     * @param array<string,string> $cellIdByLower lowercase-UUID → canonical UUID
     * @param array<string,string> $cellIdByName  lowercase-name → canonical UUID
     */
    private static function resolveImpactCellId(string $value, array $cellIdByLower, array $cellIdByName): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (Str::isUuid($value)) {
            return $cellIdByLower[mb_strtolower($value)] ?? null;
        }

        return $cellIdByName[mb_strtolower($value)] ?? null;
    }

    /**
     * Inverse of the CSV formula-injection guard applied on export (see
     * CsvExportController::streamCsv): strip the protective leading
     * apostrophe when it guards a formula-start character (', +, -, @), so a
     * value exported by this app as "'=SUM(A1)" comes back in as the original
     * "=SUM(A1)". A literal leading apostrophe (e.g. a name like "'John")
     * is left untouched.
     */
    private static function stripFormulaGuard(string $value): string
    {
        return preg_match("/^'(?=[=+\-@])/", $value) === 1 ? substr($value, 1) : $value;
    }

    /**
     * Phase 10c — download a ready-made sample CSV for an import template.
     *
     * One sample per existing CSV system (default / officer / team / impact).
     * The header row uses the canonical snake_case column names — every one
     * is a valid header alias in CsvColumns::aliasesForTemplate(), so a file
     * saved from here re-imports cleanly even completely unedited. One example
     * row is included with valid enum spellings / shapes the importer accepts.
     * Admin-gated like the rest of the CSV import surface.
     */
    public function sample(Request $request, string $template = ''): StreamedResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        if (! in_array($template, ['', 'officer', 'team', 'impact'], true)) {
            abort(404);
        }

        $columns = CsvColumns::sampleColumnsForTemplate($template);

        // Example values — valid enum spellings / shapes the importer accepts.
        $example = [
            'guest_name'             => 'John Doe',
            'phone'                  => '08012345678',
            'event'                  => 'Sunday Service',
            'contacted_status'       => 'AvailableForVisit',
            'visited'                => 'false',
            'follow_up_status'       => 'NOT CONTACTED',
            'follow_up_contacts'     => '[]',
            'impact_status'          => 'Not Contacted',
            // First real primary cell (or '' when the DB has none) — the
            // importer resolves the name to the cell's UUID, so a sample
            // saved as-is and re-imported (Phase 10e "Import to test" flow)
            // lands on an actual cell in ANY deployment, not a hardcoded
            // name that may not exist elsewhere.
            'nearest_impact_cell_id' => ImpactCell::query()
                ->where('is_primary', true)
                ->orderBy('order')
                ->value('name') ?? '',
        ];

        $row = array_map(static fn (string $column) => $example[$column] ?? '', $columns);

        $filename = 'guest-import-sample' . ($template !== '' ? '-' . $template : '') . '.csv';

        return response()->streamDownload(
            static function () use ($columns, $row): void {
                $out = fopen('php://output', 'w');
                fputcsv($out, $columns);
                fputcsv($out, $row);
                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }
}
