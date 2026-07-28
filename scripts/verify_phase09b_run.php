<?php
/**
 * Phase 09b verifier — Notifications polish round.
 *
 * 15 source-pattern sub-assertions covering:
 *   - GuestController::store() fires GUEST_ASSIGNED helper on initial assignment.
 *   - GuestController::update() captures $beforeCellId + fires helper ONLY on change.
 *   - GuestController helper wraps Mail::raw in try/catch + Log::warning per-recipient.
 *   - NotificationSettingsController::index() returns mailConfigured boolean.
 *   - NotificationSettingsController::testEmail() admin-gated + Mail::raw try/catch + JSON.
 *   - routes/web.php registers POST /notification-settings/test-email + named route.
 *   - Settings.tsx renders mail-config-badge + ✓/⚠ text + per-rule Send test email button.
 *
 * Style note: assertions use simple `str_contains` (no complex regex) to absorb whitespace
 * tolerance + quote-style variation + multi-line positioning drift. This avoids the prior
 * 9/6 brittle-verifier state where regex patterns over-cited specific spacing.
 *
 * Run: php scripts/verify_phase09b_run.php
 * Expected: 15 pass / 0 fail.
 */

declare(strict_types=1);

set_error_handler(function (int $errno, string $errstr, string $errfile, int $errline): void {
    fwrite(STDERR, "PHP error (#{$errno}): {$errstr} in {$errfile}:{$errline}\n");
    exit(1);
});

$pass = 0;
$fail = 0;
$failed = [];

function check(int $n, string $label, bool $cond, string $expected): void
{
    global $pass, $fail, $failed;
    if ($cond) {
        $pass++;
        echo "  [{$n}] pass -- {$label}\n";
    } else {
        $fail++;
        $failed[] = "[{$n}] {$label} -- expected: {$expected}";
        echo "  [{$n}] FAIL -- {$label} (expected: {$expected})\n";
    }
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
$app  = require $root . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$gcPath = $root . '/app/Http/Controllers/GuestController.php';
$nscPath = $root . '/app/Http/Controllers/NotificationSettingsController.php';
$routesPath = $root . '/routes/web.php';
$settingsPagePath = $root . '/resources/js/Pages/Notifications/Settings.tsx';

$gcSrc = is_file($gcPath) ? file_get_contents($gcPath) : '';
$nscSrc = is_file($nscPath) ? file_get_contents($nscPath) : '';
$routesSrc = is_file($routesPath) ? file_get_contents($routesPath) : '';
$settingsSrc = is_file($settingsPagePath) ? file_get_contents($settingsPagePath) : '';

// ---------------------------------------------------------------------------
// [1] self-syntax -- verifier file must be PHP-parseable (no fatal).
// ---------------------------------------------------------------------------
$tmpLint = tempnam(sys_get_temp_dir(), 'p09b_lint_');
file_put_contents($tmpLint, file_get_contents(__FILE__));
$lintOut = shell_exec(escapeshellcmd(PHP_BINARY) . ' -l ' . escapeshellarg($tmpLint) . ' 2>&1');
unlink($tmpLint);
check(1, 'verify_phase09b_run.php parses cleanly (php -l)',
    is_file(__FILE__) && str_contains((string) $lintOut, 'No syntax errors detected'),
    'php -l reports "No syntax errors detected"');

// ---------------------------------------------------------------------------
// [2] GuestController has the GUEST_ASSIGNED helper.
// ---------------------------------------------------------------------------
check(2, 'GuestController has private sendGuestAssignedNotification(Guest $guest): void helper',
    str_contains($gcSrc, 'private function sendGuestAssignedNotification(Guest $guest): void'),
    'private function sendGuestAssignedNotification(Guest $guest): void');

// ---------------------------------------------------------------------------
// [3] store() fires the helper after Guest::create on initial assignment.
// ---------------------------------------------------------------------------
check(3, 'GuestController::store() fires helper after Guest::create (initial assignment)',
    str_contains($gcSrc, '$guest = Guest::create(')
    && str_contains($gcSrc, '$this->sendGuestAssignedNotification($guest)'),
    '$guest = Guest::create(...) + $this->sendGuestAssignedNotification($guest) call site in store()');

// ---------------------------------------------------------------------------
// [4] update() captures $beforeCellId BEFORE write + change-detection gate.
// ---------------------------------------------------------------------------
check(4, 'GuestController::update() captures $beforeCellId + change-detection !== gate',
    str_contains($gcSrc, '$beforeCellId = $guest->nearest_impact_cell_id')
    && str_contains($gcSrc, '($beforeCellId ?? \'\') !== ($guest->nearest_impact_cell_id ?? \'\')'),
    'capture $beforeCellId = $guest->nearest_impact_cell_id BEFORE write + post-write ($beforeCellId ?? \'\') !== ($guest->nearest_impact_cell_id ?? \'\') gate');

// ---------------------------------------------------------------------------
// [5] Helper queries NotificationSetting::where(action, GUEST_ASSIGNED) + Log::info skip.
// ---------------------------------------------------------------------------
check(5, "Helper queries NotificationSetting::where('action', 'GUEST_ASSIGNED') + Log::info skip-message",
    str_contains($gcSrc, "NotificationSetting::where('action', 'GUEST_ASSIGNED')")
    && str_contains($gcSrc, "Log::info('GUEST_ASSIGNED notification skipped"),
    "NotificationSetting::where('action', 'GUEST_ASSIGNED')->where('enabled', true)->get() + Log::info('GUEST_ASSIGNED notification skipped...') when no rules configured");

// ---------------------------------------------------------------------------
// [6] Helper wraps Mail::raw in try/catch (\\Exception $e) + Log::warning per-recipient.
// ---------------------------------------------------------------------------
check(6, 'Helper wraps Mail::raw in try/catch (\\Exception $e) + Log::warning per-recipient',
    str_contains($gcSrc, 'Mail::raw(')
    && str_contains($gcSrc, 'catch (\Exception $e)')
    && str_contains($gcSrc, 'Log::warning(')
    && str_contains($gcSrc, 'send GUEST_ASSIGNED email'),
    'try { Mail::raw(...) ... } catch (\\Exception $e) { Log::warning("...Failed to send GUEST_ASSIGNED email to..."); } per-recipient');

// ---------------------------------------------------------------------------
// [7] NotificationSettingsController::testEmail() is admin-gated.
// ---------------------------------------------------------------------------
check(7, "NotificationSettingsController::testEmail(Request): JsonResponse admin-gated via abort_unless",
    str_contains($nscSrc, 'public function testEmail(Request $request): JsonResponse')
    && str_contains($nscSrc, 'abort_unless(')
    && str_contains($nscSrc, "activeRole() === 'Administrator'")
    && str_contains($nscSrc, ', 403'),
    "public function testEmail(Request \$request): JsonResponse + abort_unless(activeRole === Administrator, 403)");

// ---------------------------------------------------------------------------
// [8] NotificationSettingsController::index() returns 'mailConfigured' boolean.
// ---------------------------------------------------------------------------
check(8, 'NotificationSettingsController::index() returns \'mailConfigured\' => $this->isMailConfigured() prop',
    str_contains($nscSrc, "'mailConfigured'")
    && str_contains($nscSrc, '$this->isMailConfigured()'),
    "'mailConfigured' => \$this->isMailConfigured() in Inertia::render payload (any whitespace between key and arrow)");

// ---------------------------------------------------------------------------
// [9] testEmail returns response()->json(['sent', 'message']) shape.
// ---------------------------------------------------------------------------
check(9, "NotificationSettingsController::testEmail() returns response()->json(['sent', 'message']) shape",
    str_contains($nscSrc, "response()->json([")
    && str_contains($nscSrc, "'sent'")
    && str_contains($nscSrc, "'message'"),
    "response()->json(['sent' => bool, 'message' => string]) on testEmail success path");

// ---------------------------------------------------------------------------
// [10] NotificationSettingsController has private isMailConfigured(): bool.
// ---------------------------------------------------------------------------
check(10, "NotificationSettingsController has private isMailConfigured(): bool + 4 mail.* keys",
    str_contains($nscSrc, 'private function isMailConfigured(): bool')
    && str_contains($nscSrc, "'mail.default'")
    && str_contains($nscSrc, "'mail.host'")
    && str_contains($nscSrc, "'mail.port'")
    && str_contains($nscSrc, "'mail.username'")
    && str_contains($nscSrc, "'mail.password'"),
    "private function isMailConfigured(): bool + checks config('mail.default') === 'smtp' + iterates [mail.host, mail.port, mail.username, mail.password]");

// ---------------------------------------------------------------------------
// [11] Settings.tsx renders data-testid="mail-config-badge" + per-state badge text.
// ---------------------------------------------------------------------------
check(11, 'Pages/Notifications/Settings.tsx renders data-testid="mail-config-badge" with ✓ SMTP / ⚠ Mail: log text',
    str_contains($settingsSrc, 'data-testid="mail-config-badge"')
    && (str_contains($settingsSrc, 'SMTP configured') || str_contains($settingsSrc, '✓'))
    && (str_contains($settingsSrc, 'Mail: log') || str_contains($settingsSrc, '⚠')),
    '<span data-testid="mail-config-badge"> with text containing "✓ SMTP configured" (when configured) OR "⚠ Mail: log (dev)" (when unconfigured)');

// ---------------------------------------------------------------------------
// [12] routes/web.php registers POST /notification-settings/test-email + named route.
// ---------------------------------------------------------------------------
check(12, "routes/web.php registers POST /notification-settings/test-email + named 'notification-settings.test-email'",
    str_contains($routesSrc, 'Route::post')
    && str_contains($routesSrc, '/notification-settings/test-email')
    && str_contains($routesSrc, "'testEmail'")
    && str_contains($routesSrc, "'notification-settings.test-email'"),
    "Route::post('/notification-settings/test-email', [Controller, 'testEmail'])->name('notification-settings.test-email')");

// ---------------------------------------------------------------------------
// [13] Settings.tsx renders per-rule "Send test email" button.
// ---------------------------------------------------------------------------
check(13, 'Pages/Notifications/Settings.tsx renders per-rule "Send test email" button label',
    str_contains($settingsSrc, 'Send test email'),
    'button text containing "Send test email"');

// ---------------------------------------------------------------------------
// [14] Settings.tsx reads CSRF token from meta[name="csrf-token"] before submit.
// ---------------------------------------------------------------------------
check(14, 'Pages/Notifications/Settings.tsx references csrf-token (from meta or X-CSRF-Token header)',
    str_contains($settingsSrc, 'csrf-token'),
    "document.querySelector('meta[name=\"csrf-token\"]') OR X-CSRF-Token header");

// ---------------------------------------------------------------------------
// [15] Settings.tsx posts to /notification-settings/test-email (router.post OR fetch) + recipient_email.
// ---------------------------------------------------------------------------
check(15, "Pages/Notifications/Settings.tsx posts to /notification-settings/test-email + recipient_email",
    str_contains($settingsSrc, '/notification-settings/test-email')
    && (str_contains($settingsSrc, 'router.post') || str_contains($settingsSrc, 'fetch'))
    && str_contains($settingsSrc, 'recipient_email'),
    "router.post OR fetch to /notification-settings/test-email with recipient_email data in payload");

// ---------------------------------------------------------------------------

echo "\nPhase 09b verifier: {$pass} pass / {$fail} fail\n";
if ($fail > 0) {
    echo "\nFailures:\n";
    foreach ($failed as $f) {
        echo "  - {$f}\n";
    }
}
exit($fail === 0 ? 0 : 1);
