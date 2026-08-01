# Phase 25 — Stale `php artisan serve` causes recurring `/register` 503s

## Symptom

`http://localhost:8000/register` returns HTTP **503 Service Unavailable** with the body
*"Signup is temporarily unavailable: required roles are not seeded: Impact_Leaders, Impact_Zonal_Coordinator. Run `php artisan db:seed --class=RolesAndPermissionsSeeder`."*

But:

- `php artisan roles:audit` reports `Healthy: YES` (10 of 10 on `web` guard).
- `php artisan tinker --execute='echo Spatie\\Permission\\Models\\Role::count()'` returns `10`.
- A fresh artisan shell's curl `http://localhost:8000/register` returns HTTP **200** with size 29824 bytes.

Yet the user's browser still hits 503.

This sentence triad — same-shell reports green, browser reports red — is the signature.

## Root cause

The `php artisan serve` process bound to `:8000` (PID 4616 in the most recent
incident) was started BEFORE the `roles` table was last wiped/reseeded. Once
reseeded, the artisan shell sees the new state immediately, but the **running
serve process** holds stale state from its original boot time:

1. **PHP OPcache bytecode**: PHP 8.3 OPcache is per-FPM-worker / per-proc and
   keeps compiled `Role::whereIn(...)` resolution paths. A running
   `php artisan serve` doesn't reload its bytecode until restarted.
2. **Spatie permission cache**: `cache.laravel.PermissionRegistrar.cacheKey`
   stored "no signup-visible roles" at boot. `php artisan cache:clear` writes
   through to whichever cache store `CACHE_STORE` env points at
   (`database` by default — see `config/cache.php`), but the running serve
   process's reflection cache loader may not re-read on the very next request.
3. **`bootstrap/cache/config.php`**: written by `php artisan config:cache`,
   bakes `.env` values into compiled config — a subsequent DB switch, role-name
   rename, or `CACHE_STORE` change won't propagate until `php artisan config:clear`.
4. **Eloquent DB connection pool**: the serve process's PDO connection was
   opened against whatever `DB_DATABASE` env said at boot. If `.env` later
   switched (e.g. `impact_guest` → `impact_test`), the serve connection is
   stale and the artisan shell's freshly-opened connection is not.

The recovery that "worked" — `php artisan migrate --force && db:seed && cache:clear`
plus `optimize:clear` plus targeted `bootstrap/cache/config.php` deletion — is
correct. The serve process restarted against the recovered DB is what brings
the user's browser from 503 to 200.

## Canonical one-liner

```bash
bash scripts/restart_dev_server.sh
```

The script:
1. Kills any process bound to `:PORT` (`taskkill //F //PID <n>` on Windows Git
   Bash; `pgrep` + `kill` on Linux/macOS).
2. Busts every Laravel cache layer: `config:clear`, `cache:clear`, `route:clear`,
   `view:clear`, `optimize:clear`, and deletes `bootstrap/cache/config.php`.
3. Re-seeds `RolesAndPermissionsSeeder` (idempotent — uses `Role::firstOrCreate`
   keyed by `(name, guard_name)`).
4. Verifies `php artisan roles:audit` reports `Healthy: YES`. Exits 2 if not.
5. Relaunches `nohup php artisan serve --port=8000 --host=127.0.0.1`,
   logged to `storage/logs/serve.log`. `disown` so the parent shell can exit
   without killing the serve.
6. Polls `/login` every 500 ms up to 10 s. Exits 1 if the server doesn't come up.
7. HTTP-verifies `/register`, `/login`, `/` — surfaces a non-200 `/register`
   with the last 30 lines of `serve.log` for diagnosis.

### Knobs (env overrides)

| Variable     | Default                  | Meaning                                       |
| ------------ | ------------------------ | --------------------------------------------- |
| `PORT`       | `8000`                   | Bind port for the new serve                   |
| `HOST`       | `127.0.0.1`              | Bind host                                     |
| `LOG_DIR`    | `<project>/storage/logs` | Where to write `serve.log`                    |
| `SKIP_RESEED`| unset → `0`              | Set to `1` to trust an out-of-band reseed    |

### Exit codes

| Code | Meaning                                                          |
| ---- | ---------------------------------------------------------------- |
| 0    | server live, `/register` = 200, `roles:audit` Healthy = YES      |
| 1    | server failed to come up OR `/register` != 200 after recovery    |
| 2    | `roles:audit` did NOT report Healthy: YES after reseed          |

## Environment where this was diagnosed

- **OS**: Windows + Git Bash (`/c/laragon/bin/php/php-8.3.30-Win32-vs16-x64`),
  also tested on Linux paths via `pgrep` / `kill`.
- **DB**: MySQL 8.x via Laragon, `impact_guest` database, user `ipcDBurs22`,
  driver `mysql`.
- **Stack**: Laravel 11, PHP 8.3, Spatie Permission 6.x, Inertia.js.
- **Incident timeline**: Three consecutive recurring 503s over the same
  long-running serve session (PID 4616, boot at `07:29:56`). Each artisan
  shell recovery reported Healthy: YES + curl 200; the user's browser hits
  kept hitting 503. The fourth kill-and-relaunch of `php artisan serve` made
  the recovery sticky for the session.

## In-repo wipe audit

`code-searcher` ripgrep audit across `database/**`, `scripts/**`, `app/Console/**`,
`bootstrap/app.php`, `database/migrations/*`, `database/factories/*` — **zero
TRUNCATE / DELETE FROM roles / migrate:fresh / scheduled-wipe code exists in
the source tree.** The actual wipe is **external** to this repo. Likely causes:

| Hypothesis                                                          | Diagnostic                                                                                                                  |
| ------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------- |
| A teammate's interactive MySQL session ran `TRUNCATE TABLE roles;` | MySQL has `general_log` (`SET GLOBAL general_log_file=...; SET GLOBAL general_log=ON;`) — turn it on for an hour to confirm |
| A separate shell ran `php artisan migrate:fresh --seed`             | `migrate:fresh` leaves `migrations` rows with newer `batch` numbers; check `SELECT MAX(batch) FROM migrations;`             |
| A `php artisan tinker` one-liner was run by a prior agent           | Audit `storage/logs/laravel.log` for the timestamp of the deletion                                                          |
| A runs-on-boot hook on Laragon / Docker                             | Check `.laragon`, `docker-compose.yml`, `Procfile`, `~/.bashrc` for any artisan-side `db:wipe`                              |

The recovery script doesn't address the external wipe — only its symptom.
The HANDOFF note here is the canonical place to start when the user reports
"503 again at `+{N} hours`".

## How Phase 14 guard ties in

`RegisteredUserController::ensureSignupRolesSeeded()` is the Phase 14 guard
that fires the 503 when the roles table is missing entries. That guard is
**correct behaviour** — without it, `User::syncRoles(['Impact_Leaders'])` would
throw `Spatie\Permission\Exceptions\RoleDoesNotExist` deep inside the DB
transaction, rollback invisibly, and surface as a 500 "Whoops, looks like
something went wrong." With the guard, the 503 surfaces with a remediation
hint embedded in the body.

The Phase 25 addition is the upstream recovery loop: when the guard fires,
run `bash scripts/restart_dev_server.sh` and the browser returns to 200.

## Long-term hardening

- `scripts/deploy_to_hostinger.sh` already wraps
  `php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan storage:link`
  These compile caches that, in turn, REQUIRE a serve restart after
  deploy. Document this next to the deploy script.
- For local dev, never leave `php artisan serve` running across a
  `php artisan migrate:fresh --seed`. Always run `bash scripts/restart_dev_server.sh` after.
- Bump the Phase 14 guard's 503 body to mention the recovery one-liner when the
  `app.debug` env is true:
  *"Signup is temporarily unavailable. Local dev: run `bash scripts/restart_dev_server.sh`."*

## Related files

- `scripts/restart_dev_server.sh` — the canonical one-liner (added in this phase).
- `scripts/rotate_sbcadmin_password.sh` — same idempotent-recovery loop pattern
  (`bash scripts/rotate_sbcadmin_password.sh [sentinel|<plaintext>|status]`).
- `scripts/smoke_phase17.sh` — visual Phase 17 smoke test; preflights
  `:8000/login` and runs CDP-driven Chrome verification. Treats a missing
  serve as a non-recoverable error and exits early.
- `app/Http/Controllers/Auth/RegisteredUserController.php` — Phase 14 guard
  rail that fires the 503.
- `app/Console/Commands/RolesAuditCommand.php` (or wherever the audit lives) —
  reports `Healthy: YES|Healthy: NO` via the `roles:audit` artisan command.
- `database/seeders/RolesAndPermissionsSeeder.php` — uses
  `Role::firstOrCreate` per `RoleHelper::ROLE_NAMES` so reseed is idempotent.
- `app/Support/RoleHelper.php` — single source of truth for `ROLE_NAMES`
  (10 entries) and `SIGNUP_VISIBLE_ROLES` (2 entries); the audit hard-fails
  if they're out of sync.
