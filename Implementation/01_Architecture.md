# 01 — Architecture & Tech Stack

> Decide before Phase 01 begins.

## TL;DR

Maintain the **current proven stack** (Node.js + Express + Prisma + MySQL + React 19 SPA) and layer in the v2 features (3 user groups, Impact Cell hierarchy, Leadership Board). This is the lowest-risk path because:

- The codebase is already shipping at `app.summitdata.one`.
- We have a clean `prisma/schema.prisma` to evolve (add a self-relation + a new boolean).
- The React 19 + TanStack Router shell is already in place; we add new pages/components inside it.

A future "migrate to Laravel 12" plan is parked; we will revisit **after** v2 ships.

---

## Stack (locked for v2)

| Layer | Choice | Why |
|------|--------|-----|
| Runtime | Node.js 22 (Hostinger alt) | Already provisioned |
| Backend | Express 4 (`server/`) | Already shipping |
| ORM | Prisma 5 (MySQL) | Already shipping |
| DB | MySQL/MariaDB (Hostinger) | Production-ready |
| AuthN | JWT (jsonwebtoken) + bcrypt | Already shipping |
| Mailer | Nodemailer | Already shipping |
| Frontend | React 19 (`src/`) | Already shipping |
| Routing | TanStack Router (file-based) | Already shipping |
| UI | Radix / shadcn primitives + Tailwind v4 | Already shipping |
| Charts | Recharts | Already shipping |
| Toasts | Sonner | Already shipping |
| Forms | RHF + Zod (for new screens) | Already shipping |

If the team decides to migrate to Laravel 12, we follow the existing `Plan/Technical_Documentation/20_Laravel_Migration_Strategy.md` and `21_Recommended_Laravel_Project_Structure.md`, but ONLY after v2 features land.

---

## Repo layout (v2)

```
followo_up_officer/                 # existing repo
├── prisma/
│   ├── schema.prisma               # UPDATED for v2 (Impact Cell hierarchy + access flags)
│   ├── seed.js                     # UPDATED (seed primary cells + a few sub-cells for demo)
│   └── migrations/                 # new migrations tracked
├── server/
│   ├── server.js
│   ├── db.js
│   ├── middleware/
│   │   ├── auth.js                 # UPDATED: accept X-Active-Role
│   │   ├── error.js
│   │   └── access.js               # NEW: column-level access helpers
│   ├── lib/
│   │   ├── roles.js                # UPDATED: 3 group constants + helpers
│   │   ├── access.js               # NEW: shared column-access policy
│   │   ├── mailer.js
│   │   ├── notifications.js
│   │   └── impact-cells.js         # UPDATED: parent/sub-cell helpers + 70 legacy cells
│   ├── controllers/                # UPDATED per phase
│   │   └── ...
│   └── routes/                     # UPDATED per phase
│       └── ...
├── src/
│   ├── lib/
│   │   ├── api.ts                  # UPDATED per phase
│   │   ├── types.ts                # UPDATED: add ImpactCellHierarchy types
│   │   ├── roles.ts                # UPDATED: 3 group helpers
│   │   ├── access.ts               # NEW: mirror server column-access policy
│   │   └── auth-context.tsx
│   ├── components/
│   │   ├── AppLayout.tsx           # UPDATED: new sidebar group layout per user group
│   │   └── ui/ ...                 # shared shadcn
│   ├── style/
│   │   └── dashboard.css           # NEW: design-system tokens
│   └── routes/_authenticated/
│       ├── dashboard.tsx           # UPDATED: 3 dashboards, leadership board component
│       ├── guests.tsx              # UPDATED: column-scoped UI per group
│       └── ...
├── public/
│   └── logo.png                    # The Summit/SBC red logo (already in old app)
└── Implementation/                 # NEW: v2 plan docs (this folder)
```

---

## Environment

`.env.example` (lock these names for v2):

```
DATABASE_URL="mysql://ipcDBurs22:Ldycgw^5676GGH@HOST:3306/impact_guest"

JWT_SECRET="CHANGE_ME_LONG_RANDOM_STRING"
JWT_EXPIRES_IN="7d"

PORT=3000
CORS_ORIGIN="https://app.summitdata.one"
NODE_ENV=production

SMTP_HOST=""
SMTP_PORT=587
SMTP_SECURE=false
SMTP_USER=""
SMTP_PASS=""
SMTP_FROM="SBC Application <no-reply@summitdata.one>"

VITE_API_BASE_URL=/api
APP_URL="https://app.summitdata.one"
```

> ⚠️ **Never commit the `.env` file.** Only `.env.example`.

---

## Cross-cutting decisions

### Active role & multi-role

The current `X-Active-Role` header stays. Behaviour:
- If a user has only one role, header is ignored.
- If multi-role, header is honoured when one of `[…user.roles]`.
- Frontend stores active role in `localStorage` `cgms.activeRole`.

### Column-level access (the new bit)

- **Server** (`server/lib/access.js`) is the **source of truth** for which fields a role can see/edit. **Never bypass it.**
- **Client** (`src/lib/access.ts`) is only for UI affordance (display OR hide OR disable). The server is still authoritative.
- The pre-existing `sanitize()` filter on Guests is **extended** in Phase 04 to strip fields the role cannot edit before Prisma writes.

### Soft deletes

- All `Guest` and `ImpactSubmission` deletes become soft (add `deletedAt DateTime?`). Hard delete is Admin-only via a separate "purge" endpoint, never wired into UI.

### Audit

- Every write goes through an `auditLog.create({ actorId, action, detail })` helper in `server/lib/audit.js`. We add convenience wrappers like `auditGuestChange(req, before, after)`.

---

## Build & deploy

Already documented in `Plan/Technical_Documentation/22_*` and `handoff.md`. Recap:

- Build **locally** with `npm run build` (Hostinger hits process/thread limits).
- Upload `dist/client` + `dist/server` together.
- Run on Hostinger via `server/server.js`, restart via `touch tmp/restart.txt`.
- Use `https://app.summitdata.one` as the canonical URL.

---

## What NOT to do in v2

- ❌ Don't introduce a new auth provider.
- ❌ Don't add a mobile-native app.
- ❌ Don't use a new state management library.
- ❌ Don't manually create `dist/client/index.html`.
- ❌ Don't store passwords in plaintext.
- ❌ Don't skip the audit log on any write path.

---
*Next: [02_Database_Schema.md](./02_Database_Schema.md).*
