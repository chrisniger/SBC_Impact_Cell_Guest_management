# 04 — Impact Cell Hierarchy (parent → sub-cell)

## Why

Today, `ImpactCell` is a flat list of ~70 hardcoded names (e.g. `APO`, `APO-DUTSE`, `APO RESETTLEMENT`, `APO RESETTLEMENT B`). These names clearly want to live in a **hierarchy**: an `APO` parent cell containing the four APO-* sub-cells. The redesign gives us that hierarchy, so the Leadership Board can show primary cells rolling up metrics from their children.

## Goals

1. **Self-referential relation.** `ImpactCell.parentCellId` references another `ImpactCell.id`. Up to 1 level of nesting (no grandchildren in v2).
2. **Primary flag.** `ImpactCell.isPrimary` marks the top-level cells. A primary cell shows the Leadership Board; a non-primary cell does not.
3. **Display order.** `ImpactCell.order` gives a stable, drag-reorderable sort on the board.
4. **Soft delete awareness.** Deleting a sub-cell should not orphan its submissions — `onDelete: SetNull` on the FK.
5. **Backward compat.** The existing 70 cells keep working as primary cells (`parentCellId = NULL`, `isPrimary = true`).

## Final decision: parent → sub-cell only (1 level)

- No grandchildren. A sub-cell trying to take children rejects in `validateHierarchy()`.
- Primary cells are always `parentCellId = NULL`.
- A non-primary cell **must** have a parent (`parentCellId != NULL`).

This keeps validation logic in code (single source of truth), the UI simple, and the board unambiguous.

## Schema

See [02_Database_Schema.md](./02_Database_Schema.md) § "ImpactCell". Highlights:

```prisma
model ImpactCell {
  id           String       @id @default(uuid())
  name         String       @unique
  phone        String?
  address      String?
  parentCellId String?
  isPrimary    Boolean      @default(false)
  order        Int          @default(0)

  parentCell   ImpactCell?  @relation("SubCells", fields: [parentCellId], references: [id], onDelete: SetNull)
  subCells     ImpactCell[] @relation("SubCells")

  leaders      User[]              @relation("ImpactCellLeader")
  submissions  ImpactSubmission[]

  @@index([parentCellId])
  @@index([isPrimary])
}
```

## Code invariants — `server/lib/impact-cells.js`

```js
async function validateHierarchy(parentCellId, isPrimary) {
  if (isPrimary && parentCellId) {
    throw Object.assign(new Error("A primary cell cannot have a parent"), { status: 400 });
  }
  if (!isPrimary && !parentCellId) {
    throw Object.assign(new Error("A non-primary cell must have a parent"), { status: 400 });
  }
  if (parentCellId) {
    const parent = await prisma.impactCell.findUnique({ where: { id: parentCellId } });
    if (!parent) throw Object.assign(new Error("Parent cell not found"), { status: 400 });
    if (!parent.isPrimary) {
      throw Object.assign(new Error("Only primary cells can have sub-cells (1-level hierarchy)"), { status: 400 });
    }
  }
}

/// Demo-only helper. In production, `parentCellId` is passed by the Admin UI.
/// This is to make seed data show the feature works.
async function splitCell(parentId, subCellName) { ... }
```

## Seed strategy (`prisma/seed.js`)

1. Existing admin user stays.
2. Call `ensureImpactCells()` — idempotent. All 70 existing cells get `isPrimary = true`, `parentCellId = NULL`, `order = max + 1`.
3. For dev convenience, after the initial seed, optionally split one cell:
   - `APO` becomes primary with sub-cells `APO-DUTSE`, `APO RESETTLEMENT`, `APO RESETTLEMENT B`, `APO LEGISLATIVE QTRS` (already from the legacy list — we just point their `parentCellId` to the new `APO` primary).

> The prod seed should **NOT** create these dev-only sub-cell links; admin creates them via UI.

## API surface (will be implemented in Phase 03)

| Method | Path | Body / Filter | Roles |
|---|---|---|---|
| GET | `/api/impact/cells` | optional `?hierarchy=1` to include `subCells[]` | any authed |
| POST | `/api/impact/cells` | `{ name, phone?, address?, parentCellId?, isPrimary? }` | Administrator |
| PUT | `/api/impact/cells/:id` | partial update | Administrator |
| DELETE | `/api/impact/cells/:id` | (soft: clears `parentCellId` of children) | Administrator |
| POST | `/api/impact/cells/:id/split` | `{ subCellNames: [string] }` → bulk create sub-cells under this primary | Administrator |

## UI surface

- **Admin → Impact Cells page**: a tree view with primary cells at the top, sub-cells indented.
- **Split button** next to each primary cell: "Split into sub-cells" → modal where admin lists new sub-cell names.
- **Re-attach**: drag a sub-cell under a different primary.

## Migration plan

```bash
npx prisma migrate dev --name 0002_impact_cell_hierarchy
```

The migration creates the FK, indices, and backfills existing rows (`parentCellId = NULL`, `isPrimary = true`, `order = …`, ordered alphabetically).

## Rollback plan

If the hierarchy causes problems in production:

```sql
-- Remove the new columns (manually, after creating a safety backup)
ALTER TABLE ImpactCell DROP COLUMN parentCellId, DROP COLUMN isPrimary, DROP COLUMN `order`;
```

Replace the 70 hardcoded names back into the flat list. The application reads `name` only for paths that don't depend on hierarchy.

---
*Next: [05_Leadership_Board.md](./05_Leadership_Board.md).*
