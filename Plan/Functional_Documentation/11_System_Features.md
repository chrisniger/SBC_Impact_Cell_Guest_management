# 11 — System Features

## CSV Import

**Endpoint:** `POST /api/csv/upload`

**Access:** Administrator only (enforced on the frontend page — non-admin users are redirected to dashboard)

### Template Variants
Three CSV templates with different column sets:

| Template | Extra Columns |
|----------|--------------|
| Follow UP Officer | Basic fields (13 columns) |
| Follow_UP | Adds "Follow Up Status" column |
| Impact Cell | Adds "Impact Status" column |

### Column Aliases
The import system maps multiple column name variants automatically:

| Field | Accepted Column Names |
|-------|---------------------|
| Phone | Phone, phone, Phone Number, phoneNumber, Mobile, mobile |
| Guest Name | Guest Name, guestName, Name, Full Name, fullName |
| Address | Address, address, Residential Address, ResidentialAddress |
| Nearest Impact Cell | Nearest Impact Cell, NearestImpactCell, Impact Cell, ImpactCell, impactCell |
| Follow Officer | Follow Officer, Follow Up Officer, Follow_UP Officer, FollowUPOfficer, followOfficer, Assigned Officer |
| Age | Age, age, Age (years), ageYears |
| Event | Event, EVENT, event |
| Contacted Status | Contacted Status, ContactedStatus, contactedStatus |
| Follow Up Status | Follow Up Status, FollowUpStatus, followUpStatus |
| Impact Status | Impact Status, impactStatus, ImpactStatus |
| Join When | Join When, JoinWhen, joinWhen |
| Marital Status | Marital Status, MaritalStatus, maritalStatus |
| Gender | Gender, gender |

### Value Mapping
- **Join When:** "first timer" → FirstTimer, "new member" → NewMember, "old member" → OldMember (case-insensitive)
- **Contacted Status:** "no" → No, "yes" → Yes, "available for visit" → Available for Visit, etc.
- **Follow Up Status:** "not contacted" → NOT CONTACTED, "contacted" → CONTACTED, "wrong number" → WRONG NUMBER, "not reachable" → NOT REACHABLE

### Duplicate Detection
- Existing phone numbers are loaded before import
- Rows with duplicate phone numbers are skipped
- Response includes: `{ created: number, skipped: number, skippedDetails: array }`

### Officer Resolution
- Follow Officer name is matched (case-insensitive) against active users with assignable roles
- If no match, officer is set to null (unassigned)

## CSV Export

### Guest Data Export
- Available to Administrator and Follow_UP Admin
- Columns: id, date, event, eventOther, followOfficer, guestName, gender, maritalStatus, phone, address, age, nearestImpactCell, impactStatus, contactedStatus, joinWhen, followUpStatus
- Generated client-side from API data

### Impact Submission Exports
- Available to Administrator and Impact_Cell_Admin
- Types: report (impact-leader-reports.csv), member (impact-members-data.csv), childbirth (child-notice-data.csv)
- Dynamic columns: includes all unique data keys across submissions
- Generated client-side from API data

## Audit Logging

The `AuditLog` model tracks system actions:

| Field | Type | Description |
|-------|------|-------------|
| id | UUID | Primary key |
| at | DateTime | When the action occurred |
| actorId | String (nullable) | User ID of the actor |
| actor | User (relation) | User who performed the action |
| action | String | Action description (e.g., "GUEST_CREATED") |
| detail | Text | Human-readable details |

- Last 500 entries displayed on the Audit Log page
- Ordered by `at` descending
- Accessible by REPORT_ROLES

## Search

Available on the Guests page and Dashboard:

| Page | Searchable Fields |
|------|------------------|
| Guests | guestName, phone, followOfficer, address |
| Dashboard | guestName, phone, followOfficer, contactedStatus |
| Visit Schedule | guestName, phone, address, nearestImpactCell |

- Case-insensitive substring matching (client-side filter)
- Real-time filtering as user types

## Filtering

| Filter | Page | Options |
|--------|------|---------|
| Contacted Status | Guests | All statuses, No, Yes, Available for Visit, Not Available for Visit, Not Reachable, Wrong Number, Others |
| Join When | Guests | All categories, First Timer (Last 2 Weeks), New Members (Last 6 Months), Old Members |
| Visitation Status | Visit Schedule | All visit statuses, Pending, Visited |
| Month | Dashboard (Follow_UP Team) | YYYY-MM format, default: current month |

## Priority Sorting

For assigned-only and follow-up team roles, the guest list is sorted by Follow Up Status:

1. **NOT CONTACTED / empty** → shown first (priority 0)
2. **CONTACTED** → second (priority 1)
3. **WRONG NUMBER / NOT REACHABLE / other** → last (priority 2)

## Role Switching

- Users with multiple roles see a "Switch dashboard" dropdown menu in the top-right user menu
- Active role is persisted in `localStorage` (`cgms.activeRole`)
- Sent as `X-Active-Role` HTTP header on all API requests
- Switching roles triggers redirect to `/dashboard` with the new role's view

## Dark/Light Theme

- Toggle available on login page and in authenticated header
- Persisted to `localStorage` (`cgms.theme`)
- Falls back to system `prefers-color-scheme`
- Applies `dark` class to `<html>` element

## Collapsible Sidebar

- Toggle button (hamburger menu icon) in the header
- Two states: expanded (292px) and collapsed (80px, icons only)
- State persisted to `localStorage` (`cgms.sidebar.collapsed`)
- Applies CSS transitions for smooth animation
- On mobile (<md breakpoint): sidebar becomes an overlay drawer with backdrop

## SMS/WhatsApp Notifications (Not Yet Implemented)

The system currently only supports email notifications via Nodemailer. SMS and WhatsApp channels are not implemented but the notification rules system is designed to be extensible.
