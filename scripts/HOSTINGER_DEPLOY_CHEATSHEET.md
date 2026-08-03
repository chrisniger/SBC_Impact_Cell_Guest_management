# Hostinger SSH Deploy Cheatsheet — Laravel 12 + Vite (v2)

> One-page reference for `bash scripts/deploy_to_hostinger.sh` — the v2 Laravel-native deploy sequence per `Implementation/Phase_12_Deployment.md`. **NOT** the v1 Node/Passenger narrative — that's retired.

## 🚀 RUN AFTER EVERY `git pull` — production command block (verified 2026-08-03)

> Use this when updating the deployed code via `git pull` (the everyday flow).
> Key facts about this box: plain `php`/`npm` do **NOT** resolve in the SSH shell —
> you must use the full alt-php84 path and export the alt-nodejs22 PATH. The two
> seeders below are `firstOrCreate`-idempotent, so re-running them on every pull
> is safe and prevents the `/register` 503 (missing roles) and empty cell-dropdown
> incidents from ever recurring.

```bash
# 1. SSH in
ssh u188660189@ssh.hostinger.com

# 2. Project root — the folder where composer.json lives (run `pwd` to confirm)
cd /home/u188660189/domains/app.summitdata.one/public_html

# 3. Full-path binaries
PHP=/opt/alt/php84/usr/bin/php
export PATH=/opt/alt/alt-nodejs22/root/bin:$PATH

# 4. Pull the update branch
git pull origin update

# 5. Composer + frontend build (skip npm if no JS/React changes in the pull)
$PHP /opt/alt/php84/usr/bin/composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist
npm ci --no-audit --no-fund && npm run build

# 6. Migrate + idempotent seeders (both safe to re-run)
$PHP artisan migrate --force --no-interaction
$PHP artisan db:seed --class=RolesAndPermissionsSeeder --force
$PHP artisan db:seed --class=ImpactCellSeeder --force

# 7. Cache trio + storage link (RE-RUN after any .env change too!)
$PHP artisan config:cache && $PHP artisan route:cache && $PHP artisan view:cache && $PHP artisan storage:link

# 8. Permissions
chmod -R 775 storage bootstrap/cache && chown -R u188660189:www-data storage bootstrap/cache

# 9. Verify
curl -s -o /dev/null -w 'home: %{http_code}\n' https://app.summitdata.one/
curl -s -o /dev/null -w 'register: %{http_code}\n' https://app.summitdata.one/register
#    Expect 200 for both. Hard-refresh the browser (Ctrl-Shift-R) after.
```

**Maintenance-mode tip:** if you ever run `$PHP artisan down` before a deploy, the
block above does NOT re-enable the app — run `$PHP artisan up` as the very last
step (the app was found stuck in maintenance once already; `up` must always run).

## Stack reference (reminder)

```
Browser (HTTPS) → Cloudflare/Hostinger → Apache → DocumentRoot: public_html/laravel/public/
                                              ↓
                                          index.php (Laravel 12)
                                              ↓
                                          Vite-emitted public/build/app-*.{js,css} (React/Inertia)
                                              ↓
                                          Spatie Permission + Activitylog → MySQL 8.4
```

## The 7 critical Hostinger-side commands (deploy order)

> ⚠️ This block is the **rsync full-deploy variant** (`deploy_to_hostinger.sh`).
> For the everyday **`git pull`** flow use the command block at the top of this
> page — it uses the full php84 path that this box requires.

```bash
# 1. SSH into Hostinger (loads PATH=/opt/alt/alt-nodejs22/root/bin if using v1 Node; v2 doesn't need it)
ssh u188660189@ssh.hostinger.com

# 2. cd into project root on host (NOT public_html/)
cd /home/u188660189/domains/app.summitdata.one/public_html/laravel

# 3. composer install (production mode — drops phpunit, fakerphp, whoops etc.)
composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist

# 4. migrate (FORCE on prod — bypasses the "are you sure" prompt)
php artisan migrate --force --no-interaction

# 5. cache trio (config:cache + route:cache + view:cache + storage:link in one shell pass)
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link

# 6. permissions (storage/ + bootstrap/cache/ must be writable by Apache)
chmod -R 775 storage bootstrap/cache && chown -R u188660189:www-data storage bootstrap/cache

# 7. post-deploy health check (curl from local Windows AFTER SSH-side completes)
curl -sS -o /dev/null -w '%{http_code}' https://app.summitdata.one/api/health
#    Expect: 200 (or 302 redirected to /login if unauthenticated)
```

The full deploy sequence (rsync + scp + all 7 commands above) is one bash script entrypoint:
```bash
bash scripts/deploy_to_hostinger.sh        # run from local (rsync-over-ssh)
bash scripts/deploy_to_hostinger.sh rollback  # revert to .previous
```

## DocumentRoot configuration (Hostinger control panel)

**Critical:** Hostinger control panel → Hosting → Domains → `app.summitdata.one` → DocumentRoot must be set to:

```
/home/u188660189/domains/app.summitdata.one/public_html/laravel/public
```

NOT `/home/u188660189/domains/app.summitdata.one/public_html/laravel/` — Laravel 12 only routes through `public/index.php`. Without this, hitting `/` returns the project-root directory listing (security issue) instead of the Inertia-rendered home.

If Hostinger forces `DocumentRoot = public_html/` (shared hosting constraint), add this `.htaccess` at the user-provided subdomain root:
```apache
RewriteEngine On
RewriteRule ^(.*)$ /laravel/public/$1 [L]
```

Alternative Hostinger workflow: use the "Addon Domain" subdomain config and map `app.summitdata.one/` directly to `/laravel/public/` (no .htaccess workaround needed).

## Rollback procedure (if the new build is broken)

```bash
# From local Windows, after confirming the health endpoint is 5xx:
bash scripts/deploy_to_hostinger.sh rollback

# Manual rollback (if bash script is unavailable):
ssh u188660189@ssh.hostinger.com
cd /home/u188660189/domains/app.summitdata.one/public_html/laravel
mv . .broken-$(date +%Y%m%d-%H%M%S)
cp -r .previous .
php artisan config:cache && php artisan route:cache && php artisan view:cache
chmod -R 775 storage bootstrap/cache && chown -R u188660189:www-data storage bootstrap/cache
# Verify:  curl https://app.summitdata.one/api/health
```

The `.previous` directory is kept at every deploy success. **Do not delete it** unless you've validated the new build for at least 24 hours.

**Note:** Phase 12 v2 does NOT use `touch tmp/restart.txt` (that was v1 Phusion Passenger for Node.js 22). Laravel Apache picks up the new `index.php` on the next request automatically.

## Quick troubleshooting (4-row reference)

| Symptom | First thing to check | Fix |
|---|---|---|
| Browser shows old UI (cached) | Browser cache | `Cmd-Shift-R` (macOS) / `Ctrl-Shift-R` (Windows/Linux) hard-refresh to bypass cached `app-<old-hash>.js` |
| Direct hit to `/` returns raw file listing | DocumentRoot misconfig | Hostinger control panel → DocumentRoot → `/home/u188660189/domains/app.summitdata.one/public_html/laravel/public` |
| `RuntimeException: No application encryption key has been specified` | `.env` not uploaded | Re-run `scp .env u188660189@ssh:.../laravel/.env` (must be separate from rsync) |
| 500 + `Base table or view not found` | Migrations not run on prod | `cd .../laravel && php artisan migrate --force --no-interaction` |
| 404 on `/storage/*` paths | Storage symlink missing | `cd .../laravel && php artisan storage:link` |
| Logout / session 419 errors | `SESSION_DRIVER=database` + sessions table migrated | Same as migrations — `php artisan migrate` runs the Breeze `sessions` table |

## After-deploy browser verification (manual)

1. Open `https://app.summitdata.one/login` (expect 200 + Breeze login form)
2. Log in as `sbcadmin@impact.test` / `//Chris##101` → admin Dashboard renders, 7 KPI cards visible
3. Log out, log in as `officer1@impact.test` / `//Officer##101` → Officer Dashboard with 5 KPIs + team queue
4. Log out, log in as `phase04-test-Impact_Leaders@impact.test` / `//Impact_Leaders##101` → Impact Cell Leader dashboard with assigned guests table (and `/leadership` accessible)
5. Audit log check: SSH in + `cd .../laravel && php artisan tinker --execute='echo \Spatie\Activitylog\Models\Activity::count()'` — expect > 0 entries

## Companion scripts

- `scripts/deploy_to_hostinger.sh` — the v2 deploy script (all 7 commands + preflight + rollback)
- `scripts/v2_deploy_preflight.sh` — local-only pre-deploy artifact check (composer + npm + Vite + artisan caches)
- `scripts/verify_phase12_run.php` — 19-sub-assertion verifier confirming this doc is current + HANDOFF §1 Phase 12 row still ✅ Done (19/19)

---

*If a deploy fails and rollback fails too, escalate to Hostinger support — they can manually restore `.previous` from backup-archive.*
