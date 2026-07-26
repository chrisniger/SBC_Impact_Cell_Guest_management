# 03 — User Workflows

## Login Flow

```mermaid
flowchart TD
    A[User navigates to /login] --> B[Enter username/email + password]
    B --> C[Click "Sign in"]
    C --> D{API POST /auth/login}
    D -->|401| E[Show "Invalid credentials" toast]
    D -->|200| F[Store JWT in localStorage]
    F --> G[Store active role in localStorage]
    G --> H[Redirect to /dashboard]
    H --> I[Show "Welcome, {fullName}" toast]
```

**Technical details:**
- Login accepts username or email (checked via `OR` on both fields)
- Password is compared with bcrypt hash
- JWT is generated with `sub` (user ID), `role`, `roles`, `name` claims
- Token expiry: 7 days (configurable via `JWT_EXPIRES_IN` env var)
- Token stored in `localStorage` under key `cgms.token`
- Active role stored under key `cgms.activeRole`

## Logout Flow

1. User clicks "Sign out" button from the sidebar or dropdown menu
2. API call `POST /auth/logout` (returns `{ ok: true }` — no server-side invalidation)
3. Token removed from `localStorage`
4. Active role removed from `localStorage`
5. User state set to `null`
6. Redirect to `/login`

## Password Reset Flow

```mermaid
flowchart TD
    A[Login page] --> B[Click "Forgot password?"]
    B --> C[Dialog: Enter email]
    C --> D[POST /auth/forgot-password]
    D --> E[If email exists: send reset link]
    E --> F[Show "If that address exists, check your inbox"]
    F --> G[User clicks link: /reset-password?token=xxx]
    G --> H[Enter new password + confirm]
    H --> I[POST /auth/reset-password]
    I --> J[Token validated, password updated]
    J --> K[Redirect to /login]
```

**Technical details:**
- Token generated via `crypto.randomBytes(32).toString("hex")`
- Token expiry: 1 hour (stored in `PasswordReset.expiresAt`)
- Token is single-use (`used` flag set to `true` after reset)
- Reset link sent via Nodemailer using configured SMTP
- Forgot password endpoint always returns `{ ok: true }` regardless of whether email exists (prevents enumeration)
- New password minimum: 6 characters (client-side validation)

## Profile Update Flow

1. User navigates to `/profile`
2. Form pre-filled with current `fullName`, `email`, `phone`
3. User edits fields and clicks "Save"
4. `PUT /auth/profile` with `{ fullName, email, phone }`
5. Auth context refreshed with updated user data
6. Success toast displayed

## Password Change Flow

1. From profile page, user enters current password and new password
2. Client-side validation: new password must be at least 6 characters
3. `POST /auth/change-password` with `{ currentPassword, newPassword }`
4. Server validates current password against bcrypt hash
5. New password hashed with bcrypt and saved
6. Success toast displayed, form cleared

## Role Switching Flow

```mermaid
flowchart TD
    A[User with multiple roles] --> B[Click dropdown in header]
    B --> C[See "Switch dashboard" menu]
    C --> D[Click target role]
    D --> E[localStorage: cgms.activeRole = new role]
    E --> F[user.role updated client-side]
    F --> G[Redirect to /dashboard]
    G --> H[New role's dashboard renders]
```

**Technical details:**
- Only displayed when `user.roles.length > 1`
- Active role sent as `X-Active-Role` header on all API requests
- Server-side middleware reads this header and can filter data accordingly
- Role switching is client-side only (no server call)
- Dashboard page re-renders with role-appropriate layout

## Navigation Patterns

```mermaid
flowchart TD
    subgraph Authenticated Routes
        D[/dashboard] --> G[/guests]
        G --> GD[/guests/$id]
        D --> US[/users]
        D --> IC[/impact-cells]
        D --> ST[/settings]
        D --> NT[/notifications]
        D --> IM[/import]
        D --> AU[/audit]
        D --> PR[/profile]
    end
    subgraph Public Routes
        L[/login] --> RP[/reset-password]
        JC[/join-impact-cell]
    end
    subgraph Entry
        IND[/] -->|Has token?| D
        IND -->|No token?| L
    end
```

**Sidebar navigation items** (filtered by role):
- Dashboard — All roles
- Guests — Reassign roles + Follow_UP roles + View_Only roles + Impact_Leaders
- Users — Admin only
- Impact Cell — Admin only
- Settings — Admin only
- Notifications — Admin only
- Import CSV — Admin only
- Audit Log — Report roles

**Impact Leader sidebar nav** (replaces main nav items for Impact_Leaders):
- Members Data → `/dashboard?section=member`
- Submit Report → `/dashboard?section=report`
- Childbirth Notice → `/dashboard?section=childbirth`
- Souls Registration → `/dashboard?section=soul`
- Soul Search → `/dashboard?section=search`
- My Reports → `/dashboard?section=reports`

**Impact Cell Admin sidebar nav:**
- Overview → `/dashboard?impactSection=overview`
- Impact Members → `/dashboard?impactSection=members`
- Assigned Guest → `/dashboard?impactSection=assigned`
- Child Naming → `/dashboard?impactSection=child-naming`
- Weekly Reports → `/dashboard?impactSection=reports`
