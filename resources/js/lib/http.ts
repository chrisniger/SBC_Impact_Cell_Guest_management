/**
 * resources/js/lib/http.ts — minimal helpers for calling plain-JSON
 * (non-Inertia) endpoints.
 *
 * Why this exists (2026-08-04): the Impact Status inline pill (and the
 * follow-up status controls) used Inertia's `router.patch` against
 * endpoints that return a plain `response()->json(...)`. Inertia requires
 * every response to be an Inertia response (X-Inertia header), so a plain
 * JSON payload surfaced the full-screen
 *   "All Inertia requests must receive a valid Inertia response"
 * error modal. The correct pattern for these lightweight JSON endpoints is
 * a plain `fetch` — matching the established app pattern (SoulSearch,
 * assigned rosters, admin settings test buttons).
 *
 * CSRF: Laravel web routes are CSRF-protected. The same-origin cookie
 * `XSRF-TOKEN` (URL-encoded, encrypted) is what Inertia's own axios
 * instance sends as the `X-XSRF-TOKEN` header — `readXsrfToken()` does the
 * same, with a `<meta name="csrf-token">` fallback for deployments that
 * provide one.
 */

/** Read the Laravel XSRF-TOKEN cookie (URL-decoded) for the X-XSRF-TOKEN header. */
export function readXsrfToken(): string {
    if (typeof document === 'undefined') return '';

    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (m && m[1]) {
        try {
            return decodeURIComponent(m[1]);
        } catch {
            return m[1];
        }
    }

    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    return meta?.content ?? '';
}

/**
 * Minimal JSON PATCH against a non-Inertia endpoint. Resolves with the
 * parsed JSON body; rejects on network error or non-2xx status.
 */
export async function patchJson<T = unknown>(
    url: string,
    payload: Record<string, unknown>,
): Promise<T> {
    const res = await fetch(url, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-XSRF-TOKEN': readXsrfToken(),
        },
        body: JSON.stringify(payload),
    });

    if (!res.ok) {
        throw new Error(`Request to ${url} failed with status ${res.status}`);
    }

    return res.json() as Promise<T>;
}
