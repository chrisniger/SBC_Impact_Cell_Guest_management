# Phase 04 — Guest Records (Core CRUD + Column-Level Access)

> Read **[02_Database_Schema.md](./02_Database_Schema.md)**, **[03_Three_User_Groups.md](./03_Three_User_Groups.md)**, and **[01_Architecture.md](./01_Architecture.md)** before starting.

## Goal

The single most important phase. After Phase 04, the **Guest** entity is fully operational:

- Each of the 3 user groups can list guests scoped correctly.
- Each of the 3 user groups can edit only the columns they own.
- The 4 user-flow edges (search, filter, sort, audit-when-edited) all work.
- Existing flows (contact status → visitation, follow-up status, etc.) keep working with the new column-level rules.

## In scope

1. **Server `server/lib/access.js`** (NEW) — column-level permission helpers (already designed in Phase 02 conceptually; full impl here).
2. **Server `server/controllers/guest.controller.js`** — extend `sanitize(body)` to:
   1. Run `stripDisallowed(req.user.role, body)` FIRST.
   2. Run the existing validations (`visitationStatus`, `followUpStatus`, `impactStatus`, `followUpContacts`, `event`).
   3. Cross-cutting rule: if `contactedStatus` is being set to anything other than `Available for Visit`, **null out** `visitationStatus` and `feedback` server-side (even if the client forgot).
3. **Server endpoints** (existing, may need adjusting):
   - `GET /api/guests` — for `FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`, scope to where `followOfficerId = req.user.sub`. (Already true today — verify.)
   - `POST /api/guests` — `Administrator`, `FollowUpOfficer` only. Admin can assign to anyone in `ASSIGNABLE_FOLLOW_UP_ROLES`. Officer can self-assign by passing their own `followOfficerId`.
   - `PUT /api/guests/:id` — same role list, but apply `canEdit(req.user, guest)`. **Strip columns** via `stripDisallowed`.
   - `DELETE /api/guests/:id` — Admin only.
   - `POST /api/guests/:id/reassign` — Admin + `Follow_UP_Admin`. Notify on Impact Leader assignment (existing).
4. **Frontend `src/lib/access.ts`** (NEW) — mirror of server policy. Used to **render** the Add/Edit Guest dialog differently per role.
5. **Frontend Add/Edit dialogs** (`src/routes/_authenticated/guests.tsx`):
   - Use `<Field name="impactStatus">…</Field>` with helper that reads `canEdit(role, "impactStatus")` and `canView(role, "impactStatus")` to either render an editable `<select>`, a disabled `<select>`, or hide altogether.
   - Same pattern for every other column (see matrix in [03_Three_User_Groups.md](./03_Three_User_Groups.md)).
6. **Frontend guests list** column-level visibility:
   - **Compact view** for the Follow Up Team group (mobile-friendly, already exists, enhance).
   - Default sort for Follow Up Officer + Follow Up Team: `NOT CONTACTED` first, then `CONTACTED`, then others (already exists).
   - Filter by `impactStatus` is hidden for non-Impact Cell groups.
7. **Audit**: every successful write calls `server/lib/audit.js`:
   ```js
   auditGuestChange(req, before, after, action)
   ```
   Persists in `AuditLog` with `entity: "guest"`, `entityId: <id>`, `detail: "Changed contactedStatus: No → AvailableForVisit"`. Even on creation.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/lib/access.js` | create | **NEW** — column policy. |
| `server/lib/audit.js` | create | **NEW** — `auditGuestChange(req, before, after)`. |
| `server/controllers/guest.controller.js` | major modify | `sanitize()`, `create`, `update`, `reassign` all hit access + audit. |
| `src/lib/access.ts` | create | **NEW** — mirrored policy. |
| `src/lib/types.ts` | modify | add column flags if needed. |
| `src/lib/api.ts` | modify | `guests.create / update` already use access layer (light refactor). |
| `src/routes/_authenticated/guests.tsx` | major modify | column-aware Add/Edit dialogs. |
| `src/routes/_authenticated/guests.$id.tsx` | major modify | already rewritten off mockApi; verify row-level scoping. |
| `tests/access.test.ts` | create | **NEW** — unit tests for `stripDisallowed`. |
| `tests/guest.controller.test.ts` | create | **NEW** — integraton: a user cannot edit another group's field. |

## Acceptance criteria

- [ ] Admin creates a guest assigned to a `FollowUpOfficer`. The officer logs in, sees the guest, **can** edit `phone`, `address`, `contactedStatus`, `daysAvailable`. **Cannot** edit `followUpStatus`.
- [ ] A `FollowUpOfficer` cannot see an "Edit" button next to `impactStatus`.
- [ ] A `Follow_UP` cannot edit `phone`, `address`, `contactedStatus`. Can edit `followUpStatus` and the 3 `followUpContacts` sections.
- [ ] An `Impact_Leaders` user — assigned that guest — sees `impactStatus` as editable. All other group-owned fields are read-only.
- [ ] Setting `contactedStatus` away from `Available for Visit` **clears** `visitationStatus` and `feedback` (verified via API response).
- [ ] Audit log shows the change with `actor.fullName`, `action = "GUEST_UPDATED"`, `entityId = <guest id>`.
- [ ] Reassign to an Impact Leader sends an email (when SMTP configured; otherwise the warn message in the logs is acceptable).

## Tests

| Test | Expectation |
|---|---|
| Unit: `stripDisallowed('Follow_UP', body)` | removes every field not in the team's owned list |
| Unit: `stripDisallowed('Administrator', …)` | leaves the body intact |
| Integration: `PUT /api/guests/<x>` by a `Follow_UP` user attempting to set `phone` | 403 or sanitized response (no change applied) |
| Integration: `POST /api/guests` by a `Follow_UP` user | 403 (only Admin + `FollowUpOfficer` may create) |
| UI: open `/guests/<id>` as a `Follow_UP` user | the phone/address inputs are disabled with a tooltip "managed by Follow Up Officer" |

## Out of scope

- Dashboard aggregations (Phase 06/07).
- CSV import (Phase 10).
- Visitation flow UI polish (already done in v1).

## Rollback

If the column-level strip accidentally blocks legitimate updates, the rules live in **one file** (`server/lib/access.js` and `src/lib/access.ts`). Backing out is a revert of those two files plus `sanitize()` reverts in `guest.controller.js`.

---
*Next: [Phase_05_Follow_Up_Officer.md](./Phase_05_Follow_Up_Officer.md).*
