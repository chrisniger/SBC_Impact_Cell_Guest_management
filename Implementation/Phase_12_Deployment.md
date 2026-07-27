# Phase 12 — Deployment to Hostinger (v2)

> The previous Phase 12 (Node/v1 CRA-style build) deploy narrative was retired when the build pivoted to Laravel 12 + Vite + Inertia + React. This file is the **v2 sequence** — Hostinger handoff for the production stack at `https://app.summitdata.one`.

## Goal

Ship the v2 build to **`https://app.summitdata.one`** (the active preferred URL). End-to-end, the deployed app must match the local dev experience, boot from Laravel + Vite + Inertia + Spatie, and serve all 3 user groups without 500s.

## Stack layer reminders (v2)

```
Browser (HTTPS) → Cloudflare/Hostinger → Apache → DocumentRoot: public_html/laravel/public/
                                              ↓
                                          index.php (Laravel 12 front controller)
                                              ↓
                                          routes/web.php (Inertia::render)
                                              ↓
                    ┌─────────────────────────┼─────────────────────────┐
                    ↓                         ↓                         ↓
            Phase 04-08 controllers   Spatie Permission/Activitylog   Vite-emitted bundle
                    ↓                         ↓                         ↓
              MySQL 8.4 (caching_sha2)   activity_log / permission   public/build/app-<hash>.{js,css}

Frontend (React 18 + Inertia v2) bundled by Vite → public/build/ assets (JS/CSS, content-hashed)
Tailwind 3.2 → compiled via @tailwindcss/postcss → public/build/assets/*.css
Spatie Permission + Activitylog → MySQL 8.4 (caching_sha2_password) → impact_guest
Mail (SMTP) → Mailtrap / Gmail / SES / etc. → via Laravel Mail (defaults to MAIL_MAILER=log in dev)
```

## In scope

### 1. Pre-deploy verification

- [ ] Hostinger control panel: `DocumentRoot` points at `public_html/laravel/public/` (NOT `public_html/laravel/` itself) — required for Laravel 12 + tight `.htaccess` access to `index.php`
- [ ] Hostinger PHP version ≥ 8.2 (Laravel 12 requires PHP 8.2+; ideally 8.4 to match `composer.json`)
- [ ] `.env` on Hostinger has prod credentials populated:
  - `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://app.summitdata.one`
  - `APP_KEY` populated via `php artisan key:generate --show` (NEVER commit `.env` to git)
  - DB: `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=impact_guest_prod`, `DB_USERNAME=u188660189_prod`, `DB_PASSWORD=<secret>`
  - Mail: `MAIL_MAILER=smtp` + `MAIL_HOST/PORT/USERNAME/PASSWORD` per chosen provider
  - Session: `SESSION_DRIVER=database` (re-uses the Breeze-shipped `sessions` table)
- [ ] `composer.lock` committed (hosting will run `composer install` to honour the lock)
- [ ] MySQL prod DB created + `php artisan migrate --force` planned as part of this deploy
- [ ] Breeze-shipped `sessions/cache/jobs` tables migrated on prod (re-uses Breeze's default migrations)

### 2. Build locally

- [ ] `composer install --optimize-autoloader --no-dev` — produces a `vendor/` tree shipping only production dependencies (~few MB lighter without `phpunit`/`laravel-debugbar`/dev tools)
- [ ] `npm install` (or `pnpm install`) — installs Vite + Tailwind + React 18 + Inertia deps into `node_modules/`
- [ ] `npm run build` — Vite emits the bundled contents to `public/build/` (assets/content-hashed-named — `app-<hash>.js`, `app-<hash>.css`, manifest under `public/build/manifest.json`)
- [ ] `php artisan view:cache route:cache config:cache storage:link` — caches Blade/Inertia routes + Laravel config + creates the `public/storage` symlink

### 3. Upload to Hostinger

Two equally valid paths (rsync preferred for iterative deploys; zip+scp for first deploys without rsync access):

**A) `rsync`** (preferred for iterative deploys):
```
rsync -avz --delete \
  --exclude '.env' --exclude 'node_modules' --exclude 'storage/logs/*' \
  ./ /home/u188660189/domains/app.summitdata.one/public_html/laravel/
```

**B) ZIP + SCP + unzip** (for first deploys without rsync access):
```
zip -r deploy.zip . -x '.env' 'node_modules/*' 'storage/logs/*'
scp deploy.zip u188660189@server:~/
ssh u188660189@server <<'EOF'
  unzip -o deploy.zip -d /home/u188660189/domains/app.summitdata.one/public_html/laravel/
EOF
```

Note: keep `.env` out of the build artifact. Upload it separately (or `scp .env.production` after the rsync).

### 4. Production preflight (host-side, after upload)

- [ ] Upload `.env` separately (NOT in the rsync) — keep secrets out of the build artifact
- [ ] SSH in: `cd /home/u188660189/domains/app.summitdata.one/public_html/laravel && php artisan migrate --force`
- [ ] `php artisan config:cache route:cache view:cache` (re-cache against the host-side `.env`)
- [ ] `php artisan storage:link` (creates `public/storage` → `../storage/app/public`)
- [ ] `chmod -R 775 storage bootstrap/cache` + set ownership to the Apache user (`chown -R u188660189:www-data`)
- [ ] Verify the standard Laravel `.htaccess` is present at the project root and forwards everything to `public/index.php`
- [ ] Verify Hostinger control panel: `DocumentRoot` → `/home/u188660189/domains/app.summitdata.one/public_html/laravel/public/` (NOT `/public_html/laravel/`)
- [ ] Confirm `public/build/` exists in the uploaded tree (rsync/zipped should've included it) — required for React/Inertia hydration

### 5. Verify

- [ ] `https://app.summitdata.one/api/health` → `{"ok": true}` (or whatever the Inertia health endpoint returns — confirm the route is wired in `routes/web.php` + `produce HealthController`)
- [ ] `https://app.summitdata.one/login` renders the Breeze/Inertia login form (200, no 500)
- [ ] Log in as `sbcadmin@impact.test` (Administrator) → admin Dashboard `.component/admin` renders without 500 (verify with seeded admin user)
- [ ] Log in as `officer1@impact.test` (FollowUpOfficer) → Officer Dashboard variant with 5 KPIs renders
- [ ] Log in as `phase04-test-Impact_Leaders@impact.test` (Impact_Leaders) → Impact Cell Leader variant with assignedGuests table renders (and Leadership Board `/leadership` is also accessible)
- [ ] Confirm no 500s in `storage/logs/laravel.log` after the 3-user verification

### 6. Smoke tests

- [ ] **Guest create** — Admin creates a guest from `/guests/create` → appears in officer's queue + Audit log records `GUEST_CREATED`
- [ ] **Contacted-status update** — Officer sets `contactedStatus` to `Contacted`; KPI on Teams dashboard updates from "Contacted Today" counter
- [ ] **Member submission** — Impact Leader submits a Member record from `/impact-submissions/create?type=member`; appears in My Reports
- [ ] **Cell split** — Admin splits a primary cell into 3 sub-cells via `ImpactCell` hierarchy; Leadership Board re-renders

### 7. Audit log coverage

- [ ] At least one entry per write type, verified by querying `select description, count(*) from activity_log group by description`:
  - `GUEST_CREATED` (after smoke test 6.1)
  - `GUEST_UPDATED` (after smoke test 6.2 — Officer status update writes Activity log entry via Active column updates)
  - `member_submission` (after smoke test 6.3 — `ImpactSubmission::create()` writes Activity log)
  - `weekly_report_submitted` (after Impact Leader submits weekly report — Phase 09 `ImpactSubmissionController::notifyReportSubmitted()` trigger)
- [ ] All 4 entries have `causer_id` set to the acting user's id (no entries with `causer_id = null` for the smoke tests)

### 8. Common pitfall: stale build / wrong DocumentRoot

- Symptom: deployed site shows an older version OR 500s on every page.
- Diagnosis 1 — Vite bundle cache: hard-refresh browser (`Cmd-Shift-R` / `Ctrl-Shift-R`) to bypass browser's cached `app-<old-hash>.js`. If the bundled filename changes after `npm run build` but the browser still loads the old hash, the browser is using cached HTML.
- Diagnosis 2 — DocumentRoot misconfig: `curl https://app.summitdata.one/robots.txt` should return a Laravel-rendered 404 (NOT `public/index.php` running a normal route). If Apache serves a raw file listing from project root instead of routing through `public/index.php`, the wrong DocumentRoot is set.
- Diagnosis 3 — `.env` not uploaded: `php artisan config:cache` will fail with `RuntimeException: No application encryption key has been specified.`. Re-upload `.env` and re-cache.
- Diagnosis 4 — migrations not run: any model route will 500 with "Base table or view not found". Run `php artisan migrate --force`.
- Diagnosis 5 — storage not linked: `php artisan storage:link` not run, `/storage/*` 404s. Re-link.

### 9. Rollback

If the new build is broken:

1. `cd /home/u188660189/domains/app.summitdata.one/public_html/laravel`
2. `mv . .broken-$(date +%Y%m%d-%H%M%S)` (rename current broken build with timestamp suffix)
3. `cp -r .previous .` (restore the previous build from disk)
4. Verify Hostinger `DocumentRoot` still points at `laravel/public/` (NOT `.broken-*`)
5. (No `tmp/restart.txt` touch needed — Laravel on Hostinger Apache picks up new `index.php` on next request; no Phusion Passenger restart.)

We keep `.previous` as a discipline — at the start of each deploy, `cp -r laravel laravel.previous`. So `.previous` is always a known-good fallback.

### 10. Final go/no-go checklist

- [ ] All 12 phases have `composer install --optimize-autoloader --no-dev` + `npm run build` passing locally
- [ ] `https://app.summitdata.one/login` matches local (200, identical rendered HTML for unauthenticated users)
- [ ] No 500s on `/api/health`, `/login`, `/dashboard` for any of the 3 user groups (`sbcAdmin` sbcadmin@impact.test, `officer1` officer1@impact.test, `Impact_Leaders` phase04-test-Impact_Leaders@impact.test)
- [ ] Three user group column policies verified on the live site (tested by submitting a guest as Admin then editing as Officer — Admin sees all columns, Officer sees stripped columns)
- [ ] Leadership Board renders for at least one primary cell on the live site (`/leadership`)
- [ ] Audit log captures at least one entry per write type (guest create + guest update + member submission + weekly report submitted)
- [ ] Storage symlink resolves: `/storage/laravel-logo.png` returns 200 from `https://app.summitdata.one/storage/laravel-logo.png`

When all green, mark the v2 redesign complete.

---

*End of implementation plan. Total: 7 architecture + 12 phase = 19 docs. The deployment story is now Laravel-native + Hostinger-native; no Node/Passenger narrative remains.*
