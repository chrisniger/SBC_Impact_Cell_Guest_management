import { useEffect, useState } from 'react';

/**
 * Phase 06e — Theme Toggle (Light / Dark mode).
 *
 * Persists the user's choice to `localStorage['cgms.theme']` so the page
 * paints in the correct theme on next load. Updates `document.documentElement`
 * directly via `classList.toggle('dark', …)` — that's the class that
 * `tailwind.config.js` `darkMode: 'class'` is gated on, so every existing
 * `dark:bg-*` / `dark:text-*` utility immediately reflects the change.
 *
 * The corresponding anti-flash inline script lives at the top of
 * `resources/views/app.blade.php` and re-applies the same `.dark` class
 * before the browser paints on cold load — without it the user would
 * observe one frame of wrong theme before React hydrates.
 */
type Theme = 'light' | 'dark';

function readStoredTheme(): Theme {
    try {
        const stored = window.localStorage.getItem('cgms.theme');
        if (stored === 'light' || stored === 'dark') return stored;
    } catch (_) {
        /* localStorage may throw in private modes */
    }
    if (typeof window !== 'undefined' && window.matchMedia) {
        return window.matchMedia('(prefers-color-scheme: dark)').matches
            ? 'dark'
            : 'light';
    }
    return 'light';
}

function applyTheme(theme: Theme): void {
    if (typeof document === 'undefined') return;
    document.documentElement.classList.toggle('dark', theme === 'dark');
    try {
        window.localStorage.setItem('cgms.theme', theme);
    } catch (_) {
        /* ignore */
    }
}

export default function ThemeToggle() {
    const [theme, setTheme] = useState<Theme>('light');
    const [mounted, setMounted] = useState(false);

    // Hydrate the displayed theme from localStorage (or OS preference)
    // AFTER mount — prevents React from overwriting the anti-flash script
    // with a stale default on first hydration.
    useEffect(() => {
        setTheme(readStoredTheme());
        setMounted(true);
    }, []);

    const handleToggle = () => {
        const next: Theme = theme === 'dark' ? 'light' : 'dark';
        setTheme(next);
        applyTheme(next);
    };

    return (
        <button
            type="button"
            onClick={handleToggle}
            aria-label={
                mounted
                    ? `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`
                    : 'Toggle theme'
            }
            title={
                mounted
                    ? `Switch to ${theme === 'dark' ? 'light' : 'dark'} mode`
                    : 'Toggle theme'
            }
            // While `mounted` is false we still need a non-empty button so
            // the layout doesn't shift; render a neutral icon to avoid
            // hydration mismatch flicker.
            className="inline-flex items-center justify-center rounded-md border border-gray-200 bg-white/70 p-1.5 text-gray-700 transition-all hover:bg-gray-50 hover:shadow-card focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200 dark:hover:bg-gray-800"
            data-testid="theme-toggle"
            data-theme={mounted ? theme : 'light'}
        >
            {!mounted ? (
                // Server-rendered placeholder — neutral empty pill
                <span className="block h-4 w-4" aria-hidden="true" />
            ) : theme === 'dark' ? (
                // Sun icon — invites user back to light mode
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-4 w-4"
                    aria-hidden="true"
                >
                    <circle cx="12" cy="12" r="4" />
                    <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
                </svg>
            ) : (
                // Moon icon — invites user to dark mode
                <svg
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.6"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    className="h-4 w-4"
                    aria-hidden="true"
                >
                    <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
                </svg>
            )}
        </button>
    );
}
