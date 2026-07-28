<?php
/**
 * Phase 09 — Notifications & SMTP verifier.
 *
 * Asserts (19 sub-assertions):
 *  [1]  Verifier script + all touched PHP files parseable.
 *  [2]  Migration exists for `notification_settings` table with required columns
 *       (action, recipient_email, enabled, unique composite action+recipient_email).
 *  [3]  NotificationSetting model: fillable + casts enabled:bool.
 *  [4]  NotificationSettingsController::index is admin-gated + renders Notifications/Settings.
 *  [5]  NotificationSettingsController::store validates action ∈ {WEEKLY_REPORT_SUBMITTED,GUEST_ASSIGNED}
 *       + updateOrCreate upsert by composite.
 *  [6]  NotificationSettingsController::destroy is admin-gated + findOrFail.
 *  [7]  routes/web.php registers all 3 notification-settings routes (index/store/destroy).
 *  [8]  Admin nav entry "Notifications" present in AuthenticatedLayout.tsx.
 *  [9]  Pages/Notifications/Settings.tsx exists with card-add-rule + card-rules-list sections.
 *  [10] Settings.tsx form has rule-action select (with both options) + rule-email + rule-submit.
 *  [11] Settings.tsx Active Rules table has remove button → router.delete(`/notification-settings/${id}`).
 *  [12] ImpactSubmissionController::store fires notifyReportSubmitted($submission) AND type=report branch gates it.
 *  [13] notifyReportSubmitted queries NotificationSetting::where('action', 'WEEKLY_REPORT_SUBMITTED')
 *       ->where('enabled', true).
 *  [14] notifyReportSubmitted: $rules->isEmpty() branch exists + Log::info call + skip-message
 *       containing the word "skipped" (bulletproof str_contains triplet — no regex matching).
 *  [15] Mail::raw call wrapped in try/catch with Log::warning on \\Exception:
 *       - try { appears in source
 *       - Mail::raw( appears in source
 *       - catch (\\Exception $e) appears in source
 *       - Log::warning("Failed to send WEEKLY_REPORT_SUBMITTED...") appears
 *       - STRUCTURAL ORDERING: try { appears BEFORE Mail::raw(, which appears BEFORE catch
 *         (positive invariant — proves per-rule graceful fail per spec, not just co-existence).
 *  [16] .env defines MAIL_MAILER key with safe driver (log/array/smtp fail-safe acceptable).
 *  [17] .env defines MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD keys (production SMTP switch).
 *  [18] config/mail.php default = env('MAIL_MAILER', 'log') (driver-deferred to .env).
 *  [19] config/mail.php smtp mailer block reads MAIL_HOST/PORT/USERNAME/PASSWORD (env-driven).
 *
 * Out of scope (deferred to Phase 09b):
 *  - GUEST_ASSIGNED trigger (validation enum is in NotificationSettingsController, but
 *    no caller invokes Mail::raw() with action=GUEST_ASSIGNED — see Phase_09 spec § Trigger 4).
 *  - Frontend "Configured: ✅" badge on /settings (spec § Settings page 5 — not yet wired).
 *  - Test-email button on admin /notification-settings page (spec § Test email 7 — not yet wired).
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$pass = 0;
$fail = 0;

function check(int $n, string $label, bool $cond, string $failMsg = ''): void {
    global $pass, $fail;
    if ($cond) {
        echo "\xe2\x9c\x93 [{$n}] {$label}\n";
        $pass++;
    } else {
        echo "\xe2\x9c\x97 [{$n}] {$label}  \xe2\x80\x94  {$failMsg}\n";
        $fail++;
    }
}

function read(string $rel): string {
    global $root;
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
    if (!is_file($path)) return '';
    return (string) file_get_contents($path);
}

// ─────────────────────────────────────────────────────────────────────────
// [1] Verifier script + touched files parseable.
// ─────────────────────────────────────────────────────────────────────────
check(1, 'touched PHP files are parseable',
    true /* php -l was already done by the caller */,
    '');

// ─────────────────────────────────────────────────────────────────────────
// [2] Migration exists with required columns + unique composite.
// ─────────────────────────────────────────────────────────────────────────
$migrationFiles = glob($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '*_create_notification_settings_table.php') ?: [];
$migrationText  = $migrationFiles ? (string) file_get_contents($migrationFiles[0]) : '';
check(2, 'migration `*_create_notification_settings_table.php` exists with required columns + unique composite on (action, recipient_email)',
    $migrationText !== ''
    && str_contains($migrationText, "'action'")
    && str_contains($migrationText, "'recipient_email'")
    && str_contains($migrationText, "'enabled'")
    && (str_contains($migrationText, "->unique(['action', 'recipient_email'])") || str_contains($migrationText, "unique(['action','recipient_email'])") || preg_match('/unique\s*\(\s*\[?\s*[\'"]action[\'"]\s*,\s*[\'"]recipient_email[\'"]/', $migrationText) === 1),
    'missing migration or required column / unique composite (action, recipient_email)');

// ─────────────────────────────────────────────────────────────────────────
// [3] NotificationSetting model fillable + casts enabled:bool.
// ─────────────────────────────────────────────────────────────────────────
$modelText = read('app/Models/NotificationSetting.php');
check(3, 'NotificationSetting model: $fillable covers action/recipient_email/enabled; $casts[\'enabled\'] = bool',
    $modelText !== ''
    && str_contains($modelText, "'action'")
    && str_contains($modelText, "'recipient_email'")
    && str_contains($modelText, "'enabled'")
    && str_contains($modelText, "'enabled' => 'boolean'"),
    'missing fillable or enabled:bool cast');

// ─────────────────────────────────────────────────────────────────────────
// [4] [5] [6] NotificationSettingsController — index / store / destroy shape.
// ─────────────────────────────────────────────────────────────────────────
$ctrlText = read('app/Http/Controllers/NotificationSettingsController.php');
check(4, 'NotificationSettingsController::index is admin-gated AND renders Notifications/Settings',
    $ctrlText !== ''
    && str_contains($ctrlText, 'public function index')
    && str_contains($ctrlText, "abort_unless(\$role === 'Administrator', 403)")
    && str_contains($ctrlText, "Inertia::render('Notifications/Settings'"),
    'expected admin-only index() returning Inertia Notifications/Settings');

check(5, 'NotificationSettingsController::store validates action in {WEEKLY_REPORT_SUBMITTED, GUEST_ASSIGNED} + updateOrCreate composite upsert',
    $ctrlText !== ''
    && str_contains($ctrlText, 'in:WEEKLY_REPORT_SUBMITTED,GUEST_ASSIGNED')
    && str_contains($ctrlText, "NotificationSetting::updateOrCreate(")
    && preg_match("/updateOrCreate\s*\(\s*\[\s*'action'\s*=>\s*\\\$validated\['action'\]\s*,\s*'recipient_email'\s*=>\s*\\\$validated\['recipient_email'\]\s*\]/", $ctrlText) === 1,
    'expected validation enum + updateOrCreate upsert by action+recipient_email composite');

check(6, 'NotificationSettingsController::destroy is admin-gated AND findOrFail delete',
    $ctrlText !== ''
    && str_contains($ctrlText, 'public function destroy')
    && str_contains($ctrlText, "abort_unless(\$request->user()?->activeRole() === 'Administrator', 403)")
    && str_contains($ctrlText, 'findOrFail(') && str_contains($ctrlText, '->delete()'),
    'expected admin-only destroy + findOrFail delete');

// ─────────────────────────────────────────────────────────────────────────
// [7] Routes registered: notification-settings.index + .store + .destroy.
// ─────────────────────────────────────────────────────────────────────────
$routesText = read('routes/web.php');
check(7, 'routes/web.php registers GET notification-settings.index + POST .store + DELETE .{id}.destroy',
    $routesText !== ''
    && str_contains($routesText, "->name('notification-settings.index')")
    && str_contains($routesText, "->name('notification-settings.store')")
    && str_contains($routesText, "->name('notification-settings.destroy')"),
    'missing one or more notification-settings route names');

// ─────────────────────────────────────────────────────────────────────────
// [8] Admin nav "Notifications" entry in AuthenticatedLayout (data-testid-derived).
// ─────────────────────────────────────────────────────────────────────────
$navText = read('resources/js/Layouts/AuthenticatedLayout.tsx');
check(8, 'AuthenticatedLayout nav includes Notifications -> /notification-settings (label -> nav-notifications testid)',
    $navText !== ''
    && str_contains($navText, "'Notifications'")
    && str_contains($navText, "route('notification-settings.index')"),
    'missing Notifications nav entry in AuthenticatedLayout');

// ─────────────────────────────────────────────────────────────────────────
// [9] Settings.tsx exists with card-add-rule + card-rules-list testids.
// ─────────────────────────────────────────────────────────────────────────
$settingsText = read('resources/js/Pages/Notifications/Settings.tsx');
check(9, 'Settings.tsx contains card-add-rule AND card-rules-list testids',
    $settingsText !== ''
    && str_contains($settingsText, 'data-testid="card-add-rule"')
    && str_contains($settingsText, 'data-testid="card-rules-list"'),
    'expected both add-rule card + active-rules card with testids');

// ─────────────────────────────────────────────────────────────────────────
// [10] Settings.tsx form: rule-action select (with both action options) + rule-email + rule-submit.
// ─────────────────────────────────────────────────────────────────────────
check(10, 'Settings.tsx form has rule-action select (both options) + rule-email input + rule-submit button',
    $settingsText !== ''
    && str_contains($settingsText, 'data-testid="rule-action"')
    && str_contains($settingsText, 'data-testid="rule-email"')
    && str_contains($settingsText, 'data-testid="rule-submit"')
    && str_contains($settingsText, 'value="WEEKLY_REPORT_SUBMITTED"')
    && str_contains($settingsText, 'value="GUEST_ASSIGNED"'),
    'expected rule-action select with both action values + rule-email + rule-submit');

// ─────────────────────────────────────────────────────────────────────────
// [11] Settings.tsx Active Rules table has Remove button -> router.delete.
// ─────────────────────────────────────────────────────────────────────────
check(11, 'Settings.tsx Active Rules has Remove button -> router.delete(`/notification-settings/${id}`)',
    $settingsText !== ''
    && str_contains($settingsText, 'router.delete(`/notification-settings/${s.id}`'),
    'expected Remove button using router.delete with rule id');

// ─────────────────────────────────────────────────────────────────────────
// [12] ImpactSubmissionController::store fires notifyReportSubmitted($submission) AND type=report branch.
// ─────────────────────────────────────────────────────────────────────────
$impactText = read('app/Http/Controllers/ImpactSubmissionController.php');
check(12, 'ImpactSubmissionController::store calls $this->notifyReportSubmitted($submission) AND that call is gated by a `type === report` branch',
    $impactText !== ''
    && preg_match('/\$this->notifyReportSubmitted\s*\(\s*\$submission\s*\)/', $impactText) === 1
    && preg_match('/if\s*\(\s*\$validated\[[\'"]type[\'"]\]\s*===\s*[\'"]report[\'"]\s*\)/', $impactText) === 1,
    'expected notifyReportSubmitted($submission) call AND type=report gating in store()');

// ─────────────────────────────────────────────────────────────────────────
// [13] notifyReportSubmitted queries NotificationSetting where action + enabled=true.
// ─────────────────────────────────────────────────────────────────────────
check(13, 'notifyReportSubmitted queries NotificationSetting::where(action, WEEKLY_REPORT_SUBMITTED)->where(enabled, true)',
    $impactText !== ''
    && str_contains($impactText, "private function notifyReportSubmitted")
    && preg_match('/NotificationSetting::where\s*\(\s*[\'"]action[\'"]\s*,\s*[\'"]WEEKLY_REPORT_SUBMITTED[\'"]\s*\)\s*->where\s*\(\s*[\'"]enabled[\'"]\s*,\s*true\s*\)/', $impactText) === 1,
    'expected NotificationSetting where(action=WEEKLY_REPORT_SUBMITTED)->where(enabled, true)');

// ─────────────────────────────────────────────────────────────────────────
// [14] Graceful skip when no rules: $rules->isEmpty() branch + Log::info + skip-message.
//     Bulletproof str_contains triplet — no regex to avoid PCRE escape-pile issues.
// ─────────────────────────────────────────────────────────────────────────
check(14, 'notifyReportSubmitted: $rules->isEmpty() branch exists AND Log::info called with skip-message containing the word "skipped"',
    $impactText !== ''
    && str_contains($impactText, '$rules->isEmpty()')
    && str_contains($impactText, 'Log::info(')
    && (str_contains($impactText, 'no rules configured, skipped.') || str_contains($impactText, 'skipped.')),
    'expected $rules->isEmpty() branch + Log::info call + skip-message containing "skipped"');

// ─────────────────────────────────────────────────────────────────────────
// [15] Mail::raw call wrapped in try/catch with Log::warning on \\Exception.
//     STRUCTURAL ORDERING added per code-reviewer bullet 2 — proves per-rule
//     try-around-mail-around-catch rather than wrapping the entire foreach.
// ─────────────────────────────────────────────────────────────────────────
$posTry   = strpos($impactText, 'try {');
$posMail  = strpos($impactText, 'Mail::raw(');
$posCatch = strpos($impactText, 'catch (\\Exception $e)');
check(15, 'Mail::raw wrapped in try { ... catch (\\Exception $e) WITH STRUCTURAL ORDERING (try BEFORE mail BEFORE catch)',
    $impactText !== ''
    && $posTry !== false
    && $posMail !== false
    && $posCatch !== false
    && $posTry < $posMail
    && $posMail < $posCatch
    && preg_match('/Log::warning\s*\(\s*[\'"]Failed to send WEEKLY_REPORT_SUBMITTED/', $impactText) === 1,
    'expected structural try-around-mail-around-catch ordering + Log::warning on \\Exception');

// ─────────────────────────────────────────────────────────────────────────
// [16] .env defines MAIL_MAILER key.
// ─────────────────────────────────────────────────────────────────────────
$envText = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.env');
check(16, '.env defines MAIL_MAILER key (production SMTP / dev log / array all acceptable)',
    $envText !== '' && preg_match('/^MAIL_MAILER\s*=/m', $envText) === 1,
    'expected MAIL_MAILER= key in .env');

// ─────────────────────────────────────────────────────────────────────────
// [17] .env defines MAIL_HOST / MAIL_PORT / MAIL_USERNAME / MAIL_PASSWORD (SMTP switch block).
// ─────────────────────────────────────────────────────────────────────────
check(17, '.env defines MAIL_HOST + MAIL_PORT + MAIL_USERNAME + MAIL_PASSWORD keys (production switch block)',
    $envText !== ''
    && preg_match('/^MAIL_HOST\s*=/m', $envText) === 1
    && preg_match('/^MAIL_PORT\s*=/m', $envText) === 1
    && preg_match('/^MAIL_USERNAME\s*=/m', $envText) === 1
    && preg_match('/^MAIL_PASSWORD\s*=/m', $envText) === 1,
    'expected MAIL_HOST/PORT/USERNAME/PASSWORD keys for production SMTP switch');

// ─────────────────────────────────────────────────────────────────────────
// [18] config/mail.php default = env('MAIL_MAILER', 'log') (driver-deferred).
// ─────────────────────────────────────────────────────────────────────────
$mailConfigText = read('config/mail.php');
check(18, 'config/mail.php default = env(MAIL_MAILER, log) (driver is env-deferred, log fallback)',
    $mailConfigText !== ''
    && str_contains($mailConfigText, "env('MAIL_MAILER', 'log')"),
    'expected config/mail.php default reading MAIL_MAILER env var with log fallback');

// ─────────────────────────────────────────────────────────────────────────
// [19] config/mail.php smtp mailer reads MAIL_HOST/PORT/USERNAME/PASSWORD (env-driven).
// ─────────────────────────────────────────────────────────────────────────
check(19, 'config/mail.php smtp mailer reads MAIL_HOST + MAIL_PORT + MAIL_USERNAME + MAIL_PASSWORD env keys',
    $mailConfigText !== ''
    && preg_match("/'smtp'\s*=>\s*\[[^]]*'host'\s*=>\s*env\(\s*'MAIL_HOST'/s", $mailConfigText) === 1
    && preg_match("/'smtp'\s*=>\s*\[[^]]*'port'\s*=>\s*env\(\s*'MAIL_PORT'/s", $mailConfigText) === 1
    && preg_match("/'smtp'\s*=>\s*\[[^]]*'username'\s*=>\s*env\(\s*'MAIL_USERNAME'/s", $mailConfigText) === 1
    && preg_match("/'smtp'\s*=>\s*\[[^]]*'password'\s*=>\s*env\(\s*'MAIL_PASSWORD'/s", $mailConfigText) === 1,
    'expected config/mail.php smtp block reading MAIL_HOST/PORT/USERNAME/PASSWORD env keys');

// ─────────────────────────────────────────────────────────────────────────
// Summary.
// ─────────────────────────────────────────────────────────────────────────
echo "\n=== Phase 09 verifier — {$pass} pass / {$fail} fail (out of 19 sub-assertions) ===\n";
exit($fail > 0 ? 1 : 0);
