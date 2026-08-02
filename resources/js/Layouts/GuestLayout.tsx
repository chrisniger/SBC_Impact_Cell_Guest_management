import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    const year = new Date().getFullYear();

    return (
        <div className="relative min-h-screen overflow-hidden bg-gradient-to-br from-slate-50 via-white to-indigo-50 dark:from-gray-950 dark:via-gray-900 dark:to-slate-950">
            <div className="grid min-h-screen lg:grid-cols-[5fr_4fr]">
                {/* ── Brand Panel (desktop only) ───────────────────────────── */}
                <aside className="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-600 via-blue-700 to-indigo-900 p-10 text-white lg:flex xl:p-14">
                    {/* Decorative animated glow orbs (staggered durations via
                        Tailwind 3 arbitrary animations — REPLACES the whole
                        `animate-pulse` so we can also tune the duration) */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -right-32 -top-32 h-96 w-96 rounded-full bg-white/10 blur-3xl animate-[pulse_8s_ease-in-out_infinite]"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -bottom-24 -left-24 h-80 w-80 rounded-full bg-blue-400/25 blur-3xl animate-[pulse_10s_ease-in-out_infinite]"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute right-1/3 top-1/2 h-64 w-64 rounded-full bg-indigo-400/15 blur-3xl animate-[pulse_12s_ease-in-out_infinite]"
                    />

                    {/* SVG grid pattern overlay (ID `brand-grid` — kept unique
                        to avoid collision with `form-dots` elsewhere) */}
                    <svg
                        aria-hidden
                        className="pointer-events-none absolute inset-0 h-full w-full opacity-[0.07] text-white"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <defs>
                            <pattern
                                id="brand-grid"
                                width="32"
                                height="32"
                                patternUnits="userSpaceOnUse"
                            >
                                <path
                                    d="M 32 0 L 0 0 0 32"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1"
                                />
                            </pattern>
                        </defs>
                        <rect
                            width="100%"
                            height="100%"
                            fill="url(#brand-grid)"
                        />
                    </svg>

                    {/* Right edge accent strip — gradient rail between panels */}
                    <div
                        aria-hidden
                        className="pointer-events-none absolute right-0 top-0 h-full w-px bg-gradient-to-b from-transparent via-white/30 to-transparent"
                    />

                    {/* Top: Brand */}
                    <div className="relative z-10 flex items-center justify-between">
                        <Link
                            href="/"
                            className="group inline-flex items-center gap-3 rounded-xl p-1 transition-opacity hover:opacity-95"
                        >
                            <img
                                src="/logos/logo1.png"
                                alt="SBC"
                                className="h-12 w-12 rounded-xl bg-white/95 p-1.5 shadow-lg ring-1 ring-white/20 transition-transform group-hover:scale-105"
                            />
                            <div className="flex flex-col leading-tight">
                                <span className="text-lg font-semibold tracking-tight">
                                    SBC Guest Portal
                                </span>
                                <span className="text-[11px] uppercase tracking-[0.18em] text-blue-100/70">
                                    Outreach · Follow-up · Integration
                                </span>
                            </div>
                        </Link>
                    </div>

                    {/* Center: Headline + features */}
                    <div className="relative z-10 max-w-lg space-y-7">
                        <div className="space-y-3">
                            <span className="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1 text-xs font-medium uppercase tracking-[0.15em] text-blue-100/90 ring-1 ring-white/15 backdrop-blur-sm">
                                <span className="h-1.5 w-1.5 rounded-full bg-emerald-300" />
                                Mission Hub
                            </span>
                            <h1 className="text-4xl font-bold leading-[1.1] tracking-tight xl:text-5xl">
                                Impact Cell Operations,
                                <br />
                                <span className="bg-gradient-to-r from-white via-blue-100 to-indigo-200 bg-clip-text text-transparent">
                                    Guest Management.
                                </span>
                            </h1>
                            <p className="text-base leading-relaxed text-blue-100/85 xl:text-lg">
                                One workspace for your Impact Cells, Follow-Up
                                team, and Leadership Board — track every
                                conversation, every visit, and every milestone.
                            </p>
                        </div>

                        <ul className="grid grid-cols-1 gap-3 pt-2 sm:grid-cols-3">
                            {[
                                {
                                    label: 'Impact Cells',
                                    desc: ' felloship',
                                    icon: (
                                        <path
                                            fillRule="evenodd"
                                            d="M8.25 6.75a3.75 3.75 0 1 1 7.5 0 3.75 3.75 0 0 1-7.5 0ZM15 11.25a3 3 0 1 1 6 0 3 3 0 0 1-6 0ZM1.5 19.125a7.125 7.125 0 0 1 14.25 0v.003l-.001.119a.75.75 0 0 1-.363.63 13.067 13.067 0 0 1-6.761 1.873c-2.472 0-4.786-.684-6.76-1.873a.75.75 0 0 1-.364-.63l-.001-.122ZM17.25 19.128l-.001.144a2.25 2.25 0 0 1-.233.96 10.088 10.088 0 0 0 5.06-1.01.75.75 0 0 0 .42-.643 4.875 4.875 0 0 0-6.957-4.611 8.586 8.586 0 0 1 1.71 5.16v.002Z"
                                            clipRule="evenodd"
                                        />
                                    ),
                                },
                                {
                                    label: 'Follow-Up',
                                    desc: 'Conversations',
                                    icon: (
                                        <path d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.249-.126.352a11.285 11.285 0 0 0 6.697 6.697c.103.038.25.009.352-.126l.97-1.293a1.875 1.875 0 0 1 1.955-.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22 1.5 14.948 1.5 6.75V4.5Z" />
                                    ),
                                },
                                {
                                    label: 'Leadership',
                                    desc: 'Insights',
                                    icon: (
                                        <path
                                            fillRule="evenodd"
                                            d="M10.788 3.21c.448-1.077 1.978-1.077 2.426 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006Z"
                                            clipRule="evenodd"
                                        />
                                    ),
                                },
                            ].map((f) => (
                                <li
                                    key={f.label}
                                    className="group flex flex-col gap-0.5 rounded-lg bg-white/10 px-3 py-2.5 ring-1 ring-white/15 backdrop-blur-sm transition hover:bg-white/15"
                                >
                                    <span className="inline-flex items-center gap-2 text-sm font-medium">
                                        <svg
                                            viewBox="0 0 24 24"
                                            fill="currentColor"
                                            className="h-4 w-4 text-blue-100"
                                            aria-hidden="true"
                                        >
                                            {f.icon}
                                        </svg>
                                        {f.label}
                                    </span>
                                    <span className="pl-6 text-[11px] uppercase tracking-wider text-blue-200/80">
                                        {f.desc}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {/* Bottom: Footer */}
                    <div className="relative z-10 flex items-center justify-between text-xs text-blue-100/70">
                        <span>© {year} SBC Portal</span>
                        <span className="inline-flex items-center gap-1.5 rounded-full bg-white/5 px-2.5 py-1 ring-1 ring-white/10 font-mono">
                            <span className="h-1 w-1 rounded-full bg-emerald-400" />
                            v1.0 · stable
                        </span>
                    </div>
                </aside>

                {/* ── Form Panel ───────────────────────────────────────────── */}
                <main className="relative flex flex-col bg-gradient-to-br from-white via-white to-indigo-50/30 dark:from-gray-900 dark:via-gray-950 dark:to-slate-950">
                    {/* Decorative dots pattern in the top-right corner — unique
                        ID `form-dots` to avoid collision with `brand-grid`. */}
                    <svg
                        aria-hidden
                        className="pointer-events-none absolute right-0 top-0 h-72 w-72 opacity-[0.05] text-gray-900 dark:text-white dark:opacity-[0.08]"
                        xmlns="http://www.w3.org/2000/svg"
                    >
                        <defs>
                            <pattern
                                id="form-dots"
                                width="20"
                                height="20"
                                patternUnits="userSpaceOnUse"
                            >
                                <circle
                                    cx="2"
                                    cy="2"
                                    r="1"
                                    fill="currentColor"
                                />
                            </pattern>
                        </defs>
                        <rect
                            width="100%"
                            height="100%"
                            fill="url(#form-dots)"
                        />
                    </svg>

                    {/* Mobile brand header */}
                    <header className="flex items-center justify-between border-b border-gray-200/60 bg-white/70 px-6 py-4 backdrop-blur-md lg:hidden dark:border-gray-800 dark:bg-gray-900/70">
                        <Link
                            href="/"
                            className="flex items-center gap-3"
                        >
                            <img
                                src="/logos/logo1.png"
                                alt="SBC"
                                className="h-9 w-9 rounded-lg object-contain ring-1 ring-gray-200 dark:ring-gray-700"
                            />
                            <div className="flex flex-col leading-tight">
                                <span className="text-sm font-semibold text-gray-900 dark:text-white">
                                    SBC Guest Portal
                                </span>
                                <span className="text-[10px] uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400">
                                    Mission Hub
                                </span>
                            </div>
                        </Link>
                    </header>

                    {/* Form area */}
                    <div className="flex flex-1 items-center justify-center px-6 py-10 sm:px-10 lg:py-16">
                        <div className="w-full max-w-md motion-safe:animate-fade-in">
                            {children}
                        </div>
                    </div>

                    {/* Mobile footer */}
                    <footer className="border-t border-gray-200/60 px-6 py-4 text-center text-xs text-gray-500 lg:hidden dark:border-gray-800 dark:text-gray-400">
                        © {year} SBC Guest Portal
                    </footer>
                </main>
            </div>            {/* Phase 06c — `@keyframes fadeIn` was DROPPED here. The keyframe
                + animation now live in `tailwind.config.js`
                (`theme.extend.keyframes.fadeIn` + `animation['fade-in']`),
                so `motion-safe:animate-fade-in` resolves globally for any
                auth-flow Page reachable through this layout. Phase 06b had
                kept the inline copy as a safety net for any page that still
                used `animate-[fadeIn_0.4s_ease-out]` arbitrary values; 06c
                refactored those pages, so the safety net is no longer
                necessary. The `pulse_*` decorative orbs above (lines ~17–25)
                still rely on their own inline keyframes — that's a separate
                Pulse/Orb concern and is intentionally untouched here. */}
        </div>
    );
}
