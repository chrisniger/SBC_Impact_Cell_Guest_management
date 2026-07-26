# Phase 02 — Authentication & User Management

> Read **[00_Vision](./00_Vision.md)** § "Personas" and **[03_Three_User_Groups.md](./03_Three_User_Groups.md)** before starting.

## Goal

Implement the full auth + user-management pipeline so that **each of the 3 user groups** can log in and exist in the system as a real user — but no UI shows yet for *what they'll do* (that's Phase 04+).

## In scope

1. **JWT-based login** with bcrypt, with `X-Active-Role` header respected for multi-role users.
2. **Forgot/reset password** (already exists; verify it works with SMTP).
3. **User CRUD** (Admin only):
   - Create user with multi-role selection (e.g., a user can be both `FollowUpOfficer` and `Impact_Leaders`).
   - Update user.
   - Deactivate (soft).
4. **`/me` endpoint** returning `roles[]`.
5. **Frontend auth context** (`src/lib/auth-context.tsx`):
   - Stores the `User` object with the active role applied.
   - Persists token (`cgms.token`) and active role (`cgms.activeRole`) in `localStorage`.
   - `switchRole(role)` updates UI state, redirects to `/dashboard`.
6. **Frontend role helpers** (`src/lib/roles.ts`) updated to expose the **3 group** names:
   - `GROUP_IMPACT_CELL = ["Impact_Leaders", "Impact_Cell_Admin", "Impact_Cell_Report"]`
   - `GROUP_FOLLOW_UP_OFFICER = ["FollowUpOfficer", "Follow_UP_Admin"]`
   - `GROUP_FOLLOW_UP_TEAM = ["Follow_UP", "Follow_UP_View_Only"]`
   - helpers: `groupOf(role) → 'impactCell' | 'followUpOfficer' | 'followUpTeam' | null`, `isImpactCellRole`, `isFollowUpOfficerRole`, `isFollowUpTeamRole`.

## Files to create / modify

| Path | Action | Notes |
|---|---|---|
| `server/middleware/auth.js` | modify | keep existing + ensure `req.user.role` honours `X-Active-Role`. |
| `server/lib/roles.js` | modify | add the 3 group constants and `groupOf()`. |
| `server/routes/auth.routes.js` | modify | already has the right shape; add `PUT /auth/switch-role` (optional, for explicit persistence). |
| `server/routes/user.routes.js` | modify | already there; verify multi-role payload works. |
| `server/controllers/user.controller.js` | modify | verify `sanitizeRoles` includes 3 group role names. |
| `src/lib/roles.ts` | modify | add 3 group helpers exported from `roles.ts`. |
| `src/lib/roles.test.ts` | create | **NEW** — Vitest unit tests for `groupOf()` and the 9-role mapping. |
| `src/lib/auth-context.tsx` | modify | add `switchRole` and persist it; ensure `applyActiveRole` returns latest on mount. |
| `src/routes/_authenticated/users.tsx` | modify | updated user form lets admin select any combination of roles. Show "Group" pill (Impact Cell / Officer / Team). |
| `src/routes/login.tsx` | already exists; verify | |
| `src/routes/reset-password.tsx` | already exists; verify | |

## Acceptance criteria

- [ ] Admin can create a user with the 3 roles in any combination (e.g., a user with `FollowUpOfficer + Impact_Leaders`). Saving succeeds, the user shows in the list.
- [ ] Logging in as that multi-role user, dropdown shows the two roles. Switching reloads `/dashboard`.
- [ ] `GET /api/auth/me` returns `{ id, fullName, email, role, roles[], impactCellId, active, createdAt }`.
- [ ] An `Impact_Leaders` user can log in and see a dashboard placeholder page (we only stub the layout now).
- [ ] A `Follow_Up` user can log in and see a dashboard placeholder.
- [ ] A `FollowUpOfficer` user can log in and see a dashboard placeholder.
- [ ] All roles inactive users cannot log in.
- [ ] `POST /api/auth/forgot-password` returns `{ ok: true }` even for missing emails.
- [ ] Reset link (when SMTP works) lands on `/reset-password?token=…` and saves the new password.

## Tests

| Test | Expectation |
|---|---|
| **Unit (Vitest)** `src/lib/roles.test.ts` | `groupOf('Impact_Leaders') === 'impactCell'` |
| | `groupOf('FollowUpOfficer') === 'followUpOfficer'` |
| | `groupOf('Follow_UP') === 'followUpTeam'` |
| | `groupOf('Administrator')` → returns `null` (Admin is not in any of the 3 groups) |
| | `groupOf('Bogus')` → returns `null` |
| **API smoke** | `curl -X POST /api/auth/login -d '{"username":"sbcAdmin","password":"//Chris##101"}'` → 200 with `token`. |
| **API smoke** | `curl /api/auth/me` with `Bearer` token → user payload. |

## Rollback

If a regression locks everyone out: `DELETE FROM User WHERE username = 'sbcAdmin';` then re-run `npm run seed`.

---
*Next: [Phase_03_Impact_Cell_Model.md](./Phase_03_Impact_Cell_Model.md).*
