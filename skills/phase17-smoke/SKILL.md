---
name: phase17-smoke
description: |
  When the user asks to *visually* verify the Phase 17 admin Impact
  Cell Edit Details happy-path in a real browser (login as
  sbcadmin@impact.test, navigate to ACO/JEDO, click "Edit details",
  type a new phone, save, verify the value persists across a reload),
  run `bash scripts/smoke_phase17.sh` to drive a headless Chrome via
  the raw CDP (Chrome DevTools Protocol) over a local WebSocket. Do
  NOT use the `browser-use` agent — its chrome-devtools wrapper has
  failing schema checks on every interactive primitive (`fill_form`,
  `evaluate_script`, `type_text` all reject with required-field
  `undefined`), and we deliberately bypass that wrapper here.

  Note: PHPUnit already covers this exact flow end-to-end via
  `tests/Feature/Phase17ImpactCellEditTest.php` — 9 sub-assertions,
  42 total assertions. Use this skill only when pixel-level or
  screenshot evidence is required (e.g. attaching the screenshot to
  HANDOFF.md, dev sign-off capture, or demonstrating the flow to a
  stakeholder).
---

# Phase 17 Visual Smoke Test

Headless Chrome + direct CDP via the raw `ws` module. NO `chrome-devtools` schema wrapper, NO `puppeteer`, NO `playwright`. JSON-RPC frames are constructed by hand inside `scripts/smoke_phase17_driver.mjs`.

## When to invoke

Use this skill when the user asks for visual / browser evidence of any Phase 17 admin Impact Cell flow. The canonical happy-path is the Edit Details phone-update flow on ACO/JEDO; the same scaffold can drive Edit Leadership Team, Manage Sub-cells, Create, or attach/detach.

Do NOT invoke for backend proof — `tests/Feature/Phase17ImpactCellEditTest.php` already covers all of it.

## Invoke

```bash
bash scripts/smoke_phase17.sh
```

The wrapper handles everything else automatically.

## What the wrapper pre-flights

1. **Chrome discovery** — walks `CHROME_PATH` env, then `which chrome / google-chrome / chromium`, then the platform fallbacks:
   - Windows: `C:\Program Files\Google\Chrome\Application\chrome.exe` (and the `Program Files (x86)` variant)
   - Linux: `/usr/bin/google-chrome`, `/usr/bin/chromium`
   - macOS: `/Applications/Google Chrome.app/Contents/MacOS/Google Chrome`
2. **Laravel dev server** reachable on `:8000` — refuses early if not.
3. **Port `9222` free** — refuses if a stale headless Chrome debugger is lingering (otherwise two debuggers collide).
4. **Node ≥ 18** — needed for stable `ws` CJS interop from the ESM driver.
5. **`ws` install** — auto-runs `npm install` into `scripts/.smoke_phase17_node_modules` on first run so the project root's `npm install` is never polluted.
6. **ACO/JEDO UUID** — looks it up via `php artisan tinker`. Re-seeds change the UUID so the script auto-tracks.
7. **SBC Admin password warn** (does NOT abort) — if `sbcadmin@impact.test` is not on `ImpactAdmin2026!`, prints the one-liner to set it but lets the driver run regardless.

## Output expectation

```
[preflight] chrome: /c/Program Files/Google/Chrome/Application/chrome.exe
[preflight] laravel: 200 OK on /login
[preflight] port 9222: free
[preflight] node: v20.x.y
[preflight] ws module: present
[preflight] aco_jedo_id: 019fbc9e-82d6-735a-b33c-559872014bc2
[lifecycle] chrome user-data-dir: /tmp/phase17-smoke-chrome-XXX
[lifecycle] chrome log: /tmp/phase17-smoke-chrome-XXX.log
[lifecycle] chrome launched, pid=12345
[lifecycle] debugger ready on :9222 (attempt 1/20)
[lifecycle] running Node CDP driver
[PASS] step "connect"
[PASS] step "navigate-login"
[PASS] step "type-email"
[PASS] step "type-password"
[PASS] step "click-signin"
[PASS] step "navigate-cell"
[PASS] step "click-edit-details"
[PASS] step "type-phone"
[PASS] step "save-collapses-form" — editor card collapsed
[PASS] step "screenshot" — ./storage/app/smoke-phase17-…png
[PASS] step "reload"
[PASS] step "assert-phone-persisted" — dd="+1-555-ACO-JEDO"  expected="+1-555-ACO-JEDO"

✅ ALL PHASE 17 STEPS PASS — Edit Details round-trip confirmed.
```

Screenshot is at `storage/app/smoke-phase17-{YYYYMMDD-HHMMSS}.png`.

## Exit codes

| Code | Meaning |
|---|---|
| `0` | All 12 step() entries PASS and `assert-phone-persisted` equals `NEW_PHONE`. |
| `1` | Pre-flight failed (no Chrome / no server / port busy / no Node). |
| `2` | Driver harness crashed (CDP connect failed, ws import failed, etc.). |
| `3` | Driver exited inside `step()` (assertion failed, selector missing, post-save didn't collapse). |

## Failure modes + resolutions

| Symptom | Likely cause | Fix |
|---|---|---|
| `Chrome not found` | No Chrome on PATH or in fallback paths | Install Chrome, or set `CHROME_PATH=/path/to/chrome.exe` env var. |
| `port 9222 already serving a Chrome debugger` | Stale Chrome instance from a previous run | `pkill -f "remote-debugging-port=9222"` then re-run. |
| `node not on PATH` | Node.js not installed | Install Node 18+. |
| `Node N detected; need ≥18` | EOL Node version | Upgrade Node. |
| `Laravel dev server not reachable` | `php artisan serve` not running | `cd /c/laragon/www/impact_portal_plus && php artisan serve --port=8000` in another terminal. |
| `ACO/JEDO cell not found` | DB not seeded | `php artisan db:seed`. |
| `Bad password` warning logged | SBC Admin not on `ImpactAdmin2026!` | See the tinker one-liner the wrapper prints AFTER the `cd`. |
| Step `type-password` FAIL: `no match: input[name="password"]` | Breeze form intercepted email/password selectors | The driver falls back to `input[type="password"], #password`. If still empty, screenshot the login form, raise the selector list. |
| Step `save-collapses-form` FAIL: editor card still present after 4.5 s | Server rejected the PUT (validation error) | Tinker-read the cell's `phone`; if unchanged, look at `storage/logs/laravel.log` for the 422 cause. |
| Step `assert-phone-persisted` FAIL | Server didn't persist the new phone | Same — tinker-read + laravel.log + check `impact_cell_user` etc. context. |

## Why NOT `browser-use` + `chrome-devtools`

In a prior session attempt, the `browser-use` agent's chrome-devtools wrappers structured-call-validated three different primitives and rejected each with the same shape (`required field received undefined`):

| Primitive | Required field | Provider |
|---|---|---|
| `fill_form` | `elements: array` | chrome-devtools / chrome-devtools-fill_form |
| `evaluate_script` | `function: string` | chrome-devtools / chrome-devtools-evaluate_script |
| `type_text` | `text: string` | chrome-devtools / chrome-devtools-type_text |

That is a wrapper-side schema regression (not fixable from this skill) — `browser-use` constructs the argument payload via internal helper code that omits the field. The skill bypasses the wrapper entirely: `scripts/smoke_phase17_driver.mjs` constructs JSON-RPC frames by hand over a vanilla `ws` WebSocket connection to `ws://127.0.0.1:9222/...`. The schema failure surface shrinks to:

- the WebSocket transport itself (`Connection refused`, `handshake timeout`)
- our own selector strings (`document.querySelector(selector)` returning null)
- React-controlled `value` setter plumbing (covered by `replaceInputValue` using the prototype descriptor trick)

All three are debuggable from `--lh:chrome /tmp/phase17-smoke-chrome-XXX.log` and the `[PASS]/[FAIL]` step log.

## Extension to other Phase 17 / Phase 18 flows

The bash wrapper is feature-agnostic; only the driver changes. To turn the smoke harness into a Phase 18 dashboard smoke, copy `scripts/smoke_phase17_driver.mjs` to `scripts/smoke_phase18_dashboard.mjs`, rewrite the steps, and re-point the bash wrapper at it. The pre-flight + Chrome lifecycle boilerplate stays the same.

## Cleanup

The bash wrapper installs `trap cleanup EXIT INT TERM` that:

- `kill -TERM` Chrome, then `kill -KILL` after 2 s
- `rm -rf` the temporary `--user-data-dir`

If a crash leaves a stray headless Chrome alive, run `pkill -f "remote-debugging-port=9222"` to clean it up.

## Files

```
scripts/
├── smoke_phase17.sh              ← bash wrapper (preflight + lifecycle)
├── smoke_phase17_driver.mjs      ← raw-CDP walkthrough
├── smoke_phase17_package.json    ← local dep manifest (ws only)
└── .smoke_phase17_node_modules/  ← conditionally-installed runtime (gitignored)
skills/
└── phase17-smoke/
    └── SKILL.md                  ← this file
```
