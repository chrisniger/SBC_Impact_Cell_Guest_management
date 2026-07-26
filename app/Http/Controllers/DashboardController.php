<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard controller — Phase 05.
 *
 * Single entry point for `GET /dashboard` (replaces the inline closure
 * that was registered in routes/web.php during Phase 02). The same page
 * component (`Pages/Dashboard.tsx`) reads `props.variant` to switch
 * between the role-specific layouts:
 *
 *   variant = "officer"  →  5 KPIs + top-8 follow-up queue
 *                             (renders in `activeGroup = followUpOfficer`)
 *   variant = "admin"    →  admin "You're logged in!" welcome (default)
 *
 * Team / Cell Leader variants will be added in Phase 06 / Phase 07. The
 * `activeGroup` check is the single source of truth — extend in one
 * place when those phases land.
 *
 * KPI math (all scoped to the officer's assigned guests):
 *   - pendingContacts:  contacted_status is NULL/""/'No'/'Not Contacted'
 *   - totalCalls:       contacted_status is set + non-empty
 *   - visited:          visited = true
 *   - pendingVisit:     contacted_status = 'AvailableForVisit' + visited = false
 *   - responseRate:     visited / totalCalls, percentage to 1dp
 *
 * Queue ordering: NOT CONTACTED first, then available-for-visit, then
 * contacted/yes/available, then everything else; ties broken by most
 * recent created_at.
 */
class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = $user?->activeGroup();

        return match ($group) {
            'followUpOfficer' => $this->officerDashboard($user, $role),
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
     * Default — admin / Supervisor / multi-role-with-out-of-scope-active
     * see Breeze's "You're logged in!" welcome. Active-group branches for
     * the team + cell-leader groups will land in Phase 06 / Phase 07.
     */
    private function adminDashboard(?string $role, ?string $group): Response
    {
        return Inertia::render('Dashboard', [
            'variant'     => 'admin',
            'kpis'        => null,
            'queue'       => [],
            'activeRole'  => $role,
            'activeGroup' => $group,
        ]);
    }
}
