// scripts/smoke_phase17_driver.mjs
//
// Phase 17 — Raw CDP walkthrough for the admin Impact Cell Edit Details
// happy path. NO chrome-devtools, NO browser-use wrapper, NO puppeteer.
// Just Node 18+ standard library + the `ws` package from
// scripts/.smoke_phase17_node_modules/node_modules/ws.
//
// Talking to Chrome directly via WebSocket means we own every JSON-RPC
// frame end-to-end. The wrapper-bugs that hit browser-use's
// chrome-devtools integration in prior turns (fill_form wanted
// `elements: array`, type_text wanted `text: string`, evaluate_script
// wanted `function: string`, all rejected with `undefined`) cannot
// happen here because we never invoke those wrappers — we hand-roll
// the JSON-RPC frames in `cdp.send()` below.
//
// Usage (driven by scripts/smoke_phase17.sh):
//   CDP_BASE_URL=http://localhost:9222 \
//   LARAVEL_BASE_URL=http://localhost:8000 \
//   ACO_JEDO_ID=019fbc9e-82d6-735a-b33c-559872014bc2 \
//   EMAIL=sbcadmin@impact.test \
//   PASSWORD='ImpactAdmin2026!' \
//   NEW_PHONE='+1-555-ACO-JEDO' \
//   WS_ROOT=/c/laragon/www/impact_portal_plus/scripts/.smoke_phase17_node_modules \
//   SCREENSHOT_OUT=./storage/app/smoke-phase17.png \
//   node smoke_phase17_driver.mjs
//
// Exit codes: 0 = all 12 steps pass and assert-phone-persisted == NEW_PHONE
//             2 = fatal (uncaught exception in the harness itself)
//             3 = one or more step() calls recorded FAIL

import { createRequire } from 'node:module';
import { writeFileSync } from 'node:fs';
const require = createRequire(import.meta.url);

// ── ENV pre-flight (must run BEFORE the require() on ws) ─────────────────
// Each guard exits 2 with a reason if the env is malformed so the bash
// wrapper can surface a clean error rather than a Node stack trace.
const CDP_BASE      = process.env.CDP_BASE_URL     || 'http://localhost:9222';
const LARAVEL_BASE  = process.env.LARAVEL_BASE_URL || 'http://localhost:8000';
const CELL_ID       = process.env.ACO_JEDO_ID      || '';
const EMAIL         = process.env.EMAIL            || 'sbcadmin@impact.test';
const PASSWORD      = process.env.PASSWORD         || 'ImpactAdmin2026!';
const NEW_PHONE     = process.env.NEW_PHONE        || '+1-555-ACO-JEDO';
const SCREENSHOT_OUT = process.env.SCREENSHOT_OUT || '';
const WS_ROOT       = process.env.WS_ROOT          || '';

function fatal(msg) {
    process.stderr.write(`FATAL: ${msg}\n`);
    process.exit(2);
}
if (!WS_ROOT) {
    fatal('WS_ROOT env var is unset — the bash wrapper must export it. Run via scripts/smoke_phase17.sh.');
}
if (!CELL_ID || CELL_ID.length !== 36) {
    fatal(`ACO_JEDO_ID env var missing or wrong shape: "${CELL_ID}" (must be a 36-char UUID)`);
}
if (!/^[^\s@]+@[^\s@]+$/.test(EMAIL)) {
    fatal(`EMAIL env var does not look like an email: "${EMAIL}"`);
}

// Load `ws` from the local node_modules the shell wrapper provisioned.
const { WebSocket } = require(`${WS_ROOT}/node_modules/ws`);

// ── CDP transport ────────────────────────────────────────────────────────
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

async function fetchDebuggerUrl() {
    const res = await fetch(`${CDP_BASE}/json/version`);
    if (!res.ok) throw new Error(`CDP /json/version responded ${res.status}`);
    const { webSocketDebuggerUrl } = await res.json();
    return webSocketDebuggerUrl;
}

class CDPClient {
    constructor() {
        this.ws = null;
        this.nextId = 1;
        this.pending = new Map(); // id -> { resolve, reject }
        this.events = [];
    }

    async connect(timeoutMs = 5000) {
        const url = await fetchDebuggerUrl();
        return await new Promise((resolve, reject) => {
            const ws = new WebSocket(url, { handshakeTimeout: timeoutMs });
            const timer = setTimeout(() => {
                ws.terminate();
                reject(new Error('CDP WebSocket open timed out'));
            }, timeoutMs);
            ws.on('open', () => {
                clearTimeout(timer);
                this.ws = ws;
                resolve();
            });
            ws.on('error', (err) => { clearTimeout(timer); reject(err); });
            ws.on('message', (data) => {
                let msg;
                try { msg = JSON.parse(data.toString()); } catch (e) { return; }
                if (typeof msg.id === 'number' && this.pending.has(msg.id)) {
                    const { resolve, reject } = this.pending.get(msg.id);
                    this.pending.delete(msg.id);
                    if (msg.error) reject(new Error(`${msg.method || 'CDP'}: ${JSON.stringify(msg.error)}`));
                    else resolve(msg.result);
                } else if (msg.method) {
                    this.events.push(msg);
                }
            });
        });
    }

    async send(method, params = {}, timeoutMs = 5000) {
        const id = this.nextId++;
        return await new Promise((resolve, reject) => {
            const timer = setTimeout(() => {
                this.pending.delete(id);
                reject(new Error(`CDP ${method} timed out after ${timeoutMs}ms`));
            }, timeoutMs);
            this.pending.set(id, {
                resolve: (v) => { clearTimeout(timer); resolve(v); },
                reject:  (e) => { clearTimeout(timer); reject(e); },
            });
            this.ws.send(JSON.stringify({ id, method, params }));
        });
    }

    async waitFor(method, timeoutMs = 5000) {
        const start = Date.now();
        while (Date.now() - start < timeoutMs) {
            const idx = this.events.findIndex((e) => e.method === method);
            if (idx >= 0) return this.events.splice(idx, 1)[0];
            await sleep(50);
        }
        throw new Error(`Timed out ${timeoutMs}ms waiting for event ${method}`);
    }

    close() {
        try { this.ws?.close(); } catch (_) { /* noop */ }
    }
}

// ── Driver-level helpers (each posts ONE Runtime.evaluate round-trip) ─────
async function evalResolve(cdp, expr) {
    const r = await cdp.send('Runtime.evaluate', {
        expression: expr,
        returnByValue: true,
        awaitPromise: false,
    });
    if (r.exceptionDetails) {
        throw new Error(`Runtime.evaluate threw: ${r.exceptionDetails.text} ${r.exceptionDetails.exception?.description || ''}`);
    }
    return r.result?.value;
}

async function evalRunExpr(cdp, expr) {
    const r = await cdp.send('Runtime.evaluate', {
        expression: expr,
        returnByValue: false,
        awaitPromise: false,
    });
    if (r.exceptionDetails) {
        throw new Error(`Runtime.evaluate threw: ${r.exceptionDetails.text} ${r.exceptionDetails.exception?.description || ''}`);
    }
}

async function focusSelector(cdp, selector) {
    await evalRunExpr(cdp,
        `(() => { const el = document.querySelector(${JSON.stringify(selector)});`
      + `     if (!el) throw new Error('no match: ' + ${JSON.stringify(selector)});`
      + `     el.focus(); })()`
    );
}

async function typeInto(cdp, selector, text) {
    await focusSelector(cdp, selector);
    // Per-char, via raw key dispatch. Each char gets type='char' so React's
    // onChange fires once per keystroke (React-controlled inputs
    // re-render on every char by design).
    for (const ch of text) {
        await cdp.send('Input.dispatchKeyEvent', {
            type: 'char',
            text: ch,
            unmodifiedText: ch,
            windowsVirtualKeyCode: 0,
            nativeVirtualKeyCode: 0,
        });
    }
}

async function clickSelector(cdp, selector) {
    await evalRunExpr(cdp,
        `(() => { const el = document.querySelector(${JSON.stringify(selector)});`
      + `     if (!el) throw new Error('no match: ' + ${JSON.stringify(selector)});`
      + `     el.click(); })()`
    );
}

async function replaceInputValue(cdp, selector, value) {
    // React reads from a private getter on the element prototype; bypass it
    // by calling the native value setter BEFORE dispatching input/change.
    // Without this, React's onChange doesn't fire when we just .value = …
    await evalRunExpr(cdp,
        `(() => { const el = document.querySelector(${JSON.stringify(selector)});`
      + `     if (!el) throw new Error('no match: ' + ${JSON.stringify(selector)});`
      + `     const proto = Object.getPrototypeOf(el);`
      + `     const setter = Object.getOwnPropertyDescriptor(proto, 'value')?.set;`
      + `     setter?.call(el, ${JSON.stringify(value)});`
      + `     el.dispatchEvent(new Event('input', { bubbles: true }));`
      + `     el.dispatchEvent(new Event('change', { bubbles: true })); })()`
    );
}

// ── Step logger ──────────────────────────────────────────────────────────
const stepLog = [];
class StepFailure extends Error {
    constructor(name, info) { super(`Step "${name}" failed: ${info}`); this.name = name; this.info = info; }
}
function step(name, ok, info = '') {
    stepLog.push({ name, ok, info });
    process.stdout.write(`${ok ? '[PASS]' : '[FAIL]'} step "${name}"${info ? ' — ' + info : ''}\n`);
    if (!ok) throw new StepFailure(name, info);
}

// ── Walkthrough ──────────────────────────────────────────────────────────
// ── assertPhonePersisted (step-12 final assertion) ────────────────────────
// After step 11 reload, re-query the real DOM for the rendered phone.
// Selector strategy (verified against resources/js/Pages/ImpactCells/Show.tsx):
//   1) Live input (Edit details card still open): id="phone" via DetailsField.
//   2) Read-only display (after reload): walk all <dt>Phone</dt> and pick
//      the sibling <dd> from the Details card dl.
// Then compare by trimmed-string equality (NOT substring) so a stale
// prefix like "+1-555-ACO-JEDO-OLD" cannot pass for "+1-555-ACO-JEDO".
// Throws on mismatch so the driver exits non-zero with a clear message.
async function assertPhonePersisted(cdp, expected) {
    const expr = `
        (() => {
            const live = document.getElementById('phone');
            if (live && (live.tagName === 'INPUT' || live.tagName === 'TEXTAREA')) {
                return (live.value ?? '').toString();
            }
            const dts = document.querySelectorAll('dt');
            for (const dt of dts) {
                if ((dt.textContent || '').trim() === 'Phone') {
                    const dd = dt.nextElementSibling;
                    if (dd && dd.tagName === 'DD') {
                        return (dd.textContent || '').trim();
                    }
                }
            }
            return '';
        })()
    `;
    // 5-second CDP timeout: protect against re-render-storm hangs after
    // the post-save reload. evalRunExpr has no internal timeout.
    const CDP_TIMEOUT_MS = 5000;
    const evalPromise = evalRunExpr(cdp, expr);
    let timeoutHandle;
    const timeoutPromise = new Promise((_, reject) => {
        timeoutHandle = setTimeout(
            () => reject(new Error('CDP timeout after ' + CDP_TIMEOUT_MS + 'ms in assertPhonePersisted')),
            CDP_TIMEOUT_MS
        );
    });
    let result;
    try {
        result = await Promise.race([evalPromise, timeoutPromise]);
    } finally {
        clearTimeout(timeoutHandle);
    }
    const rawVal = (result && result.result && result.result.value !== undefined)
        ? result.result.value
        : null;
    // typeof guard: prevent `String(obj)` magic like "[object Object]" from
    // accidentally satisfying an includes() match.
    const got = (typeof rawVal === 'string') ? rawVal.trim() : '';
    const expectedTrim = (typeof expected === 'string') ? expected.trim() : '';
    if (got === expectedTrim) {
        console.log('  PASS: phone persisted after reload (' + JSON.stringify(got) + ' === expected)');
        return { pass: true, got };
    }
    throw new Error(
        'FAIL: phone did not persist after reload. expected ' + JSON.stringify(expectedTrim) +
        ', got ' + JSON.stringify(got)
    );
}

const cdp = new CDPClient();
try {
    await cdp.connect();
    step('connect', true);

    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');

    // 1. Navigate to /login
    await cdp.send('Page.navigate', { url: `${LARAVEL_BASE}/login` });
    await cdp.waitFor('Page.loadEventFired', 5000);
    step('navigate-login', true);

    // 2. Type email fallbacks cover the three conventions Breeze /
    //    Laravel UI / custom registration pages use.
    await typeInto(cdp, 'input[name="email"], input[type="email"], #email', EMAIL);
    step('type-email', true);

    // 3. Type password
    await typeInto(cdp, 'input[name="password"], input[type="password"], #password', PASSWORD);
    step('type-password', true);

    // 4. Click the Sign In button
    await clickSelector(cdp, 'button[type="submit"], form button:not([type="button"])');
    await sleep(900);
    step('click-signin', true);

    // 5. Navigate to ACO/JEDO directly.
    const cellUrl = `${LARAVEL_BASE}/impact-cells/${CELL_ID}`;
    await cdp.send('Page.navigate', { url: cellUrl });
    await cdp.waitFor('Page.loadEventFired', 5000);
    step('navigate-cell', true, cellUrl);

    // 6. Click Edit details
    await clickSelector(cdp, '[data-testid="impact-cell-edit-details-button"]');
    await sleep(400);
    step('click-edit-details', true);

    // 7. Type phone via the React-safe setter
    await replaceInputValue(cdp, '#phone', NEW_PHONE);
    step('type-phone', true, NEW_PHONE);

    // 8. Click Save details
    await clickSelector(cdp, '[data-testid="impact-cell-edit-details-submit"]');
    // Wait for the form card to disappear (server PUT + Inertia refresh).
    let collapsedOk = false;
    for (let i = 0; i < 30; i++) {
        const stillPresent = await evalResolve(cdp,
            `!!document.querySelector('[data-testid="impact-cell-edit-details-form-card"]')`
        );
        if (!stillPresent) { collapsedOk = true; break; }
        await sleep(150);
    }
    step('save-collapses-form', collapsedOk, collapsedOk ? 'editor card collapsed' : 'still present >4.5s');

    // 9. Capture a screenshot of the post-save read-only Details row.
    if (SCREENSHOT_OUT) {
        const cap = await cdp.send('Page.captureScreenshot', {
            format: 'png',
            captureBeyondViewport: false,
        });
        writeFileSync(SCREENSHOT_OUT, Buffer.from(cap.data, 'base64'));
        step('screenshot', true, SCREENSHOT_OUT);
    } else {
        step('screenshot-skip', true, 'no SCREENSHOT_OUT env');
    }

    // 10. Reload the page
    await cdp.send('Page.reload');
    await cdp.waitFor('Page.loadEventFired', 5000);
    step('reload', true);

    // 11. Assert the read-only Details card `<dt>Phone</dt>` neighbor
    //     `<dd>` equals NEW_PHONE. This is the persistent-DB guarantee.
    const ddText = await evalResolve(cdp,
        `(() => { const dts = Array.from(document.querySelectorAll('dt'));`
      + `        const dt = dts.find(e => e.textContent.trim().toLowerCase() === 'phone');`
      + `        if (!dt) return null;`
      + `        const dd = dt.nextElementSibling;`
      + `        return dd ? dd.textContent.trim() : null; })()`
    );
    step('assert-phone-persisted', ddText === NEW_PHONE, `dd="${ddText}"  expected="${NEW_PHONE}"`);

    process.stdout.write('\n[OK] ALL PHASE 17 STEPS PASS — Edit Details round-trip confirmed.\n');
    process.exit(0);
} catch (err) {
    if (err instanceof StepFailure) {
        process.stderr.write(`\n[FAIL] Driver stopped at step "${err.name}": ${err.info}\n`);
        process.exit(3);
    }
    process.stderr.write(`\nFATAL: ${err.message}\n${err.stack || ''}\n`);
    process.exit(2);
} finally {
    cdp.close();
}
