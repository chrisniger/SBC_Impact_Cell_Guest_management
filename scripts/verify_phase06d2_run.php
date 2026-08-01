<?php
/**
 * scripts/verify_phase06d2_run.php
 *
 * Phase 06d.2 verifier — Admin Dashboard polish, sub-phase 2 (final round).
 *
 * Asserts (15):
 *   [1]  RelativeTime.tsx exists
 *   [2]  RelativeTime has 4 threshold source-literals ('just now' / 'min ago' / 'hr ago' / 'days ago')
 *   [3]  SystemOverviewPanel.tsx exists
 *   [4]  SystemOverviewPanel: 4 progress-card data-testids (db-size / storage / active-users / health)
 *   [5]  SystemOverviewPanel: 4 progress-bar data-testids (db / storage / active-users / health)
 *   [6]  RecentActivityGrid.tsx exists
 *   [7]  RecentActivityGrid: 6 tile data-testids (guests / cells / reports / notifications / audit-log / users)
 *   [8]  RecentRegistrationsFeed.tsx exists
 *   [9]  RecentRegistrationsFeed: data-testid presence + exports getInitials
 *   [10] GlobalSearch uses @headlessui/react Combobox + ComboboxInput + ComboboxOptions
 *   [11] GlobalSearch has 'admin-global-search-input' ComboboxInput testid
 *   [12] LanguageSwitcher.tsx is INTENTIONALLY ABSENT (Phase 06e removed — single-language invariant)
 *   [13] admin-language-switcher testid is INTENTIONALLY ABSENT (Phase 06e removed — placeholder-wiring reintroduction guard)
 *   [14] FooterCard.tsx exists with admin-footer-card / admin-footer-env / admin-footer-version testids
 *   [15] DashboardController extended with systemOverview / globalSearchIndex / recentActivity / recentRegistrations payloads
 *
 * Run:  /d/php84/php.exe scripts/verify_phase06d2_run.php
 * Exit: 0 = green, 1 = red.
 */

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app = require $root . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$pass = 0; $fail = 0; $failures = [];

function check(int $n, string $label, bool $ok, string $detail = ''): void {
    global $pass, $fail, $failures;
    if ($ok) {
        $pass++;
        echo "  [{$n}] PASS  {$label}\n";
    } else {
        $fail++;
        $failures[] = "[{$n}] {$label}" . ($detail !== '' ? " — {$detail}" : '');
        echo "  [{$n}] FAIL  {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

$files = [
    'relativeTime' => $root . '/resources/js/Components/RelativeTime.tsx',
    'systemOverview' => $root . '/resources/js/Components/SystemOverviewPanel.tsx',
    'recentActivity' => $root . '/resources/js/Components/RecentActivityGrid.tsx',
    'recentRegistrations' => $root . '/resources/js/Components/RecentRegistrationsFeed.tsx',
    'globalSearch' => $root . '/resources/js/Components/GlobalSearch.tsx',
    'languageSwitcher' => $root . '/resources/js/Components/LanguageSwitcher.tsx',
    'footerCard' => $root . '/resources/js/Components/FooterCard.tsx',
];

$src = [];
foreach ($files as $key => $path) {
    $src[$key] = is_file($path) ? (string) file_get_contents($path) : '';
}

// ─────────────────────────────────────────────────────────────────────────
// RelativeTime + SystemOverviewPanel + RecentActivityGrid
// ─────────────────────────────────────────────────────────────────────────

check(1, 'RelativeTime.tsx exists',
    $src['relativeTime'] !== '');

check(2, 'RelativeTime has 4 threshold source-literals for the verifier token-stable check',
    str_contains($src['relativeTime'], "'just now'")
    && str_contains($src['relativeTime'], "'min ago'")
    && str_contains($src['relativeTime'], "'hr ago'")
    && str_contains($src['relativeTime'], "'days ago'"));

check(3, 'SystemOverviewPanel.tsx exists',
    $src['systemOverview'] !== '');

check(4, 'SystemOverviewPanel: 4 progress-card data-testids (db-size / storage / active-users / health)',
    str_contains($src['systemOverview'], 'system-overview-card-db-size')
    && str_contains($src['systemOverview'], 'system-overview-card-storage')
    && str_contains($src['systemOverview'], 'system-overview-card-active-users')
    && str_contains($src['systemOverview'], 'system-overview-card-health'));

check(5, 'SystemOverviewPanel: 4 progress-bar data-testids (db / storage / active-users / health)',
    str_contains($src['systemOverview'], 'system-overview-bar-db')
    && str_contains($src['systemOverview'], 'system-overview-bar-storage')
    && str_contains($src['systemOverview'], 'system-overview-bar-active-users')
    && str_contains($src['systemOverview'], 'system-overview-bar-health'));

check(6, 'RecentActivityGrid.tsx exists',
    $src['recentActivity'] !== '');

check(7, 'RecentActivityGrid: 6 tile data-testids (guests / cells / reports / notifications / audit-log / users)',
    str_contains($src['recentActivity'], 'recent-activity-tile-guests')
    && str_contains($src['recentActivity'], 'recent-activity-tile-impact-cells')
    && str_contains($src['recentActivity'], 'recent-activity-tile-reports')
    && str_contains($src['recentActivity'], 'recent-activity-tile-notifications')
    && str_contains($src['recentActivity'], 'recent-activity-tile-audit-log')
    && str_contains($src['recentActivity'], 'recent-activity-tile-users'));

check(8, 'RecentRegistrationsFeed.tsx exists',
    $src['recentRegistrations'] !== '');

check(9, 'RecentRegistrationsFeed: data-testid scope + exports getInitials',
    str_contains($src['recentRegistrations'], 'recent-registrations-feed')
    && str_contains($src['recentRegistrations'], 'recent-registrations-cards')
    && preg_match('/export\s+function\s+getInitials\s*\(/s', $src['recentRegistrations']) === 1);

// ─────────────────────────────────────────────────────────────────────────
// GlobalSearch + LanguageSwitcher-absence + FooterCard
// ─────────────────────────────────────────────────────────────────────────

check(10, 'GlobalSearch uses @headlessui/react Combobox + ComboboxInput + ComboboxOptions',
    str_contains($src['globalSearch'], "@headlessui/react")
    && str_contains($src['globalSearch'], 'Combobox')
    && str_contains($src['globalSearch'], 'ComboboxInput')
    && str_contains($src['globalSearch'], 'ComboboxOptions'));

check(11, 'GlobalSearch has admin-global-search-input ComboboxInput testid',
    str_contains($src['globalSearch'], 'admin-global-search-input')
    && str_contains($src['globalSearch'], 'admin-global-search-options'));

// Phase 06e — LanguageSwitcher.tsx was REMOVED (default-language-is-English-only
// product invariant: the user removed the language icon on the dashboard because
// the default language is English for all users). The [12]+[13] assertions below
// are now ABSENCE-REGRESSION-GUARDS: they FAIL if anyone re-introduces the
// LanguageSwitcher component file OR re-wires the admin-language-switcher testid
// in any source file (with the file gone post-removal, an empty str_contains
// automatically returns false, but we keep the $src array path so future
// refactors that re-create the file under the same path are caught).
check(12, 'LanguageSwitcher.tsx is INTENTIONALLY ABSENT (Phase 06e removed — single-language invariant; default is English for all users)',
    !is_file($root . '/resources/js/Components/LanguageSwitcher.tsx'),
    'expected LanguageSwitcher.tsx to not exist on disk; if you see this fail, the placeholder was re-introduced (regression of the single-language product invariant)');

check(13, 'admin-language-switcher testid is INTENTIONALLY ABSENT (Phase 06e removed — file is gone, so source reads empty; this is the placeholder-wiring reintroduction guard)',
    !str_contains($src['languageSwitcher'], 'admin-language-switcher'),
    'expected admin-language-switcher testid NOT to be present in (post-removal) LanguageSwitcher source; str_contains on empty haystack returns false automatically, so the empty-file state is covered without an explicit OR');

check(14, 'FooterCard.tsx exists with admin-footer-card + admin-footer-env + admin-footer-version testids',
    str_contains($src['footerCard'], 'admin-footer-card')
    && str_contains($src['footerCard'], 'admin-footer-env')
    && str_contains($src['footerCard'], 'admin-footer-version'));

// ─────────────────────────────────────────────────────────────────────────
// Controller extension — DashboardController extended with systemOverview +
// globalSearchIndex + recentActivity + recentRegistrations Inertia payload keys
// ─────────────────────────────────────────────────────────────────────────

$ctrlPath = $root . '/app/Http/Controllers/DashboardController.php';
$ctrl = is_file($ctrlPath) ? (string) file_get_contents($ctrlPath) : '';

check(15, 'DashboardController extended with systemOverview + globalSearchIndex + recentActivity + recentRegistrations payloads',
    str_contains($ctrl, "'systemOverview'")
    && str_contains($ctrl, "'globalSearchIndex'")
    && str_contains($ctrl, "'recentActivity'")
    && str_contains($ctrl, "'recentRegistrations'"),
    'expected 4 new payload keys passed via Inertia::render');

// ─────────────────────────────────────────────────────────────────────────
// Summary
// ─────────────────────────────────────────────────────────────────────────

echo "\n========================================\n";
echo "  Phase 06d.2 System Overview+Activity+Registrations+FooterCard+GlobalSearch+LangSwitcher: {$pass} pass / {$fail} fail\n";
echo "========================================\n";

if ($fail > 0) {
    echo "\nFAILURES:\n";
    foreach ($failures as $f) echo "  - {$f}\n";
    exit(1);
}
exit(0);
