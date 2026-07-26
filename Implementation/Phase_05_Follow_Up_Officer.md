# Phase 05 — Follow Up Officer Group

> Read **[03_Three_User_Groups.md](./03_Three_User_Groups.md)** § "Group 2 — Follow Up Officer" before starting.

## Goal

Polished, professional **Follow Up Officer dashboard** and **Guest management flow** for officers. Officers should be able to:

1. See **all guests assigned to them** (default sort: `NOT CONTACTED → CONTACTED → other`).
2. Open a guest, update `contactedStatus`, `joinWhen`, demographics, schedule a visit, leave comments.
3. See **personal KPIs** (Pending Contacts, Total Calls, Visited, Pending Visit, Conversion Rate).
4. View their `My Submissions` page only if they have a `Impact_Leaders` secondary role (else hidden).

## In scope

1. **Custom sidebar nav** for the Follow Up Officer group.
   - Replaces the Admin sidebar items with: Dashboard, My Guests, Profile.
   - When multi-role, keeps the Admin-only links (Users, etc.) hidden.
2. **Dashboard layout** (already exists at `dashboard.tsx` — modify):
   - **Hero row**: 5 KPI cards (Pending Contacts, Total Calls, Visited, Pending Visit, Response Rate). Each card has trend line (last month vs current).
   - **My queue**: top 8 guests sorted `NOT CONTACTED` first, click-to-open.
   - **Performance**: bar chart for last 8 weeks — Calls Made vs Visited.
3. **My Guests page** (`/guests` filtered to `followOfficerId === req.user.sub`):
   - Compact table (already exists for `Follow_UP`; extend to `FollowUpOfficer`).
   - Quick-edit `contactedStatus` inline (the most frequent action).
   - Search, filter by status, filter by month.
4. **Visitation flow** (existing; verify polish):
   - When `contactedStatus = "Available for Visit"`, the **Visitation fields card** appears below.
   - Required: `visitationStatus` (`Visited` or `Pending`).
   - Optional: `daysAvailable[]`, `feedback`, `visitedAt`, `indicatedToJoin`.
   - A clear visual "Visit scheduled" badge.
5. **Group-aware empty states**:
   - "No guests assigned" + button to copy a self-assign link (Admin can use to assign them, doesn't apply for self).
   - Skeleton loaders (dark + light).

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `src/components/AppLayout.tsx` | modify | add `FOLLOW_UP_OFFICER_NAV` array; integrate into the existing switch. |
| `src/lib/roles.ts` | already updated in Phase 02 | verify |
| `src/routes/_authenticated/dashboard.tsx` | major modify | Officer dashboard layout. |
| `src/routes/_authenticated/guests.tsx` | modify | Officer inline status updates. |
| `src/components/KPICard.tsx` | create | **NEW** — reusable KPI card (used across groups). |
| `src/components/TrendLine.tsx` | create | **NEW** — small Sparkline (Recharts) for KPI trends. |
| `src/components/QueueList.tsx` | create | **NEW** — top 8 follow-up queue. |
| `src/components/VisitationBlock.tsx` | create | **NEW** — flip-in Visitation fields card. |
| `src/components/StatusPill.tsx` | create | **NEW** — shared status pill. |
| `src/lib/api.ts` | modify | add `api.guests.queue()` returning top 8 assigned. |
| `server/controllers/report.controller.js` | modify | add Officer KPI computation. |

## Acceptance criteria

- [ ] Officer logs in, sees sidebar with `Dashboard`, `My Guests`, `Profile` only.
- [ ] The dashboard hero row shows 5 KPI cards with proper counts and a trend line.
- [ ] Below the hero row, a "My queue" lists top 8 assigned guests — `NOT CONTACTED` first.
- [ ] Clicking a guest opens the edit dialog; editing `contactedStatus` succeeds and persists.
- [ ] Setting `contactedStatus = Available for Visit` reveals the Visitation Block inline.
- [ ] Setting it back hides it AND the API confirmed `visitationStatus` is null.
- [ ] Officer cannot see admin links (`Users`, `Settings`, etc.).
- [ ] Officer cannot reach `/guests` for a guest not assigned to them — redirected or shown an empty state.

## Tests

| Test | Expectation |
|---|---|
| Manual: log in as officer → nav | only 3 items |
| API: `GET /api/reports/dashboard` with Follow Up Officer token | numbers restricted to officer's guests |
| UI: open guest assigned to another officer via direct URL | show "Not assigned to you" empty state |
| UI: change contactedStatus on a guest | persists, KPI updates on next refetch |

## Rollback

If KPI math is wrong, it's a single file (`server/controllers/report.controller.js`). Sidebar nav rollback is `AppLayout.tsx`.

---
*Next: [Phase_06_Follow_Up_Team.md](./Phase_06_Follow_Up_Team.md).*
