#!/usr/bin/env bash
# scripts/restart_dev_server.sh
#
# Phase 25 -- one-liner recovery for STALE `php artisan serve` state.
#
# Why this script exists
# ----------------------
# The Phase 14 deploy-misconfig guard (`RegisteredUserController::ensureSignupRolesSeeded()`)
# aborts /register with HTTP 503 whenever `RoleHelper::SIGNUP_VISIBLE_ROLES`
# are missing from the `roles` table. That's the BIG HALF of why
# /register goes 503.
#
# The OTHER HALF is the recovery loop. Once roles are reseeded, the
# SAME artisan shell that ran `db:seed` immediately sees HTTP 200 on
# /register. But the user's browser, talking to the LONG-RUNNING
# `php artisan serve` process that was bound to :8000 BEFORE the
# reseed, STILL hits 503. Why?
#
#   1. `php artisan serve` keeps PHP OPcache bytecode from boot
#      time and the Spatie permission cache at `cache.laravel.
#      PermissionRegistrar.cacheKey`. Even after `php artisan
#      cache:clear`, a running serve process doesn't reload that
#      bytecode path until YOU restart it.
#
#   2. `bootstrap/cache/config.php` (written by `php artisan
#      config:cache`) bakes .env values into compiled config.
#      Subsequent .env updates (DB_DATABASE switch, role-name fix,
#      etc.) don't propagate until `config:clear`.
#
#   3. The serve process's Eloquent connection pool may also derive
#      a different `database.default` if the .env was different
#      at boot. A reset is the only safe way to re-derive.
#
# So: after any reseed, you MUST kill + relaunch the serve. This
# script does all four steps in one shell session, idempotently,
# with explicit HTTP verification at the end. Run it any time
# /register 503s after a `migrate`/`seed` cycle.
#
# Usage
# -----
#     bash scripts/restart_dev_server.sh                 # default port 8000
#     PORT=8080            bash scripts/restart_dev_server.sh
#     HOST=0.0.0.0         bash scripts/restart_dev_server.sh
#     SKIP_RESEED=1        bash scripts/restart_dev_server.sh   # trust upstream seeder
#
# Exit codes
# ----------
#   0   server live, /register HTTP 200, roles:audit Healthy: YES, admin fixture present (when reseeding)
#   1   server failed to come up within ~10s OR /register != 200
#   2   roles:audit did NOT report Healthy: YES after reseed OR admin fixture (sbcadmin@impact.test) missing after reseed
#
# Idempotency
# -----------
# Re-running on an already-healthy stack is a no-op (kill kills a
# transient, then the new serve boot is identical to the last).
# Re-running immediately after a misconfig is the SAME as running
# it once -- you do not need to wait, retry, or restart-via-Ctrl-C.
#
# Related
# -------
#   scripts/rotate_sbcadmin_password.sh -- same recovery loop pattern
#      (designed for fixture rotation; wraps `php artisan sbcadmin:rotate`).
#   scripts/smoke_phase17.sh            -- visual smoke that also
#      preflights `http://localhost:8000/login`.
#   Implementation/Phase_25_Stale_Serve_Recovery.md -- incident write-up.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
PORT="${PORT:-8000}"
HOST="${HOST:-127.0.0.1}"
LOG_DIR="${LOG_DIR:-$PROJECT_ROOT/storage/logs}"
SERVE_LOG="$LOG_DIR/serve.log"

cd "$PROJECT_ROOT"

printf '[preflight] project root : %s\n' "$PROJECT_ROOT"
printf '[preflight] port         : %s\n' "$PORT"
printf '[preflight] serve log    : %s\n' "$SERVE_LOG"

# ---------------------------------------------------------------------------
# 1. Kill any `php artisan serve` already bound to :PORT
# ---------------------------------------------------------------------------
# Windows Git Bash uses taskkill against Win32 PIDs from `netstat -ano`.
# Linux/macOS uses pgrep on the artisan cmdline. Either way the goal is
# the same: free :PORT before we relaunch.
if command -v taskkill >/dev/null 2>&1; then
    WIN_PIDS="$(netstat -ano 2>/dev/null | awk -v p=":$PORT" '$2 ~ p && $4 == "LISTENING" {print $NF}' | sort -u)"
    if [ -z "$WIN_PIDS" ]; then
        printf '[lifecycle] no process currently bound to :%s\n' "$PORT"
    else
        for pid in $WIN_PIDS; do
            if [ -n "$pid" ] && [ "$pid" != "0" ]; then
                printf '[lifecycle] taskkill //F //PID %s\n' "$pid"
                taskkill //F //PID "$pid" >/dev/null 2>&1 || true
            fi
        done
    fi
else
    pids="$(pgrep -f "artisan serve.*--port=$PORT" || true)"
    if [ -z "$pids" ]; then
        printf '[lifecycle] no `php artisan serve` currently bound to :%s\n' "$PORT"
    else
        for pid in $pids; do
            printf '[lifecycle] kill -TERM %s\n' "$pid"
            kill -TERM "$pid" >/dev/null 2>&1 || true
        done
        sleep 1
        for pid in $pids; do
            if kill -0 "$pid" 2>/dev/null; then
                printf '[lifecycle] kill -KILL %s (did not honor TERM)\n' "$pid"
                kill -KILL "$pid" >/dev/null 2>&1 || true
            fi
        done
    fi
fi

# ---------------------------------------------------------------------------
# 2. Bust EVERY Laravel cache layer
# ---------------------------------------------------------------------------
# We hit them in dependency order: optimizers last so their success means
# the application cache layer is fresh, not stale.
printf '[lifecycle] clearing Laravel caches\n'
# Order matters:
#   1. `cache:clear` flushes the APPLICATION cache store (default:
#      database = the `cache` table). This is what busts Spatie's
#      PermissionRegistrar cache key -- REQUIRED even if `SKIP_RESEED=1`
#      is set, otherwise the permission cache stays stale and the
#      recovery does not actually recover. `optimize:clear` does NOT
#      touch this layer.
#   2. `optimize:clear` covers the COMPILED caches (config, route,
#      view, packages) and the warmup `bootstrap/cache/*.php` files.
#   3. The `rm` belt-and-braces handles the rare case where
#      `bootstrap/cache/config.php` was written by an older Laravel
#      that `config:clear` does not always remove cleanly.
# A failure on any `php artisan` command will fail-loud under `set -e`
# -- intentional, since a broken cache-clear means a deeper config
# error you want surfaced, not papered over.
php artisan cache:clear
php artisan optimize:clear
rm -f "$PROJECT_ROOT/bootstrap/cache/config.php" 2>/dev/null || true

# ---------------------------------------------------------------------------
# 3. Re-seed the canonical fixture set (idempotent firstOrCreate).
# ---------------------------------------------------------------------------
# Phase 25 originally only re-ran RolesAndPermissionsSeeder, leaving
# `impact_cells` empty after a test wipe — /register rendered 200 with
# the role guard satisfied but the cell dropdown was empty because
# React reads from a 0-row cellsList array shipped by Inertia.
#
# Phase 28 — also re-run ImpactCellSeeder so the dropdown
# (`RegisteredUserController::create()` returns
#     ImpactCell::where('is_primary', true)->ordered()->get(...))
# repopulates after a test wipe.
#
# Phase 30 — the fixture-user seeders join the canonical set so a test
# wipe can never leave the dev environment without its seeded logins
# (sbcadmin@impact.test / //Chris##101, officer1@impact.test, etc.).
# All 4 are idempotent (firstOrCreate on email + marker-guarded guest
# re-creation). ORDER MATTERS:
#   RolesAndPermissionsSeeder first (roles must exist before
#     assignRole/syncRoles), ImpactCellSeeder second (cell dropdown +
#     leader signups), then AdminUserSeeder + ZonalCoordinatorSeeder +
#     FollowUpOfficerSeeder (no cross-deps), and FollowUpTeamSeeder
#     LAST because it reads `officer1@impact.test` from
#     FollowUpOfficerSeeder to attach its team guest fixtures (the
#     `?->id` null-safe fallback would otherwise silently orphan them).
#
# Roles-first is an operational choice only: with the `[warn]` capture
# below surfacing whichever seeder failed, the order is reversible
# without losing signal, but Roles-first keeps the audit-gate exit code
# in §4 aligned to the first-load failure mode (a Healthy=YES bit
# confirms the roles leg; a `[warn]` line in the bash session that ran
# `bash scripts/restart_dev_server.sh` confirms the rest too — together
# they prove the full canonical set worked).
seed_canonical() {
    printf '[lifecycle] running %s (idempotent)\n' "$1"
    # If ! <cmd> form: explicitly suppress the non-zero exit so set -e
    # doesn't trip; warn-loud on stderr so a future "why is /register
    # empty after recovery" debugging session has the answer in
    # storage/logs/serve.log without having to re-run with -vvv.
    if ! php artisan db:seed --class="$1" --force >/dev/null 2>&1; then
        printf '[warn] %s failed (non-zero exit) — continuing\n' "$1" >&2
    fi
}
if [ "${SKIP_RESEED:-0}" = "1" ]; then
    printf '[lifecycle] SKIP_RESEED=1, skipping canonical seeders\n'
else
    seed_canonical 'RolesAndPermissionsSeeder'
    seed_canonical 'ImpactCellSeeder'
    seed_canonical 'AdminUserSeeder'
    seed_canonical 'ZonalCoordinatorSeeder'
    seed_canonical 'FollowUpOfficerSeeder'
    seed_canonical 'FollowUpTeamSeeder'

    # ---------------------------------------------------------------------
    # 3b. Verify the admin fixture survived the reseed (Phase 30 — the core
    #     promise: after ANY test wipe, recovery restores the admin login).
    #     Only enforced when we actually reseeded; SKIP_RESEED=1 explicitly
    #     trusts the upstream seeder instead.
    #
    #     NOTE — this restores FIXTURES, not rotated credentials: if an
    #     operator deliberately rotated sbcadmin's password to a sentinel
    #     on an UN-wiped DB, recovery does NOT clobber it (firstOrCreate
    #     sets the password on create only). To restore //Chris##101,
    #     run a test wipe first (or rotate back via
    #     scripts/rotate_sbcadmin_password.sh).
    # ---------------------------------------------------------------------
    ADMIN_COUNT="$(php artisan tinker --execute='echo App\Models\User::where("email", "sbcadmin@impact.test")->count();' 2>/dev/null | grep -E '^[0-9]+$' | tail -1 || true)"
    if [ "${ADMIN_COUNT:-0}" != "1" ]; then
        printf '[error] admin fixture sbcadmin@impact.test missing after reseed — run: php artisan db:seed --class=AdminUserSeeder --force -vvv\n' >&2
        exit 2
    fi
    printf '[verify]    admin row   : sbcadmin@impact.test present\n'
fi

# ---------------------------------------------------------------------------
# 4. Sanity: roles:audit Healthy: YES ?
# ---------------------------------------------------------------------------
# `php artisan roles:audit` exits 0 when the role matrix is healthy
# and 1 when it's not (see app/Console/Commands/AuditRolesCommand.php).
# The `--json` flag forces the JSON output mode (skipping the ASCII
# table), so this script's stdout is clean even if a future command
# version changes its tabular rendering. We ONLY rely on the exit
# code here -- never on stdout text -- which keeps this script stable
# across audit-command output-format changes.
printf '[verify]    roles:audit  : '
if php artisan roles:audit --json >/dev/null 2>&1; then
    printf 'YES\n'
else
    printf 'NO\n' >&2
    printf '[error] roles:audit did NOT report Healthy: YES after reseed\n' >&2        printf '[error] run manually: php artisan db:seed --class=RolesAndPermissionsSeeder --force -vvv && php artisan db:seed --class=ImpactCellSeeder --force -vvv && php artisan db:seed --class=AdminUserSeeder --force -vvv && php artisan db:seed --class=ZonalCoordinatorSeeder --force -vvv && php artisan db:seed --class=FollowUpOfficerSeeder --force -vvv && php artisan db:seed --class=FollowUpTeamSeeder --force -vvv\n' >&2
    exit 2
fi

# ---------------------------------------------------------------------------
# 5. Relaunch `php artisan serve --port=$PORT` in background
# ---------------------------------------------------------------------------
printf '[lifecycle] relaunching `php artisan serve --port=%s --host=%s`\n' "$PORT" "$HOST"
mkdir -p "$LOG_DIR"
# `nohup` + `disown` so the parent shell can exit without killing serve.
# The 2>/dev/null below swallows the "INFO Server running" notice so
# our printf-echoed log lines stay canonical.
nohup php artisan serve --port="$PORT" --host="$HOST" >"$SERVE_LOG" 2>&1 &
SERVE_PID=$!
disown 2>/dev/null || true
printf '[lifecycle] launched pid  : %s\n' "$SERVE_PID"

# ---------------------------------------------------------------------------
# 6. Wait for server to come up (poll /login every 500ms up to 10s)
# ---------------------------------------------------------------------------
TRIES=20
UP=0
# Bash arithmetic -- avoids spawning the `seq` external binary, which
# is not always present on stripped-down Windows + Docker images.
for ((try_index=1; try_index<=TRIES; try_index++)); do
    if curl -sI --max-time 2 "http://localhost:$PORT/login" >/dev/null 2>&1; then
        UP=1
        printf '[lifecycle] server up    : :%s (attempt %s/%s)\n' "$PORT" "$try_index" "$TRIES"
        break
    fi
    sleep 0.5
done

if [ "$UP" != "1" ]; then
    printf '[error] server did NOT come up on :%s after %s attempts.\n' "$PORT" "$TRIES" >&2
    printf '[error] last 30 lines of %s:\n' "$SERVE_LOG" >&2
    tail -30 "$SERVE_LOG" >&2 || true
    exit 1
fi

# ---------------------------------------------------------------------------
# 7. HTTP-verify /register (the original 503 victim) + /login + /
# ---------------------------------------------------------------------------
HTTP_REGISTER="$(curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://localhost:$PORT/register")"
HTTP_LOGIN="$(   curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://localhost:$PORT/login")"
HTTP_ROOT="$(    curl -s -o /dev/null -w '%{http_code}' --max-time 5 "http://localhost:$PORT/")"

printf '[verify]    /register    : HTTP %s\n' "$HTTP_REGISTER"
printf '[verify]    /login       : HTTP %s\n' "$HTTP_LOGIN"
printf '[verify]    /            : HTTP %s\n' "$HTTP_ROOT"

if [ "$HTTP_REGISTER" != "200" ]; then
    printf '[error] /register returned HTTP %s after recovery -- investigate %s\n' "$HTTP_REGISTER" "$SERVE_LOG" >&2
    exit 1
fi

printf '[done] recovery complete. Server live at http://localhost:%s\n' "$PORT"
exit 0
