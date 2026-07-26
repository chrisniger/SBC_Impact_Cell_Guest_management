# Dashboards

## Overview

The dashboard (`src/routes/_authenticated/dashboard.tsx`, 2226 lines) is a **single component** that renders completely different content based on the current user's active role. It uses URL search parameters (`section`, `impactSection`, `analysisSection`) to switch between sub-sections within each role's dashboard.

---

## Dashboard Role System

| Role(s) | Dashboard Type | URL Section Param |
|---------|---------------|-------------------|
| Administrator | Analytics Dashboard | — |
| Supervisor | Analytics Dashboard | — |
| FollowUpOfficer | Analytics Dashboard | — |
| Follow_UP | Analytics Dashboard | — |
| Follow_UP_Admin | Analytics Dashboard | — |
| Follow_UP_View_Only | Analytics Dashboard | — |
| Impact_Leaders | Impact Leader Dashboard | `section` |
| Impact_Cell_Admin | Impact Cell Admin Dashboard | `impactSection` |
| Impact_Cell_Report | Impact Cell Admin Dashboard | `impactSection` (read-only) |

---

## Analytics Dashboard

**Renders for:** Administrator, Supervisor, FollowUpOfficer, Follow_UP, Follow_UP_Admin, Follow_UP_View_Only

### Data Fetching

```javascript
GET /api/reports/dashboard?month=2024-03
```

### KPI Cards (MetricCard)

| KPI | Calculation | Icon |
|-----|------------|------|
| Pending Contacts | `guest.count({ contactedStatus: "No" })` | Phone |
| Total Calls Made | `guest.count({ NOT: { contactedStatus: "No" } })` | PhoneCall |
| Visited | `guest.count({ visited: true })` | CheckCircle2 |
| Pending Visit | `guest.count({ contactedStatus: "AvailableForVisit", visited: false })` | Clock |

### Charts

#### Status Distribution (BarChart)
- Data: `byStatus` array from API
- X-axis: contactedStatus values
- Y-axis: count
- Colors: `STATUS_COLORS` mapping (per status value)
- Custom `ChartTooltip` component
- Legend via `LegendRow` component

#### Join When Distribution (PieChart/Donut)
- Data: `byJoin` array from API
- Segments: First Timer, New Members, Old Members
- Inner radius for donut effect
- Custom labels showing percentage
- Legend via `LegendRow`

#### Monthly Guest Trend (AreaChart)
- Data: `monthlyGuests` array from API
- X-axis: month (YYYY-MM)
- Y-axis: count
- Gradient fill under the area
- Custom `ChartTooltip`

#### Follow-Up Status (BarChart)
- Data: `byFollowUpStatus` array from API
- Shows: NOT CONTACTED, CONTACTED, WRONG NUMBER, NOT REACHABLE

#### Event Distribution (BarChart)
- Data: `byEvent` array from API
- Shows: COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER

### Month Filter
- Dropdown/select showing months
- Format: `YYYY-MM`
- Default: current month
- When changed, re-fetches dashboard data with `?month=` parameter
- Available only for Follow_UP team roles

### Analysis Section Tabs (Follow_UP Roles)
Available for `Follow_UP`, `Follow_UP_Admin` roles via `analysisSection` param:

| Section | Value | Content |
|---------|-------|---------|
| Event Analysis | `event` | Event distribution chart |
| Status Overview | `status` | Contacted status distribution |
| Categories | `categories` | Join when + follow-up status |
| Days Available | `days` | Day-of-week analysis |

### Data Scoping
- Assigned-only roles (FollowUpOfficer, Follow_UP): data is scoped to their own guests
- All other roles: see all data

### Fallback on Error
```javascript
try {
  // ... queries
} catch (error) {
  console.error("[reports.dashboard] failed", error)
  res.json({
    stats: { pendingContacts: 0, totalCalls: 0, visited: 0, pendingVisit: 0 },
    byStatus: [], byJoin: [], byFollowUpStatus: [], byEvent: [], monthlyGuests: [],
    warning: "Dashboard data is temporarily unavailable",
  })
}
```

---

## Impact Leader Dashboard

**Renders for:** `Impact_Leaders` role

Controlled by URL search parameter `section`:
```typescript
type ImpactLeaderSection = "member" | "report" | "childbirth" | "soul" | "search" | "reports"
```

### Sections

#### 1. Members Data (`section=member`)
- Form with fields: Name, Phone, Gender, Marital Status, Address, Nearest Impact Cell, Join When, etc.
- Uses `ImpactFormField` component
- Submits to: `apiClient.impact.createSubmission("member", data)`
- Data stored as JSON in `ImpactSubmission.data`
- File upload: Transfer receipt via `FileReader` as data URL

#### 2. Submit Report (`section=report`)
- Form with fields: Fellowship Date, Impact Cell (auto-filled from user), attendance numbers, offerings, first-timers, etc.
- Validates uniqueness: cannot submit duplicate report for same cell + fellowship date (backend returns 409)
- Submits to: `apiClient.impact.createSubmission("report", data, impactCellId)`

#### 3. Childbirth Notice (`section=childbirth`)
- Form: Parent Name, Child Name, Gender, Date of Birth, Phone, Cell
- Submits to: `apiClient.impact.createSubmission("childbirth", data)`

#### 4. Souls Registration (`section=soul`)
- Form: Name, Phone, Email, Centre, Gender, Address, How they heard, Decision, etc.
- Submits to: `apiClient.impact.createSubmission("soul", data)`

#### 5. Soul Search (`section=search`)
- Search input for name, phone, email, centre
- Queries `apiClient.impact.submissions("soul")` and filters client-side
- Displays results in a table

#### 6. My Reports (`section=reports`)
- Table of all submissions by the current user
- Data from: `apiClient.impact.submissions()` (filtered by userId on backend)
- Shows: type, data preview, impact cell, createdAt

### KPI Summary
- Data from: `apiClient.impact.summary()`
- Cards show: Pending Follow Up, Total Members, Total Souls
- Scoped to the Impact Leader's own data

---

## Impact Cell Admin Dashboard

**Renders for:** `Impact_Cell_Admin`, `Impact_Cell_Report` roles

Controlled by URL search parameter `impactSection`:
```typescript
type ImpactCellAdminSection = "overview" | "members" | "assigned" | "child-naming" | "reports"
```

### Sections

#### 1. Overview (`impactSection=overview`)
- KPI summary cards
- Data from: `apiClient.impact.summary()` (all data for admin, scoped for cell)

#### 2. Impact Members (`impactSection=members`)
- Table of member submissions
- Data from: `apiClient.impact.submissions("member")`
- Columns: Name, Phone, Gender, Cell, etc.
- Bulk CSV download

#### 3. Assigned Guest (`impactSection=assigned`)
- Table of guests assigned to the cell's leaders
- Shows guest info with follow-up status
- Data from: `apiClient.guests.list()` filtered by cell

#### 4. Child Naming (`impactSection=child-naming`)
- Table of childbirth submissions
- Data from: `apiClient.impact.submissions("childbirth")`
- Columns: Child Name, Parent Name, DOB, Phone, Cell

#### 5. Weekly Reports (`impactSection=reports`)
- Table of report submissions
- Data from: `apiClient.impact.submissions("report")`
- Shows: Fellowship Date, Cell, submitted by, key metrics
- Bulk CSV download

### Download (CSV Export)
The Impact Cell Admin sidebar includes download buttons for:
- Reports CSV (from submissions type "report")
- Members Data CSV (from submissions type "member")
- Child Notice Data CSV (from submissions type "childbirth")
- Guest Data CSV (from `apiClient.guests.list()`)
- All downloads generate CSV via `buildSubmissionCsv()` or `buildGuestCsv()` functions

---

## Chart Configurations (Recharts)

### BarChart
```tsx
<BarChart data={byStatus}>
  <CartesianGrid strokeDasharray="3 3" />
  <XAxis dataKey="contactedStatus" />
  <YAxis />
  <Tooltip content={<ChartTooltip />} />
  <Bar dataKey="count" radius={[4, 4, 0, 0]}>
    {byStatus.map((entry, index) => (
      <Cell key={index} fill={STATUS_COLORS[entry.contactedStatus]} />
    ))}
  </Bar>
</BarChart>
```

### PieChart / Donut
```tsx
<PieChart>
  <Pie
    data={byJoin}
    dataKey="count"
    nameKey="joinWhen"
    cx="50%"
    cy="50%"
    innerRadius={60}
    outerRadius={100}
  >
    <LabelList dataKey="count" position="outside" />
  </Pie>
  <Tooltip />
</PieChart>
```

### AreaChart
```tsx
<AreaChart data={monthlyGuests}>
  <CartesianGrid strokeDasharray="3 3" />
  <XAxis dataKey="month" />
  <YAxis />
  <Tooltip content={<ChartTooltip />} />
  <Area type="monotone" dataKey="count" stroke="var(--primary)" fill="url(#gradient)" />
</AreaChart>
```

---

## Data Aggregation Helpers

The backend `report.controller.js` computes dashboard aggregations:

| Query | Method | Purpose |
|-------|--------|---------|
| Pending contacts | `guest.count({ contactedStatus: "No" })` | Guests not yet contacted |
| Total calls | `guest.count({ NOT: { contactedStatus: "No" } })` | Guests with any contact |
| Visited | `guest.count({ visited: true })` | Guests marked as visited |
| Pending visit | `guest.count({ contactedStatus: "AvailableForVisit", visited: false })` | Ready for visit but not visited |
| By status | `guest.groupBy({ by: ["contactedStatus"], _count: true })` | Distribution |
| By join when | `guest.groupBy({ by: ["joinWhen"], _count: true })` | Distribution |
| By follow-up status | `guest.groupBy({ by: ["followUpStatus"], _count: true })` | Distribution (month-scoped) |
| By event | `guest.groupBy({ by: ["event", "eventOther"], _count: true })` | Distribution (month-scoped) |
| Monthly trend | Raw SQL `GROUP BY DATE_FORMAT(date, '%Y-%m')` | Guest trend over time |
