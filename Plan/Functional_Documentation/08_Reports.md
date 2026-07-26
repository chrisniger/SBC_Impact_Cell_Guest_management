# 08 — Reports

## Dashboard KPIs

The dashboard report endpoint (`GET /api/reports/dashboard?month=YYYY-MM`) returns aggregate statistics.

### Response Structure
```json
{
  "stats": {
    "pendingContacts": 42,
    "totalCalls": 156,
    "visited": 28,
    "pendingVisit": 14
  },
  "byStatus": [
    { "contactedStatus": "No", "count": 42 },
    { "contactedStatus": "Yes", "count": 78 }
  ],
  "byJoin": [
    { "joinWhen": "FirstTimer", "count": 15 },
    { "joinWhen": "NewMember", "count": 22 }
  ],
  "byFollowUpStatus": [
    { "status": "NOT CONTACTED", "count": 60 }
  ],
  "byEvent": [
    { "event": "COMBINED SERVICE", "eventOther": null, "count": 45 }
  ],
  "monthlyGuests": [
    { "month": "2026-01", "count": 30 }
  ]
}
```

### KPI Definitions

| KPI | SQL/Prisma Logic | Description |
|-----|-----------------|-------------|
| Pending Contacts | `count WHERE contactedStatus = "No"` | Guests not yet contacted |
| Total Calls | `count WHERE contactedStatus != "No"` | Guests that have been contacted (any status except No) |
| Visited Homes | `count WHERE visited = true` | Homes where visitation was completed |
| Pending Visit | `count WHERE contactedStatus = "AvailableForVisit" AND visited = false` | Guests awaiting visit |
| Conversion Rate | `(visited / totalGuests) * 100` | Calculated client-side |

### Filtering
- Monthly filtering via `?month=YYYY-MM` query parameter
- If month is provided, `byFollowUpStatus` and `byEvent` groups use a date range filter on `createdAt`
- For assigned-only roles, all queries include `where: { followOfficerId: req.user.sub }`

---

## Officer Performance Report

**Endpoint:** `GET /api/reports/officer-performance`

Returns count of guests per officer:
```json
[
  { "id": "uuid", "name": "Officer Name", "total": 25 },
  { "id": "uuid", "name": "Another Officer", "total": 18 }
]
```

- Only includes officers with roles in `FOLLOW_UP_ROLES` (Follow UP Officer, Follow_UP)
- Counts total assigned guests per officer

---

## Audit Log

**Endpoint:** `GET /api/reports/audit`

Returns the last 500 audit log entries:
```json
[
  {
    "id": "uuid",
    "at": "2026-07-19T10:30:00.000Z",
    "actor": "Admin User",
    "action": "GUEST_CREATED",
    "detail": "Created guest John Doe"
  }
]
```

- Ordered by `at` descending (newest first)
- Limited to 500 entries
- Shows actor name (or "system" if null)
- Accessible by REPORT_ROLES: Administrator, Supervisor, Follow_UP Admin, Follow_UP_View_Only, Impact_Leaders, Impact_Cell_Admin, Impact_Cell_Report

---

## Weekly Reports (Impact Cell)

**Endpoint:** `GET /api/impact/submissions?type=report`

Returns list of weekly report submissions with:
- Fellowship date, impact cell name, adults/children counts, offerings
- Detail view shows all data fields of a selected report
- Filtered to last fellowship window (Thursday, Saturday, or Sunday within the past 7 days)

### Fellowship Window Validation
Reports are considered current if:
- Fellowship date is within the last 7 days
- Day of week is Thursday, Saturday, or Sunday

---

## CSV Exports

Available from the sidebar "Downloads" section (role-dependent):

| Export | File Name | Roles | Data Source |
|--------|-----------|-------|-------------|
| Reports | `impact-leader-reports.csv` | Administrator, Impact_Cell_Admin | ImpactSubmission type=report |
| Members Data | `impact-members-data.csv` | Administrator, Impact_Cell_Admin | ImpactSubmission type=member |
| Child Notice | `child-notice-data.csv` | Administrator, Impact_Cell_Admin | ImpactSubmission type=childbirth |
| Guest Data | `guest-data.csv` | Administrator, Follow_UP Admin | Guest list |

**Guest CSV Export columns:** id, date, event, eventOther, followOfficer, guestName, gender, maritalStatus, phone, address, age, nearestImpactCell, impactStatus, contactedStatus, joinWhen, followUpStatus

**Impact Submission CSV Export columns:** id, type, submittedAt, impactCell, submittedBy, [dynamic data field keys sorted alphabetically]

---

## Monthly Guest Growth

Tracked via raw SQL query in the dashboard report:
```sql
SELECT DATE_FORMAT(date, '%Y-%m') as month, COUNT(*) as count
FROM Guest
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY month ASC
```

Returns monthly guest registration counts for trend analysis (visible in the charts section).
