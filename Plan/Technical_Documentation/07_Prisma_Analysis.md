# Prisma Analysis

## Schema File

**Location:** `prisma/schema.prisma`
**Provider:** `mysql`
**Client:** `prisma-client-js`

```prisma
datasource db {
  provider = "mysql"
  url      = env("DATABASE_URL")
}

generator client {
  provider = "prisma-client-js"
}
```

---

## Enums (3)

### 1. `Role` — 9 values
```
Administrator      → DB: 'Administrator'
Supervisor          → DB: 'Supervisor'
FollowUpOfficer     → DB: 'FollowUpOfficer'
Follow_UP           → DB: 'Follow_UP'
Follow_UP_Admin     → DB: 'Follow_UP_Admin'
Follow_UP_View_Only → DB: 'Follow_UP_View_Only'
Impact_Leaders      → DB: 'Impact_Leaders'
Impact_Cell_Admin   → DB: 'Impact_Cell_Admin'
Impact_Cell_Report  → DB: 'Impact_Cell_Report'
```

### 2. `ContactedStatus` — 7 values
```
No                → DB: 'No'
Yes               → DB: 'Yes'
AvailableForVisit → DB: 'AvailableForVisit'
NotAvailableForVisit → DB: 'NotAvailableForVisit'
NotReachable      → DB: 'NotReachable'
WrongNumber       → DB: 'WrongNumber'
Others            → DB: 'Others'
```

### 3. `JoinWhen` — 3 values
```
FirstTimer → DB: 'FirstTimer'
NewMember  → DB: 'NewMember'
OldMember  → DB: 'OldMember'
```

---

## Models (8)

1. **User** — System users with multi-role support
2. **Guest** — Guest/visitor records (core entity)
3. **ImpactCell** — Small group/cell definitions
4. **ImpactSubmission** — Impact cell submissions (flexible JSON data)
5. **NotificationRule** — Email notification rules
6. **SmtpSetting** — SMTP configuration (singleton pattern)
7. **PasswordReset** — Password reset tokens
8. **AuditLog** — Audit trail entries

---

## Field Details

### UUID Primary Keys
All models use `@id @default(uuid())` except `SmtpSetting` which uses `@default("singleton")`.

```prisma
id String @id @default(uuid())     // All models except SmtpSetting
id String @id @default("singleton") // SmtpSetting
```

### JSON Fields
Three fields use the `Json` Prisma type (stored as MySQL JSON column):

| Model | Field | Purpose |
|-------|-------|---------|
| User | `roles` | Array of Role values for multi-role users |
| Guest | `followUpContacts` | Array of contact sections `[{comments, date, contact}]` |
| ImpactSubmission | `data` | Flexible form payload per submission type |

### Text Fields
Two fields use `@db.Text` for larger content:

| Model | Field |
|-------|-------|
| Guest | `comments` |
| Guest | `feedback` |
| AuditLog | `detail` |

### Optional/Nullable Fields
Most fields are optional (`String?`, `DateTime?`, `Int?`, `Json?`). The only **required** fields across all models are:

- User: `fullName`, `email`, `username`, `passwordHash`, `active`
- Guest: `guestName`, `contactedStatus`
- ImpactCell: `name`
- ImpactSubmission: `type`, `data`
- NotificationRule: `action`, `email`
- PasswordReset: `userId`, `token`, `expiresAt`, `used`
- AuditLog: `action`

### Date Fields
| Model | Field | Default |
|-------|-------|---------|
| All models | `createdAt` | `@default(now())` |
| All models (except PasswordReset, AuditLog) | `updatedAt` | `@updatedAt` |
| Guest | `date` | `@default(now())` |
| AuditLog | `at` | `@default(now())` |

---

## Relationship Analysis

```
User (1) ──< Guest (N)           via followOfficerId  [OfficerGuests]
User (N) >── ImpactCell (1)      via impactCellId     [ImpactCellLeader]
User (1) ──< ImpactSubmission (N) via userId           [ImpactSubmissionUser]
User (1) ──< PasswordReset (N)    via userId           [CASCADE DELETE]
User (1) ──< AuditLog (N)         via actorId

ImpactCell (1) ──< ImpactSubmission (N) via impactCellId
```

### Cascade Delete
- **Only** `PasswordReset → User` has `onDelete: Cascade`
- All other foreign keys have no cascade behavior (Prisma default: restrict on delete)

---

## Indexes

| Model | Index | Fields |
|-------|-------|--------|
| User | `@@index([impactCellId])` | For querying users by cell |
| Guest | `@@index([followOfficerId])` | For querying guests by officer |
| Guest | `@@index([contactedStatus])` | For status filtering |
| Guest | `@@index([followUpStatus])` | For follow-up status filtering |
| Guest | `@@index([event])` | For event filtering |
| Guest | `@@index([source])` | For source filtering |
| ImpactSubmission | `@@index([type])` | For type filtering |
| ImpactSubmission | `@@index([impactCellId])` | For cell-based queries |
| ImpactSubmission | `@@index([fellowshipDateKey])` | For duplicate report detection |
| ImpactSubmission | `@@index([userId])` | For user-based queries |
| NotificationRule | `@@index([action])` | For action-based filtering |

### Unique Constraints
- `User.email` — unique
- `User.username` — unique
- `ImpactCell.name` — unique
- `PasswordReset.token` — unique
- `SmtpSetting.id` — unique (singleton pattern)

---

## Migration Status

**No migrations have been applied.** The `schema.prisma` file is the authoritative source of truth for the database schema. The project uses `prisma:migrate deploy` in scripts but has not been run in production.

The project's `package.json` includes:
```json
"prisma:migrate": "prisma migrate deploy"
```

And postinstall:
```json
"postinstall": "prisma generate || true"
```

This means `prisma generate` runs after `npm install` to generate the Prisma Client, but migrations must be run manually.

---

## Key Design Notes

1. **Role duality:** `User.role` (single enum) + `User.roles` (JSON array) stores both a primary role and a full set of roles. The `normalizeRoles()` function always includes the primary role in the roles array.

2. **Singleton SmtpSetting:** The `SmtpSetting` table uses a fixed `id = "singleton"` to ensure only one row. The controller uses Prisma's `upsert` with `where: { id: "singleton" }`.

3. **String age:** `Guest.age` is stored as `String?` not `Int?` for flexibility.

4. **Flexible schema:** `ImpactSubmission.data` uses `Json` type for flexible form data across different submission types.

5. **Free-text cell:** `Guest.nearestImpactCell` is a free-text string, not a foreign key to `ImpactCell`.

6. **No proper foreign keys for enums:** Event types, visitation status, follow-up status, impact status are all stored as free strings, not enums or lookup tables.
