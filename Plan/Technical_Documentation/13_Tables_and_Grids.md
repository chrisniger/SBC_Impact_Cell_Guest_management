# Tables and Grids

## Overview

All tables in the UI are custom-built with HTML table elements or CSS Grid, styled with Tailwind CSS. No formal table library is used (e.g., TanStack Table, AG Grid). Sorting and filtering are implemented with client-side JavaScript.

---

## Guest Table

**Location:** `src/routes/_authenticated/guests.tsx`

### Full View

| Column | Data Type | Sorted | Filterable |
|--------|-----------|--------|------------|
| Date | dd-mm-yyyy | No | No |
| Guest Name | string | No | Yes (search) |
| Gender | Male/Female | No | No |
| Phone | string | No | Yes (search) |
| Address | string | No | Yes (search) |
| Age | number | No | No |
| Nearest Impact Cell | string | No | No |
| Contacted Status | StatusBadge | No | Yes (dropdown) |
| Join When | string | No | Yes (dropdown) |
| Follow Up Status | StatusBadge | No | No |
| Officer | string | No | Yes (search) |
| Actions | Edit/Delete/Reassign | — | — |

**Sorting logic for Follow UP roles:**
```typescript
if (isAssignedOnlyRole(user?.role) || isFollowUpTeamRole(user?.role)) {
  const priority = (status) => {
    if (status === "NOT CONTACTED" || !status) return 0
    if (status === "CONTACTED") return 1
    return 2
  }
  list = [...list].sort((a, b) => priority(a.followUpStatus) - priority(b.followUpStatus))
}
```

**Filter logic:**
- Search query (`q`): matches guestName, phone, followOfficer, address (case-insensitive, partial match)
- Status filter: exact match on contactedStatus
- Join when filter: exact match on joinWhen
- All filtering is client-side on the full guest list

**Data scoping:**
- Assigned-only roles see only their own guests (pre-filtered by backend)
- All other roles see all guests

### Compact View (optional view mode)
Reduced column set for mobile or dense display.

### Empty State
```
+--------------------------------------------+
|                                            |
|   No guests found                          |
|   Try adjusting your search or filters     |
|                                            |
+--------------------------------------------+
```

---

## User Table

**Location:** `src/routes/_authenticated/users.tsx`

| Column | Data Type | Notes |
|--------|-----------|-------|
| Name | string | Full name |
| Email | string | |
| Phone | string | |
| Username | string | |
| Role | Badge | Primary role displayed as badge |
| Roles | Badge list | All roles shown as multiple badges |
| Active | Switch | Toggle active/inactive state |
| Created | date | |
| Actions | Edit/Deactivate | Deactivate button for active users |

**Role badges:**
- Each role displayed as a colored badge
- Inline display of all assigned roles

---

## Audit Log Table

**Location:** `src/routes/_authenticated/audit.tsx`

| Column | Data Type | Notes |
|--------|-----------|-------|
| Timestamp | iso_string → formatted | |
| Actor | string | User fullName or "system" |
| Action | string | e.g., "Login", "Create guest", "Update user" |
| Detail | text | Additional context |

**Data source:** Limited to 500 most recent entries from backend, ordered by `at` descending.

---

## Notification Rules Table

**Location:** `src/routes/_authenticated/notifications.tsx`

| Column | Data Type | Notes |
|--------|-----------|-------|
| Action | string | Human-readable action label |
| Email | string | Recipient email |
| Active | Switch | Toggle rule on/off |
| Created | date | |
| Actions | Edit/Delete | |

---

## Impact Cells Table

**Location:** `src/routes/_authenticated/impact-cells.tsx`

| Column | Data Type | Notes |
|--------|-----------|-------|
| Name | string | Cell name (unique) |
| Phone | string | Cell contact |
| Address | string | Cell location |
| Leader | string | First active leader's fullName |
| Actions | Edit | |

**Behavior:**
- Auto-seeds 70 hardcoded cells on first load
- Administrators can add/edit cells
- Cell name must be unique

---

## Impact Submissions Tables (in Dashboard)

### Members Table
**Section:** Impact Leader dashboard → Members Data

| Column | Notes |
|--------|-------|
| Name | |
| Phone | |
| Gender | |
| Marital Status | |
| Nearest Impact Cell | |
| (dynamic columns from JSON data) | |

### Reports Table
**Section:** Impact Leader dashboard → My Reports

| Column | Notes |
|--------|-------|
| Fellowship Date | |
| Impact Cell | |
| Attendance | |
| Offerings | |
| Submitted At | |
| (dynamic columns from JSON data) | |

### Child Naming Table
**Section:** Impact Cell Admin dashboard → Child Naming

| Column | Notes |
|--------|-------|
| Child Name | |
| Parent Name | |
| Date of Birth | |
| Phone | |
| Cell | |

---

## Officer Performance Table

**Section:** Report → Officer Performance

| Column | Data Type | Notes |
|--------|-----------|-------|
| Officer Name | string | |
| Total Guests | number | Count of assigned guests |

---

## Download CSV Export Tables

The sidebar "Downloads" section generates CSV exports from submission data:

### CSV Column Generation (Dynamic)
```typescript
const columns = ["id", "type", "submittedAt", "impactCell", "submittedBy", ...Object.keys(data).sort()]
```
- First 5 columns are static (id, type, submittedAt, impactCell, submittedBy)
- Remaining columns are dynamically extracted from all data JSON keys across all rows
- Columns are sorted alphabetically after the static ones

### Guest CSV Export Columns
```
id, date, event, eventOther, followOfficer, guestName, gender, maritalStatus,
phone, address, age, nearestImpactCell, impactStatus, contactedStatus,
joinWhen, followUpStatus
```

---

## Table Styling Patterns

All tables follow consistent Tailwind styling:
- `w-full` for full-width tables
- `text-left` alignment
- Header row with `text-xs uppercase tracking-wide text-muted-foreground`
- Body rows with hover highlight
- Alternating or border-separated rows
- Responsive overflow handling (`overflow-x-auto`)

---

## Summary

| Table | Route | Role Access | Data Source |
|-------|-------|-------------|-------------|
| Guest | /guests | REASSIGN + FOLLOW_UP + VIEW_ONLY + Impact_Leaders | apiClient.guests.list() |
| User | /users | ADMIN only | apiClient.users.list() |
| Audit Log | /audit | REPORT_ROLES | apiClient.reports.audit() |
| Notification Rules | /notifications | ADMIN only | apiClient.notifications.rules() |
| Impact Cells | /impact-cells | ADMIN only | apiClient.impact.cells() |
| Impact Members | /dashboard | Impact_Leaders, Impact_Cell roles | apiClient.impact.submissions("member") |
| Impact Reports | /dashboard | Impact_Leaders, Impact_Cell_Admin | apiClient.impact.submissions("report") |
| Child Naming | /dashboard | Impact_Cell roles | apiClient.impact.submissions("childbirth") |
| Souls | /dashboard | Impact_Leaders | apiClient.impact.submissions("soul") |
| Officer Performance | /dashboard | REPORT_ROLES | apiClient.reports.officerPerformance() |
