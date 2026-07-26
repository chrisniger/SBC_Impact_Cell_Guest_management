<?php

namespace App\Http\Controllers;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadershipBoardController extends Controller
{
    public function show(Request $request, string $cellId): JsonResponse
    {
        $user = $request->user();
        $role = $user?->activeRole();

        $cell = ImpactCell::with('subCells')->findOrFail($cellId);

        if (! $cell->is_primary) {
            abort(422, 'Leadership board requires a primary cell.');
        }

        $canView = $role === 'Administrator'
            || $role === 'Impact_Cell_Admin'
            || $role === 'Impact_Cell_Report';

        if (! $canView) {
            abort(403, 'You do not have access to this leadership board.');
        }

        $subCells = $cell->subCells()->ordered()->get();

        $tiles = $subCells->map(function (ImpactCell $sub) {
            $members = ImpactSubmission::where('impact_cell_id', $sub->id)
                ->where('type', 'member')->count();

            $souls = ImpactSubmission::where('impact_cell_id', $sub->id)
                ->where('type', 'soul')->count();

            $childbirths = ImpactSubmission::where('impact_cell_id', $sub->id)
                ->where('type', 'childbirth')->count();

            $latestReport = ImpactSubmission::where('impact_cell_id', $sub->id)
                ->where('type', 'report')
                ->latest()
                ->first();

            $lastReportDate = $latestReport?->created_at;
            $now = now();

            // Report status logic
            $reportStatus = 'New';
            if ($lastReportDate !== null) {
                $daysSince = $lastReportDate->diffInDays($now);
                if ($daysSince <= 7) {
                    $reportStatus = 'Submitted';
                } elseif ($daysSince <= 14) {
                    $reportStatus = 'Pending';
                } else {
                    $reportStatus = 'Overdue';
                }
            }

            return [
                'id'              => $sub->id,
                'name'            => $sub->name,
                'phone'           => $sub->phone,
                'membersCount'    => $members,
                'soulsCount'      => $souls,
                'childbirthsCount' => $childbirths,
                'reportStatus'    => $reportStatus,
                'lastReportDate'  => $lastReportDate?->toIso8601String(),
            ];
        });

        return response()->json([
            'primaryCell' => [
                'id'   => $cell->id,
                'name' => $cell->name,
            ],
            'tiles'       => $tiles,
            'totals'      => [
                'members'     => $tiles->sum('membersCount'),
                'souls'       => $tiles->sum('soulsCount'),
                'childbirths' => $tiles->sum('childbirthsCount'),
                'subCells'    => $subCells->count(),
            ],
        ]);
    }
}
