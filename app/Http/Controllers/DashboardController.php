<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

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

        if ($role === 'Impact_Zonal_Cordinator') {
            return $this->zonalDashboard($user, $role);
        }

        return match ($group) {
            'followUpOfficer' => $this->officerDashboard($user, $role),
            'followUpTeam'    => $this->teamDashboard($role),
            'impactCell'      => $this->leaderDashboard($user, $role),
            default           => $this->adminDashboard($role, $group),
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

        return Inertia::render('Dashboard', [
            'variant'        => 'impactCell',
            'kpis'           => [
                'cellName'         => $favoriteCell?->name ?? '—',
                'memberCount'      => 0,
                'weekSubmissions'  => $weekCount,
                'totalSubmissions' => $totalSubmissions,
            ],
            'queue'          => [],
            'recentSubmissions' => $recentSubmissions,
            'primaryCellId'  => $primaryCellId,
            'activeRole'     => $role,
            'activeGroup'    => 'impactCell',
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
     */
    private function adminDashboard(?string $role, ?string $group): Response
    {
        $base = Guest::query();

        $pendingContacts = (clone $base)
            ->where(function ($q) {
                $q->whereNull('contacted_status')->orWhere('contacted_status', '')
                  ->orWhereIn('contacted_status', ['No', 'Not Contacted']);
            })->count();

        $totalCalls = (clone $base)
            ->whereNotNull('contacted_status')->where('contacted_status', '!=', '')->count();

        $visited = (clone $base)->where('visited', true)->count();

        $totalGuests = (clone $base)->count();
        $totalCells = ImpactCell::count();
        $totalSubmissions = ImpactSubmission::count();
        $totalUsers = User::count();

        return Inertia::render('Dashboard', [
            'variant'     => 'admin',
            'kpis'        => [
                'totalGuests'      => $totalGuests,
                'pendingContacts'  => $pendingContacts,
                'totalCalls'       => $totalCalls,
                'visited'          => $visited,
                'totalCells'       => $totalCells,
                'totalSubmissions' => $totalSubmissions,
                'totalUsers'       => $totalUsers,
            ],
            'queue'       => [],
            'activeRole'  => $role,
            'activeGroup' => $group,
        ]);
    }
}
