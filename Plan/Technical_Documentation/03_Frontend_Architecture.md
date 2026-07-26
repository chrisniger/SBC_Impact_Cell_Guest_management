# Frontend Architecture

## Technology Stack

| Technology | Version | Purpose |
|-----------|---------|---------|
| React | 19.2.0 | UI framework |
| TanStack Router | 1.168.x | File-based client-side routing |
| TanStack React Query | 5.83.x | Server state management (data fetching/caching) |
| TanStack Start | 1.167.x | SSR framework (production) |
| Tailwind CSS | 4.2.x | Utility-first CSS framework |
| Radix UI | Latest | Accessible headless UI primitives (shadcn/ui) |
| React Hook Form | 7.71.x | Form state management |
| Zod | 3.24.x | Schema validation (used with @hookform/resolvers) |
| Recharts | 2.15.x | Charting library |
| Lucide React | 0.575.x | Icon library |
| Sonner | 2.0.x | Toast notifications |
| date-fns | 4.1.x | Date manipulation |
| class-variance-authority | 0.7.x | Component variant management |
| clsx + tailwind-merge | via cn() utility | Class name merging |

## Routing Architecture

### File-Based Routing (TanStack Router)
Routes are defined as files in `src/routes/`. The route tree is auto-generated into `src/routeTree.gen.ts`.

```
__root.tsx                 → Root layout (providers, error/not-found)
index.tsx                  → /  (auth-aware redirect)
login.tsx                  → /login
reset-password.tsx         → /reset-password
join-impact-cell.tsx       → /join-impact-cell
_authenticated.tsx         → Auth gate (layout route)
_authenticated/
    dashboard.tsx          → /dashboard
    guests.tsx             → /guests
    guests.$id.tsx         → /guests/:id
    users.tsx              → /users
    impact-cells.tsx       → /impact-cells
    settings.tsx           → /settings
    notifications.tsx      → /notifications
    import.tsx             → /import
    audit.tsx              → /audit
    profile.tsx            → /profile
    visit-schedule.tsx     → /visit-schedule
```

### Route Pattern: `_authenticated` (Auth Gate)
- `src/routes/_authenticated.tsx` is a **layout route** (prefix `_`)
- The `AuthGate` component checks `useAuth()` for a logged-in user
- If loading (token present but `/me` not resolved yet), renders nothing
- If no user, redirects to `/login`
- If authenticated, renders `<AppLayout />` which includes sidebar + `<Outlet />`

### Root Layout (`__root.tsx`)
- Sets up global providers: `QueryClientProvider` → `ThemeProvider` → `AuthProvider`
- Configures `<Toaster>` (Sonner) at top-right
- Defines error component and not-found component
- Sets HTML metadata (title, description, Open Graph, Twitter cards)
- Includes inline script for theme hydration (FOUC prevention)

## Auth Gate Pattern

```
AuthProvider (context)
    └── Stores user, token, active role
    └── On mount: reads token from localStorage, calls apiClient.me()
    └── applyActiveRole(): resolves saved role from localStorage

_authenticated.tsx
    └── AuthGate component
    └── Checks useAuth().user
    └── If null → redirect /login
    └── If loading → return null
    └── If authenticated → render AppLayout

AppLayout
    └── Sidebar with role-filtered nav items
    └── Header with user dropdown, role switcher, theme toggle
    └── <Outlet /> for nested route content
```

## API Client Architecture

### Location: `src/lib/api.ts`

The API client is an object-based client with methods grouped by domain:

```
apiClient
├── login(), logout(), me(), updateProfile(), changePassword()
├── forgotPassword(), resetPassword()
├── users.list(), create(), update(), deactivate()
├── guests.list(), get(), create(), update(), remove(), reassign()
├── uploadCsv(file)
├── reports.dashboard(), officerPerformance(), audit()
├── impact.cells(), publicCells(), publicJoin(), createCell(), updateCell()
├── impact.summary(), submissions(), createSubmission()
├── notifications.smtp(), updateSmtp(), actions(), rules()
├── notifications.createRule(), updateRule(), deleteRule(), test()
```

### Request Wrapper
```typescript
async function request<T>(path: string, opts: RequestInit = {}): Promise<T>
```
- Sets `Content-Type: application/json` (unless FormData)
- Attaches `Authorization: Bearer <token>` from localStorage
- Attaches `X-Active-Role` header for role switching
- Parses JSON response
- Throws `Error` with `error` field from response body on non-ok status
- Returns `undefined` for 204 No Content

### Token Management
```typescript
tokenStore.get()    → localStorage.getItem("cgms.token")
tokenStore.set(t)   → localStorage.setItem("cgms.token", t)
tokenStore.clear()  → localStorage.removeItem("cgms.token")

activeRoleStore.get()   → localStorage.getItem("cgms.activeRole")
activeRoleStore.set(r)  → localStorage.setItem("cgms.activeRole", r)
activeRoleStore.clear() → localStorage.removeItem("cgms.activeRole")
```

### Response Normalization
The API client normalizes backend responses to frontend-friendly formats:

| Function | Purpose |
|----------|---------|
| `normalizeGuest(g)` | Maps DB field names → frontend field names, converts enums to display values, splits daysAvailable string to array, normalizes dates |
| `normalizeUser(u)` | Combines `role` + `roles` fields, maps enums to display values |
| `serializeGuest(g, officers)` | Converts frontend format → backend format (enum values, date ISO strings, officer name → ID) |

### Enum Mapping
Three bidirectional maps convert between Prisma enum values and display strings:

| Map | Direction | Purpose |
|-----|-----------|---------|
| `STATUS_FROM` | API → Display | ContactedStatus enum (e.g., "AvailableForVisit" → "Available for Visit") |
| `STATUS_TO` | Display → API | Reverse mapping for serialization |
| `JOIN_FROM` | API → Display | JoinWhen enum (e.g., "FirstTimer" → "First Timer (Last 2 Weeks)") |
| `JOIN_TO` | Display → API | Reverse mapping for serialization |
| `ROLE_FROM` | API → Display | Role enum (e.g., "FollowUpOfficer" → "Follow UP Officer") |
| `ROLE_TO` | Display → API | Reverse mapping for serialization |

## Auth Context

### Location: `src/lib/auth-context.tsx`

### AuthProvider
Provides auth state and actions to the entire app:

```typescript
interface AuthCtx {
  user: User | null;       // Current user with active role applied
  loading: boolean;        // True while initial auth check is in progress
  login(u, p): Promise<User>;  // Authenticate, store token, apply active role
  logout(): void;          // Clear token, clear active role, clear user
  refresh(): Promise<void>;// Re-fetch /me and re-apply active role
  switchRole(role: Role): void;  // Switch active role (client-side only)
}
```

### Active Role Switching
- `applyActiveRole(user)`: Reads saved role from localStorage, validates it exists in user's roles, falls back to first role
- `switchRole(role)`: Updates user.role in state + localStorage, triggers re-render with new permissions
- Role changes affect: nav items, dashboard sections, action buttons, data scoping

### ThemeProvider
- Reads initial theme from localStorage (`cgms.theme`) or prefers-color-scheme
- Toggles `dark` class on `document.documentElement`
- Syncs to localStorage on change

## Component Library

The app uses shadcn/ui components built on Radix UI primitives with Tailwind CSS v4 styling. Components are configured via `components.json`.

### Imported shadcn/Radix Components
Button, Card, Input, Select, Dialog, Checkbox, Switch, Label, Textarea, DropdownMenu, Tabs, Table, Badge, Avatar, Alert, Separator, Progress, Tooltip, ScrollArea, RadioGroup, Accordion, AlertDialog, Popover, HoverCard, Menubar, NavigationMenu, ContextMenu, Toggle, ToggleGroup, Collapsible, Slider, Sheet, Sonner (toaster)

### Custom UI Components (defined inline in pages)
- MetricCard, StatsCard, Panel (in dashboard)
- StatusBadge (in dashboard/guests)
- ProgressRing, ProgressLegend (in dashboard)
- ChartTooltip, LegendRow (in dashboard · Recharts custom tooltips)
- ImpactFormField (in dashboard · Impact Leader forms)
- AddGuestDialog, EditGuestDialog (in guests.tsx)
- ScheduleDialog (in visit-schedule)
- UserForm (in users.tsx)

## Route Tree Structure

```
__root__layout
├── / (IndexRedirect → /dashboard or /login)
├── /login (LoginPage)
├── /reset-password (ResetPasswordPage)
├── /join-impact-cell (JoinImpactCellPage)
│
└── _authenticated (AuthGate → AppLayout)
    ├── /dashboard (Dashboard — 2226 lines)
    ├── /guests (Guest list + CRUD)
    ├── /guests/:id (Guest edit)
    ├── /users (User management)
    ├── /impact-cells (Impact cell management)
    ├── /settings (SMTP settings)
    ├── /notifications (Notification rules)
    ├── /import (CSV import/export)
    ├── /audit (Audit log)
    ├── /profile (User profile)
    └── /visit-schedule (Visit scheduling)
```

## Toast Notifications (Sonner)
- Position: top-right
- `toast.success()` for success messages
- `toast.error()` for errors (displayed from API error messages)
- Used in: login, CRUD operations, form submissions, file uploads, reassign
