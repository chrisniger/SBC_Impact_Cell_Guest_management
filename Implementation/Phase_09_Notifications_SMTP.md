# Phase 09 — Notifications & SMTP

> Read **[06_Database_Schema.md](./02_Database_Schema.md)** (notification tables) and the existing `server/lib/notifications.js` before starting. Most of this phase is already in v1 — verify v2 conformance.

## Goal

Email notifications when an **Impact Cell Leader is assigned a guest** (existing trigger). Add a second trigger: **Weekly report submitted** (notify the `Impact_Cell_Admin` mailing list of report activity).

## In scope

1. **SMTP singleton settings** (already in v1; refactor only if needed). `server/lib/mailer.js` is the source.
2. **Notification rule CRUD** (Admin only; already in v1). Add new action: `WEEKLY_REPORT_SUBMITTED`.
3. **Trigger**: after `POST /api/impact/submissions` for `type="report"`, call `notify('WEEKLY_REPORT_SUBMITTED', { …payload })`.
4. **Trigger**: existing `GUEST_ASSIGNED_TO_IMPACT_LEADER` keeps working when an Admin or `Follow_UP_Admin` reassigns to an Impact Leader.
5. **Settings page** (`/settings`, Admin only):
   - **Already polished** in v1. Update to show per-trigger coverage.
6. **Notifications page** (`/notifications`, Admin only):
   - Add the new rule action to the dropdown.
7. **Test email**: Admin sends to a chosen recipient; on success, toast `Sent. Check inbox.`.
8. **Frontend `<bell>`**: keep the hardcoded `3` for now. The "real" notification count is captured as a stretch goal for v3.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/lib/notifications.js` | modify | add `WEEKLY_REPORT_SUBMITTED` action. |
| `server/lib/mailer.js` | already exists; verify | ensure `auth` field is included. |
| `server/controllers/notification.controller.js` | modify | no behavioural change; verify actions list. |
| `server/controllers/impact.controller.js` | modify | call `notify('WEEKLY_REPORT_SUBMITTED', …)` after a `report` submission. |
| `src/routes/_authenticated/settings.tsx` | modify | show a small "Configured: ✅" badge if all SMTP fields present. |
| `src/routes/_authenticated/notifications.tsx` | modify | include the new action in the dropdown. |
| `tests/notifications.test.ts` | create | **NEW** — mocks `sendMail`, asserts the new action is dispatched. |

## Acceptance criteria

- [ ] Admin saves SMTP settings, the green "Configured" badge appears.
- [ ] Admin creates a rule for `WEEKLY_REPORT_SUBMITTED` with their email.
- [ ] Submitting a weekly report (as an Impact Leader) triggers a send (verified in logs or inbox).
- [ ] Sending a test email returns `{ ok: true, configured: true }` and reaches the inbox.
- [ ] SMTP fallback: when SMTP is not configured, calls return `{ skipped: true }` with a console warning — no crash.

## Tests

| Test | Expectation |
|---|---|
| Unit: `notify('WEEKLY_REPORT_SUBMITTED', payload)` with no SMTP | returns gracefully; logs skipped |
| Unit: same, with SMTP mocked | calls `sendMail({ to, subject, text, html })` |
| Manual: Reassign a Guest to an Impact Leader | triggers send (SMTP configured) |

## Rollback

Disable the trigger by commenting in `notify(...)`; the rules table simply doesn't fire.

---
*Next: [Phase_10_CSV_Import_Export.md](./Phase_10_CSV_Import_Export.md).*
