<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Guest;
use App\Support\AnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 34 — Admin Analytics page (cross-cell trends overview).
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with a dedicated analytics
 * page that reuses the exact chart math from the Admin Dashboard via the
 * shared App\Support\AnalyticsService:
 *
 *   GET /admin/analytics — KPI delta row + recharts Overview Analytics
 *                          (Today/Week/Month/Year/Custom range) + System
 *                          Overview panel.
 *
 * Administrator-only (mirrors the other admin controllers). The listing
 * route stays behind `gate.stubs` (production-hidden per the design
 * decision).
 */
class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $analytics = app(AnalyticsService::class);
        $base = Guest::query();

        $range = $analytics->parseRange($request);

        return Inertia::render('Admin/Analytics/Index', [
            'kpis'              => $analytics->adminKpis(),
            'kpiDeltas'         => $analytics->adminKpiDeltas(),
            'kpiSeries'         => $analytics->adminKpiSeries(),
            'rangeKey'          => $range['key'],
            'rangeFrom'         => $range['from']->toDateString(),
            'rangeTo'           => $range['to']->toDateString(),
            'rangeLabels'       => $range['labels'],
            'chartSeries'       => $analytics->buildChartSeries($base, $range),
            'systemOverview'    => $analytics->systemOverviewStats(),
            'activeRole'        => 'Administrator',
        ]);
    }
}
