# `migrate.md` — Reusable prompt to clone & boot the app on a fresh local machine

> **Purpose.** This is a single, copy-paste-ready prompt you can give to a coding agent (minimax m3 / Freebuff / Claude / etc.) on a **different local machine** so it can:
>
> 1. clone this repository,
> 2. install every dependency (PHP / Node),
> 3. create the `.env` and `APP_KEY`,
> 4. bring the database schema fully up to date with the current migrations,
> 5. verify the app boots cleanly,
> 6. continue local development from where this machine left off.
>
> It is **explicitly not** a deployment prompt. No production env, no public host, no build artifact upload — just `php artisan serve` + `vite`.

---

## Before you paste — fill in these placeholders

| Placeholder | What it means | Example |
|---|---|---|
| `[PARENT_DIR]` | Absolute path of the parent folder where the clone should live (no trailing slash). | `~/projects`, `/c/work`, `C:\xampp\htdocs` |
| `[TARGET_DIR]` | Folder name for the cloned repo (also no trailing slash). | `Impact_portal_plus_guest` |
| `[DB_CHOICE]` | `sqlite` (zero-config) **or** `mysql` (if the new machine has MySQL/MariaDB, e.g. via XAMPP). | `sqlite` |
| `[MYSQL_DB_NAME]` | Only required when `[DB_CHOICE]=mysql`. | `impact_portal_plus_guest` |
| `[MYSQL_USER]` | Only required when `[DB_CHOICE]=mysql`. | `root` |
| `[MYSQL_PASSWORD]` | Only required when `[DB_CHOICE]=mysql`. | *(empty for XAMPP default)* |

> Default for a brand-new machine: **`sqlite`**. It's what `.env.example` ships with, needs no server, and the project is fully exercised against it during local dev.

---

## The prompt to paste verbatim (after substituting the placeholders)

```text
Goal
====
You are helping me clone the "Impact Portal Plus Guest" Laravel app to a new
local machine, set it up, bring the database fully up to date, and confirm it
runs locally. This is a LOCAL DEV migration — NOT a deployment. Do not push to
production, do not run any deploy scripts, do not touch any remote hosts
beyond GitHub for the clone itself.

Repo & stack
============
- Remote   : https://github.com/chrisniger/SBC_Impact_Cell_Guest_management.git
- Backend  : Laravel 12, PHP ^8.2 (tested on 8.4)
- Frontend : Inertia 2 + React 18 + TypeScript, Vite 7, TailwindCSS 4 (via PostCSS plugin)
- DB       : default = sqlite (preferred for a fresh machine); mysql also supported via XAMPP/etc.
- Auth     : Laravel Sanctum + session-based; spatie/laravel-permission for roles; spatie/laravel-activitylog
- JS pkg   : pnpm (a pnpm-lock.yaml is committed — use it, do NOT use npm install)
- Doc set  : HANDOFF.md + Implementation/Phase_*.md describe every implemented phase; skim them at the end.

Placeholders (substitute these before executing)
================================================
- PARENT_DIR        = [PARENT_DIR]
- TARGET_DIR        = [TARGET_DIR]
- DB_CHOICE         = [DB_CHOICE]                 (sqlite | mysql)
- MYSQL_DB_NAME     = [MYSQL_DB_NAME]             (only if DB_CHOICE=mysql)
- MYSQL_USER        = [MYSQL_USER]                (only if DB_CHOICE=mysql)
- MYSQL_PASSWORD    = [MYSQL_PASSWORD]            (only if DB_CHOICE=mysql)

Important rules before you start
================================
- NEVER install packages globally without asking me first. Compose/pnpm/npm
  install confined to the project folder is fine.
- NEVER switch the package manager from pnpm to npm or yarn — the repo pins pnpm.
- NEVER modify .env values that hold secrets; copy .env.example and patch only
  the keys listed below.
- NEVER commit a .env file or a filled-in database.sqlite. They are .gitignore'd
  but verify before any commit.
- If any step blocks, STOP and report the exact error. Do not improvise fixes.

Procedure — execute in order, report after each numbered step
=============================================================

STEP 1 — Prerequisites check (report versions, do not install yet)
-----------------------------------------------------------------
Run and report the output of:
  php -v        | head -1   (must report PHP 8.2 or newer)
  composer --version
  node -v                 (must be >= 20)
  pnpm -v
  git --version
  sqlite3 --version      (only required if DB_CHOICE=sqlite)
For DB_CHOICE=mysql also report: which mysql / mariadb, mysql --version

If anything is missing, stop and tell me exactly which installer to run.
Do not auto-install.

STEP 2 — Clone
--------------
  cd "[PARENT_DIR]"
  git clone https://github.com/chrisniger/SBC_Impact_Cell_Guest_management.git "[TARGET_DIR]"
  cd "[TARGET_DIR]"

Verify:
  git log -1 --oneline
  git remote -v

If the remote is missing or wrong, run:
  git remote add origin https://github.com/chrisniger/SBC_Impact_Cell_Guest_management.git

Report the latest 3 commits so I can confirm we are in sync with origin/master.

STEP 3 — Backend dependencies
-----------------------------
  composer install --no-interaction --prefer-dist

If it errors, paste the full error and stop.

STEP 4 — Frontend dependencies
-------------------------------
  pnpm install --frozen-lockfile

If pnpm is unavailable, STOP — do not fall back to npm. Tell me to install
pnpm via `npm i -g pnpm` (corepack is fine too: `corepack enable && corepack prepare pnpm@latest --activate`).

STEP 5 — Environment file
-------------------------
  cp .env.example .env
  php artisan key:generate

Then edit .env. ONLY change the keys listed below; leave everything else as-is.

A) Always set:
   APP_NAME="Impact Portal Plus Guest"
   APP_ENV=local
   APP_DEBUG=true
   APP_URL=http://localhost:8000
   MAIL_MAILER=log                      # keeps mail in storage/logs/laravel.log — do not switch to smtp on a fresh local

B) If DB_CHOICE = sqlite (recommended):
   DB_CONNECTION=sqlite

   Then ensure the SQLite file exists:
     mkdir -p database
     [ -f database/database.sqlite ] || touch database/database.sqlite

   No DB_HOST / DB_PORT / DB_USERNAME / DB_PASSWORD lines are needed (and the
   .env.example already comments them out).

C) If DB_CHOICE = mysql:
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=[MYSQL_DB_NAME]
   DB_USERNAME=[MYSQL_USER]
   DB_PASSWORD=[MYSQL_PASSWORD]

   Before running migrate, create the schema:
     mysql -u[MYSQL_USER] -p'[MYSQL_PASSWORD]' \
       -e "CREATE DATABASE IF NOT EXISTS \`[MYSQL_DB_NAME]\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

   Confirm utf8mb4 (the app relies on it for the emoji/full-Unicode guest notes).

STEP 6 — Database
-----------------
  php artisan migrate --force

Then:
  php artisan migrate:status       # every row should show "Ran"
  php artisan db:seed --force      # ONLY if database/seeders/DatabaseSeeder.php
                                   # contains seeders you actually want locally.
                                   # Read it first; skip harmless/no-op seeders.

Sanity checks (report any failures):
  php artisan tinker --execute='echo App\Models\User::count();'
  php artisan tinker --execute='echo count(Schema::getTableListing());'

STEP 7 — Build frontend assets
------------------------------
  pnpm run build

This runs `tsc && vite build` and outputs to public/build/. If you plan to
iterate, leave this build artifact in place and use `pnpm run dev` (Vite HMR)
during development.

STEP 8 — Smoke test the app
---------------------------
Start the dev stack (single command that runs server + queue + pail + vite):
  composer run dev

In a second terminal (or after cancelling the above), confirm the simpler path:
  php artisan serve --host=127.0.0.1 --port=8000

Then open the following in a browser and report the HTTP status of each:
  GET http://127.0.0.1:8000/                  → should 200/302 (landing/login)
  GET http://127.0.0.1:8000/login             → 200
If the route is gated, just confirm the HTML returns without a 500.

STEP 9 — Quick test suite run
-----------------------------
  composer test
# equivalent to: php artisan config:clear && php artisan test

Report pass/fail summary. Do NOT silence failing tests; if anything fails,
paste the failure and stop.

STEP 10 — Hand-off summary
--------------------------
Report back to me in this exact shape:

  CLONE         : commit <sha> on branch master
  PHP           : <version>
  NODE          : <version>
  PNPM          : <version>
  COMPOSER      : <version>
  DB            : <sqlite | mysql @ host>
  MIGRATIONS    : <N of N ran>
  BUILD         : OK / FAIL
  SERVE         : http://127.0.0.1:8000 → <status code>
  TESTS         : <N pass / N total>

If ANY step failed, list every failed step with the exact command and error.

Then say "READY FOR LOCAL DEV" and stop — do not start editing files,
do not commit anything, do not push anywhere. I will tell you what to work on
next.

What NOT to do
==============
- Do not run `php artisan key:generate` on a .env that already has APP_KEY set,
  unless I explicitly ask — it will invalidate any signed URLs / cookies.
- Do not run `php artisan optimize` or `php artisan config:cache` in local —
  we want live reload while iterating.
- Do not touch .htaccess, public/index.php, vite.config.js, tsconfig.json,
  tailwind.config.js, composer.json, or package.json without asking.
- Do not switch MAIL_MAILER from `log` to anything else unless I give SMTP creds.
- Do not add or remove git remotes beyond the single `origin` above.

Troubleshooting reference (use only if a step fails)
===================================================
- "Class 'X' not found" after `composer install` → re-run
    composer dump-autoload
- Vite error "Cannot find module @inertiajs/react" → re-run
    pnpm install --frozen-lockfile
  (do NOT delete pnpm-lock.yaml)
- SQLite says "database is locked" → ensure no other process holds
    database/database.sqlite; on Windows, close any open DB Browser sessions.
- MySQL "Unknown database '[MYSQL_DB_NAME]'" → re-run the CREATE DATABASE
  step in STEP 5C; remember to use backticks around the name.
- Migrations appear to "have already run" but tables are empty →
    php artisan migrate:fresh --force
  (DESTROYS LOCAL DATA — only do this on a brand-new DB.)
- Permission errors on storage/ or bootstrap/cache/ →
    chmod -R ug+rwx storage bootstrap/cache
  (or on Windows: right-click → Properties → Security → grant your user
  Modify.)

Where the agent should look next (read-only, no edits)
======================================================
- HANDOFF.md                                  — overall migration ledger
- Implementation/Phase_*.md                   — what each phase built
- Plan/Technical_Documentation/00_Table_of_Contents.md
- routes/web.php, routes/auth.php             — route map
- config/permission.php, config/database.php  — runtime config
- database/migrations                         — full schema history

I will then tell you what to work on. Stay stopped after the hand-off summary.
```

---

## How to use this file

1. On the new machine, `git clone` (or just `scp`/`cp`) this single file into a scratch folder.
2. Open it, replace the `[PARENT_DIR]`, `[TARGET_DIR]`, and `[DB_CHOICE]` placeholders (and MySQL fields if applicable).
3. Copy the fenced code block inside **The prompt to paste verbatim** — including the "Goal / Repo & stack / Procedure" sections — and paste it as the *first message* to your coding agent.
4. The agent will clone, install, migrate, smoke-test, and report back with the exact hand-off shape in **STEP 10**.

> Tip: if you want the agent to *also* know which local machine + OS your target is on (so it picks Windows-PowerShell vs Linux-bash command form), add one extra line above the `Goal` section:
>
> `Target machine: <Windows 11 with XAMPP | macOS 14 | Ubuntu 24.04 | …>`

---

## Notes for future-me

- The repo's `composer setup` script (`composer.json` → `scripts.setup`) does ~80% of this in one shot, but it runs `npm install` (not `pnpm install`) and the project's lockfile is **pnpm-lock.yaml**, so the script as-written would drift a lockfile. The manual procedure above intentionally sticks to pnpm to keep the lockfile authoritative.
- Keep this file in sync if any of the following change:
  - Git remote URL
  - Default DB in `.env.example`
  - Package manager change (pnpm → npm or vice versa)
  - Major version bumps of PHP / Node / Laravel


#============================================================================== Production Command

# 2. Go to project root
cd /home/u188660189/domains/app.summitdata.one/public_html

# 3. Enable Node.js
export PATH=/opt/alt/alt-nodejs22/root/bin:$PATH

# 4. Pull latest code
git pull origin update

# 5. Install PHP dependencies
/opt/alt/php84/usr/bin/php composer.phar install --optimize-autoloader --no-dev --no-interaction --prefer-dist


# 6. Install frontend dependencies and build
npm ci --no-audit --no-fund
npm run build

# 7. Run database migrations
/opt/alt/php84/usr/bin/php artisan migrate --force --no-interaction

# 8. Seed required data
/opt/alt/php84/usr/bin/php artisan db:seed --class=RolesAndPermissionsSeeder --force
/opt/alt/php84/usr/bin/php artisan db:seed --class=ImpactCellSeeder --force

# Optional seeders
/opt/alt/php84/usr/bin/php artisan db:seed --class=AdminUserSeeder --force
/opt/alt/php84/usr/bin/php artisan db:seed --class=FollowUpOfficerSeeder --force
/opt/alt/php84/usr/bin/php artisan db:seed --class=FollowUpTeamSeeder --force
/opt/alt/php84/usr/bin/php artisan db:seed --class=ZonalCoordinatorSeeder --force

# 9. Optimize caches
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
/opt/alt/php84/usr/bin/php artisan event:cache
/opt/alt/php84/usr/bin/php artisan storage:link

# 10. Fix permissions
chmod -R 775 storage bootstrap/cache
chown -R u188660189:www-data storage bootstrap/cache

# 11. Verify deployment
curl -s -o /dev/null -w 'home: %{http_code}\n' https://app.summitdata.one/
curl -s -o /dev/null -w 'register: %{http_code}\n' https://app.summitdata.one/register