# 02 — Database Schema (v2)

This is the authoritative `prisma/schema.prisma` for v2.

## What changes from v1

1. **`ImpactCell`** becomes **hierarchical** (self-referential 1:many). New fields:
   - `parentCellId String?`
   - `isPrimary Boolean @default(false)` (true for top-level cells meant to host a Leadership Board).
   - `order Int @default(0)` (for stable ordering on the leadership board).
2. **`Guest`** adds a soft-delete column: `deletedAt DateTime?`.
3. **`User`** adds `deletedAt DateTime?` (mirror of `active` flag, keeps both for clarity).
4. **`AuditLog`** adds `entity String?` and `entityId String?` so it can be filtered ("show me all changes to this guest").
5. **`ImpactSubmission`** adds `deletedAt DateTime?`.
6. **New model:** `DashboardCache` so the Leadership Board reads rollups in <100ms even during peak. Keyed by `cellId`.

> Indices are tightened where it matters (`Guest.followOfficerId + deletedAt`, `ImpactCell.parentCellId`, `ImpactSubmission.{ impactCellId, type, fellowshipDateKey }`).

---

## Full schema

```prisma
datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}

generator client {
  provider = "prisma-client-js"
}

enum Role {
  Administrator
  Supervisor
  FollowUpOfficer
  Follow_UP
  Follow_UP_Admin
  Follow_UP_View_Only
  Impact_Leaders
  Impact_Cell_Admin
  Impact_Cell_Report
}

enum ContactedStatus {
  No
  Yes
  AvailableForVisit
  NotAvailableForVisit
  NotReachable
  WrongNumber
  Others
}

enum JoinWhen {
  FirstTimer
  NewMember
  OldMember
}

model User {
  id           String   @id @default(uuid())
  fullName     String
  email        String   @unique
  phone        String?
  username     String   @unique
  passwordHash String
  role         Role     @default(FollowUpOfficer)
  roles        Json?
  impactCellId String?
  active       Boolean  @default(true)
  deletedAt    DateTime?
  createdAt    DateTime @default(now())
  updatedAt    DateTime @updatedAt

  guests        Guest[]           @relation("OfficerGuests")
  impactCell    ImpactCell?       @relation("ImpactCellLeader", fields: [impactCellId], references: [id])
  impactReports ImpactSubmission[] @relation("ImpactSubmissionUser")
  resetTokens   PasswordReset[]
  auditLogs     AuditLog[]

  @@index([impactCellId])
  @@index([deletedAt])
}

model Guest {
  id                String           @id @default(uuid())
  date              DateTime         @default(now())
  event             String?
  eventOther        String?
  guestName         String
  gender            String?
  maritalStatus     String?
  phone             String?
  address           String?
  age               String?
  nearestImpactCell String?
  impactStatus      String?          // OWNED by Impact Cell group
  contactedStatus   ContactedStatus  @default(No)
  joinWhen          JoinWhen?
  daysAvailable     String?
  comments          String?          @db.Text
  visited           Boolean          @default(false)
  visitedAt         String?
  indicatedToJoin   String?
  visitationStatus  String?
  feedback          String?          @db.Text
  followUpStatus    String?          // OWNED by Follow Up Team group
  followUpContacts  Json?            // OWNED by Follow Up Team group
  source            String?

  followOfficerId   String?
  followOfficer     User?            @relation("OfficerGuests", fields: [followOfficerId], references: [id])

  deletedAt         DateTime?
  createdAt         DateTime         @default(now())
  updatedAt         DateTime         @updatedAt

  @@index([followOfficerId, deletedAt])
  @@index([contactedStatus])
  @@index([followUpStatus])
  @@index([nearestImpactCell])
  @@index([event])
  @@index([source])
  @@index([deletedAt])
}

/// SELF-REFERENTIAL — primary cells display the Leadership Board for their subCells.
model ImpactCell {
  id           String       @id @default(uuid())
  name         String       @unique
  phone        String?
  address      String?
  parentCellId String?
  isPrimary    Boolean      @default(false)
  order        Int          @default(0)
  createdAt    DateTime     @default(now())
  updatedAt    DateTime     @updatedAt

  parentCell   ImpactCell?  @relation("SubCells", fields: [parentCellId], references: [id], onDelete: SetNull)
  subCells     ImpactCell[] @relation("SubCells")

  leaders      User[]              @relation("ImpactCellLeader")
  submissions  ImpactSubmission[]

  @@index([parentCellId])
  @@index([isPrimary])
}

model ImpactSubmission {
  id               String     @id @default(uuid())
  type             String     // "member" | "report" | "childbirth" | "soul"
  data             Json
  impactCellId     String?
  fellowshipDateKey String?
  userId           String?
  deletedAt        DateTime?
  createdAt        DateTime   @default(now())
  updatedAt        DateTime   @updatedAt

  impactCell ImpactCell? @relation(fields: [impactCellId], references: [id])
  user       User?       @relation("ImpactSubmissionUser", fields: [userId], references: [id])

  @@index([type, impactCellId])
  @@index([impactCellId])
  @@index([impactCellId, type, fellowshipDateKey])   // duplicate-report lookup
  @@index([userId])
  @@index([deletedAt])
}

model NotificationRule {
  id        String   @id @default(uuid())
  action    String
  email     String
  active    Boolean  @default(true)
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt

  @@index([action])
}

model SmtpSetting {
  id        String   @id @default("singleton")
  host      String?
  port      Int      @default(587)
  secure    Boolean  @default(false)
  user      String?
  pass      String?
  fromEmail String?
  fromName  String?
  createdAt DateTime @default(now())
  updatedAt DateTime @updatedAt
}

model PasswordReset {
  id        String   @id @default(uuid())
  userId    String
  token     String   @unique
  expiresAt DateTime
  used      Boolean  @default(false)
  user      User     @relation(fields: [userId], references: [id], onDelete: Cascade)
}

model AuditLog {
  id       String   @id @default(uuid())
  at       DateTime @default(now())
  actorId  String?
  action   String
  detail   String?  @db.Text
  entity   String?  // "guest" | "user" | "impactCell" | ...
  entityId String?  // the UUID of the thing acted on

  actor    User?    @relation(fields: [actorId], references: [id])

  @@index([at])
  @@index([entity, entityId])
}

/// Cached rollups for the Leadership Board (TTL: 5 min, regenerated lazily).
model DashboardCache {
  id        String   @id @default(uuid())
  cellId    String
  scope     String   // "leadership-board" | "dashboard"
  payload   Json
  expiresAt DateTime
  updatedAt DateTime @updatedAt

  @@unique([cellId, scope])
  @@index([expiresAt])
}
```

---

## Migrations

Apply in order:

1. `0001_init_baseline` — keep the existing initial migration for the 8 baseline tables.
2. `0002_impact_cell_hierarchy` — add `parentCellId`, `isPrimary`, `order` + the self-relation on `ImpactCell`.
3. `0003_soft_deletes` — add `deletedAt` to `User`, `Guest`, `ImpactSubmission`.
4. `0004_audit_enrichment` — add `entity`, `entityId` to `AuditLog` + index.
5. `0005_dashboard_cache` — create `DashboardCache`.
6. `0006_seed_primary_cells` — backfill: all 70 existing cells become `isPrimary = true` (we will demonstrate a single split in seed for dev).

Command sequence:

```bash
npx prisma migrate dev --name 0002_impact_cell_hierarchy
npx prisma migrate dev --name 0003_soft_deletes
npx prisma migrate dev --name 0004_audit_enrichment
npx prisma migrate dev --name 0005_dashboard_cache
node prisma/seed.js
```

> Existing 70-cell seed keeps working — `parentCellId = NULL`, `isPrimary = true`. The split feature is opt-in via Admin UI.

---

## Data invariants enforced in code (not in DB)

| Invariant | Where enforced |
|-----------|----------------|
| A primary cell has no parent | `server/lib/impact-cells.js::validateHierarchy()` |
| A non-primary cell must have a parent | `server/lib/impact-cells.js::validateHierarchy()` |
| Sub-cells cannot themselves have sub-cells (1 level deep only) | `server/lib/impact-cells.js::validateHierarchy()` |
| A guest's `followOfficerId` is non-null when they go to assignment | `server/controllers/guest.controller.js::sanitize()` |
| Only one unsigned `report` submission per `(impactCellId, fellowshipDateKey)` | `server/controllers/impact.controller.js::createSubmission()` |
| The primary ImpactCell seeding list is idempotent | `server/lib/impact-cells.js::ensureImpactCells()` |

---
*Next: [03_Three_User_Groups.md](./03_Three_User_Groups.md).*
