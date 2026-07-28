<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use App\Support\RoleHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/**
 * Dashboard controller — Phase 05 / Phase 06.
 *
 * Single entry point for `GET /dashboard` (replaces the inline closure
 * that was registered in routes/web.php during Phase 02). The same page
 * component (`Pages/Dashboard.tsx`) reads `props.variant` to switch
 * between the role-specific layouts:
 *
 *   variant = "officer"  →  5 KPIs + top-8 follow-up queue
 *                             (renders in `activeGroup = followUpOfficer`)
 *   variant = "team"     →  4 team KPIs + team queue with inline status
 *                             (renders in `activeGroup = followUpTeam`)
 *   variant = "admin"    →  admin "You're logged in!" welcome (default)
 *
 * Cell Leader variant will be added in Phase 07. The `activeGroup` check
 * is the single source of truth — extend in one place when those phases land.
 *
 * Officer KPI math (all scoped to the officer's assigned guests):
 *   - pendingContacts:  contacted_status is NULL/""/'No'/'Not Contacted'
 *   - totalCalls:       contacted_status is set + non-empty
 *   - visited:          visited = true
 *   - pendingVisit:     contacted_status = 'AvailableForVisit' + visited = false
 *   - responseRate:     visited / totalCalls, percentage to 1dp
 *
 * Team KPI math (scoped to ALL guests):
 *   - pendingContacts:  follow_up_status is NULL/""/'NOT CONTACTED'
 *   - contactedToday:   count of guests with a contact section dated today
 *   - wrongNumber:      contacted_status = 'Wrong Number'
 *   - notReachable:     contacted_status = 'Not Reachable'
 *
 * Team queue ordering: NOT CONTACTED first, then CONTACTED, then
 * WRONG NUMBER / NOT REACHABLE; ties broken by most recent updated_at.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = $user?->activeGroup();

        // Phase 09 — Impact_Cell_Admin (cross-cell + cross-zonal supervisor)
        // gets its own dashboard variant. Routed BEFORE the zonal/group
        // match so it short-circuits leaderDashboard() which would
        // otherwise pin them to a single primary cell.
        if (RoleHelper::isImpactCellAdmin($role)) {
            return $this->impactCellAdminDashboard($request, $role);
        }

        if ($role === 'Impact_Zonal_Cordinator') {
            return $this->zonalDashboard($user, $role);
        }

        return match ($group) {
            'followUpOfficer' => $this->officerDashboard($user, $role),
            'followUpTeam'    => $this->teamDashboard($role),
            'impactCell'      => $this->leaderDashboard($user, $role),
            default           => $this->adminDashboard($request, $role, $group),
        };
    }

    private function officerDashboard(User $user, ?string $role): Response
    {
        $officerId = $user->id;

        // Each KPI is built off a fresh base query so the closures don't
        // mutate each other's constraints. (clone $scoped is the canonical
        // way to fork an Eloquent builder.)
        $scoped = Guest::query()->where('follow_officer_id', $officerId);

        $pendingContacts = (clone $scoped)
            ->where(function ($q) {
                $q->whereNull('contacted_status')
                  ->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            })
            ->count();

        $totalCalls = (clone $scoped)
            ->whereNotNull('contacted_status')
            ->where('contacted_status', '!=', '')
            ->count();

        $visited = (clone $scoped)
            ->where('visited', true)
            ->count();

        $pendingVisit = (clone $scoped)
            ->where('contacted_status', 'AvailableForVisit')
            ->where('visited', false)
            ->count();

        $responseRate = $totalCalls > 0
            ? round(($visited / $totalCalls) * 100, 1)
            : 0.0;

        // Top-8 queue: bucketed by contact-status priority, then created_at desc.
        $queue = (clone $scoped)
            ->orderByRaw(
                "CASE
                    WHEN contacted_status IS NULL OR contacted_status = '' OR contacted_status IN ('No', 'Not Contacted') THEN 0
                    WHEN contacted_status = 'AvailableForVisit' THEN 1
                    WHEN contacted_status IN ('Contacted', 'Yes', 'Available') THEN 2
                    ELSE 3
                END"
            )
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['id', 'guest_name', 'phone', 'contacted_status', 'visited', 'created_at']);

        return Inertia::render('Dashboard', [
            'variant'     => 'officer',
            'kpis'        => [
                'pendingContacts' => (int) $pendingContacts,
                'totalCalls'      => (int) $totalCalls,
                'visited'         => (int) $visited,
                'pendingVisit'    => (int) $pendingVisit,
                'responseRate'    => (float) $responseRate,
            ],
            'queue' => $queue
                ->map(fn (Guest $g) => [
                    'id'              => $g->id,
                    'guestName'       => $g->guest_name,
                    'phone'           => $g->phone,
                    'contactedStatus' => $g->contacted_status,
                    'visited'         => (bool) $g->visited,
                    'createdAt'       => $g->created_at?->toIso8601String(),
                ])
                ->all(),
            'activeRole'  => $role,
            'activeGroup' => 'followUpOfficer',
        ]);
    }

    /**
     * Team dashboard — Phase 06.
     *
     * Renders 4 KPIs scoped to ALL guests + a team queue with inline
     * follow_up_status editing. KPIs reflect the team's collective
     * progress, not an individual officer's.
     */
    private function teamDashboard(?string $role): Response
    {
        $base = Guest::query();

        $pendingContacts = (clone $base)
            ->where(function ($q) {
                $q->whereNull('follow_up_status')
                  ->orWhere('follow_up_status', '')
                  ->orWhere('follow_up_status', 'NOT CONTACTED');
            })
            ->count();

        $contactedToday = (clone $base)
            ->whereNotNull('follow_up_contacts')
            ->where('follow_up_contacts', '!=', '[]')
            ->get()
            ->filter(function (Guest $g) {
                $contacts = $g->follow_up_contacts;
                if (! is_array($contacts) || empty($contacts)) return false;
                $today = now()->toDateString();
                foreach ($contacts as $c) {
                    if (($c['date'] ?? '') === $today) return true;
                }
                return false;
            })
            ->count();

        $wrongNumber = (clone $base)
            ->where('contacted_status', 'Wrong Number')
            ->count();

        $notReachable = (clone $base)
            ->where('contacted_status', 'Not Reachable')
            ->count();

        // Team queue sorted by follow_up_status priority:
        // NOT CONTACTED → CONTACTED → WRONG NUMBER / NOT REACHABLE → others
        $queue = (clone $base)
            ->with('followOfficer:id,name')
            ->orderByRaw(
                "CASE
                    WHEN follow_up_status IS NULL OR follow_up_status = '' OR follow_up_status = 'NOT CONTACTED' THEN 0
                    WHEN follow_up_status = 'CONTACTED' THEN 1
                    WHEN follow_up_status IN ('WRONG NUMBER', 'NOT REACHABLE') THEN 2
                    ELSE 3
                END"
            )
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get(['id', 'guest_name', 'phone', 'follow_up_status', 'follow_up_contacts', 'follow_officer_id', 'updated_at']);

        return Inertia::render('Dashboard', [
            'variant'     => 'team',
            'kpis'        => [
                'pendingContacts' => (int) $pendingContacts,
                'contactedToday'  => (int) $contactedToday,
                'wrongNumber'     => (int) $wrongNumber,
                'notReachable'    => (int) $notReachable,
            ],
            'queue' => $queue
                ->map(fn (Guest $g) => [
                    'id'               => $g->id,
                    'guestName'        => $g->guest_name,
                    'phone'            => $g->phone,
                    'followUpStatus'   => $g->follow_up_status,
                    'latestContact'    => $this->latestContactDate($g->follow_up_contacts),
                    'officerName'      => $g->followOfficer?->name,
                    'updatedAt'        => $g->updated_at?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'activeRole'  => $role,
            'activeGroup' => 'followUpTeam',
        ]);
    }

    /**
     * Extract the latest contact date from the follow_up_contacts JSON array.
     */
    private function latestContactDate(?array $contacts): ?string
    {
        if (! is_array($contacts) || empty($contacts)) return null;
        $dates = array_map(fn ($c) => $c['date'] ?? null, $contacts);
        $dates = array_filter($dates);
        if (empty($dates)) return null;
        return max($dates);
    }

    /**
     * Impact Cell Leader dashboard — Phase 07.
     *
     * Shows submission stats, quick-action links, and the Leadership Board
     * when the leader's most-used cell is a primary cell.
     */
    private function leaderDashboard(User $user, ?string $role): Response
    {
        $recentSubmissions = ImpactSubmission::where('user_id', $user->id)
            ->with('impactCell:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (ImpactSubmission $s) => [
                'id'          => $s->id,
                'type'        => $s->type,
                'cellName'    => $s->impactCell?->name,
                'preview'     => $s->data['full_name'] ?? $s->data['name'] ?? '—',
                'createdAt'   => $s->created_at?->toIso8601String(),
            ]);

        $thisWeek = now()->startOfWeek()->toDateString();
        $weekCount = ImpactSubmission::where('user_id', $user->id)
            ->whereDate('created_at', '>=', $thisWeek)
            ->count();

        $totalSubmissions = ImpactSubmission::where('user_id', $user->id)->count();

        $favoriteCellId = ImpactSubmission::where('user_id', $user->id)
            ->whereNotNull('impact_cell_id')
            ->selectRaw('impact_cell_id, COUNT(*) as cnt')
            ->groupBy('impact_cell_id')
            ->orderByDesc('cnt')
            ->first()?->impact_cell_id;

        $favoriteCell = $favoriteCellId ? ImpactCell::find($favoriteCellId) : null;

        // If the favorite cell is a sub-cell, walk up to its primary.
        $primaryCellId = null;
        if ($favoriteCell) {
            $primaryCellId = $favoriteCell->is_primary
                ? $favoriteCell->id
                : $favoriteCell->parent_cell_id;
        }

        // Phase 07b — assigned guests scoped to the leader's primary cell, plus
        // real member submission count (was hard-coded 0 in earlier rounds).
        $memberCount = ImpactSubmission::where('user_id', $user->id)
            ->where('type', 'member')
            ->count();

        $assignedGuests = $primaryCellId
            ? Guest::query()
                ->where('nearest_impact_cell_id', $primaryCellId)
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'guest_name', 'phone', 'impact_status', 'created_at'])
                ->map(fn (Guest $g) => [
                    'id'           => $g->id,
                    'guestName'    => $g->guest_name,
                    'phone'        => $g->phone,
                    'impactStatus' => $g->impact_status,
                    'createdAt'    => $g->created_at?->toIso8601String(),
                ])
                ->all()
            : [];

        $canEditImpactStatus = $user->activeGroup() === 'impactCell';

        return Inertia::render('Dashboard', [
            'variant'        => 'impactCell',
            'kpis'           => [
                'cellName'         => $favoriteCell?->name ?? '—',
                'memberCount'      => $memberCount,
                'weekSubmissions'  => $weekCount,
                'totalSubmissions' => $totalSubmissions,
            ],
            'assignedGuests' => $assignedGuests,
            'recentSubmissions' => $recentSubmissions,
            'primaryCellId'  => $primaryCellId,
            'canEditImpactStatus' => $canEditImpactStatus,
            'activeRole'     => $role,
            'activeGroup'    => 'impactCell',
        ]);
    }

    /**
     * Phase 09 — Impact Cell Administrator dashboard (cross-cell + cross-zonal supervisor).
     *
     * Spec: "the role Impact_Cell_Admin should view all activities from the impact
     * cel units and zonal cordinators. this role server as the adminitrator for
     * impact cell, and zonal cordinators".
     *
     * Surface:
     *   - LeadershipRollup (one card per primary — same widget admin uses) — gives
     *     a single-screen overscan of every primary's engagement delta.
     *   - Recent cross-cell submissions feed (filtered server-side to
     *     GROUP_IMPACT_CELL authors; matches ImpactSubmissionController::index()
     *     scope).
     *   - Recent zonal-coordinator submissions feed (same scope, but filtered to
     *     Impact_Zonal_Cordinator role only).
     *   - KPIs: total primaries, total sub-cells, total cross-group users,
     *     submissions in last 7d.
     *
     * Why a dedicated variant (vs reusing `impactCell`/LeaderDashboard): LeaderDashboard
     * keys off the user's most-used sub-cell and renders ONE primary's full board —
     * semantically wrong for a supervisor who needs cross-cell visibility.
     */
    private function impactCellAdminDashboard(Request $request, ?string $role): Response
    {
        $now = now();
        $weekStart = $now->copy()->subDays(7);

        // Cross-cell submission KPIs (filtered to GROUP_IMPACT_CELL authors).
        $crossGroupBase = ImpactSubmission::query()
            ->whereHas('user', fn ($q) => $q->whereIn('active_role', RoleHelper::GROUP_IMPACT_CELL));

        $totalSubmissions = (clone $crossGroupBase)->count();
        $weekSubmissions = (clone $crossGroupBase)->where('created_at', '>=', $weekStart)->count();

        $cellCounts = [
            'totalPrimaries' => (int) ImpactCell::primary()->count(),
            'totalSubCells'  => (int) ImpactCell::sub()->count(),
            'crossGroupUsers'=> (int) User::query()->whereIn('active_role', RoleHelper::GROUP_IMPACT_CELL)->count(),
            'zonalCoordinators' => (int) User::query()->where('active_role', 'Impact_Zonal_Cordinator')->count(),
        ];

        // Recent cross-cell submissions feed (mixed-source across groupImpactCell authors).
        $recentCrossCellSubs = (clone $crossGroupBase)
            ->with(['impactCell:id,name', 'user:id,name,active_role'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ImpactSubmission $s) => [
                'id'         => $s->id,
                'type'       => $s->type,
                'cellName'   => $s->impactCell?->name,
                'preview'    => $s->data['full_name'] ?? $s->data['name'] ?? '—',
                'authorName' => $s->user?->name,
                'authorRole' => $s->user?->active_role,
                'createdAt'  => $s->created_at?->toIso8601String(),
            ])->all();

        // Zonal-specific feed — same GROUP_IMPACT_CELL authors but narrowed to
        // Impact_Zonal_Cordinator so the supervisor can audit zonal activity
        // separately from cell-leader activity.
        $recentZonalSubs = ImpactSubmission::query()
            ->whereHas('user', fn ($q) => $q->where('active_role', 'Impact_Zonal_Cordinator'))
            ->with(['impactCell:id,name', 'user:id,name'])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (ImpactSubmission $s) => [
                'id'         => $s->id,
                'type'       => $s->type,
                'cellName'   => $s->impactCell?->name,
                'preview'    => $s->data['full_name'] ?? $s->data['name'] ?? '—',
                'authorName' => $s->user?->name,
                'createdAt'  => $s->created_at?->toIso8601String(),
            ])->all();

        return Inertia::render('Dashboard', [
            'variant'        => 'impactCellAdmin',
            'kpis'           => [
                'totalPrimaries'    => $cellCounts['totalPrimaries'],
                'totalSubCells'     => $cellCounts['totalSubCells'],
                'crossGroupUsers'   => $cellCounts['crossGroupUsers'],
                'zonalCoordinators' => $cellCounts['zonalCoordinators'],
                'totalSubmissions'  => (int) $totalSubmissions,
                'weekSubmissions'   => (int) $weekSubmissions,
            ],
            'queue'              => [],
            'recentCrossCellSubs'=> $recentCrossCellSubs,
            'recentZonalSubs'    => $recentZonalSubs,
            'leadershipRollup'   => $this->buildLeadershipRollup(),
            'globalSearchIndex'  => $this->buildGlobalSearchIndex(),
            'activeRole'         => $role,
            'activeGroup'        => 'impactCell',
        ]);
    }

    /**
     * Impact Zonal Cordinator dashboard.
     *
     * Shows all impact cells, recent submissions across their cells,
     * and guest KPIs (pending + contacted).
     */
    private function zonalDashboard(User $user, ?string $role): Response
    {
        $cells = ImpactCell::ordered()->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'is_primary' => $c->is_primary,
        ]);

        $submissions = ImpactSubmission::with('impactCell:id,name')
            ->latest()->limit(10)->get()
            ->map(fn (ImpactSubmission $s) => [
                'id' => $s->id, 'type' => $s->type,
                'cellName' => $s->impactCell?->name,
                'preview' => $s->data['full_name'] ?? $s->data['name'] ?? '—',
                'createdAt' => $s->created_at?->toIso8601String(),
            ]);

        $pendingGuests = Guest::where(function ($q) {
            $q->whereNull('contacted_status')->orWhere('contacted_status', '')
              ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
        })->count();

        $contactedGuests = Guest::whereNotNull('contacted_status')
            ->where('contacted_status', '!=', '')->count();

        return Inertia::render('Dashboard', [
            'variant'  => 'zonal',
            'kpis'     => [
                'totalCells'       => $cells->count(),
                'totalSubmissions' => ImpactSubmission::count(),
                'pendingGuests'    => $pendingGuests,
                'contactedGuests'  => $contactedGuests,
            ],
            'queue'               => [],
            'zonalCells'          => $cells,
            'zonalSubmissions'    => $submissions,
            'activeRole'          => $role,
            'activeGroup'         => 'impactCell',
        ]);
    }

    /**
     * Admin dashboard — global KPIs + recent activity + quick links.
     * Supervisor and other roles without a group also land here.
     *
     * Phase 06d.0 — extends with per-KPI delta (current 7d vs prior 7d) +
     * sparkline series (last 14d daily creation count) so the new 7-card
     * horizontal row can render with motion-safe inline SVG sparklines.
     */
    private function adminDashboard(Request $request, ?string $role, ?string $group): Response
    {
        $base = Guest::query();

        // ── 7 KPI snapshots (matches stripe-style 7-card admin grid) ──
        $totalGuests = (clone $base)->count();
        $pendingContacts = (clone $base)
            ->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            })->count();
        $totalCalls = (clone $base)
            ->whereNotNull('contacted_status')->where('contacted_status', '!=', '')->count();
        $visited = (clone $base)->where('visited', true)->count();
        $totalCells = ImpactCell::count();
        $totalSubmissions = ImpactSubmission::count();
        $totalUsers = User::count();

        $kpis = [
            'totalGuests'      => (int) $totalGuests,
            'pendingContacts'  => (int) $pendingContacts,
            'totalCalls'       => (int) $totalCalls,
            'visited'          => (int) $visited,
            'totalCells'       => (int) $totalCells,
            'totalSubmissions' => (int) $totalSubmissions,
            'totalUsers'       => (int) $totalUsers,
        ];

        // ── Per-KPI delta + sparkline series ──
        $now = now();
        $currStart = $now->copy()->subDays(7);
        $priorStart = $now->copy()->subDays(14);

        $kpiDeltas = [
            'totalGuests'      => $this->kpiDelta((clone $base), $currStart, $now, $priorStart),
            'pendingContacts'  => $this->kpiDelta((clone $base)->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            }), $currStart, $now, $priorStart),
            'totalCalls'       => $this->kpiDelta((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $currStart, $now, $priorStart),
            'visited'          => $this->kpiDelta((clone $base)->where('visited', true), $currStart, $now, $priorStart),
            'totalCells'       => $this->kpiDelta(new ImpactCell, $currStart, $now, $priorStart),
            'totalSubmissions' => $this->kpiDelta(new ImpactSubmission, $currStart, $now, $priorStart),
            'totalUsers'       => $this->kpiDelta(new User, $currStart, $now, $priorStart),
        ];

        $kpiSeries = [
            'totalGuests'      => $this->kpiSeries((clone $base), $now, 14),
            'pendingContacts'  => $this->kpiSeries((clone $base)->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            }), $now, 14),
            'totalCalls'       => $this->kpiSeries((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $now, 14),
            'visited'          => $this->kpiSeries((clone $base)->where('visited', true), $now, 14),
            'totalCells'       => $this->kpiSeries(new ImpactCell, $now, 14),
            'totalSubmissions' => $this->kpiSeries(new ImpactSubmission, $now, 14),
            'totalUsers'       => $this->kpiSeries(new User, $now, 14),
        ];

        // ── Phase 06d.1 — DateRangeFilter + OverviewAnalytics chart payload ──
        // Parses ?range=today|week|month|year|custom (default 'week', keeping the
        // pre-06d.1 sparkline window unchanged so existing callers stay intact).
        // Returns chartSeries for 4 cumulative growth metrics + rangeLabels /
        // rangeKey consumed by AdminDashboard's lazy-loaded OverviewAnalytics.
        $range = $this->parseRange($request);
        $chartSeries = $this->buildChartSeries($base, $range);

        return Inertia::render('Dashboard', [
            'variant'     => 'admin',
            'kpis'        => $kpis,
            'kpiDeltas'   => $kpiDeltas,
            'kpiSeries'   => $kpiSeries,
            'queue'       => [],
            'rangeKey'    => $range['key'],
            'rangeFrom'   => $range['from']->toDateString(),
            'rangeTo'     => $range['to']->toDateString(),
            'rangeLabels' => $range['labels'],
            'chartSeries' => $chartSeries,
            'systemOverview'      => $this->systemOverviewStats(),
            'globalSearchIndex'   => $this->buildGlobalSearchIndex(),
            'recentActivity'      => $this->buildRecentActivityTiles(),
            'recentRegistrations' => $this->buildRecentRegistrations(),
            // Phase 08+ — admin-wide leadership rollup (one compact card per primary,
            // computed via 3 bulk queries → no N+1). Renders below the chart panel
            // and links through to /leadership (the stacked multi-board Inertia page).
            'leadershipRollup'    => $this->buildLeadershipRollup(),
            'activeRole'  => $role,
            'activeGroup' => $group,
        ]);
    }

    /**
     * Phase 08+ — overall leadership tree surface for the ADMIN dashboard.
     *
     * One compact card per primary cell. Built via 3 bulk queries (sub-cell map,
     * submission counts by type, latest report timestamp per primary) so an admin
     * with 65 primaries triggers ≤5 SQL roundtrips total — no N+1.
     *
     * Output shape per primary:
     *   { id, name, subCells, members, souls, childbirths, status, lastReportDate,
     *     href }
     *
     * `status` matches the per-tile convention from LeadershipBoardController::
     * buildBoardData() so frontend status pills render with identical colors:
     *   Submitted  ≤7 days,  Pending 8-14,  Overdue >14,  New never reported.
     *
     * Returns [] when no primaries exist (defensive — admin can still render
     * an EmptyState).
     *
     * SoftDeletes handling: `ImpactCell` does NOT use the SoftDeletes trait
     * (verified against `app/Models/ImpactCell.php` + the
     * `2026_07_27_120000_create_impact_cells_table.php` migration — the table
     * has no `deleted_at` column), so rollup totals simply count every live
     * sub-cell. If soft-delete is added to `ImpactCell` later, add a
     * `whereNull('deleted_at')` filter to BOTH Q2 and Q3 — the dual-filter
     * pattern (one in Eloquent Q1 + one in DB::table joins) is irrelevant today
     * because there is no `deleted_at` column to filter on.
     */
    private function buildLeadershipRollup(): array
    {
        $primaries = ImpactCell::primary()->ordered()->get();
        if ($primaries->isEmpty()) {
            return [];
        }

        $primaryIds = $primaries->pluck('id')->all();

        // Query 1 — sub-cell map (parent_cell_id → [sub-cell id, ...]).
        // Uses Eloquent (rather than DB::table) so future cross-cutting
        // scopes (SoftDeletes if added later, global `where('is_active')`,
        // tenant isolation, etc.) are honored consistently — Q2 + Q3 bulk
        // joins intentionally bypass global scopes for performance, which
        // is the documented trade-off.
        $subCellMap = ImpactCell::query()
            ->whereIn('parent_cell_id', $primaryIds)
            ->get(['id', 'parent_cell_id'])
            ->groupBy('parent_cell_id')
            ->map(fn ($rows) => $rows->pluck('id')->all())
            ->all();

        // Flattened list of every sub-cell id across every primary → reuse for
        // queries 2 and 3 (single IN list, no per-primary loop).
        $subCellIds = collect($subCellMap)->flatten()->unique()->values()->all();

        // Query 2 — submission counts grouped by (primary, type) in ONE join.
        // Cast → string (ImpactCell PKs are UUIDs across the codebase; React
        // side declares `id: string` everywhere). Grouping by the raw column
        // (not the SELECT alias) keeps this query MySQL/SQLite/PostgreSQL-
        // portable — same philosophy as the existing seriesForRange() helper.
        $typeCounts = []; // [primary_id][type] => int
        if ($subCellIds !== []) {
            DB::table('impact_submissions')
                ->join('impact_cells', 'impact_submissions.impact_cell_id', '=', 'impact_cells.id')
                ->whereIn('impact_submissions.impact_cell_id', $subCellIds)
                ->select(
                    'impact_cells.parent_cell_id',
                    'impact_submissions.type',
                    DB::raw('COUNT(*) as cnt')
                )
                ->groupBy('impact_cells.parent_cell_id', 'impact_submissions.type')
                ->get()
                ->each(function ($r) use (&$typeCounts) {
                    $typeCounts[(string) $r->parent_cell_id][$r->type] = (int) $r->cnt;
                });
        }

        // Query 3 — latest report timestamp per primary (one row per primary).
        $lastReportAt = []; // [primary_id] => Carbon|null
        if ($subCellIds !== []) {
            DB::table('impact_submissions')
                ->join('impact_cells', 'impact_submissions.impact_cell_id', '=', 'impact_cells.id')
                ->whereIn('impact_submissions.impact_cell_id', $subCellIds)
                ->where('impact_submissions.type', 'report')
                ->select(
                    'impact_cells.parent_cell_id',
                    DB::raw('MAX(impact_submissions.created_at) as last_report')
                )
                ->groupBy('impact_cells.parent_cell_id')
                ->get()
                ->each(function ($r) use (&$lastReportAt) {
                    $lastReportAt[(string) $r->parent_cell_id] = $r->last_report
                        ? \Illuminate\Support\Carbon::parse($r->last_report)
                        : null;
                });
        }

        $now = now();
        return $primaries->map(function (ImpactCell $primary) use ($subCellMap, $typeCounts, $lastReportAt, $now) {
            $subIds = $subCellMap[$primary->id] ?? [];

            $members = (int) ($typeCounts[$primary->id]['member'] ?? 0);
            $souls = (int) ($typeCounts[$primary->id]['soul'] ?? 0);
            $childbirths = (int) ($typeCounts[$primary->id]['childbirth'] ?? 0);

            // Per-primary report status — same convention as the per-tile logic
            // in LeadershipBoardController::buildBoardData() so the pill colors
            // match between admin rollup and per-board view.
            $lastReport = $lastReportAt[$primary->id] ?? null;
            $status = 'New';
            if ($lastReport !== null) {
                $days = (int) $lastReport->diffInDays($now);
                if ($days <= 7)       { $status = 'Submitted'; }
                elseif ($days <= 14)  { $status = 'Pending'; }
                else                  { $status = 'Overdue'; }
            }

            return [
                'id'             => (string) $primary->id,
                'name'           => $primary->name,
                'subCells'       => count($subIds),
                'members'        => $members,
                'souls'          => $souls,
                'childbirths'    => $childbirths,
                'status'         => $status,
                'lastReportDate' => $lastReport?->toIso8601String(),
                'href'           => route('leadership.index'),
            ];
        })->values()->all();
    }

    /**
     * Phase 06d.0 — KPI delta helper.
     *
     * Counts rows where created_at is in [currStart, now] vs
     * [priorStart, currStart), then returns a percent change. Uses PHP-side
     * bucketing to stay SQLite-compatible.
     */
    private function kpiDelta($query, $currStart, $now, $priorStart): array
    {
        $curr  = (clone $query)->where('created_at', '>=', $currStart)->count();
        $prior = (clone $query)->where('created_at', '>=', $priorStart)->where('created_at', '<', $currStart)->count();
        $pct = $prior > 0
            ? round((($curr - $prior) / $prior) * 100, 1)
            : ($curr > 0 ? 100.0 : 0.0);
        return ['value' => (float) $pct, 'positiveIsGood' => true];
    }

    /**
     * Phase 06d.0 — KPI sparkline series helper.
     *
     * Returns a length-N array of daily counts (oldest to newest) over the
     * last N days. Days with zero rows get 0. SQLite-compatible — uses
     * PHP-side diffInDays bucketing instead of MySQL DATE().
     */
    private function kpiSeries($query, $now, int $days): array
    {
        $start = $now->copy()->subDays($days - 1)->startOfDay();
        $rows = (clone $query)->where('created_at', '>=', $start)->get(['created_at']);
        $buckets = array_fill(0, $days, 0);
        $idx = 0;
        foreach ($rows as $r) {
            $offset = (int) floor($start->diffInDays($r->created_at));
            if ($offset >= 0 && $offset < $days) {
                $buckets[$offset]++;
            }
            $idx++;
            if ($idx > 50000) break; // defensive ceiling
        }
        return $buckets;
    }

    /**
     * Phase 06d.1 — parse ?range= from request into a typed range config.
     *
     * Returns:
     *   key          'today' | 'week' | 'month' | 'year' | 'custom'
     *   from, to     Carbon endpoints (inclusive window)
     *   bucketCount  int — expected number of buckets
     *   bucketUnit   'hour' | 'day' | 'month'
     *   labels       array<string> — X-axis labels (oldest → newest)
     *
     * Validation:
     *   - rangeKey must be in the whitelist; invalid values fall back to 'week'.
     *   - Custom range requires from/to as Y-m-d strings, from ≤ to, and
     *     span ≤ 365 days. Falls back to 'week' on bad input.
     */
    private function parseRange(Request $request): array
    {
        $allowed = ['today', 'week', 'month', 'year', 'custom'];
        $range = (string) $request->query('range', 'week');
        if (! in_array($range, $allowed, true)) {
            $range = 'week';
        }

        $now = now();
        $from = $now->copy()->subHours(23)->startOfHour();
        $to = $now->copy()->endOfHour();
        $labels = [];
        $bucketCount = 24;
        $bucketUnit = 'hour';

        switch ($range) {
            case 'today':
                $bucketCount = 24;
                $bucketUnit = 'hour';
                $from = $now->copy()->subHours(23)->startOfHour();
                $to = $now->copy()->endOfHour();
                for ($i = 23; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subHours($i)->format('H:00');
                }
                break;

            case 'week':
                $bucketCount = 7;
                $bucketUnit = 'day';
                $from = $now->copy()->subDays(6)->startOfDay();
                $to = $now->copy()->endOfDay();
                for ($i = 6; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subDays($i)->format('M j');
                }
                break;

            case 'month':
                $bucketCount = 30;
                $bucketUnit = 'day';
                $from = $now->copy()->subDays(29)->startOfDay();
                $to = $now->copy()->endOfDay();
                for ($i = 29; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subDays($i)->format('M j');
                }
                break;

            case 'year':
                $bucketCount = 12;
                $bucketUnit = 'month';
                $from = $now->copy()->subMonths(11)->startOfMonth();
                $to = $now->copy()->endOfMonth();
                for ($i = 11; $i >= 0; $i--) {
                    $labels[] = $now->copy()->subMonths($i)->format('M Y');
                }
                break;

            case 'custom':
                $fromStr = (string) $request->query('from', '');
                $toStr = (string) $request->query('to', '');
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromStr)
                    || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $toStr)) {
                    return $this->parseRange(new Request(['range' => 'week']));
                }
                $customFrom = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $fromStr)->startOfDay();
                $customTo = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $toStr)->endOfDay();
                if ($customFrom->gt($customTo) || $customFrom->diffInDays($customTo) > 365) {
                    return $this->parseRange(new Request(['range' => 'week']));
                }
                $spanDays = (int) $customFrom->diffInDays($customTo) + 1;
                $bucketUnit = $spanDays > 60 ? 'month' : 'day';
                $bucketCount = $bucketUnit === 'month'
                    ? max(1, (int) floor($customFrom->diffInMonths($customTo)) + 1)
                    : $spanDays;
                $labels = [];
                if ($bucketUnit === 'day') {
                    $cursor = $customFrom->copy();
                    while ($cursor->lte($customTo)) {
                        $labels[] = $cursor->format('M j');
                        $cursor->addDay();
                    }
                } else {
                    $cursor = $customFrom->copy()->startOfMonth();
                    while ($cursor->lte($customTo)) {
                        $labels[] = $cursor->format('M Y');
                        $cursor->addMonth();
                    }
                }
                $from = $customFrom;
                $to = $customTo;
                break;
        }

        return [
            'key' => $range,
            'from' => $from,
            'to' => $to,
            'bucketCount' => $bucketCount,
            'bucketUnit' => $bucketUnit,
            'labels' => $labels,
        ];
    }

    /**
     * Phase 06d.1 — chart payload builder for the Overview Analytics panel.
     *
     * Returns the 4 cumulative metric series scoped to the chosen date range.
     * Each value array matches the bucketCount + order from parseRange().
     */
    private function buildChartSeries($base, array $range): array
    {
        $from = $range['from'];
        $to = $range['to'];
        $bucketCount = $range['bucketCount'];
        $bucketUnit = $range['bucketUnit'];
        $labels = $range['labels'];
        return [
            'totalGuests'      => $this->seriesForRange((clone $base), $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalCalls'       => $this->seriesForRange((clone $base)->whereNotNull('contacted_status')->where('contacted_status', '!=', ''), $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalSubmissions' => $this->seriesForRange(new ImpactSubmission, $from, $to, $bucketCount, $bucketUnit, $labels),
            'totalUsers'       => $this->seriesForRange(new User, $from, $to, $bucketCount, $bucketUnit, $labels),
        ];
    }

    /**
     * Phase 06d.1 — bucket-aware row counter for a date range.
     *
     * Equivalent to kpiSeries() but parameterized by Carbon endpoints + a
     * pre-computed label array. Uses PHP-side bucketing to stay SQLite-
     * compatible (no MySQL DATE() / SQLite strftime() dependence).
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model  $query
     */
    /**
     * Pin a Carbon datetime to config('app.timezone') before formatting.
     *
     * Defensive TZ anchor for seriesForRange's integer day / month / hour
     * offset arithmetic. parseRange() always builds $from from Carbon::now()
     * or Carbon::createFromFormat('Y-m-d') — both anchored to app TZ — so
     * the OBSERVABLE behavior of "pin to $from->timezone" vs "pin to
     * config('app.timezone')" is identical for our current call sites.
     * We hard-pin to config('app.timezone') (rather than $from->timezone)
     * so a future "custom" range branch that accepts dates with explicit
     * TZ suffixes (Z / +HH:MM) still resolves to a single anchor TZ.
     *
     * @param  \Carbon\Carbon  $c
     * @param  string         $format
     * @return string
     */
    private function tzFormat(\Carbon\Carbon $c, string $format): string
    {
        return $c->copy()->setTimezone(config('app.timezone'))->format($format);
    }

    private function seriesForRange($query, $from, $to, int $bucketCount, string $bucketUnit, array $labels): array
    {
        // TZ guard delegated to private `tzFormat()` helper below — see that method's
        // docblock. Catches a future refactor (e.g. storing `created_at` as UTC) at
        // runtime: without pinning, the stored TZ and app TZ can diverge across a
        // DST boundary, causing integer day / month / hour offsets to silently drift.
        $buckets = array_fill(0, $bucketCount, 0);
        $rows = (clone $query)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<=', $to)
            ->get(['created_at']);

        if ($bucketUnit === 'hour') {
            $fromHour = (int) $this->tzFormat($from, 'YmdH');
            foreach ($rows as $r) {
                $rowHour = (int) $this->tzFormat($r->created_at, 'YmdH');
                $offset = $rowHour - $fromHour;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        } elseif ($bucketUnit === 'day') {
            $fromDay = (int) $this->tzFormat($from, 'Ymd');
            foreach ($rows as $r) {
                $rowDay = (int) $this->tzFormat($r->created_at, 'Ymd');
                $offset = $rowDay - $fromDay;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        } elseif ($bucketUnit === 'month') {
            $fromYm = (int) ($this->tzFormat($from, 'Y') . $this->tzFormat($from, 'm'));
            foreach ($rows as $r) {
                $rowYm = (int) ($this->tzFormat($r->created_at, 'Y') . $this->tzFormat($r->created_at, 'm'));
                $offset = $rowYm - $fromYm;
                if ($offset >= 0 && $offset < $bucketCount) {
                    $buckets[$offset]++;
                }
            }
        }
        return $buckets;
    }

    /**
     * Phase 06d.2 — system overview stats for the 4 progress-bar cards.
     * Each external call (SHOW TABLE STATUS / ActivityLog query / dir-size)
     * is wrapped in try/catch so an unavailable dependency degrades to "—"
     * or 0 instead of breaking the entire dashboard render.
     */
    private function systemOverviewStats(): array
    {
        $dbSizeBytes = 0;
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'mysql') {
                foreach (DB::select('SHOW TABLE STATUS') as $r) {
                    $dbSizeBytes += (int) ($r->Data_length ?? 0) + (int) ($r->Index_length ?? 0);
                }
            } elseif ($driver === 'sqlite') {
                $dbPath = config('database.connections.sqlite.database');
                if ($dbPath && file_exists($dbPath)) {
                    $dbSizeBytes = (int) (filesize($dbPath) ?: 0);
                }
            }
        } catch (\Throwable $e) {
            $dbSizeBytes = 0;
        }

        $storageBytes = 0;
        foreach (['app', 'framework'] as $sub) {
            $path = storage_path($sub);
            if (is_dir($path)) {
                $storageBytes += $this->dirSize($path);
            }
        }

        $activeUsers = 0;
        try {
            $activeUsers = (int) Activity::where('updated_at', '>=', now()->subDays(7))
                ->whereNotNull('causer_id')
                ->distinct('causer_id')
                ->count('causer_id');
        } catch (\Throwable $e) {
            $activeUsers = 0;
        }

        $errorEvents = 0;
        try {
            $errorEvents = (int) Activity::where('updated_at', '>=', now()->subHours(24))
                ->where(function ($q) {
                    $q->where('log_name', 'error')->orWhere('description', 'like', '%fail%');
                })
                ->count();
        } catch (\Throwable $e) {
            $errorEvents = 0;
        }

        if ($errorEvents === 0) {
            $health = ['label' => 'Healthy', 'tone' => 'success'];
        } elseif ($errorEvents < 5) {
            $health = ['label' => '1 issue', 'tone' => 'warn'];
        } else {
            $health = ['label' => $errorEvents . ' issues', 'tone' => 'danger'];
        }

        return [
            'dbSizeMb'     => round($dbSizeBytes / 1024 / 1024, 2),
            'dbSizeLabel'  => $dbSizeBytes > 0 ? $this->humanBytes($dbSizeBytes) : '—',
            'storageMb'    => round($storageBytes / 1024 / 1024, 2),
            'storageLabel' => $storageBytes > 0 ? $this->humanBytes($storageBytes) : '—',
            'activeUsers'  => $activeUsers,
            'healthLabel'  => $health['label'],
            'healthTone'   => $health['tone'],
        ];
    }

    /** Recursive directory-size helper using SPL iterators — portable, no Facade dependency. */
    private function dirSize(string $path): int
    {
        if (! is_dir($path)) {
            return 0;
        }
        $total = 0;
        $iter = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($iter as $file) {
            if ($file->isFile()) {
                $total += (int) $file->getSize();
            }
        }
        return $total;
    }

    /** Human-readable byte suffix helper (B / KB / MB / GB). */
    private function humanBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1024 * 1024 * 1024) return round($bytes / 1024 / 1024, 1) . ' MB';
        return round($bytes / 1024 / 1024 / 1024, 1) . ' GB';
    }

    /**
     * Phase 06d.2 — 6 colored-icon tiles (Guests / Cells / Reports /
     * Notifications / Audit Log / Users). Each tile carries a count + a
     * pre-formatted relative-time label (Carbon::diffForHumans server-side
     * — keeps dayjs/date-fns out of the bundle).
     */
    private function buildRecentActivityTiles(): array
    {
        $label = fn(string $class): string => $this->latestCreatedAtLabel($class);
        return [
            ['category' => 'Guests',        'color' => 'indigo',  'count' => (int) Guest::count(),            'latestLabel' => $label(Guest::class),               'href' => route('guests.index')],
            ['category' => 'Impact Cells',  'color' => 'emerald', 'count' => (int) ImpactCell::count(),       'latestLabel' => $label(ImpactCell::class),          'href' => route('impact-cells.index')],
            ['category' => 'Reports',       'color' => 'amber',   'count' => (int) ImpactSubmission::count(), 'latestLabel' => $label(ImpactSubmission::class),   'href' => route('impact-submissions.index')],
            ['category' => 'Notifications', 'color' => 'rose',    'count' => (int) \App\Models\NotificationSetting::count(), 'latestLabel' => '—',                          'href' => route('notification-settings.index')],
            ['category' => 'Audit Log',     'color' => 'blue',    'count' => $this->activityLogCount(),       'latestLabel' => $this->latestActivityLabel(),      'href' => route('audit.index')],
            ['category' => 'Users',         'color' => 'default', 'count' => (int) User::count(),             'latestLabel' => $label(User::class),                'href' => '/users'],
        ];
    }

    private function activityLogCount(): int
    {
        try { return (int) Activity::count(); } catch (\Throwable $e) { return 0; }
    }

    private function latestActivityLabel(): string
    {
        try {
            $latest = Activity::latest('updated_at')->first();
            return ($latest && $latest->updated_at)
                ? \Illuminate\Support\Carbon::parse($latest->updated_at)->diffForHumans()
                : '—';
        } catch (\Throwable $e) {
            return '—';
        }
    }

    /** Generic "latest created_at" relative-label helper for any Eloquent class. */
    private function latestCreatedAtLabel(string $class): string
    {
        try {
            $latest = $class::latest('created_at')->first();
            return ($latest && $latest->created_at)
                ? \Illuminate\Support\Carbon::parse($latest->created_at)->diffForHumans()
                : '—';
        } catch (\Throwable $e) {
            return '—';
        }
    }

    /**
     * Phase 06d.2 — 3 latest mixed-source registration cards (guest/user/
     * submission pre-blended then sorted desc by createdAt). Initials
     * computed server-side so the JS component is purely presentational.
     */
    private function buildRecentRegistrations(): array
    {
        $items = [];
        foreach (Guest::orderByDesc('created_at')->limit(3)->get(['id', 'guest_name', 'phone', 'created_at']) as $g) {
            $items[] = [
                'id'        => 'guest-' . $g->id,
                'label'     => $g->guest_name ?: '(unnamed)',
                'subtitle'  => $g->phone ?? '',
                'href'      => route('guests.show', $g->id),
                'initials'  => $this->adminInitials($g->guest_name),
                'color'     => 'indigo',
                'createdAt' => $g->created_at?->toIso8601String(),
            ];
        }
        foreach (User::orderByDesc('created_at')->limit(3)->get(['id', 'name', 'email', 'created_at']) as $u) {
            $items[] = [
                'id'        => 'user-' . $u->id,
                'label'     => $u->name,
                'subtitle'  => $u->email,
                'href'      => '/users',
                'initials'  => $this->adminInitials($u->name),
                'color'     => 'emerald',
                'createdAt' => $u->created_at?->toIso8601String(),
            ];
        }
        foreach (ImpactSubmission::with('impactCell:id,name')->orderByDesc('created_at')->limit(3)->get(['id', 'type', 'data', 'impact_cell_id', 'created_at']) as $s) {
            $preview = $s->data['full_name'] ?? $s->data['name'] ?? '—';
            $items[] = [
                'id'        => 'submission-' . $s->id,
                'label'     => $this->adminSubmissionTypeLabel($s->type) . ': ' . $preview,
                'subtitle'  => $s->impactCell?->name ?? '—',
                'href'      => route('impact-submissions.index'),
                'initials'  => strtoupper(substr((string) $s->type, 0, 2)),
                'color'     => 'amber',
                'createdAt' => $s->created_at?->toIso8601String(),
            ];
        }
        usort($items, fn($a, $b) => strcmp((string)($b['createdAt'] ?? ''), (string)($a['createdAt'] ?? '')));
        return array_values(array_slice($items, 0, 3));
    }

    private function adminInitials(?string $name): string
    {
        if (! $name) return '?';
        $parts = preg_split('/\s+/', trim($name));
        if (count($parts) >= 2) {
            return strtoupper($parts[0][0] . $parts[count($parts) - 1][0]);
        }
        return strtoupper(substr($parts[0], 0, 2));
    }

    private function adminSubmissionTypeLabel(string $type): string
    {
        return match ($type) {
            'member'     => 'Members',
            'report'     => 'Report',
            'childbirth' => 'Childbirth',
            'soul'       => 'Soul',
            default      => ucfirst($type),
        };
    }

    /**
     * Phase 06d.2 — global search index for the AdminDashboardLayout topbar
     * (latest 5 of each category → max 20 items; client-side filter).
     */
    private function buildGlobalSearchIndex(): array
    {
        $items = [];
        foreach (Guest::orderByDesc('created_at')->limit(5)->get(['id', 'guest_name', 'phone']) as $g) {
            $items[] = ['id' => (string) $g->id, 'category' => 'guest', 'label' => $g->guest_name ?: '(unnamed)', 'subtitle' => $g->phone ?? '', 'href' => route('guests.show', $g->id)];
        }
        foreach (ImpactCell::orderByDesc('created_at')->limit(5)->get(['id', 'name']) as $c) {
            $items[] = ['id' => (string) $c->id, 'category' => 'cell', 'label' => $c->name, 'subtitle' => 'Impact Cell', 'href' => route('impact-cells.show', $c->id)];
        }
        foreach (ImpactSubmission::with('impactCell:id,name')->orderByDesc('created_at')->limit(5)->get(['id', 'type', 'data', 'impact_cell_id']) as $s) {
            $preview = $s->data['full_name'] ?? $s->data['name'] ?? '—';
            $items[] = ['id' => (string) $s->id, 'category' => 'submission', 'label' => $this->adminSubmissionTypeLabel($s->type) . ': ' . $preview, 'subtitle' => $s->impactCell?->name ?? '', 'href' => route('impact-submissions.show', $s->id)];
        }
        foreach (User::orderByDesc('created_at')->limit(5)->get(['id', 'name', 'email']) as $u) {
            $items[] = ['id' => (string) $u->id, 'category' => 'user', 'label' => $u->name, 'subtitle' => $u->email, 'href' => '/users'];
        }
        return $items;
    }
}
