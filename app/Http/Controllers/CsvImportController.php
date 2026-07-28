<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Support\CsvColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

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
            $phone = trim($row[$columnMap['phone']] ?? '');

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

            $email = trim($row[$columnMap['email']] ?? '');

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
                'guest_name' => trim($row[$columnMap['guest_name']] ?? ''),
                'phone'      => $phone,
                'email'      => $email,
                'event'      => trim($row[$columnMap['event']] ?? ''),
                'source'     => trim($row[$columnMap['source']] ?? ''),
            ];

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
}
