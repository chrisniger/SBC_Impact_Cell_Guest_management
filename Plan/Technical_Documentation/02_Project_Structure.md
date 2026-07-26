# Project Structure

```
followo_up_officer/
│
├── server/                          # Express.js Backend
│   ├── server.js                    # Entry point: middleware, route mounting, SSR fallback
│   ├── db.js                        # Prisma client singleton (global anti-hot-reload)
│   ├── package.json                 # {"type": "commonjs"}
│   │
│   ├── routes/                      # Express route definitions
│   │   ├── auth.routes.js           # /api/auth/* — login, logout, me, profile, password
│   │   ├── user.routes.js           # /api/users/* — CRUD users (admin only)
│   │   ├── guest.routes.js          # /api/guests/* — CRUD guests, reassign
│   │   ├── report.routes.js         # /api/reports/* — dashboard, audit, officer performance
│   │   ├── impact.routes.js         # /api/impact/* — cells, submissions, public join
│   │   ├── notification.routes.js   # /api/notifications/* — SMTP, notification rules
│   │   └── csv.routes.js            # /api/csv/* — CSV upload
│   │
│   ├── controllers/                 # Business logic (one file per route domain)
│   │   ├── auth.controller.js       # JWT sign, bcrypt compare, password reset tokens
│   │   ├── user.controller.js       # User CRUD, role sanitization, bcrypt hash
│   │   ├── guest.controller.js      # Guest CRUD, sanitize(), canEdit() guard
│   │   ├── report.controller.js     # Dashboard stats, audit logs, officer performance
│   │   ├── impact.controller.js     # Impact cells CRUD, public join, submissions
│   │   ├── notification.controller.js # SMTP settings, notification rules, test email
│   │   └── csv.controller.js        # CSV parsing, column mapping, bulk create
│   │
│   ├── middleware/
│   │   ├── auth.js                  # requireAuth (JWT verify), requireRole (role check factory)
│   │   └── error.js                 # Global error handler (catch-all)
│   │
│   └── lib/
│       ├── roles.js                 # Role constants, groups, helper functions (backend)
│       ├── mailer.js                # Nodemailer transport creation, sendMail(), getSmtpSettings()
│       ├── notifications.js         # Notification action dispatch, ACTIONS constant
│       └── impact-cells.js          # 70 hardcoded impact cell names
│
├── src/                             # React Frontend
│   ├── routeTree.gen.ts             # Auto-generated TanStack Router tree (do not edit)
│   │
│   ├── routes/                      # File-based TanStack Router pages
│   │   ├── __root.tsx               # Root layout: providers, error/not-found components
│   │   ├── index.tsx                # Auth-aware redirect (→ dashboard or /login)
│   │   ├── login.tsx                # Login page with forgot-password dialog
│   │   ├── reset-password.tsx       # Password reset page (token from URL)
│   │   ├── join-impact-cell.tsx     # Public join form (no auth required)
│   │   ├── _authenticated.tsx       # Auth gate layout (redirects to /login if unauthenticated)
│   │   └── _authenticated/          # Protected routes (require auth)
│   │       ├── dashboard.tsx        # All role-based dashboards (2226 lines)
│   │       ├── guests.tsx           # Guest list + CRUD dialogs (897 lines)
│   │       ├── guests.$id.tsx       # Single guest edit page
│   │       ├── users.tsx            # User management (admin)
│   │       ├── impact-cells.tsx     # Impact cell management (admin)
│   │       ├── settings.tsx         # SMTP settings (admin)
│   │       ├── notifications.tsx    # Notification rules (admin)
│   │       ├── import.tsx           # CSV import/export (admin)
│   │       ├── audit.tsx            # Audit log viewer
│   │       ├── profile.tsx          # User profile page
│   │       └── visit-schedule.tsx   # Visit scheduling view
│   │
│   ├── components/
│   │   └── AppLayout.tsx            # Sidebar, header, navigation, role-based nav items
│   │
│   ├── lib/                         # Shared frontend utilities
│   │   ├── api.ts                   # API client: request(), normalizers, enum maps, all endpoints
│   │   ├── types.ts                 # All TypeScript types and interfaces
│   │   ├── auth-context.tsx         # AuthProvider, ThemeProvider, useAuth, useTheme hooks
│   │   ├── roles.ts                 # Frontend role constants and helper functions
│   │   ├── utils.ts                 # cn() helper (clsx + tailwind-merge)
│   │   ├── impact-cells.ts          # Impact cell name constants (frontend copy)
│   │   ├── mockApi.ts               # localStorage-based mock API for development
│   │   ├── error-page.ts            # SSR error page HTML generator
│   │   └── error-capture.ts         # Global error capture utility
│   │
│   └── styles.css                   # Global styles (Tailwind CSS v4 imports)
│
├── prisma/                          # Database schema and seeding
│   ├── schema.prisma                # Prisma schema: 8 models, 3 enums, MySQL provider
│   └── seed.js                      # Seeds admin user (sbcAdmin / //Chris##101)
│
├── dist/                            # Build output
│   ├── client/                      # Static frontend build
│   └── server/                      # SSR server build (TanStack Start)
│
├── public/                          # Static assets
│   ├── logo.png                     # Application logo
│   └── favicon.png                  # Browser favicon
│
├── vite.config.ts                   # Vite configuration (proxy, plugins)
├── package.json                     # Frontend dependencies + scripts
├── tsconfig.json                    # TypeScript configuration
├── eslint.config.js                  # ESLint flat config
├── components.json                  # shadcn/ui configuration
├── .env.example                     # Environment variable template
├── handoff_laravel/                 # Laravel migration documentation
│   └── Technical_Documentation/     # This documentation
│
└── MEMBERS DATA.xml                 # Sample data (XML)
    SOULS REGISTRATION.xml           # Sample data (XML)
    SUBMIT REPORT.xml                # Sample data (XML)
```

## Key Directory Roles

| Directory | Purpose |
|-----------|---------|
| `server/` | All backend code (Express.js, non-module CommonJS) |
| `server/routes/` | Thin route definitions — middleware chains + controller references |
| `server/controllers/` | Business logic — Prisma queries, validation, email dispatch |
| `server/middleware/` | Auth guards and error handling |
| `server/lib/` | Shared utilities (roles, mailer, notifications, constants) |
| `src/routes/` | File-based TanStack Router pages (one file per route) |
| `src/lib/` | Frontend shared code (API client, types, auth context, helpers) |
| `prisma/` | Database schema and seed scripts |
| `dist/` | Compiled build output (not committed) |
| `public/` | Static assets served at root |
