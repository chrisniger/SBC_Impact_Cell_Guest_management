# Phase 12 — Deployment to Hostinger

> The deployment story is already documented in `handoff.md` and `Plan/Functional_Documentation/13_Future_Recommendations.md`. This phase bakes it into a checklist that any AI agent or developer can follow without re-discovery.

## Goal

Ship the v2 build to **`https://app.summitdata.one`** (the active preferred URL). End-to-end, the deployed app must match the local dev experience.

## In scope

1. **Pre-deploy verification**:
   - [ ] `.env` on Hostinger matches the new credentials (Phase 01).
   - [ ] `prisma migrate deploy` already ran on host (or is part of the deploy script).
   - [ ] `prisma generate` already produced the latest client.
   - [ ] `npm run seed` re-run if needed (creates default sbcAdmin if missing).
2. **Build locally**:
   - [ ] `npm run build` passes.
   - [ ] `dist/client/` AND `dist/server/` are produced.
3. **Upload to Hostinger**:
   - [ ] Upload `dist/client/` → `/home/u188660189/domains/app.summitdata.one/public_html/.builds/last-source/dist/client/`.
   - [ ] Upload `dist/server/` → `/home/u188660189/domains/app.summitdata.one/public_html/.builds/last-source/dist/server/`.
   - [ ] Upload any updated `server/*.js` files.
4. **Restart Passenger**:
   - [ ] SSH into host with `export PATH=/opt/alt/alt-nodejs22/root/bin:$PATH`.
   - [ ] `mkdir -p tmp && touch tmp/restart.txt`.
5. **`.htaccess` completeness check**:
   - [ ] `PassengerAppType node` pointing at `node` 22 alt binary.
   - [ ] `PassengerStartupFile server/server.js`.
   - [ ] One single `PassengerAppRoot` + matching `PassengerRestartDir`.
   - [ ] No conflicting `nodejs` and `.builds/last-source` directives.
6. **Verify**:
   - [ ] `https://app.summitdata.one/` renders the login page.
   - [ ] `https://app.summitdata.one/api/health` → `{ ok: true }`.
   - [ ] Log in as `sbcAdmin`, see the redesigned Impact Cell tab.
   - [ ] Log in as a `FollowUpOfficer`, confirm group-scoped guests.
   - [ ] Log in as an `Impact_Leaders` user, see the Leadership Board.
7. **Smoke tests**:
   - [ ] Create a guest from Admin → assign to Officer → Officer sees.
   - [ ] Officer changes `contactedStatus` → saved, KPI updates.
   - [ ] Impact Leader submits a Member record → appears in My Reports.
   - [ ] Admin splits a primary cell into 3 subs → parent dashboard renders the board.

## Common pitfall: stale dist

If the deployed site shows an older version:
1. Check that `dist/client` and `dist/server` were uploaded from the **same build**.
2. Hard-refresh browser (`Cmd-Shift-R` / `Ctrl-Shift-R`).
3. Confirm Hostinger `PassengerAppRoot` and `PassengerRestartDir` match the uploaded folder.
4. Re-run `touch tmp/restart.txt`.

## Rollback

If the new build is broken:
1. `mv dist dist.broken`
2. `cp -r dist.previous dist`
3. `touch tmp/restart.txt`

(We kept a `dist.previous` archive — discipline.)

## Final go/no-go checklist

- [ ] All 12 phases have `npm run build` passing locally.
- [ ] `https://app.summitdata.one/` matches local.
- [ ] No 500s on `/api/health`, `/login`, `/dashboard` for any of the 3 user groups.
- [ ] Three user group column policies verified on the live site.
- [ ] Leadership Board renders for at least one primary cell on the live site.
- [ ] Audit log captures at least one entry per write type (guest create, guest update, member submission, weekly report submitted).

When all green, mark the v2 redesign complete.

---
*End of implementation plan. Total: 7 architecture + 12 phase = 19 docs.*
