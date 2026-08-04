<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 06e+2 — Gate admin stub pages behind local/staging environments.
 *
 * Background
 * ----------
 * The Admin sidebar nav has 5 entries that were Phase 06d.0 stubs:
 *
 *   - admin.submissions.index   (stub-with-link — links to /impact-submissions)
 *   - admin.users.index         (promoted to real CRUD in Phase 06e+1; gated
 *                                 here per explicit user selection to hide in prod)
 *   - admin.roles-permissions.index (full stub — Coming soon)
 *   - admin.messages.index      (full stub — Coming soon)
 *   - admin.analytics.index     (full stub — Coming soon)
 *
 * Of these, the 4 in `GATED_ROUTES` below are explicitly hidden in any
 * non-development environment so production admins never land on a page
 * that says "Coming soon" or shows infrastructure that hasn't shipped.
 * The dev/staging gate is intentional: verifiers (and engineers) still
 * need to surface the stubs locally for smoke-testing — they just
 * can't leak to the live deployment via stale cache hits or careless
 * URL crafting by an admin in the field.
 *
 * Behaviour
 * ---------
 *   - App env ∈ {local, staging}: passthrough (no redirect, full content).
 *   - App env ∉ {local, staging}: if the current route name is in
 *     `GATED_ROUTES`, redirect 302 to the dashboard. Otherwise passthrough.
 *
 * Adding new stubs? Append to `GATED_ROUTES` and add `->middleware('gate.stubs')`
 * to the new route — single-line edit. Removing gates? Trim the const.
 */
class GateStubPagesByEnvironment
{
    /**
     * Route names whose Inertia pages must NOT render in production.
     * Single source of truth — update + add the route-level middleware
     * via `->middleware('gate.stubs')` in routes/web.php for every entry.
     *
     * Phase 06e+1 + Phase 34 — all 4 previously-gated stub pages
     * (admin.users.index, admin.roles-permissions.index, admin.messages.index,
     * admin.analytics.index) are now real shipped pages and were removed
     * from this list. Keeping the const + middleware infrastructure intact
     * so future stubs can be gated with a single-line edit.
     */
    private const GATED_ROUTES = [];

    /**
     * Environments where stubs are still reachable.
     *
     * - `local`: developer machine + xampp "dev" vhost (running APP_ENV=local)
     * - `staging`: pre-production deploys (running APP_ENV=staging)
     * - `testing`: PHPUnit / CI runs (running APP_ENV=testing by default)
     *
     * Keeps automated tests that exercise gated routes from 302'ing to
     * /dashboard during CI. A test that *wants* to assert the production
     * behavior in CI sets `config([\u0027app.env\u0027 => \u0027production\u0027])` per-test.
     */
    private const REVEAL_ENVS = ['local', 'staging', 'testing'];

    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($routeName === null) {
            // Unnamed routes are never stubs — no gate applies.
            return $next($request);
        }

        if (! in_array($routeName, self::GATED_ROUTES, true)) {
            // Non-gated route — always render whatever it requests.
            return $next($request);
        }

        if (app()->environment(self::REVEAL_ENVS)) {
            // Reveal envs: render the stub as-is so verifiers + engineers
            // can still see, click, and assert against it.
            return $next($request);
        }

        // Production (or any other env): render a 404. Cleaner semantic
        // ("this route doesn't exist in production") than a 302 to a
        // route that may itself become gated in future. Decouples the
        // gate from any other route's liveness.
        //
        // 404 also keeps the exception handler's default behaviour: the
        // user sees the existing Inertia 404 page rather than silently
        // landing on /dashboard with no signal of what just happened.
        abort(404);
    }

    /**
     * Return the list of route names that the AdminSidebar should
     * HIDE in the current environment.
     *
     * - In a `REVEAL_ENVS` (local / staging / testing): empty list —
     *   no items hidden, the verifier + engineer see every entry.
     * - In any other env (production, etc.): returns `GATED_ROUTES`
     *   verbatim, so production admins never see a sidebar link that
     *   would 404 on click.
     *
     * Called from `HandleInertiaRequests::share()` to push the list
     * down to the React tree once per request; subsequent re-renders
     * of the sidebar consume it directly via `usePage().props`.
     *
     * Static (no instance) because the gate's only state lives in
     * `GATED_ROUTES` + `REVEAL_ENVS`; running this in a request scope
     * means `app()->environment()` read inside this method picks up
     * the live env, not some cached value from boot.
     */
    public static function hiddenNavRouteNames(): array
    {
        if (app()->environment(self::REVEAL_ENVS)) {
            return [];
        }

        return self::GATED_ROUTES;
    }
}
