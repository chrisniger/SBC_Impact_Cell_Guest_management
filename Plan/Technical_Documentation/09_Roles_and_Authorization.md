# Roles and Authorization

## Overview

The system has **9 roles** organized into logical groups. Authorization is enforced at two levels:

1. **Backend:** Middleware chain (`requireRole()`) protects route endpoints
2. **Frontend:** Role-based UI rendering (nav items, buttons, sections)

The frontend role checks are **presentation-layer filters only** — they do not replace backend authorization.

---

## Role Definitions

### Backend Constants (`server/lib/roles.js`)

```javascript
const ALL_ROLES = [
  "Administrator",
  "Supervisor",
  "FollowUpOfficer",
  "Follow_UP",
  "Follow_UP_Admin",
  "Follow_UP_View_Only",
  "Impact_Leaders",
  "Impact_Cell_Admin",
  "Impact_Cell_Report",
]
```

### Frontend Constants (`src/lib/roles.ts`)

```typescript
export const ROLES: Role[] = [
  "Follow UP Officer",     // Note: different display name
  "Administrator",
  "Supervisor",
  "Follow_UP",
  "Follow_UP Admin",       // Note: different display name
  "Follow_UP_View_Only",
  "Impact_Leaders",
  "Impact_Cell_Admin",
  "Impact_Cell_Report",
]
```

### Role Display Name Mapping

| DB/API Value | Frontend Display |
|-------------|-----------------|
| Administrator | Administrator |
| Supervisor | Supervisor |
| FollowUpOfficer | Follow UP Officer |
| Follow_UP | Follow_UP |
| Follow_UP_Admin | Follow_UP Admin |
| Follow_UP_View_Only | Follow_UP_View_Only |
| Impact_Leaders | Impact_Leaders |
| Impact_Cell_Admin | Impact_Cell_Admin |
| Impact_Cell_Report | Impact_Cell_Report |

---

## Role Groups

### Backend Groups

| Group | Roles | Purpose |
|-------|-------|---------|
| `ADMIN_ROLES` | Administrator | System administration |
| `FOLLOW_UP_ROLES` | FollowUpOfficer, Follow_UP | Guest follow-up operations |
| `ASSIGNABLE_FOLLOW_UP_ROLES` | FollowUpOfficer, Follow_UP, Impact_Leaders | Can be assigned guests |
| `REASSIGN_ROLES` | Administrator, Follow_UP_Admin | Can reassign guests |
| `VIEW_ONLY_ROLES` | Supervisor, Follow_UP_View_Only, Impact_Cell_Report | Read-only access |
| `REPORT_ROLES` | Administrator, Supervisor, Follow_UP_Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report | Report/audit access |

### Frontend Groups (`src/lib/roles.ts`)

| Group | Roles |
|-------|-------|
| `ADMIN_ROLES` | Administrator |
| `FOLLOW_UP_ROLES` | Follow UP Officer, Follow_UP |
| `ASSIGNABLE_FOLLOW_UP_ROLES` | Follow UP Officer, Follow_UP, Impact_Leaders |
| `REASSIGN_ROLES` | Administrator, Follow_UP Admin |
| `ASSIGNED_ONLY_ROLES` | Follow UP Officer, Follow_UP, Impact_Leaders |
| `REASSIGN_ONLY_ROLES` | Follow_UP Admin |
| `VIEW_ONLY_ROLES` | Supervisor, Follow_UP_View_Only, Impact_Cell_Report |
| `FOLLOW_UP_TEAM_ROLES` | Follow_UP, Follow_UP Admin, Follow_UP_View_Only |
| `IMPACT_CELL_ROLES` | Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report |
| `REPORT_ROLES` | Administrator, Supervisor, Follow_UP Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report |

---

## Helper Functions

### Backend (`server/lib/roles.js`)

| Function | Logic |
|----------|-------|
| `hasRole(role, roles)` | Checks if role exists in roles array |
| `normalizeRoles(userOrRoles)` | Combines `role` + `roles` fields into single deduplicated array, always includes primary role |
| `isAdminRole(role)` | role in ADMIN_ROLES |
| `isFollowUpRole(role)` | role in FOLLOW_UP_ROLES |
| `isReassignRole(role)` | role in REASSIGN_ROLES |
| `isAssignedOnlyRole(role)` | role === "FollowUpOfficer" \|\| "Follow_UP" \|\| "Impact_Leaders" |
| `isViewOnlyRole(role)` | role in VIEW_ONLY_ROLES |
| `canEditGuests(role)` | isAdminRole \|\| isFollowUpRole |

### Frontend (`src/lib/roles.ts`)

| Function | Equivalent Backend |
|----------|-------------------|
| `isAdminRole(role)` | Yes |
| `isFollowUpRole(role)` | Yes |
| `isAssignableFollowUpRole(role)` | Yes |
| `isReassignRole(role)` | Yes |
| `isReassignOnlyRole(role)` | No backend equivalent |
| `isAssignedOnlyRole(role)` | Yes |
| `isViewOnlyRole(role)` | Yes |
| `isFollowUpTeamRole(role)` | No backend equivalent |
| `isImpactCellRole(role)` | No backend equivalent |

---

## normalizeRoles Function

```javascript
function normalizeRoles(userOrRoles) {
  const raw = Array.isArray(userOrRoles)
    ? userOrRoles
    : userOrRoles?.roles
  const roles = Array.isArray(raw)
    ? raw.filter((role) => ALL_ROLES.includes(role))
    : []
  const primary = Array.isArray(userOrRoles)
    ? roles[0]
    : userOrRoles?.role
  if (primary && ALL_ROLES.includes(primary) && !roles.includes(primary))
    roles.unshift(primary)
  return roles.length ? roles : ["FollowUpOfficer"]
}
```

This function:
1. Extracts roles from either a raw array or a user object with `role` + `roles` fields
2. Filters to valid roles only
3. Ensures primary role is always included
4. Falls back to `["FollowUpOfficer"]` if no valid roles found

**Used in:** JWT signing (`auth.controller.js`), user serialization (`user.controller.js`), and `requireAuth` middleware.

---

## Backend Authorization

### requireRole Middleware

```javascript
function requireRole(...roles) {
  return (req, res, next) => {
    if (!req.user || !roles.includes(req.user.role)) {
      return res.status(403).json({ error: "Forbidden" })
    }
    next()
  }
}
```

### Route-Level Authorization

| Route | Middleware |
|-------|-----------|
| All auth routes except /me, /profile, /change-password | No auth |
| GET /api/users | requireAuth only |
| POST/PUT/DELETE /api/users | requireAuth + requireRole("Administrator") |
| GET/POST/PUT /api/guests | requireAuth (with role-specific controller guards) |
| DELETE /api/guests/:id | requireAuth + requireRole("Administrator") |
| POST /api/guests/:id/reassign | requireAuth + requireRole("Administrator", "Follow_UP_Admin") |
| POST /api/csv/upload | requireAuth + requireRole("Administrator") |
| GET /api/reports/dashboard | requireAuth only |
| GET /api/reports/audit | requireAuth + requireRole(...REPORT_ROLES) |
| GET /api/reports/officer-performance | requireAuth + requireRole(...REPORT_ROLES) |
| GET /api/impact/cells | requireAuth only |
| POST/PUT /api/impact/cells | requireAuth + requireRole("Administrator") |
| GET /api/impact/submissions | requireAuth + requireRole("Administrator", "Impact_Leaders", "Impact_Cell_Admin", "Impact_Cell_Report") |
| POST /api/impact/submissions | requireAuth + requireRole("Administrator", "Impact_Leaders") |
| GET /api/impact/summary | requireAuth + requireRole("Administrator", "Impact_Leaders", "Impact_Cell_Admin", "Impact_Cell_Report") |
| All notification routes | requireAuth + requireRole("Administrator") |

### Controller-Level Authorization

Some controllers implement additional authorization logic beyond route middleware:

**Guest Controller:**
```javascript
function canEdit(user, guest) {
  if (isAdminRole(user.role)) return true
  if (isAssignedOnlyRole(user.role)) return guest.followOfficerId === user.sub
  return false
}
```
- Administrators: can edit any guest
- Assigned-only roles: can only edit their own guests

**Reassign Controller:**
- `Follow_UP_Admin` can only reassign to `Follow_UP` role users
- `Administrator` can reassign to any `ASSIGNABLE_FOLLOW_UP_ROLES` role user

---

## Frontend Role-Based Rendering

### Nav Items

The sidebar navigation is filtered by role:

```typescript
const NAV = [
  { to: "/guests", label: "Guests", roles: [...REASSIGN_ROLES, ...FOLLOW_UP_ROLES, ...VIEW_ONLY_ROLES, "Impact_Leaders"] },
  { to: "/users", label: "Users", roles: ADMIN_ROLES },
  { to: "/settings", label: "Settings", roles: ADMIN_ROLES },
  // ...
]
```

Roles receive different nav structures:
- **Standard roles**: see main nav items
- **Impact Leaders**: see Impact Leader-specific nav (Members Data, Submit Report, Childbirth Notice, Souls Registration, Soul Search, My Reports)
- **Impact Cell roles**: see Impact Cell Admin nav section (Overview, Impact Members, Assigned Guest, Child Naming, Weekly Reports)

### Component-Level Checks

Throughout the application, UI elements are conditionally rendered based on role:

| UI Element | Role Check |
|------------|------------|
| Add Guest button | `isAdminRole(user?.role)` — Administrators only |
| Delete Guest button | `isAdminRole(user?.role)` — Administrators only |
| Edit Guest button | Allowed for admin + follow-up roles |
| Reassign button | `isReassignRole(user?.role)` |
| User management | `isAdminRole(user?.role)` |
| Settings page | `isAdminRole(user?.role)` |
| Import CSV page | `isAdminRole(user?.role)` |
| Notifications page | `isAdminRole(user?.role)` |
| Impact Cell management | `isAdminRole(user?.role)` |
| Audit log | `REPORT_ROLES.includes(user?.role)` |
| Dashboard data scope | `isAssignedOnlyRole(user?.role)` — scoped to own guests |
| CSV Downloads section | Separate role checks per download type |

### Data Scoping

Role affects **what data** users see:

| Role | Guest Data Scope |
|------|-----------------|
| Administrator | All guests |
| Supervisor | All guests |
| Follow_UP_Admin | All guests |
| Follow_UP_View_Only | All guests (read-only) |
| Impact_Cell_Admin | All guests |
| Impact_Cell_Report | All guests (read-only) |
| FollowUpOfficer | Only own assigned guests |
| Follow_UP | Only own assigned guests |
| Impact_Leaders | Only own assigned guests |

### Dashboard Role-Specific Rendering

The `dashboard.tsx` file (2226 lines) renders completely different UI based on the current role:
- **Follow UP Officer / Follow_UP**: Analytics dashboard with charts, KPI cards, month filter
- **Follow_UP Admin / Follow_UP_View_Only**: Same as Follow UP with all data
- **Administrator / Supervisor**: Analytics dashboard with all data
- **Impact_Leaders**: Forms for members, reports, childbirth, souls + soul search + reports view
- **Impact_Cell_Admin / Impact_Cell_Report**: Overview, members, assigned guests, child naming, reports

### Role Switcher UI

Users with multiple roles see a "Switch dashboard" dropdown in the header:
```
User Avatar ▼
├── Switch dashboard
│   ├── Administrator  ◄ Active
│   └── Supervisor
├── Sign out
```
