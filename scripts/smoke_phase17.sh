#!/usr/bin/env bash
# scripts/smoke_phase17.sh
#
# Phase 17 visual smoke test for the admin Impact Cell Edit Details
# flow. Drives a headless Chrome via raw CDP over WebSocket using the
# `ws` npm module. NO browser-use wrapper, NO chrome-devtools schema.
# See skills/phase17-smoke/SKILL.md for rationale.
#
# Strict POSIX-safe bash: no em-dashes, percent signs escaped, all
# dynamic strings assembled via printf "%s" so bash's interpolation
# quirks do not bite us.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

PORT="${PORT:-9222}"
NEW_PHONE="${NEW_PHONE:-+1-555-ACO-JEDO}"
EMAIL="${EMAIL:-sbcadmin@impact.test}"
PASSWORD="${PASSWORD:-ImpactAdmin2026!}"
SCREENSHOT_DIR="${SCREENSHOT_DIR:-$PROJECT_ROOT/storage/app}"

# 1. Chrome discovery
CHROME_BIN=""
candidates=(
    "${CHROME_PATH:-}"
    "$(command -v chrome 2>/dev/null || true)"
    "$(command -v google-chrome 2>/dev/null || true)"
    "$(command -v chromium 2>/dev/null || true)"
    "${PORT_CHROME_PATH:-}"
    "/c/Program Files/Google/Chrome/Application/chrome.exe"
    "/c/Program Files (x86)/Google/Chrome/Application/chrome.exe"
    "/usr/bin/google-chrome"
    "/usr/bin/chromium"
    "/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
)
for path in "${candidates[@]}"; do
    [ -z "$path" ] && continue
    if [ -e "$path" ]; then
        CHROME_BIN="$path"
        break
    fi
done

if [ -z "$CHROME_BIN" ]; then
    printf '%s\n' "ERROR: Chrome not found on PATH or in any fallback location." >&2
    printf '%s\n' "       Set CHROME_PATH env var to point at chrome.exe / google-chrome / chromium binary." >&2
    exit 1
fi
printf '[preflight] chrome: %s\n' "$CHROME_BIN"

# 2. Laravel server reachable
if ! curl -sI --max-time 5 "http://localhost:8000/login" >/dev/null 2>&1; then
    printf '%s\n' "ERROR: Laravel dev server not reachable on http://localhost:8000/login." >&2
    printf '%s\n' "       Start it with: cd $PROJECT_ROOT && php artisan serve --port=8000" >&2
    exit 1
fi
printf '[preflight] laravel: 200 OK on /login\n'

# 3. Port 9222 free
if curl -sI --max-time 5 "http://localhost:$PORT/json/version" >/dev/null 2>&1; then
    printf '%s\n' "ERROR: Port $PORT already serving a Chrome debugger." >&2
    printf '%s\n' "       Kill with: pkill -f 'remote-debugging-port=$PORT'" >&2
    exit 1
fi
printf '[preflight] port %s: free\n' "$PORT"

# 4. Node installed
if ! command -v node >/dev/null; then
    printf '%s\n' "ERROR: node not on PATH. Install Node.js 18+." >&2
    exit 1
fi
NODE_MAJOR="$(node -v | sed -E 's/^v([0-9]+).*/\1/')"
if [ "$NODE_MAJOR" -lt 18 ]; then
    printf '%s\n' "ERROR: Node $NODE_MAJOR detected; need 18+." >&2
    exit 1
fi
printf '[preflight] node: %s\n' "$(node -v)"

# 5. ws auto-install
WS_ROOT="$SCRIPT_DIR/.smoke_phase17_node_modules"
if [ ! -d "$WS_ROOT/node_modules/ws" ]; then
    printf '[setup] first-run install of ws into %s/node_modules\n' "$WS_ROOT"
    mkdir -p "$WS_ROOT"
    cp "$SCRIPT_DIR/smoke_phase17_package.json" "$WS_ROOT/package.json"
    ( cd "$WS_ROOT" && npm install --no-audit --no-fund --silent )
fi
printf '[preflight] ws module: present\n'

# 6. ACO/JEDO UUID lookup
ACO_JEDO_ID="$(cd "$PROJECT_ROOT" && php artisan phase17:aco-jedo-uuid 2>/dev/null | tr -d '\r\n')"
if [ -z "$ACO_JEDO_ID" ] || [ "${#ACO_JEDO_ID}" -ne 36 ]; then
    printf '%s
' "ERROR: ACO/JEDO cell not found. Run: php artisan db:seed" >&2
    exit 1
fi
printf '[preflight] aco_jedo_id: %s
' "$ACO_JEDO_ID"
# 8. Start Chrome headless
USER_DATA_DIR="$(mktemp -d /tmp/phase17-smoke-chrome-XXXXXX)"
LOG_FILE="$(mktemp -t phase17-smoke-chrome-XXXXXX.log)"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
SCREENSHOT_OUT="$SCREENSHOT_DIR/smoke-phase17-$TIMESTAMP.png"
mkdir -p "$SCREENSHOT_DIR"

printf '[lifecycle] chrome user-data-dir: %s\n' "$USER_DATA_DIR"
printf '[lifecycle] chrome log: %s\n' "$LOG_FILE"

# Note the absence of complex `$(seq ...)` chains — those break dash-style
# shells when the same script is invoked via `bash script.sh`. We rely on
# bash strictly; POSIX sh is not supported.
"$CHROME_BIN" \
    --headless=new \
    --disable-gpu \
    --no-sandbox \
    --hide-scrollbars \
    --window-size=1280,800 \
    --user-data-dir="$USER_DATA_DIR" \
    --remote-debugging-port="$PORT" >"$LOG_FILE" 2>&1 &
CHROME_PID=$!
printf '[lifecycle] chrome launched, pid=%s\n' "$CHROME_PID"

cleanup() {
    local exit_code=$?
    set +e
    if [ -n "$CHROME_PID" ]; then
        kill -KILL "$CHROME_PID" 2>/dev/null || true
    fi
    rm -rf "$USER_DATA_DIR" 2>/dev/null || true
    exit "$exit_code"
}
trap cleanup EXIT INT TERM

# 9. Wait for debugger URL. Build the URL via printf to dodge shell
# interpolation quirks of `("...")` patterns that some embeds reject.
DBG_URL="$(printf 'http://localhost:%s/json/version' "$PORT")"
TRIES=20
for try_index in 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do
    if curl -sI --max-time 2 "$DBG_URL" >/dev/null 2>&1; then
        printf '[lifecycle] debugger ready on :%s (attempt %s/%s)\n' \
            "$PORT" "$try_index" "$TRIES"
        break
    fi
    sleep 0.5
done
if ! curl -sI --max-time 2 "$DBG_URL" >/dev/null 2>&1; then
    printf '%s\n' "ERROR: Chrome debugger did not come up on :$PORT after $TRIES tries." >&2
    head -40 "$LOG_FILE" >&2
    cat "$LOG_FILE" >&2
    exit 1
fi

# 10. Run the Node driver
printf '[lifecycle] running Node CDP driver\n'

export SCREENSHOT_OUT
export ACO_JEDO_ID
export EMAIL
export PASSWORD
export NEW_PHONE
export WS_ROOT
export CDP_BASE_URL="http://localhost:$PORT"
export LARAVEL_BASE_URL="http://localhost:8000"

cd "$PROJECT_ROOT"
node --no-warnings "$SCRIPT_DIR/smoke_phase17_driver.mjs"
EXIT=$?

if [ -f "$SCREENSHOT_OUT" ]; then
    printf '[driver] screenshot at %s\n' "$SCREENSHOT_OUT"
fi
printf '[done] exit=%s\n' "$EXIT"
exit "$EXIT"
