import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.tsx',
    ],

    // Phase 06b — Breeze toggles `.dark` on <html>. Align Tailwind's darkMode
    // with that strategy so future `dark:shadow-card-*` / `dark:bg-*-*` style
    // utilities don't silently gate on prefers-color-scheme instead.
    darkMode: 'class',

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // ----------------------------------------------------------------
            // Phase 06d.2 + 06e — brand palette tokens.
            //
            // AdminSidebar references `bg-brand-red`, `bg-brand-red-soft`,
            // `text-brand-red` directly; without these keys Tailwind emits
            // those class names verbatim as CSS class names that don't exist,
            // and the active-state highlight silently fell back to default
            // (no red bar). Centralising the palette here means future role
            // dashboards inherit the same chrome without re-duplicating hex
            // values in every component.
            //
            // Mapping:  brand-red       → rose-600  (primary brand)
            //           brand-red-soft  → rose-50   (active-state wash)
            //           brand-purple-soft → indigo-50 (kpi-card wash)
            // ----------------------------------------------------------------
            colors: {
                brand: {
                    red:        '#e11d48', // rose-600
                    'red-soft': '#fff1f2', // rose-50
                },
            },
            // ----------------------------------------------------------------
            // Phase 06b — design tokens.
            // Source of truth: Implementation/Phase_06b-06c_UI_Polish.md §1 + §2.1.
            //
            // Shadow values are wrapped in CSS-variable fallbacks so app.css
            // can flip them for `.dark` mode without changing the class names:
            //   :root      { --shadow-card-default: rgba(0,0,0,0.03); … }
            //   .dark      { --shadow-card-default: rgba(0,0,0,0.6);  … }
            // Without the fallback (e.g. before app.css compiles) we still
            // get a sensible light-mode shadow so dev works without the swap.
            // ----------------------------------------------------------------
            boxShadow: {
                card: '0 4px 20px var(--shadow-card-default, rgba(0,0,0,0.03))',
                'card-hover': '0 8px 30px var(--shadow-card-hover-default, rgba(0,0,0,0.06))',
            },
            keyframes: {
                fadeIn: {
                    '0%':   { opacity: '0', transform: 'translateY(6px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
            },
            animation: {
                // Always pair with motion-safe: prefix in markup (WCAG 2.3.3).
                'fade-in': 'fadeIn 0.4s ease-out',
            },
        },
    },

    plugins: [forms],
};
