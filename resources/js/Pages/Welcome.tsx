import { PageProps } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Shared button primitives (2026-08-03 mobile pass).
 *
 * Mobile-first sizing per the responsive spec:
 *   - height 40px (py-2.5 + text-sm line-height), horizontal padding 16–20px
 *   - border-radius 12px (rounded-xl)
 *   - font-size 14px (text-sm), whitespace-nowrap so labels never wrap/overflow
 *   - icons aligned via inline-flex + items-center + gap
 */
const btnBase =
    'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-xl text-sm font-semibold transition-all focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:focus-visible:ring-offset-gray-950';

const btnFilled = `${btnBase} bg-indigo-600 px-4 py-2.5 text-white shadow-sm hover:bg-indigo-700 hover:shadow-md`;

const btnFilledHero = `${btnBase} bg-indigo-600 px-5 py-2.5 text-white shadow-md hover:bg-indigo-700 hover:shadow-lg`;

const btnOutline = `${btnBase} border border-gray-300 bg-white/60 px-4 py-2.5 text-gray-700 hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-600 dark:bg-transparent dark:text-gray-200 dark:hover:border-indigo-400 dark:hover:text-indigo-300`;

const btnOutlineHero = `${btnBase} border border-gray-300 bg-white px-5 py-2.5 text-gray-700 hover:border-indigo-300 hover:text-indigo-600 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-500 dark:hover:text-indigo-400`;

/** Small inline arrow used by primary CTAs (aligned with button text). */
const ArrowIcon = ({ className = 'h-3.5 w-3.5' }: { className?: string }) => (
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2.5"
        strokeLinecap="round"
        strokeLinejoin="round"
        className={className}
        aria-hidden="true"
    >
        <path d="M5 12h14m-7-7 7 7-7 7" />
    </svg>
);

const HamburgerIcon = () => (
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        className="h-5 w-5"
        aria-hidden="true"
    >
        <path d="M4 6h16M4 12h16M4 18h16" />
    </svg>
);

const CloseIcon = () => (
    <svg
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        strokeWidth="2"
        strokeLinecap="round"
        className="h-5 w-5"
        aria-hidden="true"
    >
        <path d="M6 6l12 12M18 6L6 18" />
    </svg>
);

export default function Welcome({
    auth,
}: PageProps<Record<string, never>>) {
    const [menuOpen, setMenuOpen] = useState(false);

    // Close the mobile menu with Escape (focus may be anywhere in the panel).
    useEffect(() => {
        if (!menuOpen) return;
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setMenuOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [menuOpen]);

    return (
        <>
            <Head title="Welcome" />
            <div className="relative flex min-h-screen flex-col overflow-x-clip bg-gradient-to-br from-slate-50 via-white to-indigo-50/40 text-gray-900 dark:from-gray-950 dark:via-gray-900 dark:to-indigo-950/40 dark:text-white">
                {/* Background glow orbs + SVG grid (own overflow-hidden layer so
                    the decorative shapes never cause horizontal scroll) */}
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 overflow-hidden"
                >
                    <div className="absolute -left-32 top-1/4 h-96 w-96 rounded-full bg-indigo-300/30 blur-3xl motion-safe:animate-[glow_14s_ease-in-out_infinite] dark:bg-indigo-500/20" />
                    <div
                        aria-hidden="true"
                        className="absolute right-0 top-3/4 h-96 w-96 rounded-full bg-violet-300/30 blur-3xl motion-safe:animate-[glow_18s_ease-in-out_infinite_2s] dark:bg-violet-500/15"
                    />
                    <svg
                        className="absolute inset-0 h-full w-full opacity-[0.04] dark:opacity-[0.06]"
                        xmlns="http://www.w3.org/2000/svg"
                        aria-hidden="true"
                    >
                        <defs>
                            <pattern
                                id="welcome-grid"
                                width="40"
                                height="40"
                                patternUnits="userSpaceOnUse"
                            >
                                <path
                                    d="M 40 0 L 0 0 0 40"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1"
                                />
                            </pattern>
                        </defs>
                        <rect
                            width="100%"
                            height="100%"
                            fill="url(#welcome-grid)"
                        />
                    </svg>
                </div>

                {/* Keyframes for the glow orbs (must live on the page since
                    Welcome does NOT use GuestLayout). */}
                <style>{`
                    @keyframes glow {
                        0%, 100% { opacity: 0.55; transform: scale(1); }
                        50%      { opacity: 0.95; transform: scale(1.06); }
                    }
                `}</style>

                {/* ── Header: sticky, single row, collapses to hamburger < md ── */}
                <header className="sticky top-0 z-50 border-b border-gray-200/60 bg-white/80 backdrop-blur dark:border-gray-800/60 dark:bg-gray-950/80">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between gap-2 px-4 sm:gap-3 sm:px-6">
                        {/* Logo + title */}
                        <Link
                            href="/"
                            onClick={() => setMenuOpen(false)}
                            className="flex min-w-0 items-center gap-2 transition-opacity hover:opacity-80 sm:gap-3"
                        >
                            <img
                                src="/logos/logo1.png"
                                alt="SBC"
                                className="h-8 w-auto shrink-0 rounded-lg object-contain sm:h-9"
                            />
                            <span className="flex min-w-0 flex-col leading-tight">
                                <span className="truncate text-[18px] font-bold tracking-tight">
                                    SBC Portal
                                </span>
                                <span className="hidden truncate text-[10px] uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400 sm:block">
                                    Impact Cell · Follow-up · Integration
                                </span>
                            </span>
                        </Link>

                        {/* Desktop navigation */}
                        <nav className="hidden items-center gap-2.5 md:flex">
                            {auth.user ? (
                                <Link href={route('dashboard')} className={btnFilled}>
                                    Dashboard
                                    <ArrowIcon />
                                </Link>
                            ) : (
                                <>
                                    <Link href={route('login')} className={btnOutline}>
                                        Sign in
                                    </Link>
                                    <Link href={route('register')} className={btnFilled}>
                                        Get started
                                        <ArrowIcon />
                                    </Link>
                                </>
                            )}
                        </nav>

                        {/* Mobile hamburger */}
                        <button
                            type="button"
                            onClick={() => setMenuOpen((o) => !o)}
                            aria-expanded={menuOpen}
                            aria-controls="welcome-mobile-nav"
                            aria-label={menuOpen ? 'Close menu' : 'Open menu'}
                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-gray-300 bg-white/60 text-gray-700 transition-colors hover:border-indigo-300 hover:text-indigo-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:border-gray-700 dark:bg-gray-900/60 dark:text-gray-200 dark:hover:border-indigo-500 dark:hover:text-indigo-300 dark:focus-visible:ring-offset-gray-950 md:hidden"
                        >
                            {menuOpen ? <CloseIcon /> : <HamburgerIcon />}
                        </button>
                    </div>

                    {/* Mobile menu panel */}
                    {menuOpen && (
                        <nav
                            id="welcome-mobile-nav"
                            aria-label="Mobile navigation"
                            className="border-t border-gray-200/60 bg-white/95 px-4 py-3 backdrop-blur dark:border-gray-800/60 dark:bg-gray-950/95 md:hidden"
                        >
                            <div className="flex flex-col gap-2">
                                {auth.user ? (
                                    <Link
                                        href={route('dashboard')}
                                        onClick={() => setMenuOpen(false)}
                                        className={`${btnFilled} w-full`}
                                    >
                                        Dashboard
                                        <ArrowIcon />
                                    </Link>
                                ) : (
                                    <>
                                        <Link
                                            href={route('register')}
                                            onClick={() => setMenuOpen(false)}
                                            className={`${btnFilled} w-full`}
                                        >
                                            Get started
                                            <ArrowIcon />
                                        </Link>
                                        <Link
                                            href={route('login')}
                                            onClick={() => setMenuOpen(false)}
                                            className={`${btnOutline} w-full`}
                                        >
                                            Sign in
                                        </Link>
                                    </>
                                )}
                            </div>
                        </nav>
                    )}
                </header>

                {/* ── Content ── */}
                <div className="relative mx-auto flex w-full max-w-7xl flex-1 flex-col px-4 sm:px-6">
                    {/* Hero */}
                    <main className="flex flex-1 items-center">
                        <div className="grid w-full grid-cols-1 items-center gap-10 py-10 lg:grid-cols-2 lg:gap-16 lg:py-12">
                            {/* Left column */}
                            <div className="space-y-8">
                                <div className="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50/80 px-3 py-1 text-xs font-medium text-indigo-700 backdrop-blur dark:border-indigo-500/30 dark:bg-indigo-500/10 dark:text-indigo-300">
                                    <span className="h-1.5 w-1.5 rounded-full bg-indigo-500 motion-safe:animate-pulse" />
                                    Impact Cell · Follow-up · Integration
                                </div>

                                <h1 className="text-4xl font-bold leading-tight tracking-tight sm:text-5xl lg:text-6xl">
                                    The Complete Guest &,{' '}
                                    <span className="bg-gradient-to-r from-indigo-600 via-violet-600 to-indigo-600 bg-clip-text text-transparent dark:from-indigo-400 dark:via-violet-400 dark:to-indigo-400">
                                        Impact Cell Management
                                    </span>{' '}
                                    Platform.
                                </h1>

                                <p className="max-w-xl text-base leading-relaxed text-gray-600 dark:text-gray-400 sm:text-lg">
                                    Manage guests, coordinate Impact Cells, 
                                    oversee Follow-Up Officers and Teams,
                                    and ensure every interaction is tracked —
                                    from first-time visitor to fully integrated church family.
                                    
                                </p>

                                <div className="flex flex-wrap items-center gap-3">
                                    {auth.user ? (
                                        <Link
                                            href={route('dashboard')}
                                            className={btnFilledHero}
                                        >
                                            Open dashboard
                                            <ArrowIcon className="h-4 w-4" />
                                        </Link>
                                    ) : (
                                        <>
                                            <Link
                                                href={route('register')}
                                                className={btnFilledHero}
                                            >
                                                Get started free
                                                <ArrowIcon className="h-4 w-4" />
                                            </Link>
                                            <Link
                                                href={route('login')}
                                                className={btnOutlineHero}
                                            >
                                                Sign in instead
                                            </Link>
                                        </>
                                    )}
                                </div>

                                {/* Trust strip */}
                                <dl className="flex flex-wrap items-center gap-x-6 gap-y-4 pt-2 text-sm text-gray-600 dark:text-gray-400 sm:gap-x-8">
                                    <div className="flex items-center gap-2">
                                        <svg
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            className="h-4 w-4 text-emerald-500"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        Role-scoped access
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <svg
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            className="h-4 w-4 text-emerald-500"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        Audit-logged activity
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <svg
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                            className="h-4 w-4 text-emerald-500"
                                            aria-hidden="true"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                        CSV import / export
                                    </div>
                                </dl>
                            </div>

                            {/* Right column — feature pillar */}
                            <div className="relative">
                                <div className="relative grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {[
                                        {
                                            title: 'Guest records',
                                            body: 'A unified guest profile with visit history, follow-up activity, and engagement tracking.',
                                            icon: (
                                                <path d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                                            ),
                                            tone: 'indigo',
                                        },
                                        {
                                            title: 'Follow-up Workflow',
                                            body: 'Coordinate every follow-up with automated schedules, reminders, and accountability.',
                                            icon: (
                                                <path d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                            ),
                                            tone: 'violet',
                                        },
                                        {
                                            title: 'Impact cells',
                                            body: 'Manage Impact Cells, leaders, and members with a structured, scalable organizational model.',
                                            icon: (
                                                <path d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.426.178-.845.497-1.148l3.006-3.006a1.62 1.62 0 0 1 2.291 0l3.006 3.006c.319.303.497.722.497 1.148V21" />
                                            ),
                                            tone: 'emerald',
                                        },
                                        {
                                            title: 'Insight & Analytics',
                                            body: 'Gain actionable insights with real-time dashboards, performance metrics, and comprehensive activity reports.',
                                            icon: (
                                                <path d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                                            ),
                                            tone: 'amber',
                                        },
                                    ].map((f) => {
                                        const toneRing: Record<string, string> = {
                                            indigo: 'ring-indigo-200 dark:ring-indigo-500/30',
                                            violet: 'ring-violet-200 dark:ring-violet-500/30',
                                            emerald:
                                                'ring-emerald-200 dark:ring-emerald-500/30',
                                            amber: 'ring-amber-200 dark:ring-amber-500/30',
                                        };
                                        const toneIcon: Record<string, string> = {
                                            indigo: 'text-indigo-600 dark:text-indigo-400 bg-indigo-50 dark:bg-indigo-500/10',
                                            violet: 'text-violet-600 dark:text-violet-400 bg-violet-50 dark:bg-violet-500/10',
                                            emerald:
                                                'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-500/10',
                                            amber: 'text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-500/10',
                                        };
                                        return (
                                            <div
                                                key={f.title}
                                                className={`group relative rounded-2xl border border-gray-200 bg-white/80 p-5 shadow-sm ring-1 backdrop-blur transition-all hover:-translate-y-0.5 hover:shadow-md ${toneRing[f.tone]} dark:border-gray-800 dark:bg-gray-900/70`}
                                            >
                                                <span
                                                    className={`mb-3 inline-flex h-10 w-10 items-center justify-center rounded-xl ${toneIcon[f.tone]}`}
                                                >
                                                    <svg
                                                        viewBox="0 0 24 24"
                                                        fill="none"
                                                        stroke="currentColor"
                                                        strokeWidth="1.75"
                                                        strokeLinecap="round"
                                                        strokeLinejoin="round"
                                                        className="h-5 w-5"
                                                        aria-hidden="true"
                                                    >
                                                        {f.icon}
                                                    </svg>
                                                </span>
                                                <h3 className="text-sm font-semibold text-gray-900 dark:text-white">
                                                    {f.title}
                                                </h3>
                                                <p className="mt-1 text-sm leading-relaxed text-gray-600 dark:text-gray-400">
                                                    {f.body}
                                                </p>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </main>

                    {/* Footer */}
                    <footer className="flex flex-col items-center justify-between gap-3 border-t border-gray-200/60 py-6 text-sm text-gray-500 dark:border-gray-800/60 dark:text-gray-400 sm:flex-row">
                        <p>
                            &copy; {new Date().getFullYear()} SBC Portal. All
                            rights reserved.
                        </p>
                        <p className="inline-flex items-center gap-1.5">
                            Built by
                            <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                                SBC Data
                            </span>
                            +
                            <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                                Administrator
                            </span>
                        </p>
                    </footer>
                </div>
            </div>
        </>
    );
}
