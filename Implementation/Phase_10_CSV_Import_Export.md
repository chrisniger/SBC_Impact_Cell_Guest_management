# Phase 10 — CSV Import / Export

> Read existing `server/controllers/csv.controller.js` and the import page in `src/routes/_authenticated/import.tsx`. v2 doesn't fundamentally change this; it fixes gaps around the new column policy.

## Goal

Allow Admin (and only Admin) to **bulk-import guests** via CSV using one of three templates (one per user-group-friendly format). Also provide **client-side CSV exports** to Admin and `Follow_UP_Admin` for offline review.

## In scope

1. **3 CSV templates** (existing in v1, verify polish):
   - **Follow Up Officer** — base columns.
   - **Follow Up Team** — base + `Follow Up Status`.
   - **Impact Cell** — base + `Impact Status`.
2. **Column-aliasing** (existing; verify):
   - e.g., `Phone|phone|Phone Number|Mobile` all map to `phone`.
3. **Duplicate detection by phone** (existing).
4. **Sanitization in line with column policy**:
   - Import all rows but apply `stripDisallowed('Administrator', body)` first (Admin can fill everything).
5. **CSV export** (client-side):
   - For Admin: full columns including all 3 user-group-owned fields.
   - For `Follow_UP_Admin`: **only** columns the Follow Up Officer group can see + edit (per matrix). Hide private Impact Cell columns from CSV.
6. **UI** (`/import`):
   - Drag-and-drop dropzone.
   - Template chooser (3 cards).
   - Result: `Created: 87, Skipped: 13 (duplicates)`.
   - Skipped details accordion.
7. **Audit**: every import creates an audit entry (`GUESTS_IMPORTED`, count, batch ID).

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/controllers/csv.controller.js` | modify | ensure import uses `stripDisallowed('Administrator', …)`. |
| `src/routes/_authenticated/import.tsx` | modify | update to use 3 template cards. |
| `src/components/CsvDropzone.tsx` | create | **NEW** — drag-and-drop + progress. |
| `src/components/TemplateCard.tsx` | create | **NEW** — one card per template. |
| `src/components/ImportResultsList.tsx` | create | **NEW** — list of skipped rows. |
| `src/components/ExportGuestCsvButton.tsx` | create | **NEW** — columns depend on user role. |
| `src/lib/access.ts` | already in Phase 04 | add `GUEST_CSV_COLUMNS(role)` helper. |
| `server/lib/audit.js` | modify | `auditBatch(req, action, detail)` helper. |

## Acceptance criteria

- [ ] Admin uploads a 100-row CSV with the **Follow Up Officer** template → response `created: 87, skipped: 13`.
- [ ] Admin uploads a CSV with the **Impact Cell** template → rows with `Impact Status` are saved; rows without default to empty.
- [ ] Admin uploads a CSV that includes `//Chris##101` as a phone number — no XSS or import of `sqlmap`-style payloads.
- [ ] Export CSV (as Admin) contains all columns defined in the matrix.
- [ ] Export CSV (as `Follow_UP_Admin`) does NOT contain `impactStatus`, `followUpStatus`, `followUpContacts`, or any other field the Follow Up Officer group shouldn't see.

## Tests

| Test | Expectation |
|---|---|
| Unit: `GUEST_CSV_COLUMNS('Administrator')` | includes all visible columns |
| Unit: `GUEST_CSV_COLUMNS('FollowUpOfficer')` | excludes `impactStatus`, `followUpStatus`, `followUpContacts` |
| API: import 5 rows where 2 are duplicate | returns `created: 3, skipped: 2` |
| Manual: paste `=cmd|' /C calc'!A1` into a CSV cell | import safely – cell stored as plain text |

## Out of scope

- Excel `.xlsx` imports (CSV only).

---
*Next: [Phase_11_Reports_And_Audit.md](./Phase_11_Reports_And_Audit.md).*
