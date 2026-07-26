# Database Schema

## Overview

- **Database:** MySQL (MariaDB on Hostinger)
- **ORM:** Prisma Client 5.20.x
- **Tables:** 8
- **Enums:** 3
- **Primary Keys:** All tables use UUID v4 (auto-generated via `@default(uuid())`)
- **Engine:** InnoDB (default)

---

## Enums

### `Role`
```prisma
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
```
- 9 values representing all user roles in the system
- Stored as MySQL ENUM

### `ContactedStatus`
```prisma
enum ContactedStatus {
  No
  Yes
  AvailableForVisit
  NotAvailableForVisit
  NotReachable
  WrongNumber
  Others
}
```
- 7 values tracking guest contact status
- Default: `No`

### `JoinWhen`
```prisma
enum JoinWhen {
  FirstTimer
  NewMember
  OldMember
}
```
- 3 values tracking when a guest joined

---

## Tables

### `User`

Stores system users with role assignments and impact cell associations.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| fullName | String | NOT NULL | | |
| email | String | NOT NULL, @unique | | |
| phone | String? | NULLABLE | | |
| username | String | NOT NULL, @unique | | |
| passwordHash | String | NOT NULL | | bcrypt hash |
| role | Role | NOT NULL | FollowUpOfficer | Primary role (enum) |
| roles | Json? | NULLABLE | | JSON array of all roles |
| impactCellId | String? | NULLABLE, FK → ImpactCell.id | | Must reference existing ImpactCell |
| active | Boolean | NOT NULL | true | Soft-deactivation |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Relations:**
- `guests` → Guest[] (as followOfficer, via `followOfficerId`)
- `impactCell` → ImpactCell (as leader, via `impactCellId`, named "ImpactCellLeader")
- `impactReports` → ImpactSubmission[] (via `userId`, named "ImpactSubmissionUser")
- `resetTokens` → PasswordReset[] (via `userId`)
- `auditLogs` → AuditLog[] (via `actorId`)

**Indexes:** `@@index([impactCellId])`

---

### `Guest`

Stores guest/visitor records with follow-up and visitation tracking.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| date | DateTime | NOT NULL | now() | Guest visit date |
| event | String? | NULLABLE | | COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER |
| eventOther | String? | NULLABLE | | Custom event description |
| guestName | String | NOT NULL | | |
| gender | String? | NULLABLE | | Male/Female |
| maritalStatus | String? | NULLABLE | | Single/Married/Divorced/Widowed |
| phone | String? | NULLABLE | | |
| address | String? | NULLABLE | | |
| age | String? | NULLABLE | | Note: stored as String, not Int |
| nearestImpactCell | String? | NULLABLE | | Free text, not FK |
| impactStatus | String? | NULLABLE | | Contacted/Not Contacted/Not Reachable |
| contactedStatus | ContactedStatus | NOT NULL | No | Enum value |
| joinWhen | JoinWhen? | NULLABLE | | Enum value |
| daysAvailable | String? | NULLABLE | | Comma-separated day names (e.g., "Mon,Tue") |
| comments | Text? (@db.Text) | NULLABLE | | |
| visited | Boolean | NOT NULL | false | |
| visitedAt | String? | NULLABLE | | Home/Office |
| indicatedToJoin | String? | NULLABLE | | Yes/No/Others |
| visitationStatus | String? | NULLABLE | | Visited/Pending |
| feedback | Text? (@db.Text) | NULLABLE | | |
| followUpStatus | String? | NULLABLE | | NOT CONTACTED/CONTACTED/WRONG NUMBER/NOT REACHABLE |
| followUpContacts | Json? | NULLABLE | | Array of contact sections |
| source | String? | NULLABLE | | e.g., "PUBLIC_IMPACT_JOIN" |
| followOfficerId | String? | NULLABLE, FK → User.id | | Assigned officer |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Relations:**
- `followOfficer` → User (via `followOfficerId`, named "OfficerGuests")

**Indexes:**
- `@@index([followOfficerId])`
- `@@index([contactedStatus])`
- `@@index([followUpStatus])`
- `@@index([event])`
- `@@index([source])`

---

### `ImpactCell`

Stores impact cell (small group) definitions.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| name | String | NOT NULL, @unique | | Cell name |
| phone | String? | NULLABLE | | Cell contact phone |
| address | String? | NULLABLE | | Cell location |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Relations:**
- `leaders` → User[] (via `User.impactCellId`, named "ImpactCellLeader")
- `submissions` → ImpactSubmission[] (via `impactCellId`)

---

### `ImpactSubmission`

Stores impact cell submissions (members, reports, childbirth, souls).

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| type | String | NOT NULL | | member/report/childbirth/soul |
| data | Json | NOT NULL | | Flexible JSON payload |
| impactCellId | String? | NULLABLE, FK → ImpactCell.id | | |
| fellowshipDateKey | String? | NULLABLE | | YYYY-MM-DD format, used for duplicate check |
| userId | String? | NULLABLE, FK → User.id | | Submitting user |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Relations:**
- `impactCell` → ImpactCell (via `impactCellId`)
- `user` → User (via `userId`, named "ImpactSubmissionUser")

**Indexes:**
- `@@index([type])`
- `@@index([impactCellId])`
- `@@index([fellowshipDateKey])`
- `@@index([userId])`

---

### `NotificationRule`

Stores email notification rules (action → email mapping).

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| action | String | NOT NULL | | e.g., "GUEST_ASSIGNED_TO_IMPACT_LEADER" |
| email | String | NOT NULL | | Recipient email |
| active | Boolean | NOT NULL | true | |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Indexes:** `@@index([action])`

---

### `SmtpSetting`

Singleton table storing SMTP configuration.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String | PK, @default("singleton") | "singleton" | Fixed ID for singleton pattern |
| host | String? | NULLABLE | | SMTP host |
| port | Int | NOT NULL | 587 | |
| secure | Boolean | NOT NULL | false | |
| user | String? | NULLABLE | | SMTP username |
| pass | String? | NULLABLE | | SMTP password (plain text) |
| fromEmail | String? | NULLABLE | | Sender email |
| fromName | String? | NULLABLE | | Sender name |
| createdAt | DateTime | @default(now()) | | |
| updatedAt | DateTime | @updatedAt | | |

**Note:** The `id` field defaults to `"singleton"` ensuring only one row exists. The upsert operation uses `where: { id: "singleton" }`.

---

### `PasswordReset`

Stores password reset tokens with expiry.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| userId | String | NOT NULL, FK → User.id | | |
| token | String | NOT NULL, @unique | | crypto.randomBytes(32) hex string |
| expiresAt | DateTime | NOT NULL | | 1 hour from creation |
| used | Boolean | NOT NULL | false | |
| user | User | @relation(onDelete: Cascade) | | |

**Relations:**
- `user` → User (via `userId`, cascade delete)

**Note:** Cascade delete is only on `PasswordReset → User`. All other foreign keys have no cascade.

---

### `AuditLog`

Stores audit trail entries.

| Column | Type | Constraints | Default | Notes |
|--------|------|-------------|---------|-------|
| id | String (UUID) | PK, @default(uuid()) | | |
| at | DateTime | NOT NULL | now() | |
| actorId | String? | NULLABLE, FK → User.id | | |
| action | String | NOT NULL | | e.g., "Login", "Create guest", "Update user" |
| detail | String? (@db.Text) | NULLABLE | | |
| actor | User | @relation (via `actorId`) | | |

**Relations:**
- `actor` → User (via `actorId`)

---

## Relationship Summary

| Model | Relationship | Target | Field | Cascade |
|-------|-------------|--------|-------|---------|
| User | 1:N → | Guest | followOfficerId | None |
| User | N:1 → | ImpactCell | impactCellId | None |
| User | 1:N → | ImpactSubmission | userId | None |
| User | 1:N → | PasswordReset | userId | **Cascade** |
| User | 1:N → | AuditLog | actorId | None |
| Guest | N:1 → | User | followOfficerId | None |
| ImpactCell | 1:N → | User | impactCellId | None |
| ImpactCell | 1:N → | ImpactSubmission | impactCellId | None |
| ImpactSubmission | N:1 → | ImpactCell | impactCellId | None |
| ImpactSubmission | N:1 → | User | userId | None |
| PasswordReset | N:1 → | User | userId | **Cascade** |
| AuditLog | N:1 → | User | actorId | None |
