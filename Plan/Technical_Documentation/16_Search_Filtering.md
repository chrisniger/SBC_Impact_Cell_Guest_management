# Search and Filtering

## Overview

Search and filtering is implemented **client-side** in most cases. The backend provides optional query parameters for limited server-side filtering on the guest list endpoint. All other filtering is done in-memory on the fetched data.

---

## Guest List Search

**Location:** `src/routes/_authenticated/guests.tsx`

### Backend Filtering (`GET /api/guests`)

Query parameters:
| Param | Type | Description |
|-------|------|-------------|
| `q` | string | Search query (matches guestName, phone, address) |
| `status` | string | Filter by contactedStatus (exact match) |
| `joinWhen` | string | Filter by joinWhen (exact match) |

```javascript
// Backend query building:
if (q) {
  where.OR = [
    { guestName: { contains: q } },
    { phone: { contains: q } },
    { address: { contains: q } },
  ]
}
```

### Client-Side Filtering

After fetching all guests, additional filtering is applied:

**Search query:**
```typescript
if (q.trim()) {
  const s = q.toLowerCase()
  list = list.filter((g) =>
    g.guestName.toLowerCase().includes(s) ||
    g.phone.toLowerCase().includes(s) ||
    g.followOfficer.toLowerCase().includes(s) ||
    g.address.toLowerCase().includes(s)
  )
}
```

**Status filter:**
```typescript
if (statusFilter !== "all") {
  list = list.filter((g) => g.contactedStatus === statusFilter)
}
```

**Join when filter:**
```typescript
if (joinFilter !== "all") {
  list = list.filter((g) => g.joinWhen === joinFilter)
}
```

### Sorting (Follow UP Roles Only)
For `isAssignedOnlyRole` and `isFollowUpTeamRole` roles, guests are sorted by follow-up priority:
```typescript
const priority = (status) => {
  if (status === "NOT CONTACTED" || !status) return 0
  if (status === "CONTACTED") return 1
  return 2
}
list = [...list].sort((a, b) => priority(a.followUpStatus) - priority(b.followUpStatus))
```

This ensures "NOT CONTACTED" guests appear first, followed by "CONTACTED", then other statuses.

---

## Visit Schedule Search

**Location:** `src/routes/_authenticated/visit-schedule.tsx`

Search fields:
- Guest name
- Phone
- Address
- Impact cell

Filtering is purely client-side on the fetched guest list. Only guests with `contactedStatus` = "Available for Visit" are shown.

---

## Soul Search

**Location:** Dashboard → Impact Leader → Soul Search (`section=search`)

Search fields:
- Name
- Phone
- Email
- Centre

Data source: `apiClient.impact.submissions("soul")` — fetches all soul submissions, filters client-side by query string.

---

## Filter UI Components

### Status Filter Dropdown
```typescript
const STATUSES: ContactedStatus[] = [
  "No", "Yes", "Available for Visit", "Not Available for Visit",
  "Not Reachable", "Wrong Number", "Others"
]
```
- Dropdown selector with "All" option
- Exact match filtering

### Join When Filter Dropdown
```typescript
const JOINS: JoinWhen[] = [
  "First Timer (Last 2 Weeks)",
  "New Members (Last 6 Months)",
  "Old Members"
]
```
- Dropdown selector with "All" option
- Exact match filtering

### Month Filter (Follow UP Dashboard)
```typescript
const [month, setMonth] = useState(new Date().toISOString().slice(0, 7)) // "YYYY-MM"
```
- Used for dashboard data fetching
- Passed as query parameter: `GET /api/reports/dashboard?month=2024-03`
- Available only for Follow_UP team roles
- Affects: byFollowUpStatus, byEvent, monthlyGuests data

---

## Search Input Pattern

Standard search input used throughout:
```typescript
const [q, setQ] = useState("")

<Input
  placeholder="Search..."
  value={q}
  onChange={(e) => setQ(e.target.value)}
/>
```

The search is triggered on every keystroke (no debounce) and filters the in-memory data array.

---

## Data Scoping (Not Filtering)

In addition to explicit search/filter, data is scoped by role:

**Assigned-only roles** (`FollowUpOfficer`, `Follow_UP`, `Impact_Leaders`):
- Backend: `where.followOfficerId = req.user.sub`
- Frontend: also filters by `g.followOfficer === user.fullName` (belt-and-suspenders)

**All other roles**: see all guests

This scoping is applied **before** any search or filter operations.

---

## Summary

| Feature | Location | Server-Side | Client-Side | Fields |
|---------|----------|-------------|-------------|--------|
| Guest search | /guests | Partial (q param) | Yes | name, phone, officer, address |
| Guest status filter | /guests | Partial (status param) | Yes | contactedStatus (exact) |
| Guest join filter | /guests | Partial (joinWhen param) | Yes | joinWhen (exact) |
| Guest sort | /guests | No | Yes | followUpStatus (priority) |
| Visit schedule search | /visit-schedule | No | Yes | name, phone, address, cell |
| Soul search | /dashboard ?section=search | No | Yes | name, phone, email, centre |
| Month filter | /dashboard | Yes (month param) | No | date range |
