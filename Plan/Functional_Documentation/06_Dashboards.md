# 06 — Dashboards

The Dashboard (`/dashboard`) is the primary landing page after login. Its content varies significantly based on the user's active role.

---

## 1. Default Dashboard (Follow UP Officer)

Shown when the active role is **Follow UP Officer**. This is the most feature-rich dashboard.

### Metric Cards (5 cards in a grid)
| Card | Icon | Description |
|------|------|-------------|
| Total Guests | Users | Total number of assigned guests |
| Pending Contacts | Clock | Guests with contactedStatus = "No" |
| Follow-up Calls | PhoneCall | Guests with contactedStatus != "No" |
| Visited Homes | Home | Guests with visited = true |
| Response/Conversion Rate | Target | (Visited / Total Guests) × 100, shown as percentage |

### Analysis Charts (4 selectable views)
A sidebar on the left lets users toggle between:

1. **Event Analysis** (BarChart) — Guest counts grouped by event (COMBINED SERVICE, CHURCH 1, CHURCH 2, OTHER)
2. **Contacted Status Breakdown** (Donut/PieChart) — Distribution of contacted statuses with center total count
3. **Guest Categories** (BarChart) — New Members vs Old Members
4. **Visit Availability — Days** (AreaChart) — Guest availability across weekdays

### Recent Guests Table
- Shows last 6 guests (columns: Date, Guest, Phone, Officer, Status, Visited, Action)
- Searchable by name, phone, officer, status
- "View all" link navigates to full guests list

### Follow-up Progress Ring
- Circular progress indicator
- Legend: Completed (green), In Progress (blue), Pending (amber)

### Officer Performance (top 3)
- Ranked by total follow-ups
- Shows officer name, follow-up count (with bar), conversion rate

---

## 2. Admin Dashboard

When the active role is **Administrator**, three analysis tabs are shown:

### Tab 1: "Follow Up Officer Analysis"
The default dashboard described above, shown when `adminAnalysis` is "group1".

### Tab 2: "Follow_UP Team Analysis"
Shown when `adminAnalysis` is "group2". Renders the `FollowUpTeamDashboard` component with:
- Month filter (default: current month, format: YYYY-MM)
- 2 metric cards: Pending Contacts, Follow-up Calls
- Guest data table with inline Follow Up Status dropdown and Follow Up Contacts management
- Reassignment capability (admin can reassign to any assignable officer)

### Tab 3: "Impact Cell Analysis"
Shown when `adminAnalysis` is "group3". Renders the `ImpactCellAnalysis` component (same as Impact Cell Admin dashboard).

---

## 3. Follow_UP Team Dashboard

Shown for roles: Follow_UP, Follow_UP Admin, Follow_UP_View_Only.

### Features
- **Month filter** — Filter data by YYYY-MM
- **2 Metric Cards**: Pending Contacts, Follow-up Calls
- **Guest Data Table** with columns:
  - Name, Phone, Follow Up Status (inline dropdown), Follow Up Contacts
  - Status can be updated inline
  - For Follow_UP Admin: Reassign button available
  - For Follow_UP_View_Only: Read-only view

### Read-only behavior
- If `readOnly` prop is true (Follow_UP_View_Only role), all edit/reassign controls are disabled

---

## 4. Impact Cell Admin Dashboard

Shown for: Impact_Cell_Admin (editable) and Impact_Cell_Report (read-only).

### 4 KPI Cards (top row)
| Card | Description |
|------|-------------|
| Active Impact Leaders | Count of active users with role "Impact_Leaders" |
| Impact Cell Members | Total members submission count |
| Upcoming Child Naming | Count of childbirths with DOB within next 9 days |
| Total Souls | Total souls registration count |

### 3 Stats Cards
1. **Last Fellowship Attendance** — Reports count, adults, children
2. **Offerings** — HQ centres, centres offerings, total
3. **Assigned Guest Records** — Contacted, Not Contacted, Not Reachable counts

### 5 Tabbed Sections

| Section | Content |
|---------|---------|
| **Overview** | Impact Members table (cell name → total members) |
| **Members** | Full Impact Members table with all cells |
| **Assigned Guest** | Guest impact status by cell (Contacted/Not Contacted/Not Reachable counts) |
| **Child Naming** | Upcoming child naming events (next 9 days) with child name, parent, DOB, impact cell |
| **Reports** | Weekly reports table + report detail panel (shows all data fields of selected report) |

### Download Section (sidebar)
- Reports CSV
- Members Data CSV
- Child Notice Data CSV

---

## 5. Impact Leader Dashboard

Shown for role Impact_Leaders.

### 4 KPI Cards
| Card | Description |
|------|-------------|
| Pending Follow Up | Guest count assigned to this leader |
| Total Members | Total member submissions by this leader |
| Total Souls | Total soul registrations by this leader |
| (additional card varies) | |

### 6 Tabbed Forms (accessed via sidebar or section search params)

| Section | Form Type | Description |
|---------|-----------|-------------|
| `section=member` | Members Data | Submit member records (41 fields) |
| `section=soul` | Souls Registration | Submit soul records (12 fields) |
| `section=childbirth` | Childbirth Notice | Submit childbirth notice (6 fields) |
| `section=report` | Submit Report | Submit weekly report (13 fields) |
| `section=search` | Soul Search | Search functionality for souls |
| `section=reports` | My Reports | View own submitted reports |
