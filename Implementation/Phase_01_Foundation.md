# Phase 01 — Foundation

> Read **[00_Vision](./00_Vision.md)**, **[01_Architecture](./01_Architecture.md)**, **[02_Database_Schema](./02_Database_Schema.md)** before starting.

## Goal

Stand up the project skeleton, wire the production-equivalent local environment, and prove the database connection works. No new features yet — just a runnable shell.

## In scope

- Confirm `package.json` from the existing `followo_up_officer/` repo is current.
- Create `.env` (NOT committed) at project root mirroring `.env.example`, with `DATABASE_URL` pointing at the production database ProxySQL/Hostinger DSN the user pasted:
  - DB: `impact_guest`
  - User: `ipcDBurs22`
  - Password: `Ldycgw^5676GGH`
  - Port: `3306`
  - Host: confirm with the user (e.g. `auth-db1349.hstgr.io`)
- Run `npm install` (uses existing `package.json`).
- Apply Prisma migrations **only up to the current baseline** (`0001_init_baseline`). Do NOT apply the v2 migrations yet — those land in Phase 03.
- Run the existing seed to create the default admin: `sbcAdmin / //Chris##101`.
- Confirm:
  - `node server/server.js` boots without errors.
  - `GET http://localhost:3000/api/health` → `{ ok: true }`.
  - `GET http://localhost:3000/api/health` works the same way locally on `:3001` via `nodemon server/server.js`.
  - `vite` boots the frontend on `:5173`, proxying `/api` → `:3001`.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `.env` | create (gitignore'd) | Use the credentials the user provided. Never commit. |
| `.env.example` | modify | Already exists; verify all required keys are listed. |
| `README.md` (root) | modify | Add a top "Quick start" block that uses the new credentials. |
| `.gitignore` | modify | Ensure `.env`, `dist/`, `node_modules/` covered. |

## Acceptance criteria

- [ ] `npm install` runs clean (no peer warnings beyond the recharts chunk-size warning expected).
- [ ] `npx prisma migrate deploy` runs without errors against `impact_guest`.
- [ ] `node prisma/seed.js` creates the seed admin.
- [ ] Express boots on port `3000` (or `PORT` env). Logs `"SBC Application running on …"`.
- [ ] Vite boots on port `5173`. Proxy works: `http://localhost:5173/api/health` returns `{ ok: true }`.
- [ ] No new schema changes yet (Phase 03 introduces them).

## Tests

- **Manual smoke**:
  ```bash
  curl http://localhost:3000/api/health
  # → {"ok":true}
  ```
- **DB check**:
  ```bash
  npx prisma studio
  # User table has 1 row: sbcAdmin / //Chris##101
  ```

## Rollback

If anything fails, the new files are limited to `.env` and `.gitignore` tweaks. Delete `.env`, revert `.env.example` to its previous state, and `git diff .gitignore` to confirm.

## Out of scope

- Anything in [02_Database_Schema.md](./02_Database_Schema.md) (the new hierarchy fields).
- Anything in [03_Three_User_Groups.md](./03_Three_User_Groups.md) (column-level access).
- Any UI changes to the dashboard.

## Hand-off

When Phase 01 is done, the team can start Phase 02 in parallel. The DB is fresh-seeded; admins can log in.

---
*Next: [Phase_02_Auth_And_Users.md](./Phase_02_Auth_And_Users.md).*
