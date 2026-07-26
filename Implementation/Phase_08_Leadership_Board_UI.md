# Phase 08 — Leadership Board UI (the showpiece)

> Read **[05_Leadership_Board.md](./05_Leadership_Board.md)** and **[06_Dashboard_Design_System.md](./06_Dashboard_Design_System.md)** before starting.

## Goal

Build the **Leadership Board** component that renders on every **primary** Impact Cell's dashboard. Admins see the boards of all primary cells. Impact Leaders see only their own primary cell's board. This is the place the pastor sees the "health" of the cell system.

## In scope

1. **Server endpoint** `GET /api/impact/leadership-board?cellId=<primaryCellId>`:
   - Validates the user can see this primary cell:
     - Admin: any.
     - Impact Leader assigned to a sub-cell under this primary: this primary.
     - `Impact_Cell_Admin` / `Impact_Cell_Report`: any.
   - Returns the shape from [05_Leadership_Board.md § "Rollup endpoint"](./05_Leadership_Board.md).
2. **Caching** via `DashboardCache` (TTL 5 min). Invalidate on write paths:
   - On any `ImpactSubmission` create/delete that touches `impactCellId` belonging to a sub-cell of the primary → invalidate `cellId = primaryCell.id, scope = "leadership-board"`.
   - On any `ImpactCell` create/edit that promotes/demotes a sub-cell → invalidate both old parent's and new parent's caches.
3. **Frontend component** `<LeadershipBoard cellId={id}/>`:
   - **Header**: cell name + KPI rollups (members total, souls total, child namings total, active sub-cell count).
   - **Grid of tiles**: one per sub-cell.
     - Each tile shows: name, leader (fullName + phone), Members count, Souls count, Reports status pill (Submitted/Pending/Overdue/New), Child namings upcoming, last report date.
   - **Sort**: by report-status (Overdue first), then members desc.
   - **Hover/focus** state shows a faint red glow (per dashboard design system).
   - **Click** a tile → opens `<SubCellPanel>` slide-in from right.
4. **`<SubCellPanel>`** side panel:
   - Sub-cell name and leader.
   - Last 4 weeks of Members data (numerical rollup: attendance counts).
   - Last 4 weeks of Reports (key fields).
   - Next 9 days of child namings.
   - Recent souls.
   - "Open full report →" button that navigates to My Reports for that sub-cell.
5. **Dashboard wiring**:
   - When the active login is for an `Impact_Leaders` user whose `impactCellId` is a primary, the dashboard renders the Leadership Board as the top section.
   - When a user is `Impact_Cell_Admin`, the dashboard shows **all** primary cells' boards stacked, each collapsible.
   - When a user logs in as a non-primary cell's leader, no board — straight to their KPI + form shortcuts.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/controllers/leadershipBoard.controller.js` | create | **NEW** — board endpoint + cache logic. |
| `server/routes/impact.routes.js` | modify | wire `/impact/leadership-board`. |
| `src/components/LeadershipBoard.tsx` | create | **NEW** — header + grid. |
| `src/components/SubCellTile.tsx` | create | **NEW** — single sub-cell card. |
| `src/components/SubCellPanel.tsx` | create | **NEW** — slide-in side panel. |
| `src/components/ReportStatusPill.tsx` | create | **NEW** — Submitted/Pending/Overdue/New pill. |
| `src/components/StackedBoardsList.tsx` | create | **NEW** — used when an admin has multiple primaries. |
| `src/lib/api.ts` | modify | `api.impact.leadershipBoard(cellId)`. |
| `src/routes/_authenticated/dashboard.tsx` | modify | render Leadership Board at the top for applicable roles. |
| `server/lib/audit.js` | modify | add helper `invalidateLeadershipBoard(cellId)` (called from controllers). |
| `tests/leadershipBoard.controller.test.ts` | create | **NEW** — unit tests for the rollup math. |

## Acceptance criteria

- [ ] Admin splits `APO` into 4 sub-cells. After visiting primary `APO`'s dashboard, the board shows 4 tiles.
- [ ] Each tile counts Members / Souls / Child Namings correctly (summed from `ImpactSubmission`).
- [ ] Tile shows the right Report status pill: ✅ Submitted this week, ⚠ Pending, 🔴 Overdue, ⚪ New.
- [ ] Clicking a tile opens the SubCellPanel with last 4 weeks of data.
- [ ] Submitting a new weekly report for any sub-cell invalidates the parent's cache; the next fetch returns updated data.
- [ ] A non-primary cell leader's dashboard does NOT show the board.

## Tests

| Test | Expectation |
|---|---|
| Unit: rollup math | sum of `member` submissions for sub-cells equals `totalMembers` |
| Unit: report status | no submissions in 7+ days → "Overdue" |
| API: `GET /api/impact/leadership-board?cellId=<X>` for a sub-cell id | 403 (must be a primary) |
| UI: open sub-cell panel | scrolls inside the panel; click outside closes it |

## Performance / caching

- TTL: 5 min.
- Invalidation: on every `ImpactSubmission` write, walk up to the primary and clear cache.
- Cache stored as JSON in `DashboardCache.payload`.

## Out of scope

- Drag-to-reorder tiles (stretch).
- Multi-week trend chart per tile (stretch).

---
*Next: [Phase_09_Notifications_SMTP.md](./Phase_09_Notifications_SMTP.md).*
