<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    /**
     * GET /notification-settings — Admin only. Phase 09b extended:
     * payload now includes `mailConfigured` bool (drives ✓/⚠ badge in Settings.tsx header).
     */
    public function index(Request $request): Response
    {
        $role = $request->user()?->activeRole();
        abort_unless($role === 'Administrator', 403);

        return Inertia::render('Notifications/Settings', [
            'settings'        => NotificationSetting::orderBy('action')->get(),
            'mailConfigured'  => $this->isMailConfigured(),
        ]);
    }

    /**
     * Phase 09b — true if mail driver is `smtp` AND all 4 required SMTP env keys are populated.
     * Renders the ✓ SMTP configured badge in Settings.tsx card header when true.
     *
     * Phase 33 follow-up — the keys are `mail.mailers.smtp.*` (Laravel nests
     * SMTP values there); the previous `mail.host` etc. were always null, so
     * the badge never turned green. Matches Admin\SettingsController::isMailConfigured().
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

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $validated = $request->validate([
            'action'          => ['required', 'string', 'in:WEEKLY_REPORT_SUBMITTED,GUEST_ASSIGNED'],
            'recipient_email' => ['required', 'email'],
            'enabled'         => ['boolean'],
        ]);

        NotificationSetting::updateOrCreate(
            ['action' => $validated['action'], 'recipient_email' => $validated['recipient_email']],
            ['enabled' => $validated['enabled'] ?? true],
        );

        return back()->with('success', 'Notification rule saved.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);
        NotificationSetting::findOrFail($id)->delete();
        return back()->with('success', 'Notification rule removed.');
    }

    /**
     * Phase 09b — POST /notification-settings/test-email : JsonResponse.
     *
     * Sends a one-off test email (subject + body + driver info) via the active mail driver.
     * Admin-only. Returns `{sent: bool, message: string}` so the Settings.tsx button can
     * surface the result inline (either as a flash toast or a per-rule success state).
     *
     * Recipient is `recipient_email` from the request body (defaults to `config('mail.from.address')`
     * if not provided) — the typical UX is to send a test to the same email as the rule being verified.
     */
    public function testEmail(Request $request): JsonResponse
    {
        abort_unless($request->user()?->activeRole() === 'Administrator', 403);

        $recipient = $request->input('recipient_email', config('mail.from.address'));
        $subject   = 'SBC Portal — Test Email';
        $body      = "This is a test email from the SBC Guest Management Portal.\n\n" .
                     "If you received this, your SMTP configuration is working correctly.\n\n" .
                     "Sent at: " . now()->toIso8601String() . "\n" .
                     "Mail driver: " . config('mail.default') . "\n";

        try {
            Mail::raw($body, function ($message) use ($recipient, $subject) {
                $message->to($recipient)->subject($subject);
            });
            return response()->json(['sent' => true, 'message' => "Test email sent to {$recipient}."]);
        } catch (\Exception $e) {
            Log::warning("Test email failed (recipient: {$recipient}): " . $e->getMessage());
            return response()->json(['sent' => false, 'message' => 'Failed to send test email: ' . $e->getMessage()], 500);
        }
    }
}
