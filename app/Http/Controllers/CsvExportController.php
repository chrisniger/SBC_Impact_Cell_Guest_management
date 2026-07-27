<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Support\CsvColumns;
use App\Support\RoleHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        $canExport = $role === 'Administrator' || $group === 'followUpOfficer' || $group === 'followUpTeam';
        abort_unless($canExport, 403);

        $columns = CsvColumns::forRole($role);

        $guests = Guest::query()
            ->when($role !== 'Administrator' && $group === 'followUpOfficer', fn ($q) => $q->where('follow_officer_id', $user->id))
            ->orderBy('created_at')
            ->get($columns);

        $headers = array_map(fn ($c) => str_replace('_', ' ', $c), $columns);

        $response = new StreamedResponse(function () use ($headers, $guests) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            foreach ($guests as $guest) {
                $row = [];
                foreach ($headers as $i => $h) {
                    $col = $columns[$i];
                    $row[] = $guest->$col ?? '';
                }
                fputcsv($handle, $row);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="guests.csv"');

        return $response;
    }
}
