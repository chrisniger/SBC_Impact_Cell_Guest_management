# UI Components

## Component Library

The UI is built on **shadcn/ui** which provides styled Radix UI primitives. Components use **class-variance-authority (cva)** for variant management and **Tailwind CSS v4** for styling.

### shadcn/ui Components Used

| Component | Package | Usage |
|-----------|---------|-------|
| Button | `@radix-ui/react-slot` + cva | All action buttons |
| Card | `@radix-ui` | Dashboard metric cards, panels |
| Input | Native + styling | Form inputs |
| Select | `@radix-ui/react-select` | Dropdown selects |
| Dialog | `@radix-ui/react-dialog` | Modals for CRUD, forgot password |
| Checkbox | `@radix-ui/react-checkbox` | Remember me, days available |
| Switch | `@radix-ui/react-switch` | User active toggle, notification rules |
| Label | `@radix-ui/react-label` | Form labels |
| Textarea | Native + styling | Comments, feedback |
| DropdownMenu | `@radix-ui/react-dropdown-menu` | User menu, role switcher |
| Table | `@radix-ui` | Data tables |
| Badge | Native + styling | Status badges, role badges |
| Avatar | `@radix-ui/react-avatar` (not used — custom initials) | — |
| Tabs | `@radix-ui/react-tabs` | Dashboard section tabs |
| Separator | `@radix-ui/react-separator` | Dividers |
| Progress | `@radix-ui/react-progress` | Progress indicators |
| Tooltip | `@radix-ui/react-tooltip` | Hover tooltips |
| ScrollArea | `@radix-ui/react-scroll-area` | Scrollable areas |
| RadioGroup | `@radix-ui/react-radio-group` | Radio buttons |
| Accordion | `@radix-ui/react-accordion` | Collapsible sections |
| AlertDialog | `@radix-ui/react-alert-dialog` | Confirmation dialogs |
| Popover | `@radix-ui/react-popover` | Popover menus |
| HoverCard | `@radix-ui/react-hover-card` | Hover details |
| Collapsible | `@radix-ui/react-collapsible` | Collapsible sections |
| Slider | `@radix-ui/react-slider` | Range sliders |
| Sheet | `@radix-ui/react-dialog` (variant) | Slide-out panels |
| Sonner (Toaster) | `sonner` | Toast notifications |
| Toggle | `@radix-ui/react-toggle` | Toggle buttons |
| ToggleGroup | `@radix-ui/react-toggle-group` | Toggle groups |

---

## Custom UI Components (Defined Inline)

### AppLayout (`src/components/AppLayout.tsx`)
The main application shell:

**Sections:**
- **Sidebar** (collapsible):
  - Logo + branding
  - Navigation items (role-filtered)
  - Impact Cell Admin nav section (role-filtered)
  - Downloads section (role-filtered)
  - Future/disabled items
  - User profile link + sign out button
- **Header**:
  - Collapse toggle button
  - Theme toggle (dark/light switch)
  - Notifications bell (hardcoded badge count "3")
  - User dropdown (profile info, role switcher, sign out)
- **Main content area** (`<Outlet />`)

**Sidebar Navigation Arrays:**
```typescript
NAV: Dashboard, Guests, Users, Impact Cell, Settings, Notifications, Import CSV, Audit Log
IMPACT_LEADER_NAV: Members Data, Submit Report, Childbirth Notice, Souls Registration, Soul Search, My Reports
IMPACT_CELL_ADMIN_NAV: Overview, Impact Members, Assigned Guest, Child Naming, Weekly Reports
```

### MetricCard (inline in dashboard)
Displays a single KPI metric:
- Icon (from Lucide)
- Title
- Value (large number)
- Optional change indicator (ArrowUp/ArrowDown)

### StatsCard (inline in dashboard)
Similar to MetricCard with additional styling for statistics display.

### Panel (inline in dashboard)
Container component for dashboard sections with title and optional action buttons.

### FilterButton (inline in dashboard)
Button component for filter controls (month selector, view switcher).

### StatusBadge (inline in dashboard/guests)
Color-coded badge for displaying status:
- Contacted statuses: different colors per status
- Follow-up statuses: color-coded

### ProgressRing (inline in dashboard)
SVG-based circular progress indicator.

### ProgressLegend (inline in dashboard)
Legend for ProgressRing showing labels and values.

### Chart Components (inline in dashboard)

#### ChartTooltip
Custom tooltip for Recharts (BarChart, AreaChart):
- Background with backdrop blur
- Color indicator dot + label + value

#### LegendRow
Custom legend component for charts:
- Color indicator dot + label + value

### Initials Avatar (inline in AppLayout)
User avatar showing initials:
- Takes first letter of first two name parts
- Gradient background (amber to primary red)
- Circular shape with shadow

### ImpactFormField (inline in dashboard)
Form field wrapper for Impact Leader forms:
- Label
- Input/Select/Textarea as needed
- Consistent styling

### Dialogs

#### AddGuestDialog (inline in guests.tsx)
Full guest creation form in a Dialog modal:
- All guest fields with proper inputs
- Conditional field visibility
- Officer assignment dropdown
- Save/Cancel buttons

#### EditGuestDialog (inline in guests.tsx)
Guest edit form, pre-populated with existing data:
- Same fields as AddGuestDialog
- Read-only for non-admin/non-owner roles
- Conditional field visibility

#### ScheduleDialog (inline in visit-schedule.tsx)
Visit scheduling form:
- Date picker
- Available time slots
- Officer assignment

#### UserForm (inline in users.tsx)
User create/edit form:
- Full name, email, phone, username
- Password (only on create)
- Role(s) selection
- Impact cell assignment (for Impact_Leaders)
- Active toggle

### Empty State
Displayed when data lists are empty:
- Centered message
- Optional action button

---

## Utility Components

### cn() (`src/lib/utils.ts`)
```typescript
import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}
```
Used throughout the application for conditional class name merging, combining Tailwind classes with proper conflict resolution.

### Toaster (`sonner`)
```tsx
<Toaster richColors position="top-right" />
```
- Positioned top-right
- Rich colors enabled (green for success, red for error)
- Used via `toast.success()`, `toast.error()`

---

## Icons

All icons come from **Lucide React** (`lucide-react`):

| Icon | Usage |
|------|-------|
| LayoutDashboard | Dashboard nav |
| Users | Guests, Users nav |
| UserCog | User management nav |
| Network | Impact Cell nav |
| Settings | Settings nav |
| Bell | Notifications nav, header |
| Upload | Import CSV nav |
| ClipboardList | Audit log nav, Submit Report |
| CalendarClock | Childbirth Notice |
| Search | Soul Search |
| BarChart3 | Reports, My Reports |
| ArrowRight, ArrowUp, ArrowDown | Directional indicators |
| Phone, PhoneCall | Contact status |
| CheckCircle2, Clock, Target, Zap | KPI indicators |
| Menu | Sidebar collapse/expand |
| LogOut | Sign out |
| ChevronDown, ChevronRight | Dropdowns, collapsibles |
| Eye, EyeOff | Password visibility toggle |
| LockKeyhole, Mail | Login form icons |
| ShieldCheck | Branding |
| Sun, Moon | Theme toggle |
| Pencil | Edit actions |
| Trash2 | Delete actions |
| Plus | Add actions |
| ArrowRightLeft | Reassign action |
| Eye | View action |
| Loader2 | Loading spinners |
| FileText, FileSpreadsheet, Download | CSV download icons |
| MoreHorizontal | Context menus |
| RefreshCw | Refresh actions |
| Info | Information tooltips |
| Home | Home navigation |
