# Current Architecture

## Overview

The SBC Guest Management System is a Single Page Application (SPA) with a separate Express.js REST API backend. The frontend is built with React 19 and TanStack Router, communicating with the backend via HTTP fetch requests. The system serves a church guest management workflow including guest registration, follow-up tracking, visitation scheduling, impact cell (small group) management, and role-based dashboards.

## High-Level Architecture Diagram

```
Browser (React SPA)
    │
    ├── Development: Vite Dev Server (port 3000)
    │   └── Proxy /api → Express (port 3001)
    │
    └── Production: Express serves static dist/client
        └── SSR via TanStack Start for non-API routes
                │
                ▼
        Express.js API (same port, under /api)
            │
            ▼
        Prisma ORM
            │
            ▼
        MySQL (MariaDB on Hostinger)
```

## Key Architectural Decisions

### SPA + Express API
- Frontend is a fully client-rendered SPA during development
- In production, Express serves the built static files from `dist/client/`
- SSR is enabled via TanStack Start (imported dynamically from `dist/server/server.js`)
- All `/api/*` routes are handled by Express routes; all other routes fall through to the SSR handler

### Development Proxy
- Vite dev server runs on port **3000**
- Express API runs on port **3001** in development
- `vite.config.ts` proxies `/api` requests to `http://localhost:3001`
- In production, Express serves both the API and the frontend on the same port (default **3000**)

### Build Pipeline
- `npm run build` compiles the React app via Vite
- Output: `dist/client/` (static assets) and `dist/server/` (SSR server entry)
- Vite 7 with `@vitejs/plugin-react`, `@tailwindcss/vite`, and `tanstackStart` plugins

### SSR (Production Only)
- TanStack Start provides server-side rendering
- Express imports the built SSR module dynamically
- SSR middleware handles non-API requests, falling back to SPA rendering
- Error handling: SSR failures return a static error page (`renderErrorPage()`)

### Prisma ORM
- Prisma Client singleton is stored in `server/db.js`
- Pattern: `global.__prisma` to prevent multiple instances in dev (hot-reload)
- Database: MySQL/MariaDB
- All database access goes through Prisma generated client
- 8 models, 3 enums defined in `prisma/schema.prisma`
- No database migrations have been applied yet (schema.prisma is the source of truth)

### JWT Authentication
- Stateless JWT tokens issued on login
- Token stored in `localStorage` under key `cgms.token`
- Sent via `Authorization: Bearer <token>` header on every request
- Also accepted from cookies as fallback
- Token expiry: 7 days (configurable via `JWT_EXPIRES_IN`)
- Active role is stored separately in localStorage (`cgms.activeRole`)
- Role switching is managed client-side via `X-Active-Role` header

### Role-Based Rendering
- Backend enforces authorization via `requireRole()` middleware
- Frontend filters UI components based on active role
- Nav items, dashboard sections, and action buttons all check `user.role`
- This is a presentation-layer filter, not a security boundary

### Client-Side State
- **localStorage** stores: JWT token (`cgms.token`), active role (`cgms.activeRole`), theme preference (`cgms.theme`), sidebar collapsed state (`cgms.sidebar.collapsed`)
- Auth state managed via React Context (`AuthProvider`/`useAuth`)
- Query data via TanStack React Query and component-level state (`useState`)
- No Redux or external state management library

## Component Communication

```
User Action → React Component → apiClient (fetch) → Express Route → Controller → Prisma → MySQL
                                                                                            │
User ← React Component ← Response JSON ← Controller ← Express Route ←───────────────────────┘
```

## Data Flow Summary

1. User interacts with React UI
2. Component calls `apiClient` method (e.g., `apiClient.guests.list()`)
3. `apiClient` builds fetch request with auth headers and active role header
4. Express receives request, `requireAuth` middleware verifies JWT
5. Route-specific middleware (`requireRole`) checks authorization
6. Controller handles business logic and calls Prisma
7. Response flows back through middleware chain
8. Frontend normalizes the response (enum mapping, date formatting)
9. Component re-renders with new data

## Frontend-Backend Boundary

| Aspect | Frontend | Backend |
|--------|----------|---------|
| Routing | TanStack Router (file-based) | Express route mounting |
| State | React state, Context, Query | JWT session, Prisma data |
| Validation | React Hook Form, manual checks | `sanitize()` function, middleware |
| Data format | Normalized display values | Prisma/DB enum values |
| Auth | Stores token, sends header | Verifies JWT, enforces roles |
| File handling | FormData/Blob | Multer (memory storage) |
| Error display | Sonner toasts | JSON error responses |
