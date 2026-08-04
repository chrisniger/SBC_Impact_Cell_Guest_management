<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Support\CsvColumns;
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
        $rows = array_map('str_getcsv', file($file->getRealPath()));

        if (empty($rows) || count($rows) < 2) {
            return response()->json(['created' => 0, 'skipped' => 0, 'errors' => ['No data rows found.']]);
        }

        $headers = array_map('strtolower', array_map('trim', $rows[0]));
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

        foreach ($dataRows as $rowIndex => $row) {
            $phone = self::stripFormulaGuard(trim($row[$columnMap['phone']] ?? ''));

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

            $email = self::stripFormulaGuard(trim($row[$columnMap['email']] ?? ''));

            // Phase 10b — email format validation. Mirrors the `email` rule in
            // GuestRequest byte-for-byte via Laravel's Validator facade (NOT
            // PHP's `filter_var(FILTER_VALIDATE_EMAIL)`, which is RFC 822 and
            // accepts trailing dots like `foo@bar.com.` — Laravel's default
            // `email` rule uses Egulias/RFC 5321 which rejects them). Skip
            // the row (counted + surfaced via $skipDetails) instead of erroring
            // the entire batch — consistent with the existing phone-presence +
            // duplicate-phone handling above.
            if ($email !== '' && Validator::make(
                ['email' => $email],
                ['email' => ['nullable', 'string', 'email', 'max:255']],
            )->fails()) {
                $skipped++;
                $skipDetails[] = "Row " . ($rowIndex + 2) . ": invalid email format";
                continue;
            }

            $data = [
                'guest_name' => self::stripFormulaGuard(trim($row[$columnMap['guest_name']] ?? '')),
                'phone'      => $phone,
                'email'      => $email,
                'event'      => self::stripFormulaGuard(trim($row[$columnMap['event']] ?? '')),
                'source'     => self::stripFormulaGuard(trim($row[$columnMap['source']] ?? '')),
            ];

            // Phase 10c — persist the template-specific columns too. Phase 10
            // acceptance: "rows with Impact Status are saved". Previously the
            // importer parsed these headers into $columnMap but silently
            // dropped them, so officer/team/impact sample files lost their
            // extended fields on re-import. Only present columns are written;
            // a plain (default-template) CSV keeps its legacy behaviour.
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

                $data[$field] = $value;
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
            'email'                  => 'john.doe@example.com',
            'event'                  => 'Sunday Service',
            'source'                 => 'Welcome Desk',
            'contacted_status'       => 'AvailableForVisit',
            'visited'                => 'false',
            'follow_up_status'       => 'NOT CONTACTED',
            'follow_up_contacts'     => '[]',
            'impact_status'          => 'Not Contacted',
            'nearest_impact_cell_id' => '',
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
