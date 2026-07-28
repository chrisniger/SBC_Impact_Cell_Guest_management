<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 06e+1 — record authenticated `last_seen_at` without DB thrash.
 *
 * Runs as part of the `web` middleware group (see bootstrap/app.php).
 * Behaviour:
 *
 *   - Skipped entirely for unauthenticated requests (no DB write).
 *   - Skipped if a cache key for this user has been written within the
 *     last 5 minutes — prevents dozens of writes per minute on chatty
 *     Inertia interactions. The DatabaseRecordLastSeenCache stores the
 *     throttle marker in the default cache store, falling back to a
 *     simple in-memory flag if Cache is misconfigured (defensive).
 *   - Touches `last_seen_at` only when the user is dirty, so a single
 *     touch covers many requests and DB-only-fresh reads stay cheap.
 *
 * Skipped during `php artisan migrate`, PHPUnit, and console commands
 * so seeding + testing don't generate phantom activity timestamps.
 */
class RecordLastSeen
{
    /** Throttle window — once per 5 minutes per user. */
    private const THROTTLE_SECONDS = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip when there's no user (e.g. login flow), or during console
        // invocation (artisan migrate / tinker / db:seed would otherwise
        // pollute the timestamps).
        if ($user === null || app()->runningInConsole()) {
            return $next($request);
        }

        // Per-user throttle guard — only touch DB once per 5 minutes.
        // `Cache::add` is atomic: returns true if the key didn't exist,
        // false if another request beat us to it.
        $cacheKey = "last_seen:{$user->id}";
        $isFreshSlot = Cache::add($cacheKey, true, self::THROTTLE_SECONDS);

        if ($isFreshSlot) {
            // Use the raw DB query builder so we don't double-cast or
            // trigger the model's saved-event observers (MassAssignment
            // isn't relevant here; only one column is being touched).
            DB::table('users')
                ->where('id', $user->id)
                ->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}
