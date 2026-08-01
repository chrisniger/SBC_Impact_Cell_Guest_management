#!/usr/bin/env bash
# scripts/rotate_sbcadmin_password.sh
#
# Phase 18 fixture helper for the seeded `sbcadmin@impact.test` admin.
# Thin dispatcher that wraps the real work in
#     app/Console/Commands/RotateSbcadminPasswordCommand.php
# (php artisan sbcadmin:rotate) so the random sentinel plaintext never
# crosses bash argv, env, or shell history.
#
# Usage:
#     bash scripts/rotate_sbcadmin_password.sh sentinel           # lock the row down
#     bash scripts/rotate_sbcadmin_password.sh                    # restore to env('SBCADMIN_PASSWORD','ImpactAdmin2026!')
#     bash scripts/rotate_sbcadmin_password.sh status             # inspect current state, no write
#     bash scripts/rotate_sbcadmin_password.sh 'MySecret123!'     # restore to literal
#
# Exit codes:
#   0 — success (or idempotent no-op)
#   1 — sbcadmin@impact.test does not exist (do not auto-seed; run db:seed manually)
#
# See skills/phase18-password-rotation if added later, or read the
# docblock in RotateSbcadminPasswordCommand.php for the leak-mitigations list.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

# Default target when no arg is supplied: restore to the env-default.
# Using "${1:-ImpactAdmin2026!}" is treated as a literal RESTORE, not a sentinel.
TARGET="${1:-ImpactAdmin2026!}"

# Friendlier label for the [info] line.
LABEL="restore (literal)"
case "$TARGET" in
    sentinel) LABEL="sentinel (random 96-char in PHP, no plaintext leaves memory)" ;;
    status)   LABEL="status (read-only)" ;;
esac
printf '[info] target=%s  label=%s\n' "$TARGET" "$LABEL"

cd "${PROJECT_ROOT}"
php artisan sbcadmin:rotate "$TARGET"
