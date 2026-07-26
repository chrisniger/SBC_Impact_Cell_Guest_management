# Phase 11 — Reports & Audit Log

> Read **[06_Database_Schema.md](./02_Database_Schema.md)** (AuditLog) and existing `server/controllers/report.controller.js`.

## Goal

A polished **Reports** screen (Admin, Supervisor, Read-only reporter roles) and a polished **Audit Log** page.

## In scope

1. **Reports screen** at `/reports`:
   - **KPI cards**: Pending Contacts, Total Calls, Visited, Pending Visit, Response Rate.
   - **Charts**:
     - **By Status** (BarChart) — counts by `contactedStatus`.
     - **By Join When** (Donut) — `FirstTimer / NewMember / OldMember`.
     - **By Follow Up Status** (BarChart) — `NOT CONTACTED / CONTACTED / WRONG NUMBER / NOT REACHABLE`.
     - **By Event** (BarChart) — `COMBINED SERVICE / CHURCH 1 / CHURCH 2 / OTHER`.
     - **Monthly Trend** (AreaChart) — guests per month.
   - **Officer Performance** table — top 10 officers by guest count + conversion rate.
   - Filters: `month` (YYYY-MM), `event`, `status`.
2. **Audit Log page** at `/audit`:
   - List last 500 entries.
   - Filters: actor, action type, entity (`guest`, `user`, …), date range.
   - Click an entry → side panel showing the full before/after diff (when applicable).
3. **Server-side**:
   - Add audit filter endpoints (`GET /api/reports/audit?actor=X&entity=guest&entityId=Y`).
   - Group-by + aggregations already exist in `report.controller.js`; add **monthly** aggregation.
4. **Polish** — beautiful KPI cards using the design system from [06_Dashboard_Design_System.md](./06_Dashboard_Design_System.md).

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/controllers/report.controller.js` | modify | add filter support and monthly aggregation. |
| `src/routes/_authenticated/reports.tsx` | create | **NEW** — full Charts page. |
| `src/routes/_authenticated/audit.tsx` | major modify | filters, side panel diff. |
| `src/components/Charts/StatusBarChart.tsx` | create | **NEW** — Recharts polish. |
| `src/components/Charts/JoinDonut.tsx` | create | **NEW**. |
| `src/components/Charts/MonthlyAreaChart.tsx` | create | **NEW**. |
| `src/components/Charts/EventBarChart.tsx` | create | **NEW**. |
| `src/components/Charts/FollowUpStatusBar.tsx` | create | **NEW**. |
| `src/components/AuditDiffPanel.tsx` | create | **NEW** — before/after viewer. |
| `src/lib/api.ts` | modify | `api.reports.audit(filters)` + `api.reports.dashboard(filters)` accepting month. |
| `tests/report.controller.test.ts` | create | **NEW** — aggregations. |

## Acceptance criteria

- [ ] Reports page renders 5 charts and the officer performance table.
- [ ] Filters by month change chart data.
- [ ] Audit Log page loads, filters, finds an entry, opens the diff panel.
- [ ] Audit Log shows a guest assignment with before/after JSON.

## Tests

| Test | Expectation |
|---|---|
| Unit: `report.dashboard(month='2026-07')` | returns counts scoped to that month |
| Unit: `audit.list({ entity: 'guest', entityId: 'X' })` | returns recent guest events |

---
*Next: [Phase_12_Deployment.md](./Phase_12_Deployment.md).*
