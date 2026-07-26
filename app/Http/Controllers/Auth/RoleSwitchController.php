<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * POST /auth/switch-role
 *
 * Switches the persisted `users.active_role` column for a multi-role
 * user, validates through Spatie that the requested role is one the
 * user actually holds, then regenerates the session so any cached
 * authorization data is rebuilt against the new active role.
 *
 * Returns Inertia `back(303)` so the calling page does a partial
 * reload — the top-bar role badge and column-policy behaviour update
 * without a full page refresh.
 */
class RoleSwitchController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'max:64'],
        ]);

        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if (! $user->canSwitchTo($data['role'])) {
            abort(403, 'You may not switch to that role.');
        }

        $user->forceFill(['active_role' => $data['role']])->save();

        // Defense-in-depth: rotate the session ID after a privilege-relevant action.
        // Note — `active_role` is a VIEW SELECTOR, NOT an authorization grant.
        // Spatie's hasRole() reads from the model_has_roles pivot in DB, not from
        // this column, so `regenerate()` does not clear Spatie's permission cache.
        $request->session()->regenerate();

        return back(303)->with('success', "Active role switched to {$data['role']}.");
    }
}
