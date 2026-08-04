<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Support\CsvColumns;
use App\Support\RoleHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        // Phase 10d — per-template export: `?template=default|officer|team|impact`
        // streams guests using the SAME column set as the matching import sample
        // (canonical snake_case headers, so a saved export re-imports cleanly
        // with that template). Admin-only: these column sets include group-owned
        // fields (impact_status, follow_up_status, follow_up_contacts, …) that
        // the role-based fallback below deliberately hides from non-admins.
        if ($request->has('template')) {
            $template = $request->string('template')->toString() ?: 'default';
            abort_unless(in_array($template, ['default', 'officer', 'team', 'impact'], true), 404);
            abort_unless($role === 'Administrator', 403);

            $columns = CsvColumns::sampleColumnsForTemplate($template === 'default' ? '' : $template);
            $headers = $columns; // canonical snake_case — matches the sample files
            $filename = "guests-{$template}.csv";

            $guests = Guest::query()
                ->orderBy('created_at')
                ->get($columns);

            return $this->streamCsv($headers, $columns, $guests, $filename);
        }

        $canExport = $role === 'Administrator' || $group === 'followUpOfficer' || $group === 'followUpTeam';
        abort_unless($canExport, 403);

        $columns = CsvColumns::forRole($role);

        $guests = Guest::query()
            ->when($role !== 'Administrator' && $group === 'followUpOfficer', fn ($q) => $q->where('follow_officer_id', $user->id))
            ->orderBy('created_at')
            ->get($columns);

        $headers = array_map(fn ($c) => str_replace('_', ' ', $c), $columns);

        return $this->streamCsv($headers, $columns, $guests, 'guests.csv');
    }

    /**
     * Shared streaming helper for both export modes.
     *
     * Cast-aware row serialization: `visited` is a boolean cast and
     * `follow_up_contacts` an array cast — writing them raw would emit ""
     * (for false) / "Array" (for arrays). Normalize to 'true'/'false' and
     * JSON so exports round-trip through the importer unchanged.
     *
     * CSV formula-injection guard: a cell that begins with = + - or @ would
     * be executed as a spreadsheet formula when the file is opened in
     * Excel/Sheets (OWASP CSV Injection). Neutralize it with a leading '
     * so it is always treated as plain text.
     */
    private function streamCsv(array $headers, array $columns, Collection $guests, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($headers, $columns, $guests) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($guests as $guest) {
                $row = [];
                foreach ($columns as $col) {
                    $value = $guest->$col ?? '';
                    if (is_bool($value)) {
                        $value = $value ? 'true' : 'false';
                    } elseif (is_array($value)) {
                        $value = json_encode($value) ?: '';
                    }
                    if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
                        $value = "'" . $value;
                    }
                    $row[] = $value;
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $filename . '"');

        return $response;
    }
}
