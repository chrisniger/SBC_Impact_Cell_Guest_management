#!/usr/bin/env bash
#
# Phase 12 v2 — Hostinger deploy script (Laravel 12 + Vite)
#
# This is the Laravel-native deploy sequence for `https://app.summitdata.one`.
# NOT the v1 Node/Passenger narrative the original (pre-Phase-12-polish) spec had.
# Reference: Implementation/Phase_12_Deployment.md (v2 Laravel doc).
#
# Usage:
#   # From a Linux/macOS workstation (rsync-over-ssh):
#   bash scripts/deploy_to_hostinger.sh
#
#   # From Windows (use the companion scripts/deploy_to_hostinger_local_init.ps1
#   # or invoke via WSL / Git Bash):
#   bash scripts/deploy_to_hostinger.sh
#
#   # Rollback to .previous:
#   bash scripts/deploy_to_hostinger.sh rollback
#
# Override the SSH endpoint via HOSTINGER_SSH (default: u188660189@ssh.hostinger.com):
#   HOSTINGER_SSH="u188660189@custom.hostinger.com" bash scripts/deploy_to_hostinger.sh
#
# Prerequisites (LOCAL, before running):
#   - .env in cwd (will be uploaded separately via scp — NOT in rsync)
#   - public/build/ present (from `npm run build`)
#   - composer.lock present (committed)
#   - vendor/ present locally or composer install runs on host
#
# Heavy-lift is on the HOSTINGER SIDE (composer install + artisan migrate +
# artisan cache + chmod + chown). Local side is just rsync + scp.

set -euo pipefail

# --- Configuration (override via env) ---
readonly HOST_SSH="${HOSTINGER_SSH:-u188660189@ssh.hostinger.com}"
readonly APP_PATH="${APP_PATH:-/home/u188660189/domains/app.summitdata.one/public_html/laravel}"
readonly PREVIOUS="${APP_PATH}.previous"
readonly HEALTH_URL="${HEALTH_URL:-https://app.summitdata.one/api/health}"

# --- Colors / log helpers ---
readonly C_RED=$'\033[0;31m'
readonly C_GREEN=$'\033[0;32m'
readonly C_YELLOW=$'\033[0;33m'
readonly C_BLUE=$'\033[0;34m'
readonly C_RESET=$'\033[0m'

log()  { printf "${C_GREEN}[%s]${C_RESET} %s\n"  "$(date +'%H:%M:%S')" "$*"; }
info() { printf "${C_BLUE}[%s]${C_RESET} %s\n"   "$(date +'%H:%M:%S')" "$*"; }
warn() { printf "${C_YELLOW}[WARN]${C_RESET} %s\n" "$*" >&2 ; }
err()  { printf "${C_RED}[FAIL]${C_RESET} %s\n" "$*" >&2 ; }
die()  { err "$*"; exit 1; }
section() { printf "\n${C_BLUE}============================================================${C_RESET}\n${C_BLUE}=== %s ===${C_RESET}\n${C_BLUE}============================================================${C_RESET}\n" "$*"; }

# --- Pre-flight: local + remote health gates ---
preflight() {
    section "PRE-FLIGHT"

    log "Local artifacts"
    [[ -f .env ]]          || die ".env not found in cwd — copy from .env.example + fill prod credentials"
    [[ -f composer.lock ]] || die "composer.lock not found — commit it"
    [[ -d public/build ]]  || die "public/build/ missing — run 'npm run build' BEFORE deploy"
    log "  ✓ .env present"
    log "  ✓ composer.lock present"
    log "  ✓ public/build/ present ($(du -sh public/build 2>/dev/null | awk '{print $1}'))"

    log "SSH connectivity to $HOST_SSH"
    ssh -q -o ConnectTimeout=15 -o BatchMode=yes "$HOST_SSH" 'echo ssh-ok' \
        || die "Cannot SSH to $HOST_SSH — check credentials + ssh-key"

    log "Host toolchain"
    ssh "$HOST_SSH" 'command -v composer >/dev/null && command -v php >/dev/null && command -v node >/dev/null' \
        || die "Host missing composer/php/node"

    local php_ver
    php_ver=$(ssh "$HOST_SSH" 'php -v | head -1 | awk "{print \$2}"')
    case "$php_ver" in
        8.[2-9]*|9.*) log "  ✓ Host PHP: $php_ver (>= 8.2 — Laravel 12 supported)";;
        *) die "Host PHP must be >= 8.2 (found $php_ver) — request PHP bump from Hostinger";;
    esac

    log "DocumentRoot targets"
    # Apache/HTTP_HOSTNAME doesn't expose DocumentRoot directly via SSH — we trust
    # Hostinger control panel config. Just warn if public/index.php missing.
    ssh "$HOST_SSH" "[[ -f $APP_PATH/public/index.php ]]" \
        && log "  ✓ public/index.php present on host" \
        || warn "public/index.php missing on host — full upload will repopulate"

    log "Pre-flight PASSED"
}

# --- Backup current build (so .previous exists for rollback) ---
backup() {
    section "BACKUP current build → .previous"
    if ssh "$HOST_SSH" "[[ -d $APP_PATH ]]" ; then
        ssh "$HOST_SSH" "rm -rf $PREVIOUS && cp -a $APP_PATH $PREVIOUS" \
            || die "Backup failed"
        log "  ✓ .previous populated ($(ssh "$HOST_SSH" "du -sh $PREVIOUS 2>/dev/null | awk '{print \$1}'"))"
    else
        warn "$APP_PATH does not exist on host yet — first deploy — skipping backup"
    fi
}

# --- Upload new build + .env ---
upload() {
    section "UPLOAD (rsync + scp .env separately)"

    log "Ensure target dir exists on host (fixes first-deploy scp race)"
    ssh "$HOST_SSH" "mkdir -p $APP_PATH" \
        || die "mkdir -p $APP_PATH on host failed"

    log "rsync new build → $HOST_SSH:$APP_PATH/"
    rsync -avz --delete \
        --exclude '.env' \
        --exclude '.previous' \
        --exclude 'node_modules' \
        --exclude 'storage/logs/*' \
        --exclude 'storage/framework/cache/*' \
        --exclude 'storage/framework/sessions/*' \
        --exclude 'storage/framework/views/*' \
        ./ "$HOST_SSH:$APP_PATH/" \
        || die "rsync failed"

    log "scp .env separately (secrets bypass rsync)"
    scp .env "$HOST_SSH:$APP_PATH/.env" \
        || die ".env scp failed"

    log "  ✓ Upload complete"
}

# --- Host-side composer + artisan ---
composer_install() {
    section "HOST: composer install --optimize-autoloader --no-dev"
    ssh "$HOST_SSH" "cd $APP_PATH && composer install --optimize-autoloader --no-dev --no-interaction --prefer-dist" \
        || die "composer install on host failed"
    log "  ✓ composer install complete"
}

artisan_round() {
    section "HOST: artisan migrate + config:cache + route:cache + view:cache + storage:link"

    ssh "$HOST_SSH" "cd $APP_PATH && \
        php artisan migrate --force --no-interaction" \
        || die "migrate --force failed"

    # Phase 14 — deploy-misconfig smoke gate. Runs `php artisan roles:audit --json`
    # IMMEDIATELY after the migrations from the step above apply, BEFORE the
    # cache trio (so any deploy-misconfig surfaces before we burn 30+ seconds
    # on config:cache/route:cache/view:cache). The audit hard-fails the
    # deploy with exit code != 0 if:
    #   - any of the 10 canonical RoleHelper::ROLE_NAMES rows are missing
    #     from the `roles` table on guard 'web',
    #   - any expected-name row lives under a non-`web` guard_name, OR
    #   - SIGNUP_VISIBLE_ROLES has drifted from ROLE_NAMES (e.g. a future
    #     PR typos one of the names — the original today-2026-07-31 incident
    #     was exactly this kind of drift, see
    #     app/Support/RoleHelper.php docblock + database/migrations/
    #     2026_07_31_150000_fix_impact_zonal_typo_in_roles_table.php).
    ssh "$HOST_SSH" "cd $APP_PATH && php artisan roles:audit --json" \
        || die "roles:audit reported unhealthy after migrate — deploy-misconfig. Read the JSON output above for missing roles / guard mismatch / SIGNUP_VISIBLE_ROLES drift, and re-run \`php artisan db:seed --class=RolesAndPermissionsSeeder\` or fix the constant drift as needed. Do NOT mark the deploy green until this passes."

    ssh "$HOST_SSH" "cd $APP_PATH && \
        php artisan config:cache && \
        php artisan route:cache && \
        php artisan view:cache && \
        php artisan storage:link" \
        || die "artisan cache trio failed"

    log "  ✓ artisan round complete"
}

# --- File permission fix (storage + bootstrap/cache must be writable) ---
fix_permissions() {
    section "HOST: chmod -R 775 + chown"
    ssh "$HOST_SSH" "cd $APP_PATH && \
        chmod -R 775 storage bootstrap/cache && \
        chown -R u188660189:www-data storage bootstrap/cache" \
        || die "Permission fix failed"
    log "  ✓ storage/bootstrap/cache writable (775) + owned by www-data"
}

# --- Verify .htaccess + public/index.php on host ---
verify_htaccess() {
    section "VERIFY: .htaccess + public/index.php"
    ssh "$HOST_SSH" "[[ -f $APP_PATH/.htaccess ]]" \
        && log "  ✓ project-root .htaccess present (forwards to public/)" \
        || warn ".htaccess missing at project root — Hostinger control panel must set DocumentRoot → laravel/public/ to compensate"
    ssh "$HOST_SSH" "[[ -f $APP_PATH/public/index.php ]]" \
        && log "  ✓ public/index.php present" \
        || die "public/index.php missing — incomplete upload or wrong rsync exclude"
}

# --- Post-deploy: curl /api/health ---
verify_health() {
    section "POST-DEPLOY: $HEALTH_URL"
    local http_code
    http_code=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 20 "$HEALTH_URL" || echo "000")
    case "$http_code" in
        2*|3*) log "  ✓ /api/health responded: $http_code";;
        *) err "  ✗ /api/health failed: $http_code — check storage/logs/laravel.log"
           return 1;;
    esac
}

# --- Smoke: /login + 3 user-group dashboards reachable ---
# (Live 3-user-group login needs CSRF + session cookies — out of scope for SSH-side
# curl. Pointer: use scripts/audit_3_user_group_live.sh from local Windows OR
# browser-use/Playwright on production.)
verify_3group_smoke() {
    section "SMOKE: /login reachability (curl only)"
    local code_login
    code_login=$(curl -sk -o /dev/null -w '%{http_code}' --max-time 20 "https://app.summitdata.one/login")
    case "$code_login" in
        200|302) log "  ✓ /login responded: $code_login";;
        *) warn "/login responded: $code_login (expected 200/302) — verify in browser";;
    esac
    warn "Full 3-user-group login smoke (admin + officer + Impact_Leaders) requires CSRF + session cookies — run 'scripts/audit_3_user_group_live.sh' from local Windows or use browser-use."
}

# --- Rollback to .previous (separate flag) ---
rollback() {
    section "ROLLBACK → .previous"
    if ssh "$HOST_SSH" "[[ ! -d $PREVIOUS ]]" ; then
        die "No .previous directory on host — was this ever deployed?"
    fi

    ssh "$HOST_SSH" "rm -rf $APP_PATH && mv $PREVIOUS $APP_PATH" \
        || die "mv .previous failed"

    ssh "$HOST_SSH" "cd $APP_PATH && \
        php artisan config:cache && \
        php artisan route:cache && \
        php artisan view:cache && \
        chmod -R 775 storage bootstrap/cache && \
        chown -R u188660189:www-data storage bootstrap/cache" \
        || die "Rollback artisan + permissions failed"

    log "ROLLBACK complete — verify $HEALTH_URL returns 2xx + browser at https://app.summitdata.one"
}

# --- Entry point ---
case "${1:-deploy}" in
    deploy|"")
        preflight
        backup
        upload
        composer_install
        artisan_round
        fix_permissions
        verify_htaccess
        verify_health
        verify_3group_smoke
        section "DEPLOY COMPLETE"
        log "Open https://app.summitdata.one/login in a browser. Log in as sbcadmin@impact.test //Chris##101 to verify the admin path. Then walk officer1@impact.test + a phase04-test-Impact_Leaders@impact.test user through their dashboards."
        ;;
    rollback)
        rollback
        ;;
    health)
        verify_health
        ;;
    *)
        err "Usage: $0 [deploy|rollback|health]"
        exit 64
        ;;
esac
