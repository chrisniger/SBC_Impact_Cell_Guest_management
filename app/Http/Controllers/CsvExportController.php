<?php

namespace App\Http\Controllers;

use App\Models\Guest;
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

        $columns = $this->columnsForRole($role);

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

    private function columnsForRole(?string $role): array
    {
        if ($role === 'Administrator') {
            return ['guest_name', 'phone', 'email', 'address', 'event', 'event_other', 'source',
                'contacted_status', 'visited', 'follow_up_status', 'follow_up_contacts',
                'nearest_impact_cell_id', 'follow_officer_id', 'created_at'];
        }
        return ['guest_name', 'phone', 'email', 'event', 'source', 'contacted_status', 'visited', 'created_at'];
    }
}
