# Phase 06 — Follow Up Team Group

> Read **[03_Three_User_Groups.md](./03_Three_User_Groups.md)** § "Group 3 — Follow Up Teams" before starting.

## Goal

Empower the **team-level workflow**. Members of the Follow-Up Team coordinate across multiple officers; they track progress on the team queue, log detailed contact attempts (up to 3 sections), and surface guests that have stalled.

## In scope

1. **Team dashboard** (`/dashboard` for the Follow Up Team group):
   - KPI row: **Pending Contacts**, **Contacted Today**, **Wrong Number**, **Not Reachable**.
   - **Team Queue** data table with:
     - Columns: Guest, Phone, Officer, **Follow Up Status** (inline editable), Latest Contact, Last Updated.
     - Inline status dropdown — saves without leaving the page.
     - Sort: `NOT CONTACTED → CONTACTED → WRONG NUMBER / NOT REACHABLE`.
2. **Contact Sections** within the Guest edit dialog (`/guests/:id`):
   - The Follow Up Team group can **add up to 3 contact sections**:
     - `{ date: YYYY-MM-DD, contact: "1st Contact" | "2nd Contact" | "3rd Contact", comments: string }`
   - Other groups see them read-only.
   - A `<ContactsTimeline>` component visualises the 3 sections as a vertical timeline.
3. **Status quick-set**:
   - Floating action button (or row button) "Mark as CONTACTED" — single click.
4. **View-only restrictions**:
   - `Follow_UP_View_Only` sees the same dashboard but every control is disabled and shows a "View-only mode" banner.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `src/components/AppLayout.tsx` | modify | add `FOLLOW_UP_TEAM_NAV`: Dashboard, Team Queue, Profile. |
| `src/routes/_authenticated/dashboard.tsx` | major modify | Team dashboard layout. |
| `src/routes/_authenticated/guests.tsx` | modify | inline status dropdown + read-only banner for `Follow_UP_View_Only`. |
| `src/components/ContactsTimeline.tsx` | create | **NEW** — vertical timeline of contacts. |
| `src/components/InlineStatusSelect.tsx` | create | **NEW** — select with optimistic update via TanStack Query. |
| `src/components/ViewOnlyBanner.tsx` | create | **NEW** — small banner across read-only screens. |
| `server/controllers/report.controller.js` | modify | add Team-level KPIs (`pendingContacts`, `contactedToday`, etc.). |
| `server/controllers/guest.controller.js` | modify | in `update`, only accept changes to `followUpStatus` and `followUpContacts[]` from this group; the existing `sanitize()` handles it once Phase 04 is in. |
| `tests/guest.controller.test.ts` | extend | add test: `Follow_UP` cannot change `phone`. |

## Acceptance criteria

- [ ] A `Follow_UP` user logs in; sees **Team Queue** only.
- [ ] Inside Team Queue, the **Follow Up Status** column has an inline dropdown.
- [ ] Changing the dropdown **persists** without leaving the page (optimistic UI).
- [ ] Opening a guest, the **Contact Sections** builder allows adding up to 3 sections dated today/last week.
- [ ] Saving updates the audit log entry.
- [ ] `Follow_UP_View_Only` opens the same screen; banner shows "View-only mode", every select is disabled.
- [ ] The Team Queue list sorts `NOT CONTACTED → CONTACTED → others`.

## Tests

| Test | Expectation |
|---|---|
| Manual: Follow_UP user opens Team Queue | inline status works |
| API: `PUT /api/guests/<id>` with `Follow_UP` JWT — only `followUpStatus` in body | succeeds (other fields silently dropped by access policy) |
| API: same, but body includes `phone: "..."` | sanitiser ignores, no DB change |
| UI: in View-Only, try to click dropdown | blocked, tooltip "View-only mode" |

## Rollback

The sanitiser in `guest.controller.js` is the line of defence; if a regression allows writes, dial back `server/lib/access.js`.

---
*Next: [Phase_07_Impact_Cell_Leader.md](./Phase_07_Impact_Cell_Leader.md).*
