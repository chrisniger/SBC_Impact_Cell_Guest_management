# API Documentation

## Base URL

- Development: `http://localhost:3001/api` (proxied via Vite from `http://localhost:3000/api`)
- Production: `https://<domain>/api` (same origin as frontend)

## Authentication

- **Required:** `Authorization: Bearer <jwt_token>` header for protected endpoints
- **Cookie fallback:** `token=<jwt_token>` cookie
- **Role override:** `X-Active-Role` header (optional, overrides `req.user.role` for the request)

## Response Format

### Success
```json
{ ...data }
```

### Error
```json
{ "error": "Error message string" }
```

### 204 No Content
Returned by some DELETE operations with no body.

---

## Auth Routes

### `POST /api/auth/login`
**Auth:** None

**Request Body:**
```json
{ "username": "string", "password": "string" }
```
- `username` accepts username or email

**Response (200):**
```json
{
  "token": "jwt_string",
  "user": {
    "id": "uuid",
    "fullName": "string",
    "email": "string",
    "phone": "string|null",
    "username": "string",
    "role": "Role",
    "roles": ["Role", ...],
    "impactCellId": "string|null",
    "active": true,
    "createdAt": "iso_string",
    "updatedAt": "iso_string"
  }
}
```

**Errors:** 401 `{ "error": "Invalid credentials" }`

---

### `POST /api/auth/logout`
**Auth:** None

**Response (200):** `{ "ok": true }`
- Stateless — client discards the token

---

### `GET /api/auth/me`
**Auth:** Required (Bearer token or cookie)

**Response (200):**
```json
{
  "id": "uuid",
  "fullName": "string",
  "email": "string",
  "phone": "string|null",
  "username": "string",
  "role": "Role",
  "roles": ["Role", ...],
  "impactCellId": "string|null",
  "active": true,
  "createdAt": "iso_string",
  "updatedAt": "iso_string"
}
```
- `roles` is normalized (combines `role` + `roles` fields)
- `passwordHash` is excluded from response

**Errors:** 401 `{ "error": "Unauthorized" }`, 404 `{ "error": "Not found" }`

---

### `PUT /api/auth/profile`
**Auth:** Required

**Request Body:**
```json
{
  "fullName": "string",
  "email": "string",
  "phone": "string"
}
```

**Response (200):** Updated user object (same shape as `/me`)

---

### `POST /api/auth/change-password`
**Auth:** Required

**Request Body:**
```json
{
  "currentPassword": "string",
  "newPassword": "string"
}
```

**Response (200):** `{ "ok": true }`

**Errors:** 401 `{ "error": "Current password incorrect" }`

---

### `POST /api/auth/forgot-password`
**Auth:** None

**Request Body:**
```json
{ "email": "string" }
```

**Response (200):** `{ "ok": true }` (always returns ok, even if email not found — security best practice)

**Behavior:**
- Looks up user by email
- Generates `crypto.randomBytes(32)` hex token
- Creates `PasswordReset` record with 1-hour expiry
- Sends email with reset link: `<baseUrl>/reset-password?token=<token>`
- Base URL derived from: `APP_URL` → `FRONTEND_URL` → `req.headers.origin` → `req.protocol://req.get("host")`

---

### `POST /api/auth/reset-password`
**Auth:** None

**Request Body:**
```json
{
  "token": "string",
  "newPassword": "string"
}
```

**Response (200):** `{ "ok": true }`

**Behavior:**
- Finds PasswordReset by token
- Validates not expired ← `expiresAt`
- Validates not used ← `used: false`
- Hashes new password with bcrypt (10 rounds)
- Updates user password in transaction
- Marks reset token as used

**Errors:** 400 `{ "error": "Invalid or expired token" }`

---

## User Routes

All require authentication (`requireAuth` router-level middleware).

### `GET /api/users`
**Auth:** Required (all authenticated users)

**Roles allowed:** All authenticated users

**Response (200):**
```json
[
  {
    "id": "uuid",
    "fullName": "string",
    "email": "string",
    "phone": "string|null",
    "username": "string",
    "role": "Role",
    "roles": ["Role", ...],
    "impactCellId": "string|null",
    "active": true,
    "createdAt": "iso_string",
    "updatedAt": "iso_string"
  },
  ...
]
```

---

### `POST /api/users`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Request Body:**
```json
{
  "fullName": "string (required)",
  "email": "string (required)",
  "phone": "string (optional)",
  "username": "string (required)",
  "password": "string (required)",
  "role": "Role (required)",
  "roles": ["Role", ...] "(optional, defaults to [role])",
  "impactCellId": "string|null (optional)"
}
```

**Response (201):** Created user object

---

### `PUT /api/users/:id`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Request Body:** Partial update — any subset of:
```json
{
  "fullName": "string",
  "email": "string",
  "phone": "string",
  "username": "string",
  "password": "string (will hash if provided)",
  "role": "Role",
  "roles": ["Role", ...],
  "active": true|false,
  "impactCellId": "string|null"
}
```

**Response (200):** Updated user object

---

### `DELETE /api/users/:id`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Response (200):** `{ "id": "uuid", "active": false }`
- Note: This is a **deactivation**, not a hard delete. Sets `active = false`.

---

## Guest Routes

All require authentication (`requireAuth` router-level middleware).

### `GET /api/guests`
**Auth:** Required

**Roles allowed:** All authenticated roles

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `q` | string | Search query (matches guestName, phone, address) |
| `status` | string | Filter by contactedStatus (enum value) |
| `joinWhen` | string | Filter by joinWhen (enum value) |

**Data Scoping:**
- Users with role `FollowUpOfficer`, `Follow_UP`, or `Impact_Leaders` see only their assigned guests (`followOfficerId = req.user.sub`)
- All other roles see all guests

**Response (200):**
```json
[
  {
    "id": "uuid",
    "date": "iso_string",
    "event": "string|null",
    "eventOther": "string|null",
    "guestName": "string",
    "gender": "string|null",
    "maritalStatus": "string|null",
    "phone": "string|null",
    "address": "string|null",
    "age": "int|null",
    "nearestImpactCell": "string|null",
    "impactStatus": "string|null",
    "contactedStatus": "ContactedStatus",
    "joinWhen": "JoinWhen|null",
    "daysAvailable": "string|null (comma-separated)",
    "comments": "text|null",
    "visited": "boolean",
    "visitedAt": "string|null",
    "indicatedToJoin": "string|null",
    "visitationStatus": "string|null",
    "feedback": "text|null",
    "followUpStatus": "string|null",
    "followUpContacts": "json|null",
    "source": "string|null",
    "followOfficerId": "string|null",
    "followOfficer": { "fullName": "string" },
    "createdAt": "iso_string",
    "updatedAt": "iso_string"
  },
  ...
]
```

---

### `GET /api/guests/:id`
**Auth:** Required

**Roles allowed:** All authenticated roles

**Response (200):** Single guest object (with full `followOfficer` relation)

**Errors:** 404 `{ "error": "Not found" }`

---

### `POST /api/guests`
**Auth:** Required

**Roles allowed:** `Administrator`, `FollowUpOfficer`, `Follow_UP`

**Request Body:**
```json
{
  "date": "iso_string|optional",
  "event": "string|optional (COMBINED SERVICE|CHURCH 1|CHURCH 2|OTHER)",
  "eventOther": "string|optional",
  "followOfficerId": "string|optional",
  "guestName": "string (required)",
  "gender": "string|optional",
  "maritalStatus": "string|optional",
  "phone": "string|optional",
  "address": "string|optional",
  "age": "int|optional",
  "nearestImpactCell": "string|optional",
  "impactStatus": "string|optional",
  "contactedStatus": "string|optional",
  "joinWhen": "string|optional",
  "daysAvailable": "string|optional (comma-separated)",
  "comments": "string|optional",
  "visited": "boolean|optional",
  "visitedAt": "string|optional",
  "indicatedToJoin": "string|optional",
  "visitationStatus": "string|optional (Visited|Pending)",
  "feedback": "string|optional",
  "followUpStatus": "string|optional",
  "followUpContacts": "array|optional"
}
```

**Response (201):** Created guest object (with followOfficer relation)

---

### `PUT /api/guests/:id`
**Auth:** Required

**Roles allowed:** `Administrator`, `FollowUpOfficer`, `Follow_UP`

**Edit Guard:**
- Administrators: can edit any guest
- Assigned-only roles (`FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`): can only edit their own guests (`followOfficerId === req.user.sub`)

**Request Body:** Same shape as POST (partial update)

**Response (200):** Updated guest object

**Errors:** 403 `{ "error": "Forbidden" }`, 404 `{ "error": "Not found" }`

---

### `DELETE /api/guests/:id`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Response (200):** `{ "ok": true }`

---

### `POST /api/guests/:id/reassign`
**Auth:** Required

**Roles allowed:** `Administrator`, `Follow_UP_Admin`

**Request Body:**
```json
{ "officerId": "string (user UUID)" }
```

**Validation:**
- Administrator: can reassign to any role in `ASSIGNABLE_FOLLOW_UP_ROLES` (`FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`)
- Follow_UP_Admin: can only reassign to `Follow_UP`
- Target officer must be `active: true`

**Notification:**
- If target officer role is `Impact_Leaders`, sends email notification via `GUEST_ASSIGNED_TO_IMPACT_LEADER` action

**Response (200):** Updated guest with followOfficer relation

**Errors:** 400 `{ "error": "Guest can only be reassigned to an active permitted Follow_UP user" }`

---

## CSV Routes

### `POST /api/csv/upload`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Content-Type:** `multipart/form-data`

**Request:**
| Field | Type | Description |
|-------|------|-------------|
| file | File | CSV file (max 5MB, memory storage) |

**CSV Column Mapping:**
| CSV Header(s) | Target Field |
|---------------|-------------|
| Guest Name, guestName, Name, Full Name, fullName | `guestName` |
| Phone, phone, Phone Number, phoneNumber, Mobile, mobile | `phone` |
| Event, EVENT, event | `event` |
| Event Other, eventOther, EventOther | `eventOther` |
| Address, address, Residential Address, ResidentialAddress | `address` |
| Gender, gender | `gender` |
| Marital Status, MaritalStatus, maritalStatus | `maritalStatus` |
| Age, age, Age (years), ageYears | `age` |
| Nearest Impact Cell, NearestImpactCell, Impact Cell, ImpactCell, impactCell | `nearestImpactCell` |
| Impact Status, impactStatus, ImpactStatus | `impactStatus` |
| Contacted Status, ContactedStatus, contactedStatus | `contactedStatus` |
| Join When, JoinWhen, joinWhen | `joinWhen` |
| Follow Up Status, FollowUpStatus, followUpStatus | `followUpStatus` |
| Follow Officer, Follow Up Officer, Follow_UP Officer, FollowUPOfficer, followOfficer, Assigned Officer | `followOfficerId` (resolved via name→ID) |

**Status Normalization (CSV):**
- `contactedStatus`: mapped via `CONTACTED_STATUS_MAP` (case-insensitive)
- `followUpStatus`: mapped via `FOLLOW_UP_STATUS_MAP` (case-insensitive)
- `joinWhen`: mapped via `JOIN_WHEN_MAP` (case-insensitive)

**Duplicate Detection:**
- Checks `phone` against existing guest phone numbers
- Duplicate phone → skipped (added to `skippedDetails`)

**Response (200):**
```json
{
  "created": "number",
  "skipped": "number",
  "skippedDetails": [{"row": {...}, "reason": "duplicate phone"}, ...]
}
```

**Errors:** 400 `{ "error": "CSV file required" }` or `{ "error": "error message" }`

---

## Report Routes

### `GET /api/reports/dashboard`
**Auth:** Required

**Roles allowed:** All authenticated roles

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| month | string | Filter by month (format: `YYYY-MM`) |

**Data Scoping:**
- Assigned-only roles (`FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`): dashboard data scoped to their assigned guests
- All other roles: see all data

**Response (200):**
```json
{
  "stats": {
    "pendingContacts": "number",
    "totalCalls": "number",
    "visited": "number",
    "pendingVisit": "number"
  },
  "byStatus": [{"contactedStatus": "string", "count": "number"}, ...],
  "byJoin": [{"joinWhen": "string", "count": "number"}, ...],
  "byFollowUpStatus": [{"status": "string", "count": "number"}, ...],
  "byEvent": [{"event": "string", "eventOther": "string|null", "count": "number"}, ...],
  "monthlyGuests": [{"month": "string (YYYY-MM)", "count": "number"}, ...]
}
```

**Fallback on Error:** Returns empty data structure with `warning` field.

---

### `GET /api/reports/audit`
**Auth:** Required

**Roles allowed:** `Administrator`, `Supervisor`, `Follow_UP_Admin`, `Follow_UP_View_Only`, `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report`

**Response (200):**
```json
[
  {
    "id": "uuid",
    "at": "iso_string",
    "actor": "string",
    "action": "string",
    "detail": "string"
  },
  ...
]
```
- Limited to 500 most recent entries
- Ordered by `at` descending

---

### `GET /api/reports/officer-performance`
**Auth:** Required

**Roles allowed:** `Administrator`, `Supervisor`, `Follow_UP_Admin`, `Follow_UP_View_Only`, `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report`

**Response (200):**
```json
[
  {
    "id": "uuid",
    "name": "string",
    "total": "number (guest count)"
  },
  ...
]
```
- Only includes users with roles `FollowUpOfficer` or `Follow_UP`

---

## Impact Routes

### `GET /api/impact/public/cells`
**Auth:** None (public)

**Response (200):**
```json
[
  { "id": "uuid", "name": "string" },
  ...
]
```
- Auto-seeds hardcoded impact cell names on every request
- Sorted by name ascending

---

### `POST /api/impact/public/join`
**Auth:** None (public)

**Request Body:**
```json
{
  "event": "string (COMBINED SERVICE|CHURCH 1|CHURCH 2|OTHER)",
  "eventOther": "string (required if event=OTHER)",
  "name": "string (required)",
  "phone": "string (required)",
  "gender": "string (Male|Female, required)",
  "impactCellId": "string (required, valid cell UUID)"
}
```

**Behavior:**
- Creates a Guest record with `source: "PUBLIC_IMPACT_JOIN"`
- Sets `contactedStatus: "No"` and `followUpStatus: "NOT CONTACTED"`
- Assigns to the first active Impact Leader of the selected cell (if any)

**Response (201):**
```json
{
  "id": "uuid",
  "assigned": "boolean (whether an Impact Leader was found)"
}
```

**Errors:** 400 (various validation messages)

---

### `GET /api/impact/cells`
**Auth:** Required

**Roles allowed:** All authenticated roles

**Response (200):**
```json
[
  {
    "id": "uuid",
    "name": "string",
    "phone": "string",
    "address": "string",
    "leader": "string (first active leader's fullName)",
    "leaderId": "string (first active leader's id)"
  },
  ...
]
```
- Auto-seeds hardcoded impact cell names on every request

---

### `POST /api/impact/cells`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Request Body:**
```json
{
  "name": "string (required)",
  "phone": "string|optional",
  "address": "string|optional"
}
```

**Response (201):** Created cell object (with leader info)

---

### `PUT /api/impact/cells/:id`
**Auth:** Required

**Roles allowed:** `Administrator` only

**Request Body:** Partial update:
```json
{
  "name": "string|optional",
  "phone": "string|optional",
  "address": "string|optional"
}
```

**Response (200):** Updated cell object

---

### `GET /api/impact/submissions`
**Auth:** Required

**Roles allowed:** `Administrator`, `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report`

**Query Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| type | string | Filter by type (`member`, `report`, `childbirth`, `soul`) |

**Data Scoping:**
- `Impact_Leaders`: sees only their own submissions (`userId = req.user.sub`)

**Response (200):**
```json
[
  {
    "id": "uuid",
    "type": "string",
    "data": "json_object",
    "impactCell": "string (cell name)",
    "user": "string (user fullName)",
    "createdAt": "iso_string"
  },
  ...
]
```

---

### `POST /api/impact/submissions`
**Auth:** Required

**Roles allowed:** `Administrator`, `Impact_Leaders`

**Request Body:**
```json
{
  "type": "string (member|report|childbirth|soul, required)",
  "data": "json_object (required)",
  "impactCellId": "string|optional (required for type=report)"
}
```

**Behavior:**
- For `type=report`: extracts `fellowshipDateKey` from data (tries "FELLOWSHIP DATE", "Fellowship Date", "fellowshipDate", "fellowship_date")
- For `type=report`: checks for duplicate (same cell + same fellowship date) → returns 409
- For `Impact_Leaders`: uses their own `impactCellId` if not provided

**Response (201):** Created submission object

**Errors:** 400 (invalid type, missing cell/date), 409 (duplicate report)

---

### `GET /api/impact/summary`
**Auth:** Required

**Roles allowed:** `Administrator`, `Impact_Leaders`, `Impact_Cell_Admin`, `Impact_Cell_Report`

**Data Scoping:**
- `Impact_Leaders`: scoped to their own data (pending follow-up from their assigned guests, member/soul submissions from their userId)

**Response (200):**
```json
{
  "pendingFollowUp": "number",
  "totalMembers": "number",
  "totalSouls": "number"
}
```

---

## Notification Routes

All require authentication + `Administrator` role (router-level middleware).

### `GET /api/notifications/actions`
**Auth:** Required + Admin

**Response (200):**
```json
[
  { "value": "GUEST_ASSIGNED_TO_IMPACT_LEADER", "label": "Guest assigned to Impact Leader" }
]
```
- Currently only one action available

---

### `GET /api/notifications/smtp`
**Auth:** Required + Admin

**Response (200):**
```json
{
  "host": "string",
  "port": "number",
  "secure": "boolean",
  "user": "string",
  "pass": "******** (masked) or empty",
  "fromEmail": "string",
  "fromName": "string",
  "configured": "boolean"
}
```
- Falls back to environment variables if no DB record exists
- Password is always masked as `********` in response

---

### `PUT /api/notifications/smtp`
**Auth:** Required + Admin

**Request Body:**
```json
{
  "host": "string|optional",
  "port": "number|optional",
  "secure": "boolean|optional",
  "user": "string|optional",
  "pass": "string|optional (ignored if '********')",
  "fromEmail": "string|optional",
  "fromName": "string|optional"
}
```

**Response (200):** Updated SMTP settings (password masked)

**Behavior:**
- Creates record if not exists (upsert with id "singleton")
- Ignores password update if value is `"********"` (masked placeholder)

---

### `GET /api/notifications/rules`
**Auth:** Required + Admin

**Response (200):**
```json
[
  {
    "id": "uuid",
    "action": "string",
    "email": "string",
    "active": true,
    "createdAt": "iso_string",
    "updatedAt": "iso_string"
  },
  ...
]
```

---

### `POST /api/notifications/rules`
**Auth:** Required + Admin

**Request Body:**
```json
{
  "action": "string (must be valid action from /actions)",
  "email": "string (valid email required)",
  "active": "boolean (default: true)"
}
```

**Response (201):** Created notification rule

---

### `PUT /api/notifications/rules/:id`
**Auth:** Required + Admin

**Request Body:** Partial update:
```json
{
  "action": "string|optional",
  "email": "string|optional",
  "active": "boolean|optional"
}
```

**Response (200):** Updated notification rule

---

### `DELETE /api/notifications/rules/:id`
**Auth:** Required + Admin

**Response (200):** `{ "ok": true }`

---

### `POST /api/notifications/test`
**Auth:** Required + Admin

**Request Body:**
```json
{ "email": "string (required)" }
```

**Response (200):**
```json
{
  "ok": true,
  "configured": "boolean"
}
```

---

## Role Enum Values

```
Administrator
Supervisor
FollowUpOfficer
Follow_UP
Follow_UP_Admin
Follow_UP_View_Only
Impact_Leaders
Impact_Cell_Admin
Impact_Cell_Report
```

## ContactedStatus Enum Values

```
No
Yes
AvailableForVisit
NotAvailableForVisit
NotReachable
WrongNumber
Others
```

## JoinWhen Enum Values

```
FirstTimer
NewMember
OldMember
```
