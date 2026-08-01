<?php

namespace App\Http\Controllers;

use App\Models\ImpactCell;
use App\Models\ImpactSubmission;
use App\Support\RoleHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 08 — Leadership Board JSON + Inertia surface.
 *
 * Two endpoints:
 *   - GET /leadership-board/{cellId}  → show()    → JSON for one primary cell
 *     Used by the LeaderDashboard inline component on the dashboard.
 *   - GET /leadership                → index()   → Inertia page (stacked multi-board view)
 *     Used by /leadership nav link (admin/Impact_Cell_Admin/Zonal/Impact_Leaders).
 *
 * Role gate (single source of truth via RoleHelper):
 *   - Administrator                       → any primary
 *   - impactCell group (Impact_Leaders / Impact_Cell_Admin /
 *                       Impact_Cell_Report / Impact_Zonal_Coordinator) → see Phase 07 § 5 spec
 *   - Everyone else                       → 403
 *
 * Impact_Leaders get a column-level gate on /leadership-board/{cellId}:
 *   must have ≥1 submission targeting a sub-cell under the requested primary.
 *
 * Index() also filters the PRIMARY LIST for Impact_Leaders: only the
 * primaries they actually have submissions under (prevents data-leak
 * of all 65 cell admin primaries).
 *
 * Deferred to a later phase:
 *   - 5-min DashboardCache (no DashboardCache table exists yet).
 *   - fromCache flag is wired but always false today.
 *   - leaderFullName per tile (we have no users.impact_cell_id column yet).
 */
class LeadershipBoardController extends Controller
{
    /**
     * GET /leadership-board/{cellId}  → JSON.
     * Used by `LeaderDashboard` for the impactCell-group leader.
     */
    public function show(Request $request, string $cellId): JsonResponse
    {
        // ── FAIL-FAST ROLE GATE (defense-in-depth — same pattern as Phase 07 column-level guard) ──
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        // Administrator (cross-group) OR any impactCell-group role.
        if ($role !== 'Administrator' && $group !== RoleHelper::GROUP_KEY_IMPACT_CELL) {
            abort(403, 'You do not have access to this leadership board.');
        }

        // Phase 13b — every cell is a primary; the eager `subCells` load is
        // dead weight (would always return []). The flat-cell lookup below
        // uses `collect([$cell])` as a single-tile synthetic $subCells.
        $cell = ImpactCell::findOrFail($cellId);

        if (! $cell->is_primary) {
            abort(422, 'Leadership board requires a primary cell.');
        }

        // ── COLUMN-LEVEL GATE for Impact_Leaders ──
        // Phase 13b flipped the world to flat-cell: every cell is a primary,
        // parent_cell_id is NULL across the board. The pre-13b gate checked
        // "must have submissions targeting a sub-cell of this primary" — that
        // branch can never be satisfied today (subCells() is always empty),
        // and Impact_Leaders were being 403'd off *every* primary they own.
        //
        // Flat-cell semantically equivalent: an Impact Leader is "assigned to"
        // a primary if EITHER they are explicitly assigned via users.impact_cell_id
        // OR they have any submission that targets the primary directly.
        if ($role === 'Impact_Leaders') {
            $isAssigned    = ($user->impact_cell_id ?? null) === $cellId;
            $hasSubmission = ImpactSubmission::where('user_id', $user->id)
                ->where('impact_cell_id', $cellId)
                ->exists();

            if (! $isAssigned && ! $hasSubmission) {
                abort(403, 'You are not assigned to this primary.');
            }
        }

        // Phase 13b — flat-cell world. The primary IS its own (and only) tile;
        // pass it as a single-element collection so buildBoardData() computes
        // members / souls / childbirths / report-status against direct
        // submissions to this primary (was: against sub-cells).
        return response()->json($this->buildBoardData($cell, collect([$cell])));
    }

    /**
     * GET /leadership  → Inertia page.
     * Stacked multi-board view (admin/Zonal: all primaries, Impact_Leaders: only theirs).
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user?->activeRole();
        $group = RoleHelper::groupOf($role);

        if ($role !== 'Administrator' && $group !== RoleHelper::GROUP_KEY_IMPACT_CELL) {
            abort(403, 'You do not have access to leadership boards.');
        }

        // IMPACT_LEADERS DATA-LEAK FIX (Phase 13b flat-cell variant):
        // Filter the primary list to ONLY the primaries the leader is
        // legitimately bound to. In the flat-cell world this union is:
        //   (a) the cell assigned to the leader via users.impact_cell_id
        //   (b) every primary they have any submission against directly.
        // (Pre-13b this also walked up `parent_cell_id` to admit sub-cell
        //  assignments; that join is dead code after Phase 13b's flatten
        //  migration which nulls every parent's parent_cell_id.)
        if ($role === 'Impact_Leaders') {
            $userSubmissionPrimaryIds = ImpactSubmission::where('user_id', $user->id)
                ->whereNotNull('impact_cell_id')
                ->pluck('impact_cell_id')
                ->all();

            $merged = $userSubmissionPrimaryIds;
            if (! empty($user->impact_cell_id)) {
                $merged[] = $user->impact_cell_id;
            }

            $userPrimaryIds = array_values(array_unique(array_filter($merged)));

            $primaries = $userPrimaryIds === []
                ? collect()
                : ImpactCell::primary()->whereIn('id', $userPrimaryIds)->ordered()->get();
        } else {
            // Administrator / Impact_Cell_Admin / Impact_Cell_Report / Impact_Zonal_Coordinator → all primaries
            $primaries = ImpactCell::primary()->ordered()->get();
        }

        // PRE-COMPUTE per-primary board data so the Inertia page can hand it
        // to `<LeadershipBoard cellId=… initialData={…} />` directly, skipping
        // the per-board fetch the inline component would otherwise fire
        // (would be 65+ concurrent AJAX calls for an admin — N+1 trap).
        // NOTE: this triggers N+1 SELECT queries (~3 per primary); acceptable
        // without caching today, will collapse to 1 query once 5-min
        // DashboardCache lands in a later phase.
        $boards = $primaries->map(function (ImpactCell $primary) {
            // Phase 13b — see show(): the primary is its own tile in the
            // flat-cell world. Passing `[primary]` here keeps the per-tile
            // stat math (members / souls / childbirths / report status)
            // computing against direct submissions to this primary.
            return [
                'cellId' => $primary->id,
                'board'  => $this->buildBoardData($primary, collect([$primary])),
            ];
        })->values()->all();

        return Inertia::render('Leadership/Index', [
            'boards'      => $boards,
            'activeRole'  => $role,
            'activeGroup' => $group,
        ]);
    }

    /**
     * Shared computation used by both show() and index().
     *
     * Tile shape (per Phase 08 spec + Phase 08 § LeadershipBoardController.md):
     *   { id, name, phone, membersCount, soulsCount, childbirthsCount,
     *     reportStatus, lastReportDate, leaderFullName, leaderPhone }
     *
     * Envelope:
     *   { primaryCell, tiles, totals, generatedAt, fromCache, cacheKey }
     *
     * fromCache is wired but always false today — caching layer lands in a
     * later phase (no DashboardCache table yet).
     */
    public function buildBoardData(ImpactCell $primary, $subCells): array
    {
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

            // Report status logic per Implementation/05_Leadership_Board.md
            //   Submitted:  ≤ 7 days
            //   Pending:    8-14 days
            //   Overdue:    > 14 days
            //   New:        no submissions ever
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
                'id'               => $sub->id,
                'name'             => $sub->name,
                'phone'            => $sub->phone,
                'membersCount'     => (int) $members,
                'soulsCount'       => (int) $souls,
                'childbirthsCount' => (int) $childbirths,
                'reportStatus'     => $reportStatus,
                'lastReportDate'   => $lastReportDate?->toIso8601String(),
                // Phase 08 additions — leaderFullName deferred until users.impact_cell_id ships
                'leaderFullName'   => null,
                'leaderPhone'      => $sub->phone,
            ];
        })->values();

        return [
            'primaryCell' => [
                'id'   => $primary->id,
                'name' => $primary->name,
            ],
            'tiles'       => $tiles,
            'totals'      => [
                'members'     => $tiles->sum('membersCount'),
                'souls'       => $tiles->sum('soulsCount'),
                'childbirths' => $tiles->sum('childbirthsCount'),
                'subCells'    => $subCells->count(),
            ],
            'generatedAt' => now()->toIso8601String(),
            'fromCache'   => false,
            'cacheKey'    => "leadership-board.{$primary->id}",
        ];
    }
}
