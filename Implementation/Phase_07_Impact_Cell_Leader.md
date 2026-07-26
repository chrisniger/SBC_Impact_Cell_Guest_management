# Phase 07 — Impact Cell Leader Group

> Read **[03_Three_User_Groups.md](./03_Three_User_Groups.md)** § "Group 1 — Impact Cell" and **[05_Leadership_Board.md](./05_Leadership_Board.md)** before starting.

## Goal

Empower an **Impact Cell Leader** with their own forms and dashboard:

- Submit weekly reports (Members Data, Submit Report, Childbirth Notice, Souls Registration).
- Search for souls across the system.
- View their own submissions (`My Reports`).
- See the sub-cells that fall under a primary cell (leadership view — the Leadership Board itself comes in Phase 08).

## In scope

1. **Sidebar nav** (already in AppLayout via `IMPACT_LEADER_NAV`):
   - Dashboard (Leader view)
   - Members Data
   - Submit Report
   - Childbirth Notice
   - Souls Registration
   - Soul Search
   - My Reports
2. **Leader dashboard** layout:
   - KPI row: Pending Follow-Up (assigned guests), Total Members, Total Souls, Childbirths.
   - Below: list of **assigned guests** with `impactStatus` editable inline.
   - "My Reports" tab: the leader's submissions across types.
3. **Members Data form** (`section=member`):
   - Long form (41 fields, defined in the XML template shipped with the app).
   - Submit → `POST /api/impact/submissions { type: "member", data, impactCellId }`.
   - Validation: required fields per existing XML schema; date keys normalised to `YYYY-MM-DD`.
4. **Submit Report form** (`section=report`):
   - Fellowship Date (required) → converts to `YYYY-MM-DD` for `fellowshipDateKey`.
   - Attendance fields (adults, children, first-timers, new-members).
   - Offering fields (HQ + centre amounts).
   - **Duplicate prevention**: backend returns 409 if a report already exists for the same `impactCellId` + `fellowshipDateKey`. Show inline error.
5. **Childbirth Notice form** (`section=childbirth`):
   - Child name, parent name, date of birth, gender, impact cell, phone.
6. **Souls Registration form** (`section=soul`):
   - Full name, phone, gender, occupation, marital status, prayer request, etc.
7. **Soul Search** (`section=search`):
   - Free-text search across `name`, `phone`, `email`, `centre`.
   - Result table sorted by recency.
8. **My Reports** (`section=reports`):
   - Table showing the leader's `ImpactSubmission` rows: type, submittedAt, impact cell, preview.
   - Click a row → side panel preview.
9. **`impactStatus`** inline editor on assigned guests:
   - Visible only to Impact Cell group.
   - Editable pill with options: `Contacted`, `Not Contacted`, `Not Reachable`.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `src/components/AppLayout.tsx` | already updated in Phase 02 | verify nav items |
| `src/routes/_authenticated/dashboard.tsx` | major modify | Leader dashboard layout, forms, search, my reports. |
| `src/components/MembersDataForm.tsx` | create | **NEW** — Members Data form. |
| `src/components/SubmitReportForm.tsx` | create | **NEW** — Weekly report form. |
| `src/components/ChildbirthNoticeForm.tsx` | create | **NEW** — Childbirth form. |
| `src/components/SoulsRegistrationForm.tsx` | create | **NEW** — Souls registration form. |
| `src/components/SoulSearch.tsx` | create | **NEW** — search input + results table. |
| `src/components/MyReports.tsx` | create | **NEW** — submissions list. |
| `src/components/InlineImpactStatusPill.tsx` | create | **NEW** — inline `impactStatus` editor (shared between dashboard and guest list). |
| `server/controllers/impact.controller.js` | modify | verify `createSubmission` duplicate prevention on `report`. |
| `src/lib/api.ts` | modify | add Leader-specific helpers (already mostly covered). |

## Acceptance criteria

- [ ] Impact Leader logs in; the sidebar shows the 7 nav items above.
- [ ] Submitting a Member record persists; appears in **My Reports**.
- [ ] Submitting a **second** weekly report for the same date returns inline 409 error and shows a toast.
- [ ] Submitting a Childbirth Notice persists.
- [ ] Submitting a Soul registration persists.
- [ ] Soul Search returns hits for name/phone/email/centre.
- [ ] Assigned-guest pill: changing `impactStatus` persists + updates the leader's KPI counts after refetch.
- [ ] A `FollowUpOfficer` cannot see the Leader sidebar items (Phase 05 handles).

## Tests

| Test | Expectation |
|---|---|
| Manual: Submit Report twice for same date | second returns 409 in toast |
| Manual: Inline `impactStatus` edit | persists, KPI updates |
| Manual: Member form — missing required field | validation error, form blocks submit |
| Manual: Soul Search — searching empty string | empty results state |
| API: `POST /api/impact/submissions` with token from Follow Up Officer role | 403 |

## Out of scope

- The Leadership Board itself (Phase 08).

---
*Next: [Phase_08_Leadership_Board_UI.md](./Phase_08_Leadership_Board_UI.md).*
