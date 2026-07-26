# 02 — User Roles and Permissions

## Role Definitions (9 Roles)

### 1. Administrator
- **Enum value:** `Administrator`
- **Responsibilities:** Full system administration
- **Accessible pages:** All pages (Dashboard, Guests, Users, Impact Cell, Settings, Notifications, Import CSV, Audit Log, Profile)
- **Allowed actions:** Create, Read, Update, Delete all records; Import/Export CSV; Manage users; Configure SMTP; Manage notification rules; View all dashboards; Bulk delete guests
- **Restrictions:** None

### 2. Supervisor
- **Enum value:** `Supervisor`
- **Responsibilities:** View-only oversight of system
- **Accessible pages:** Dashboard, Guests, Audit Log, Profile
- **Allowed actions:** Read-only viewing of all data; View audit log
- **Restrictions:** Cannot create, edit, or delete any records

### 3. Follow UP Officer
- **Enum value:** `FollowUpOfficer`
- **Responsibilities:** Assigned guest management and follow-up
- **Accessible pages:** Dashboard, Guests (assigned only), Profile
- **Allowed actions:** View and edit only own assigned guests; Update contacted status, visitation details, comments; View dashboard with personal analytics
- **Restrictions:** Assigned-only (sees only guests where `followOfficerId` matches own ID); Cannot delete; Cannot reassign; Cannot view other officers' guests

### 4. Follow_UP
- **Enum value:** `Follow_UP`
- **Responsibilities:** Team-based follow-up contact tracking
- **Accessible pages:** Dashboard, Guests (assigned only), Profile
- **Allowed actions:** View and edit own assigned guests (read-only for basic fields like guestName, phone, address, gender, maritalStatus, age); Must set Follow Up Status; Can add/update Follow Up Contact sections (max 3)
- **Restrictions:** Assigned-only; Read-only on basic guest fields; Cannot change followOfficer; Cannot delete

### 5. Follow_UP Admin
- **Enum value:** `Follow_UP_Admin`
- **Responsibilities:** Guest reassignment within Follow_UP team
- **Accessible pages:** Dashboard, Guests (full list), Profile
- **Allowed actions:** View full guest list; Reassign guests only to "Follow_UP" role officers; Access Follow_UP Team dashboard tab
- **Restrictions:** Cannot edit guest fields; Cannot delete guests; Can only reassign

### 6. Follow_UP_View_Only
- **Enum value:** `Follow_UP_View_Only`
- **Responsibilities:** View-only access to guest list
- **Accessible pages:** Dashboard, Guests, Audit Log, Profile
- **Allowed actions:** View guest list; View audit log; View Follow_UP Team dashboard
- **Restrictions:** Cannot create, edit, delete, or reassign any records

### 7. Impact_Leaders
- **Enum value:** `Impact_Leaders`
- **Responsibilities:** Impact cell leadership and data submission
- **Accessible pages:** Dashboard (Impact Leader dashboard), Guests (assigned only), Profile
- **Allowed actions:** View own assigned guests; See "Impact Status" field on guests; Submit member data, souls registration, childbirth notice, weekly reports; Search souls; View own submissions ("My Reports"); Access Impact Leader dashboard with 6 tabbed forms
- **Restrictions:** Assigned-only for guests; Cannot delete; Cannot view other cell leaders' data

### 8. Impact_Cell_Admin
- **Enum value:** `Impact_Cell_Admin`
- **Responsibilities:** Impact cell administration
- **Accessible pages:** Dashboard (Impact Cell Admin dashboard), Profile
- **Allowed actions:** View Impact Cell Admin dashboard (5 tabbed sections); Download reports CSV, members data, child notice data; View overview, members, assigned guests, child naming, weekly reports
- **Restrictions:** Read-only approach for report review; Cannot submit data

### 9. Impact_Cell_Report
- **Enum value:** `Impact_Cell_Report`
- **Responsibilities:** Read-only impact cell reporting
- **Accessible pages:** Dashboard (Impact Cell Admin dashboard — read-only), Profile
- **Allowed actions:** Same dashboard access as Impact_Cell_Admin but all sections are read-only
- **Restrictions:** Read-only; Cannot submit or edit any data

## Role Groups

| Group | Roles | Description |
|-------|-------|-------------|
| ADMIN_ROLES | Administrator | Full CRUD access |
| FOLLOW_UP_ROLES | Follow UP Officer, Follow_UP | Can create and edit guests (edit capability) |
| ASSIGNABLE_FOLLOW_UP_ROLES | Follow UP Officer, Follow_UP, Impact_Leaders | Can be assigned as guest officers |
| REASSIGN_ROLES | Administrator, Follow_UP Admin | Can reassign guests |
| ASSIGNED_ONLY_ROLES | Follow UP Officer, Follow_UP, Impact_Leaders | Only see own assigned guests |
| REASSIGN_ONLY_ROLES | Follow_UP Admin | Can only reassign (not edit) |
| VIEW_ONLY_ROLES | Supervisor, Follow_UP_View_Only, Impact_Cell_Report | Read-only access |
| FOLLOW_UP_TEAM_ROLES | Follow_UP, Follow_UP Admin, Follow_UP_View_Only | Follow-up team (team dashboard, status tracking) |
| IMPACT_CELL_ROLES | Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report | Impact cell system access |
| REPORT_ROLES | Administrator, Supervisor, Follow_UP Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report | Can view audit log |

## Accessible Pages per Role

| Page | Admin | Super-visor | Follow UP Officer | Follow_UP | Follow_UP Admin | Follow_UP View Only | Impact Leaders | Impact Cell Admin | Impact Cell Report |
|------|-------|-------------|-------------------|-----------|-----------------|--------------------|----------------|-------------------|--------------------|
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Guests (all) | ✓ | ✓ | ✓* | ✓* | ✓ | ✓ | ✓* | — | — |
| Users | ✓ | — | — | — | — | — | — | — | — |
| Impact Cell | ✓ | — | — | — | — | — | — | — | — |
| Settings | ✓ | — | — | — | — | — | — | — | — |
| Notifications | ✓ | — | — | — | — | — | — | — | — |
| Import CSV | ✓ | — | — | — | — | — | — | — | — |
| Audit Log | ✓ | ✓ | — | — | ✓ | ✓ | ✓ | ✓ | ✓ |
| Profile | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |

\* Assigned-only view

## Page Permissions — Detailed Actions

| Action | Admin | Super-visor | Follow UP Officer | Follow_UP | Follow_UP Admin | Follow_UP View Only | Impact Leaders | Impact Cell Admin | Impact Cell Report |
|--------|-------|-------------|-------------------|-----------|-----------------|--------------------|----------------|-------------------|--------------------|
| Create Guest | ✓ | — | ✓ | ✓ | — | — | — | — | — |
| Edit Guest | ✓ | — | ✓* | ✓* | — | — | ✓* | — | — |
| Delete Guest | ✓ | — | — | — | — | — | — | — | — |
| Bulk Delete | ✓ | — | — | — | — | — | — | — | — |
| Reassign Guest | ✓ | — | — | — | ✓** | — | — | — | — |
| Import CSV | ✓ | — | — | — | — | — | — | — | — |
| Export CSV | ✓ | — | — | — | ✓ | — | — | ✓ | — |
| Manage Users | ✓ | — | — | — | — | — | — | — | — |
| Manage Impact Cells | ✓ | — | — | — | — | — | — | — | — |
| Configure SMTP | ✓ | — | — | — | — | — | — | — | — |
| Manage Notifications | ✓ | — | — | — | — | — | — | — | — |
| Submit Impact Data | — | — | — | — | — | — | ✓ | — | — |

\* Only own assigned guests
\*\* Only to "Follow_UP" role officers

## Role Switching

Users can be assigned multiple roles. The `roles` field stores an array of Role enum values. The system:

1. Stores the active role in `localStorage` under key `cgms.activeRole`
2. Sends active role as `X-Active-Role` header on API requests
3. Displays a "Switch dashboard" dropdown in the user menu (top-right header) when user has >1 role
4. Switching roles updates the `user.role` field client-side and re-renders the appropriate dashboard
5. The primary role (`role` field) is the first role in the `roles` array
