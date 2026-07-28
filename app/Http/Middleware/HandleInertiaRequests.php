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
                    // Phase 05 — group key (impactCell / followUpOfficer / followUpTeam / null).
                    // Adds the 3-group classification to the frontend so AuthenticatedLayout
                    // can show a role-aware nav (Officer/Team/Cell groups see My Guests only;
                    // Administrator sees the fullset).
                    'activeGroup'      => $user->activeGroup(),
                    'roles'            => $user->getRoleNames()->all(),
                    'hasMultipleRoles' => $user->getRoleNames()->count() > 1,
                ],
            ],
            // Phase 06e+2 — route names whose sidebar entries should be
            // hidden in the current environment. Backend owns the gate
            // (GateStubPagesByEnvironment::GATED_ROUTES + REVEAL_ENVS);
            // this prop is the wire-format the AdminSidebar reads via
            // `usePage().props.gatedNavRoutes`. Empty list in reveal envs
            // (local/staging/testing) so verifiers see every entry.
            'gatedNavRoutes' => GateStubPagesByEnvironment::hiddenNavRouteNames(),
        ];
    }
}
