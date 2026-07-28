<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!--
            Phase 06e — anti-flash theme-detection script.
            MUST run BEFORE the Vite-emitted stylesheets paint, otherwise
            the page loads in light mode for one frame then flips to
            dark on hydration (FOUC).
            Reads the user's saved preference from `cgms.theme`; falls
            back to the OS prefers-color-scheme media query when nothing
            is stored. This script is intentionally tiny and synchronous —
            no `defer`/`async`, no module imports.

            NB: literal Vite-directive syntax (the at-sign followed by the
            directive name) MUST NOT appear inside Blade comments, even
            inside backticks — the Blade parser treats it as a callable
            directive and emits a malformed invocation. Use the prose form
            (e.g. "the Vite directive") in any docs that live in .blade.php.
        -->
        <script>
            (function () {
                try {
                    var stored = window.localStorage.getItem('cgms.theme');
                    var prefersDark = window.matchMedia &&
                        window.matchMedia('(prefers-color-scheme: dark)').matches;
                    var theme = stored || (prefersDark ? 'dark' : 'light');
                    if (theme === 'dark') {
                        document.documentElement.classList.add('dark');
                    } else {
                        document.documentElement.classList.remove('dark');
                    }
                } catch (_) { /* localStorage may throw in private modes */ }
            })();
        </script>

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.tsx', "resources/js/Pages/{$page['component']}.tsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
