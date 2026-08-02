<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BackupService;
use App\Support\EnvWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Phase 33 — Admin Settings page (SMTP configuration + Backup & Restore).
 *
 * Administrator-only (mirrors NotificationSettingsController's gate:
 * `abort_unless(activeRole === 'Administrator', 403)`).
 *
 * SMTP: the form writes MAIL_* keys directly into `.env` via EnvWriter
 * (per the "Save to .env file" design decision) and clears the config
 * cache so the mail system picks the values up immediately. The test
 * email endpoint applies the submitted values on the fly so an admin can
 * verify a candidate config BEFORE saving it.
 *
 * Backup: JSON download per scope (full / impact_cell / follow_up_officer
 * / follow_up_team). Restore: only a FULL backup may be uploaded — it
 * wipes and re-inserts business tables inside one transaction.
 */
class SettingsController extends Controller
{
    /**
     * GET /admin/settings — render the Settings page with the current
     * SMTP values (password masked) + mail-configured status.
     */
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        return Inertia::render('Admin/Settings/Index', [
            'smtp' => [
                'mailer'       => config('mail.default', 'log'),
                'host'         => config('mail.mailers.smtp.host', ''),
                'port'         => (string) config('mail.mailers.smtp.port', ''),
                'username'     => (string) config('mail.mailers.smtp.username', ''),
                'password_set' => ! empty(config('mail.mailers.smtp.password')),
                'scheme'       => config('mail.mailers.smtp.scheme') ?? '',
                'from_address' => (string) config('mail.from.address', ''),
                'from_name'    => (string) config('mail.from.name', ''),
            ],
            'mailConfigured' => $this->isMailConfigured(),
            'backupScopes'   => BackupService::SCOPES,
        ]);
    }

    /**
     * POST /admin/settings/smtp — persist SMTP settings into `.env`.
     */
    public function storeSmtp(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'mailer'       => ['required', 'string', 'in:smtp,log'],
            // Reviewer catch: a half-filled SMTP form must not nuke a working
            // config — host/port are mandatory the moment SMTP is selected.
            'host'         => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port'         => ['required_if:mailer,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'scheme'       => ['nullable', 'string', 'in:,tls,ssl'],
            'from_address' => ['required', 'email'],
            'from_name'    => ['nullable', 'string', 'max:255'],
        ]);

        try {
            // Path is injectable via config for tests (temp file); defaults to .env.
            $env = new EnvWriter(config('settings.env_path', base_path('.env')));

            $env->set([
                'MAIL_MAILER'       => $data['mailer'],
                'MAIL_HOST'         => $data['host'] ?? '',
                'MAIL_PORT'         => $data['port'] !== null && $data['port'] !== ''
                    ? (string) $data['port']
                    : '',
                'MAIL_USERNAME'     => $data['username'] ?? '',
                'MAIL_SCHEME'       => ($data['scheme'] ?? '') === '' ? null : $data['scheme'],
                'MAIL_FROM_ADDRESS' => $data['from_address'],
                'MAIL_FROM_NAME'    => $data['from_name'] ?? '',
            ]);

            // Password is only written when a new value is supplied —
            // a blank field keeps the existing credential untouched.
            if (! empty($data['password'])) {
                $env->set(['MAIL_PASSWORD' => $data['password']]);
            }

            // Flush the compiled config so the NEXT request re-reads .env.
            Artisan::call('config:clear');

            // ALSO refresh the in-memory config so the CURRENT request's
            // mail system uses the just-saved values immediately (e.g. a
            // follow-up Mail:: send, or isMailConfigured()/index() on the
            // redirect target) instead of waiting for the next request.
            $this->applySmtpConfig($data);

            return back()->with('success', 'SMTP settings saved and mail configuration refreshed.');
        } catch (Throwable $e) {
            Log::error('SMTP settings save failed: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'mailer' => 'Failed to save SMTP settings: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /admin/settings/smtp/test — send a test email using the
     * submitted values WITHOUT persisting them. Lets the admin verify a
     * candidate config before saving.
     */
    public function testEmail(Request $request): JsonResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'mailer'       => ['required', 'string', 'in:smtp,log'],
            'host'         => ['required_if:mailer,smtp', 'nullable', 'string', 'max:255'],
            'port'         => ['required_if:mailer,smtp', 'nullable', 'integer', 'min:1', 'max:65535'],
            'username'     => ['nullable', 'string', 'max:255'],
            'password'     => ['nullable', 'string', 'max:255'],
            'scheme'       => ['nullable', 'string', 'in:,tls,ssl'],
            'from_address' => ['required', 'email'],
            'from_name'    => ['nullable', 'string', 'max:255'],
            'recipient'    => ['required', 'email'],
        ]);

        // Apply the candidate values for THIS request only.
        $this->applySmtpConfig($data);

        try {
            Mail::raw(
                "This is a test email from the Impact Cell | Guest Portal.\n\n" .
                "If you received this, your SMTP configuration is working correctly.\n\n" .
                'Sent at: ' . now()->toIso8601String() . "\n" .
                'Mail driver: ' . $data['mailer'] . "\n",
                function ($message) use ($data) {
                    $message->to($data['recipient'])
                        ->subject('Impact Portal — SMTP Test Email');
                }
            );

            return response()->json([
                'sent'    => true,
                'message' => "Test email sent to {$data['recipient']} using the entered settings.",
            ]);
        } catch (Throwable $e) {
            Log::warning('SMTP test email failed (recipient: ' . $data['recipient'] . '): ' . $e->getMessage());

            return response()->json([
                'sent'    => false,
                'message' => 'Failed to send test email: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /admin/settings/backup?scope=full|impact_cell|follow_up_officer|follow_up_team
     * — stream a JSON download of the requested scope.
     */
    public function backup(Request $request): StreamedResponse
    {
        $this->authorizeAdmin($request);

        $scope = (string) $request->query('scope', BackupService::SCOPE_FULL);
        // Validate the scope BEFORE streaming — abort() inside the stream
        // closure would surface as a 500 StreamedResponseException.
        abort_unless(in_array($scope, BackupService::SCOPES, true), 400);
        $service = new BackupService();

        return response()->streamDownload(function () use ($service, $scope) {
            echo json_encode($service->export($scope), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, sprintf('impact-portal-backup-%s-%s.json', $scope, now()->format('Y-m-d-His')), [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * POST /admin/settings/restore — upload a FULL backup archive and
     * restore all business tables inside one transaction.
     */
    public function restore(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $request->validate([
            'backup_file' => ['required', 'file', 'mimes:json,text/plain', 'max:51200'],
        ]);

        $raw = file_get_contents($request->file('backup_file')->getRealPath());
        $payload = json_decode($raw, true);

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'backup_file' => 'The uploaded file is not valid JSON.',
            ]);
        }

        try {
            (new BackupService())->restore($payload);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error('Backup restore failed: ' . $e->getMessage());
            throw ValidationException::withMessages([
                'backup_file' => 'Restore failed: ' . $e->getMessage(),
            ]);
        }

        $message = 'Backup restored — all business data was replaced from the archive.';

        // The Settings page submits restore via fetch() with Accept:
        // application/json; return JSON there (a 302 redirect would be
        // followed to the HTML page and res.json() would fail). Regular
        // form posts get the normal Inertia redirect + flash.
        if ($request->wantsJson()) {
            return response()->json(['message' => $message, 'restored' => true]);
        }

        return back()->with('success', $message);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Apply validated SMTP form values to the in-memory config repository.
     *
     * Shared by BOTH `storeSmtp()` (after .env write + config:clear, so the
     * current request sees the new values immediately) and `testEmail()`
     * (candidate verification WITHOUT persistence). A blank password leaves
     * the existing credential in place — same rule as the .env writer.
     */
    private function applySmtpConfig(array $data): void
    {
        config([
            'mail.default'               => $data['mailer'],
            'mail.mailers.smtp.host'     => $data['host'] ?? '',
            'mail.mailers.smtp.port'     => $data['port'] !== null && $data['port'] !== '' ? (int) $data['port'] : 587,
            'mail.mailers.smtp.username' => $data['username'] ?? '',
            'mail.mailers.smtp.scheme'   => ($data['scheme'] ?? '') === '' ? null : $data['scheme'],
            'mail.from.address'          => $data['from_address'],
            'mail.from.name'             => $data['from_name'] ?? '',
        ]);
        // Blank password = keep existing (same rule as the .env writer) —
        // the key is simply left untouched so both layers stay in sync.
        if (! empty($data['password'])) {
            config(['mail.mailers.smtp.password' => $data['password']]);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);
    }

    /**
     * True when the mail driver is `smtp` AND the four required SMTP
     * keys are populated. Drives the ✓/⚠ badge in the Settings UI.
     *
     * NOTE: Laravel nests SMTP values under `mail.mailers.smtp.*` —
     * `config('mail.host')` is always null and would make this method
     * perpetually return false (the old NotificationSettingsController
     * had that latent bug; this implementation uses the correct keys).
     */
    private function isMailConfigured(): bool
    {
        if (config('mail.default') !== 'smtp') {
            return false;
        }
        foreach (['mail.mailers.smtp.host', 'mail.mailers.smtp.port', 'mail.mailers.smtp.username', 'mail.mailers.smtp.password'] as $key) {
            if (empty(config($key))) {
                return false;
            }
        }

        return true;
    }
}
