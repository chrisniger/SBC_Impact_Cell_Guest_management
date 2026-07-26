# Data Flow

## End-to-End Data Flow

```
User Action
    │
    ▼
React Component
    │
    ├── Triggers API call via apiClient
    │
    ▼
apiClient (src/lib/api.ts)
    │
    ├── Builds RequestInit with:
    │   ├── Method (GET/POST/PUT/DELETE)
    │   ├── Headers (Content-Type, Authorization, X-Active-Role)
    │   └── Body (JSON.stringify for non-FormData)
    │
    ├── Serializes data (enum values → API values)
    │
    ▼
fetch(`${BASE}${path}`, opts)
    │
    ├── If dev: Vite proxy → http://localhost:3001
    │   └── If prod: same origin (Express serves both)
    │
    ▼
Express.js (server/server.js)
    │
    ├── CORS check
    ├── cookie-parser
    ├── Morgan logging
    ├── JSON body parser (5mb limit)
    │
    ▼
Router (server/routes/)
    │
    ├── requireAuth (JWT verification)
    ├── requireRole (role check)
    │
    ▼
Controller (server/controllers/)
    │
    ├── sanitize() [for mutations]
    ├── Business logic
    ├── Notification dispatch
    │
    ▼
Prisma Client (server/db.js)
    │
    ├── Query building
    ├── Transaction support
    │
    ▼
MySQL Database
    │
    ▼ (Response flows back)
    │
Prisma Result
    │
    ▼
Controller Response
    │
    ├── res.json(data) or res.status(201).json(data)
    │
    ▼
fetch Response
    │
    ├── res.ok check → throw on error
    ├── JSON.parse response body
    │
    ▼
Normalizer (normalizeGuest / normalizeUser)
    │
    ├── Field name mapping (DB → frontend)
    ├── Enum mapping (API → display values)
    ├── Date formatting (ISO → dd-mm-yyyy)
    ├── Array conversion (daysAvailable string → array)
    │
    ▼
React Component State
    │
    ▼
Re-render
```

---

## Auth Header Flow

```
localStorage.getItem("cgms.token")
    │
    ▼
headers.set("Authorization", `Bearer ${token}`)
    │
    ▼
localStorage.getItem("cgms.activeRole")
    │
    ▼
headers.set("X-Active-Role", ROLE_TO[activeRole])
    │
    ▼
fetch()
    │
    ▼
Express: requireAuth middleware
    ├── Extract token (header → cookie)
    ├── jwt.verify(token, JWT_SECRET)
    ├── prisma.user.findUnique(payload.sub)
    ├── normalizeRoles(user) → req.user.roles
    ├── req.headers["x-active-role"] → req.user.role override
    │
    ▼
Route handler uses req.user
```

---

## Response Normalization

### Guest Data Flow

```
Backend (Prisma):
{
  "id": "uuid",
  "guestName": "John Doe",
  "contactedStatus": "AvailableForVisit",
  "joinWhen": "FirstTimer",
  "daysAvailable": "Mon,Wed",
  "date": "2024-03-15T00:00:00.000Z",
  "followOfficer": { "fullName": "Officer Name" },
  "visited": true
}
    │
    ▼
normalizeGuest():
    ├── guestName, phone, address → pickField()
    ├── contactedStatus: "AvailableForVisit" → "Available for Visit" (STATUS_FROM map)
    ├── joinWhen: "FirstTimer" → "First Timer (Last 2 Weeks)" (JOIN_FROM map)
    ├── daysAvailable: "Mon,Wed" → ["Mon", "Wed"]
    ├── date: ISO → "15-03-2024"
    ├── visited: true → "Yes"
    │
    ▼
Frontend (TypeScript):
{
  "id": "uuid",
  "guestName": "John Doe",
  "contactedStatus": "Available for Visit",
  "joinWhen": "First Timer (Last 2 Weeks)",
  "daysAvailable": ["Mon", "Wed"],
  "date": "15-03-2024",
  "followOfficer": "Officer Name",
  "visited": "Yes"
  // ... other normalized fields
}
```

### Serialization (Frontend → Backend)

```
Frontend (form data):
{
  "guestName": "John Doe",
  "contactedStatus": "Available for Visit",
  "joinWhen": "First Timer (Last 2 Weeks)",
  "daysAvailable": ["Mon", "Wed"],
  "visited": "Yes"
}
    │
    ▼
serializeGuest():
    ├── contactedStatus → STATUS_TO → "AvailableForVisit"
    ├── joinWhen → JOIN_TO → "FirstTimer"
    ├── daysAvailable → array.join(",") → "Mon,Wed"
    ├── visited: "Yes" → true
    ├── officer name → officer ID resolution
    ├── Conditional fields: visitationStatus/feedback only sent
    │   when contactedStatus is "Available for Visit"
    │
    ▼
Backend (Prisma):
{
  "guestName": "John Doe",
  "contactedStatus": "AvailableForVisit",
  "joinWhen": "FirstTimer",
  "daysAvailable": "Mon,Wed",
  "visited": true,
  "followOfficerId": "officer-uuid"
}
```

### User Data Flow

```
Backend (Prisma):
{
  "id": "uuid",
  "fullName": "John Doe",
  "role": "FollowUpOfficer",
  "roles": ["FollowUpOfficer", "Impact_Leaders"],
  "active": true
}
    │
    ▼
normalizeUser():
    ├── role: ROLE_FROM["FollowUpOfficer"] → "Follow UP Officer"
    ├── roles: map each with ROLE_FROM
    │
    ▼
Frontend:
{
  "id": "uuid",
  "fullName": "John Doe",
  "role": "Follow UP Officer",
  "roles": ["Follow UP Officer", "Impact_Leaders"],
  "active": true
}
```

---

## Enum Mapping (Complete)

### ContactedStatus

| DB/API Value | Display Value |
|-------------|---------------|
| No | No |
| Yes | Yes |
| AvailableForVisit | Available for Visit |
| NotAvailableForVisit | Not Available for Visit |
| NotReachable | Not Reachable |
| WrongNumber | Wrong Number |
| Others | Others |

### JoinWhen

| DB/API Value | Display Value |
|-------------|---------------|
| FirstTimer | First Timer (Last 2 Weeks) |
| NewMember | New Members (Last 6 Months) |
| OldMember | Old Members |

### Role

| DB/API Value | Display Value |
|-------------|---------------|
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

## CSV Import Data Flow

```
File Upload (FormData)
    │
    ▼
Multer (memoryStorage, 5MB limit)
    │
    ▼
csv-parse/sync (columns: true, skip_empty_lines, trim)
    │
    ▼
Row Processing:
    ├── pickField(row, ...keys) → flexible column name matching
    ├── Phone deduplication (check against existing DB)
    ├── Officer name → Officer ID (via officer lookup)
    ├── Status normalization (case-insensitive maps)
    └── Age, Event, etc. direct mapping
    │
    ▼
Prisma guest.createMany({ data: toCreate })
    │
    ▼
Response: { created: N, skipped: M, skippedDetails: [...] }
```

---

## Dashboard Data Flow

```
GET /api/reports/dashboard?month=2024-03
    │
    ▼
Controller:
    ├── Build WHERE clause (role-scoped + month filter)
    ├── Parallel queries:
    │   ├── guest.count(contactedStatus: "No")
    │   ├── guest.count(NOT contactedStatus: "No")
    │   ├── guest.count(visited: true)
    │   ├── guest.count(AvailableForVisit + !visited)
    │   ├── guest.groupBy(contactedStatus)
    │   ├── guest.groupBy(joinWhen)
    │   ├── guest.groupBy(followUpStatus) [month-scoped]
    │   ├── guest.groupBy(event) [month-scoped]
    │   └── Raw SQL: GROUP BY DATE_FORMAT(date, '%Y-%m')
    │
    ▼
Response → Frontend
    │
    ▼
Charts (Recharts):
    ├── BarChart (byStatus)
    ├── PieChart/Donut (byJoin)
    ├── AreaChart (monthlyGuests)
    ├── BarChart (byFollowUpStatus)
    └── BarChart (byEvent)
```

---

## Error Handling Flow

```
Frontend:
    ├── fetch receives non-ok response
    ├── readJson → extract error field
    ├── throw new Error(err.error || "Request failed")
    │
    ▼
Component:
    ├── try/catch block
    ├── toast.error(err.message)
    └── (optional) setError state

Backend:
    ├── Controller catches errors
    ├── Returns res.status(err.status || 500).json({ error: err.message })
    └── Unhandled errors → global errorHandler middleware
```

---

## Date Serialization

| Direction | Format | Example |
|-----------|--------|---------|
| DB → API | ISO 8601 | `2024-03-15T00:00:00.000Z` |
| API → Frontend | DD-MM-YYYY | `15-03-2024` |
| Frontend → API | ISO 8601 | `2024-03-15T00:00:00.000Z` |
| Frontend display | DD-MM-YYYY | `15-03-2024` |

Helper functions:
```typescript
function isoToDDMMYYYY(iso: string): string  // API → Frontend
function ddmmyyyyToISO(s: string): string    // Frontend → API
```
