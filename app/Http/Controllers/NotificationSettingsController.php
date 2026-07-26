<?php

namespace App\Http\Controllers;

use App\Models\NotificationSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $role = $request->user()?->activeRole();
        abort_unless($role === 'Administrator', 403);

        return Inertia::render('Notifications/Settings', [
            'settings' => NotificationSetting::orderBy('action')->get(),
        ]);
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
}
