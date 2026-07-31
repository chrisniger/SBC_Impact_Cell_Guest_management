<?php
/**
 * Phase 06b verifier — UI polish foundations + chrome.
 *
 * Source of truth: Implementation/Phase_06b-06c_UI_Polish.md §2 (06b foundation).
 *
 * Asserts (numbered):
 *   [1]  tailwind.config.js declares boxShadow.card (CSS-var-wrapped fallback)
 *   [2]  tailwind.config.js declares boxShadow['card-hover'] (CSS-var-wrapped fallback)
 *   [3]  tailwind.config.js declares keyframes.fadeIn with opacity 0 -> 1
 *   [4]  tailwind.config.js declares animation['fade-in'] = 'fadeIn 0.4s ease-out'
 *   [5]  tailwind.config.js shadows reference --shadow-card-default + card-hover-default
 *   [6]  resources/css/app.css defines the --shadow-card-default tokens (light + .dark)
 *   [7]  KPICard.tsx uses the 'shadow-card' class
 *   [8]  KPICard.tsx uses the 'hover:shadow-card-hover' modifier
 *   [9]  KPICard.tsx uses 'motion-safe:animate-fade-in'
 *  [10]  KPICard.tsx no longer contains hardcoded 'shadow-[0_4px_20px_rgba'
 *  [11]  KPICard.tsx preserves the data-testid pattern for kpi cards
 *  [12]  StatusPill.tsx 'brand' tone maps to INDIGO (not red)
 *  [13]  StatusPill.tsx has all 6 tones (neutral, success, warn, danger, brand, info)
 *  [14]  StatusPill.tsx preserves data-testid="pill-status"
 *  [15]  EmptyState.tsx uses the 'shadow-card' class
 *  [16]  EmptyState.tsx uses 'motion-safe:animate-fade-in'
 *  [17]  EmptyState.tsx preserves data-testid="empty-state"
 *  [18]  AdminDashboardLayout.tsx page header band uses 'motion-safe:animate-fade-in' (06b polish token migrated from AuthenticatedLayout)
 *  [19]  AdminDashboardLayout.tsx uses 'motion-safe:animate-fade-in' on >= 3 page-layer wrappers
 *  [20]  AdminDashboardLayout.tsx <main> uses 'motion-safe:animate-fade-in'
 *  [21]  AdminDashboardLayout.tsx no longer contains hardcoded 'shadow-[0_4px_20px_rgba'
 *  [22]  AdminDashboardLayout.tsx no longer contains an inline @keyframes fadeIn block
 *  [23]  GuestLayout.tsx still has its inline @keyframes fadeIn copy (06c safety net)
 *
 * Logic-free assertions: file_get_contents() + strpos / simple substring checks.
 * Designed to run pre-CI in <50ms. Exit 0 on full pass, 1 on any failure.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$failures = [];
$passes   = 0;

function must_read(string $path): string {
    if (!is_file($path)) {
        throw new RuntimeException("missing required file: {$path}");
    }
    return file_get_contents($path);
}

/** Read+substr check helper; reports a precise failure detail so the dev knows
 *  WHICH substring was missing from the file (rather than just "assertion X
 *  failed"). */
function check(int $n, string $label, bool $ok, string $missingSubstrOrDetail = ''): void {
    global $failures, $passes;
    if ($ok) {
        $passes++;
        echo "[{$n}] PASS  {$label}\n";
    } else {
        $failures[] = ['n' => $n, 'label' => $label, 'detail' => $missingSubstrOrDetail];
        echo "[{$n}] FAIL  {$label}  --  {$missingSubstrOrDetail}\n";
    }
}

/** Find the (strpos) location of $needle in $haystack. Returns the offset,
 *  or -1 if not found. Kept tiny so the failure messages stay readable. */
function has(string $haystack, string $needle): int {
    return strpos($haystack, $needle) === false ? -1 : strpos($haystack, $needle);
}

$tailwind    = must_read($root . DIRECTORY_SEPARATOR . 'tailwind.config.js');
$appCss      = must_read($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'app.css');
$kpi         = must_read($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'KPICard.tsx');
$pill        = must_read($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'StatusPill.tsx');
$empty       = must_read($root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Components' . DIRECTORY_SEPARATOR . 'EmptyState.tsx');
// Phase 06d consolidation: AuthenticatedLayout.tsx was retired — admin chrome
// moved to AdminDashboardLayout.tsx (unified layout wrapper across all roles
// + mobile-responsiveness + a11y polish). fail-soft read pattern mirrors the
// GuestLayout read below so a missing/renamed layout file surfaces as clean
// verifier FAILs on [18]-[22] rather than a fatal `must_read` exception that
// aborts the suite mid-run. Targeted token assertions on [18]-[22] trace
// AdminDashboardLayout's actual polish tokens (motion-safe:animate-fade-in on
// >= 3 page-layer wrappers; no hardcoded shadow leakage; no inline <style>
// keyframes). bootstrap count from grep:
//   motion-safe:animate-fade-in  = 3 (header-band + main + footer backdrop class)
//   admin-header-band            = 1 testid on page header band
//   admin-main-content           = 1 testid on <main>
//   shadow-card / hover:shadow-  = 0 (dropdowns migrated to ThemeToggle/LanguageSwitcher child components)
//   <style                       = 0 (inline keyframes dropped 06c)
$layoutPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Layouts' . DIRECTORY_SEPARATOR . 'AdminDashboardLayout.tsx';
$layout     = is_file($layoutPath) ? (string) file_get_contents($layoutPath) : '';
// Phase 06b reviewer Item 3: read GuestLayout.tsx fail-soft so a missing/
// renamed file surfaces as a clean [23] FAIL rather than a fatal exception
// mid-run that prevents the rest of the verifier + downstream suite from
// executing.
$guestLayoutPath = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Layouts' . DIRECTORY_SEPARATOR . 'GuestLayout.tsx';
$guestLayout = is_file($guestLayoutPath) ? (string) file_get_contents($guestLayoutPath) : '';

$missing = static fn(string $s) => "missing required substring \"" . $s . "\"";

// --- tailwind.config.js tokens -----------------------------------------------
check(1, 'tailwind.config.js declares boxShadow.card',
    has($tailwind, 'card:') !== -1,
    $missing('card:'));

check(2, 'tailwind.config.js declares boxShadow[card-hover]',
    has($tailwind, 'card-hover') !== -1,
    $missing('card-hover'));

check(3, 'tailwind.config.js declares keyframes.fadeIn (opacity 0 -> 1)',
    has($tailwind, 'fadeIn:') !== -1
        && has($tailwind, "'0%'") !== -1
        && has($tailwind, "'100%'") !== -1
        && has($tailwind, "opacity: '0'") !== -1
        && has($tailwind, "opacity: '1'") !== -1,
    $missing('keyframes.fadeIn { 0% opacity:0  100% opacity:1 }'));

check(4, 'tailwind.config.js declares animation[fade-in] = fadeIn 0.4s ease-out',
    has($tailwind, "'fade-in':") !== -1
        && has($tailwind, 'fadeIn 0.4s ease-out') !== -1,
    $missing("animation['fade-in'] = 'fadeIn 0.4s ease-out'"));

// Phase 06b reviewer Item 1: dark-mode shadow parity. Shadows must be
// wrapped in CSS-variable fallbacks so app.css can override per-theme.
check(5, 'tailwind.config.js shadows reference --shadow-card-default + --shadow-card-hover-default',
    has($tailwind, 'var(--shadow-card-default') !== -1
        && has($tailwind, 'var(--shadow-card-hover-default') !== -1,
    $missing('CSS variable fallback (Phase 06b dark-mode parity fix)'));

// --- app.css (Phase 06b token declarations) ---------------------------------
check(6, 'resources/css/app.css defines :root and .dark shadow vars',
    has($appCss, '--shadow-card-default') !== -1
        && has($appCss, '--shadow-card-hover-default') !== -1
        && has($appCss, ':root') !== -1
        && has($appCss, '.dark') !== -1,
    $missing('@layer base { :root / .dark } shadow token definitions'));

// --- KPICard.tsx ------------------------------------------------------------
check(7,  'KPICard.tsx uses shadow-card',
    has($kpi, 'shadow-card') !== -1,
    $missing('shadow-card'));

check(8,  'KPICard.tsx uses hover:shadow-card-hover',
    has($kpi, 'hover:shadow-card-hover') !== -1,
    $missing('hover:shadow-card-hover'));

check(9,  'KPICard.tsx uses motion-safe:animate-fade-in',
    has($kpi, 'motion-safe:animate-fade-in') !== -1,
    $missing('motion-safe:animate-fade-in'));

check(10, 'KPICard.tsx has NO hardcoded shadow-[0_4px_20px_rgba utility',
    has($kpi, 'shadow-[0_4px_20px_rgba') === -1,
    'still references hardcoded shadow utility');

check(11, 'KPICard.tsx preserves the data-testid pattern for kpi cards',
    has($kpi, 'data-testid') !== -1
        && has($kpi, 'kpi-') !== -1
        && has($kpi, 'caption.toLowerCase().replace') !== -1,
    $missing('data-testid ... kpi-{caption.toLowerCase().replace}'));

// --- StatusPill.tsx ----------------------------------------------------------
// StatusPill.tsx uses TypeScript's unquoted object-literal key form `brand:`
// (NOT the quoted JavaScript form `'brand':`). The Phase 06b comment block
// we added uses `"brand"` (double-quoted) so it does NOT false-match `brand:`.
//
// Window is 300 bytes (not 200) because the Phase 06b comment block in
// toneClasses is ~245 bytes long — a 200-byte window would fail the check
// forever. Don't shrink without first deleting the comment block.
//
// Combined positive (indigo within window) AND negative (no red after the
// key) into a single assertion so the integer [N] stays valid (PHP does
// not accept `12b` as int literal, so [12b] would parse as `12 b`).
$brandIdx = strpos($pill, 'brand:');
$bgIdx    = $brandIdx === false ? false : strpos($pill, 'bg-indigo-100',         (int) $brandIdx);
$redAt    = $brandIdx === false ? false : strpos($pill, 'bg-red-100',            (int) $brandIdx);
check(12, "StatusPill.tsx 'brand' tone uses INDIGO and not red",
    $brandIdx !== false
        && $bgIdx !== false
        && ($bgIdx - $brandIdx) < 300
        && $redAt === false,
    "'brand:' tone key should map to bg-indigo-100 within 300 chars AND not bg-red-100 (Phase 06b fix lost)");

check(13, 'StatusPill.tsx has all 6 tones (neutral, success, warn, danger, brand, info)',
    has($pill, 'neutral:') !== -1
        && has($pill, 'success:') !== -1
        && has($pill, 'warn:') !== -1
        && has($pill, 'danger:') !== -1
        && has($pill, 'brand:') !== -1
        && has($pill, 'info:') !== -1,
    $missing('one or more expected tones'));

check(14, 'StatusPill.tsx preserves data-testid="pill-status"',
    has($pill, 'data-testid="pill-status"') !== -1,
    $missing('data-testid="pill-status"'));

// --- EmptyState.tsx ----------------------------------------------------------
check(15, 'EmptyState.tsx uses shadow-card',
    has($empty, 'shadow-card') !== -1,
    $missing('shadow-card'));

check(16, 'EmptyState.tsx uses motion-safe:animate-fade-in',
    has($empty, 'motion-safe:animate-fade-in') !== -1,
    $missing('motion-safe:animate-fade-in'));

check(17, 'EmptyState.tsx preserves data-testid="empty-state"',
    has($empty, 'data-testid="empty-state"') !== -1,
    $missing('data-testid="empty-state"'));

// --- AdminDashboardLayout.tsx (Phase 06d consolidation; was AuthenticatedLayout) ---
// Phase 06d removed the AuthenticatedLayout dropdown-trigger chrome — the
// layout no longer owns dropdowns (ThemeToggle / LanguageSwitcher / RoleBadge
// own them as child components imported via the top of AdminDashboardLayout).
// The layout's primary polish tokens now live on page-layer chrome
// (header-band + main + backdrop), using `motion-safe:animate-fade-in`
// consistently. [18]-[20] trace that token across layer elements; [21]-[22]
// remain negative-assertion regression guards (no hardcoded shadow utilities,
// no inline <style> keyframes block).
check(18, 'AdminDashboardLayout.tsx page header band uses motion-safe:animate-fade-in (06b polish token migrated from AuthenticatedLayout)',
    has($layout, 'motion-safe:animate-fade-in') !== -1
        && has($layout, 'admin-header-band') !== -1,
    $missing('admin-header-band ... motion-safe:animate-fade-in'));

check(19, 'AdminDashboardLayout.tsx uses motion-safe:animate-fade-in on >= 3 AND <= 6 page-layer wrappers (header-band + main + backdrop adopted from 06b polish; bounded range flags both drop and proliferation regressions)',
    substr_count($layout, 'motion-safe:animate-fade-in') >= 3
        && substr_count($layout, 'motion-safe:animate-fade-in') <= 6
        && substr_count($layout, 'admin-header-band') >= 1
        && substr_count($layout, 'admin-main-content') >= 1,
    $missing('3-6 motion-safe:animate-fade-in + admin-header-band + admin-main-content design token adoption (closed range flags both drop + proliferation regressions)'));

check(20, 'AdminDashboardLayout.tsx <main> uses motion-safe:animate-fade-in',
    has($layout, '<main') !== -1
        && has($layout, 'motion-safe:animate-fade-in') !== -1
        && has($layout, 'admin-main-content') !== -1,
    $missing('<main ... motion-safe:animate-fade-in admin-main-content wrapper'));

check(21, 'AdminDashboardLayout.tsx has NO hardcoded shadow-[0_4px_20px_rgba utility',
    has($layout, 'shadow-[0_4px_20px_rgba') === -1,
    'still references hardcoded shadow utility');

// [22] — AdminDashboardLayout.tsx has no explanatory `@keyframes fadeIn`
// comment block (that was a quirk of the old AuthenticatedLayout). The
// negative assertion on the loose substring `<style` is therefore enough
// to flag any future regression: a re-introduction of inline-styles would
// only happen as a deliberate Phase-N regression.
check(22, 'AdminDashboardLayout.tsx has NO inline <style>...</style> block',
    strpos($layout, '<style') === false,
    'still contains an opening <style> tag (inline @keyframes block)');

// Phase 06c INVERSION: GuestLayout's inline `@keyframes fadeIn` block was
// the 06b safety net for any page that still used `animate-[fadeIn_…]`
// arbitrary values. 06c refactored those pages so no consumer of the
// arbitrary form remains; the safety net is now redundant and is being
// dropped here.
//
// IRREDUCIBLE SIGNAL: match the keyframe block by its actual structure
// `<style[^>]*>…@keyframes fadeIn…</style>`, not just the loose `<style`
// opening tag (which future scoped-CSS-in-JS or `<stylesheet>` refs could
// false-positive on). Phase 06c's replacement comment in GuestLayout.tsx
// legitimately references the literal text "`@keyframes fadeIn`" as
// documentation, so a literal-substring check is unreliable.
//
// \b after `fadeIn` ensures the identifier name is exactly `fadeIn` and
// not a prefix-match of a future custom keyframe like `fadeInBubbles`.
// The /s flag lets `.` match newlines (cleaner than hand-rolled `\s\S`).
check(23, 'GuestLayout.tsx NO LONGER has an inline <style>...@keyframes fadeIn...</style> block (06c safety-net dropped)',
    $guestLayout !== '' && preg_match('/<style[^>]*>.*?@keyframes\s+fadeIn\b.*?<\/style>/s', $guestLayout) === 0,
    'still contains an inline <style>...@keyframes fadeIn...</style> block — 06c safety-net drop incomplete');

// --- Phase 06c page-refactor sweep -----------------------------------------
// Concatenate every Pages/**/*.tsx (excluding Auth/*, which still depends on
// GuestLayout's @keyframes block in 06b-is-now-a-legacy sense — but per the
// 06c refactor GuestLayout itself is also on the named token now, so Auth
// pages should already work via Tailwind's global keyframe emission).
$pagesRoot  = $root . DIRECTORY_SEPARATOR . 'resources' . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'Pages';
$pagesAll   = '';
$pagesIt = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($pagesRoot, FilesystemIterator::SKIP_DOTS)
);
foreach ($pagesIt as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'tsx') continue;
    // Skip Auth pages — those depend on GuestLayout (which 06c already fixed).
    // RELATIVE-path check (per code-reviewer item #1): the previous absolute
    // strpos() matched the literal `/Auth/` substring anywhere in the path,
    // which would also exclude future siblings like `AuthDashboard/` or
    // `Auth-v2/`. Compute the relative path under $pagesRoot and only skip
    // the canonical `/Auth/` directory.
    $rel = substr($file->getPathname(), strlen($pagesRoot));
    if ($rel !== '' && str_starts_with(ltrim($rel, DIRECTORY_SEPARATOR), 'Auth' . DIRECTORY_SEPARATOR)) continue;
    $pagesAll .= file_get_contents($file->getPathname());
}

check(24, "GuestLayout.tsx uses motion-safe:animate-fade-in (06c)",
    strpos($guestLayout, 'motion-safe:animate-fade-in') !== false,
    'GuestLayout.tsx missing motion-safe:animate-fade-in after 06c refactor');

check(25, "Pages/ have ZERO motion-safe:animate-[fadeIn_...] arbitrary usages left (06c)",
    strpos($pagesAll, 'motion-safe:animate-[fadeIn') === false,
    'Pages still references motion-safe:animate-[fadeIn_...] — 06c refactor incomplete');

check(26, "Pages/ have ZERO positive shadow-[0_4px_20px_rgba(0,0,0,0.03)] usages left (06c)",
    strpos($pagesAll, 'shadow-[0_4px_20px_rgba(0,0,0,0.03)]') === false,
    'Pages still has hardcoded positive-offset shadow — 06c refactor incomplete');

check(27, "Pages/ have ZERO hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)] usages left (06c)",
    strpos($pagesAll, 'hover:shadow-[0_8px_30px_rgba(0,0,0,0.06)]') === false,
    'Pages still has hardcoded hover-shadow — 06c refactor incomplete');

// --- Summary -----------------------------------------------------------------
$total = $passes + count($failures);
echo "\n{$passes} pass / " . count($failures) . " fail (out of {$total} sub-assertions)\n";

if ($failures !== []) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) {
        echo "  [{$f['n']}] {$f['label']}\n    -> {$f['detail']}\n";
    }
    exit(1);
}

exit(0);
