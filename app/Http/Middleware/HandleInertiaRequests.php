<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    // Standard User fields (id, name, email, etc.)
                    ...$user->only(['id', 'name', 'email']),

                    // Phase 02 — role data for the top-bar role badge + switcher.
                    // These are the SINGLE SOURCE OF TRUTH — the frontend reads them
                    // via `usePage().props.auth.user.activeRole` / `roles` / `hasMultipleRoles`.
                    'activeRole'       => $user->activeRole(),
                    'roles'            => $user->getRoleNames()->all(),
                    'hasMultipleRoles' => $user->getRoleNames()->count() > 1,
                ],
            ],
        ];
    }
}