<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->user()?->activeRole();
        abort_unless(in_array($role, ['Administrator', 'Supervisor', 'Impact_Cell_Admin', 'Impact_Cell_Report', 'Follow_UP_Admin'], true), 403);

        $month = $request->get('month', now()->format('Y-m'));

        $base = Guest::query();
        if ($month) {
            $base->whereYear('created_at', substr($month, 0, 4))
                ->whereMonth('created_at', substr($month, 5, 2));
        }

        $pendingContacts = (clone $base)
            ->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            })->count();

        $totalCalls = (clone $base)
            ->whereNotNull('contacted_status')->where('contacted_status', '!=', '')->count();

        $visited = (clone $base)->where('visited', true)->count();
        $pendingVisit = (clone $base)->where('contacted_status', 'AvailableForVisit')->where('visited', false)->count();

        $responseRate = $totalCalls > 0 ? round(($visited / $totalCalls) * 100, 1) : 0;

        $byStatus = Guest::selectRaw('contacted_status, COUNT(*) as cnt')
            ->groupBy('contacted_status')->orderByDesc('cnt')->get();

        $byEvent = Guest::selectRaw('event, COUNT(*) as cnt')
            ->groupBy('event')->orderByDesc('cnt')->get();

        $byFollowUp = Guest::selectRaw("COALESCE(NULLIF(follow_up_status, ''), 'NOT CONTACTED') as status, COUNT(*) as cnt")
            ->groupBy('status')->orderByDesc('cnt')->get();

        $monthly = Guest::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as cnt")
            ->groupBy('ym')->orderBy('ym')->get();

        return Inertia::render('Reports/Index', [
            'kpis' => [
                'pendingContacts' => $pendingContacts,
                'totalCalls'      => $totalCalls,
                'visited'         => $visited,
                'pendingVisit'    => $pendingVisit,
                'responseRate'    => $responseRate,
            ],
            'byStatus'   => $byStatus,
            'byEvent'    => $byEvent,
            'byFollowUp' => $byFollowUp,
            'monthly'    => $monthly,
            'month'      => $month,
        ]);
    }
}
