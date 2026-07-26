# 05 — Leadership Board (the showpiece dashboard)

## What it is

The **Leadership Board** is the centrepiece of the Impact Cell group's dashboard. For each **primary** Impact Cell that has sub-cells, the board renders a grid showing every sub-cell side by side, with quick KPIs and a drill-down.

If a user logs into the dashboard for a cell that is **not** a primary cell (i.e., a leaf), they see only their own submissions — they don't see a board.

If a user logs in as an Admin / `Impact_Cell_Admin`, the dashboard shows **all** primary cells' boards (a multi-board view).

## Goal

The pastor / leadership should be able to glance at the dashboard and see:

- Which sub-cells are thriving.
- Which sub-cells missed their weekly report this week.
- Which sub-cells have the most member growth.
- Which sub-cells need prayer coverage or visitation support.

In one screen.

## Layout

```
┌──────────────────────────────────────────────────────────────────┐
│  HEADER  GAMES VILLAGE PRIMARY  ·  6 sub-cells  ·  1 overdue     │
│  ─────────────────────────────────────────────────────────────── │
│                                                                  │
│  KPI ROW (rollups across the whole primary cell):                │
│   ┌────────────┐ ┌────────────┐ ┌────────────┐ ┌────────────┐   │
│   │  Total     │ │ Total Souls │ │ Child     │ │ Active     │   │
│   │ Members 42 │ │  18         │ │ Namings 3 │ │ Sub-cells 6│   │
│   └────────────┘ └────────────┘ └────────────┘ └────────────┘   │
│                                                                  │
│  BOARD GRID  (1 column on mobile, 2 on tablet, 3 on desktop)     │
│  ┌──────────────────┐  ┌──────────────────┐  ┌─────────────────┐│
│  │  GAMES VILLAGE 1 │  │  GAMES VILLAGE 2 │  │ GAMES VILLAGE 3 ││
│  │  Leader: Tunde O.│  │  Leader: Mary A. │  │ Leader: —       ││
│  │  ──── KPIs ──── │  │  ...             │  │ ...             ││
│  │  Members: 12   │  │  Members: 9      │  │ Members: 5      ││
│  │  Souls: 5      │  │  Souls: 2        │  │  ...            ││
│  │  Reports: ✓    │  │  Reports: ⚠ overdue│  │ Reports: ✓     ││
│  │  Children: 2   │  │  Children: 0     │  │  ...            ││
│  │  [Open ↗]      │  │  [Open ↗]        │  │  [Open ↗]       ││
│  └──────────────────┘  └──────────────────┘  └─────────────────┘│
└──────────────────────────────────────────────────────────────────┘
```

Component name: `<LeadershipBoard cell={primaryCell}/>`. Implemented in `src/routes/_authenticated/dashboard.tsx`.

## Per sub-cell KPI card (the "tile")

```
┌──────────────────────────────┐
│  GAMES VILLAGE 1             │
│  ────────────────────────    │
│  👤 12 members               │
│  🙏 5 souls                  │  <- souls = new converts registered this month
│  📒 Report  ✓ submitted      │  <- green if submitted, amber if pending
│     for this Sunday          │     red if overdue (≥7 days no report)
│  👶 2 child namings (upcoming)│
│  ────────────────────────    │
│  Leader: Tunde Ogunleye      │
│  Phone: 0803...              │
│  [ View sub-cell ↗ ]         │
└──────────────────────────────┘
```

Computed from `ImpactSubmission` rows for that sub-cell.

## Status pill logic for "Report"

For a given sub-cell and the current week (Thursday → Sunday), find `impactSubmission.type = "report"` and matching `fellowshipDateKey` (or null) within the last 7 days.

| Condition | Pill |
|-----------|------|
| Submitted this week | ✅ green "Submitted" |
| No submission this week | ⚠ amber "Pending" |
| No submission in 7+ days | 🔴 red "Overdue" |
| No submissions ever + active | ⚪ grey "New" |

## Rollup endpoint

A computed endpoint powers the board (5-minute cached):

```
GET /api/impact/leadership-board?cellId=<primaryCellId>

→ 200 {
  primary: { id, name },
  kpis:   { totalMembers, totalSouls, childNamings, activeSubCells },
  subCells: [
    {
      id, name, leaderFullName, leaderPhone, order,
      members:      number,
      souls:        number,
      childNamings: number,
      reports:      number,
      reportStatus: "submitted" | "pending" | "overdue" | "new",
      lastReportAt: ISO | null,
    }
  ],
  generatedAt: ISO,
  fromCache:   boolean
}
```

The endpoint uses the `DashboardCache` table. On writes to any `ImpactSubmission` for a sub-cell, the relevant cache entries are invalidated.

## Caching strategy

- TTL: 5 minutes.
- Cache key: `cellId + scope = "leadership-board"`.
- On any write that touches `ImpactSubmission` with `impactCellId ∈ subCells(primary)`, invalidate the parent's cache.

## Drill-down

Clicking a sub-cell tile opens a **side panel** (slide-in from the right) with the last 4 weeks of:

- Members (last entries with names + first-timers + new members counts).
- Souls (last entries with name + phone).
- Reports (last 4 weeks — date, attendance, offerings).
- Child namings (next 9 days).

The panel is the raw data; navigation to the full "My Reports" view is one click away.

## Multi-board view (for Admins)

Admins see the same component, but stacked in a list — one block per primary cell. Each block is collapsible. Default sort: by overdue count desc, then name asc.

---

## Acceptance criteria

- [ ] Admin splits a primary cell into 3 sub-cells, opens the primary's dashboard, sees the board with 3 tiles.
- [ ] Each tile shows the correct counts and the right report-status pill.
- [ ] Clicking a tile opens the side panel with real data.
- [ ] Submitting a weekly report on a sub-cell flips that tile's pill from "Overdue" to "Submitted" within the next refresh.
- [ ] The endpoint returns `fromCache: true` within 5 minutes of the last call.

---
*Next: [06_Dashboard_Design_System.md](./06_Dashboard_Design_System.md).*
