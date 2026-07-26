# Phase 03 — Impact Cell Data Model (hierarchy)

> Read **[02_Database_Schema.md](./02_Database_Schema.md)** and **[04_Impact_Cell_Hierarchy.md](./04_Impact_Cell_Hierarchy.md)** before starting.

## Goal

Make `ImpactCell` hierarchical. After this phase, an Admin can split a primary cell into N sub-cells, see the hierarchy in the impact-cell admin page, and the primary cell will be ready to drive the Leadership Board (Phase 08).

## In scope

1. **Migration** adding `parentCellId`, `isPrimary`, `order` to `ImpactCell`. `@@index([parentCellId])`, `@@index([isPrimary])`.
2. **Hardcoded cell seed** backfilled: all existing 70 cells → `parentCellId = NULL`, `isPrimary = true`, `order = …` (alphabetical position).
3. **`server/lib/impact-cells.js`** new helpers:
   - `validateHierarchy(parentCellId, isPrimary)` — already designed.
   - `splitCell(parentId, subCellNames)` — bulk create sub-cells.
   - `attachSubCell(subCellId, newParentCellId)` — change parent.
   - `detachSubCell(subCellId)` — promote to primary.
4. **API endpoints**:
   - `GET /api/impact/cells?hierarchy=1` → returns primary cells with nested `subCells[]`.
   - `POST /api/impact/cells` (Admin) — body: `{ name, phone?, address?, parentCellId?, isPrimary? }`. Enforces `validateHierarchy`.
   - `PUT /api/impact/cells/:id` (Admin) — updates parentCellId/order; refuses grandchildren.
   - `DELETE /api/impact/cells/:id` (Admin) — deletes; children → `parentCellId = NULL` → promote to primary (or refuse if a cell has submitted data, in which case it's a soft-delete via update `isPrimary = false` and keep on the tree).
   - `POST /api/impact/cells/:id/split` (Admin) — body: `{ subCellNames: ["A", "B", "C"] }` — bulk create.
5. **Frontend admin page** `src/routes/_authenticated/impact-cells.tsx`:
   - Replace the existing flat list with a tree view.
   - Primary cells → top, indented sub-cells.
   - "Split" button next to each primary cell.
   - Drag-to-attach (optional stretch goal).
6. **Frontend API helpers** in `src/lib/api.ts`:
   - `api.impact.cells(hierarchy?)` → returns hierarchy wrapper.
   - `api.impact.createCell(...)`, `splitCell(...)`, `updateCell(...)`, `deleteCell(...)`.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `prisma/schema.prisma` | modify | add the 3 new fields, self-relation, indices. |
| `prisma/migrations/0002_impact_cell_hierarchy/migration.sql` | create | ALTER TABLE. |
| `prisma/seed.js` | modify | preserve existing behaviour; backfill `isPrimary = true` for legacy cells. |
| `server/lib/impact-cells.js` | modify | add `validateHierarchy`, `splitCell`, etc. |
| `server/controllers/impact.controller.js` | modify | new endpoints + sanitize. |
| `server/routes/impact.routes.js` | modify | wire new endpoints. |
| `src/lib/api.ts` | modify | new impact methods taking hierarchy / split. |
| `src/lib/types.ts` | modify | `ImpactCell` becomes a tree type with optional `subCells?: ImpactCell[]`. |
| `src/routes/_authenticated/impact-cells.tsx` | major modify | tree view + split modal. |
| `src/components/ImpactCellTree.tsx` | create | **NEW** — recursive tree component (drag-handle optional). |
| `src/components/ImpactCellSplitModal.tsx` | create | **NEW** — admin can list new sub-cell names. |
| `tests/access.test.ts` | create | **NEW** — Vitest: `validateHierarchy` rejects grandchildren. |

## Acceptance criteria

- [ ] `npx prisma migrate dev --name 0002_impact_cell_hierarchy` runs cleanly.
- [ ] Existing 70 cells show in the impact-cells admin page as primaries.
- [ ] Admin clicks "Split" on `APO`, types `APO-DUTSE`, `APO RESETTLEMENT`, `APO LEGISLATIVE QTRS`, hits Split. The three show as sub-cells under `APO`.
- [ ] Trying to attach a sub-cell to another sub-cell returns 400.
- [ ] Reordering via `order` field works (drag-drop OR up/down arrow).
- [ ] `GET /api/impact/cells?hierarchy=1` returns the full tree.
- [ ] `GET /api/impact/cells` (without hierarchy query) returns the flat list — used by the public join form.

## Tests

| Test | Expectation |
|---|---|
| `validateHierarchy(parentId=null, isPrimary=false)` | throws "A non-primary cell must have a parent" |
| `validateHierarchy(parentId=X, isPrimary=true)` | throws "A primary cell cannot have a parent" |
| `validateHierarchy(parentId=child, …)` | throws "Only primary cells can have sub-cells" |
| `splitCell(existingPrimary, ["A","B"])` | 2 new sub-cells exist with `parentCellId = existingPrimary.id` |
| API smoke: `curl -X DELETE /api/impact/cells/<sub>` | sub-cell removed; children (none, by rule) unaffected |

## Out of scope

- The Leadership Board UI itself (Phase 08).
- Rollup caches (Phase 08).

## Rollback

- Drop the migration: `npx prisma migrate resolve --rolled-back 0002_impact_cell_hierarchy`.
- The flat list behaviour still works because the API falls back when `?hierarchy=1` is not set.

---
*Next: [Phase_04_Guest_Records_Core.md](./Phase_04_Guest_Records_Core.md).*
