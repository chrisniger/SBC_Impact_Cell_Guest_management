/**
 * Phase 35 — read-only notice banner.
 *
 * Shown to Impact_Cell_Admin users on the Impact Cells surface (Index +
 * Show pages), where they are view-only: they can read cells but cannot
 * add/edit/delete them. Renders an eye icon + "Read-only view" heading
 * with a per-page description so it's immediately obvious why no edit
 * affordances are present.
 *
 * Usage: gate the caller on `activeRole === 'Impact_Cell_Admin'`, then
 * render `<ReadOnlyBanner description="…" />`.
 */
export default function ReadOnlyBanner({ description, testId }: { description: string; testId: string }) {
    return (
        <div
            role="note"
            className="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-700/40 dark:bg-sky-900/20 dark:text-sky-200"
            data-testid={testId}
        >
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z" />
                <circle cx="12" cy="12" r="3" />
            </svg>
            <div>
                <p className="font-semibold">Read-only view</p>
                <p className="mt-0.5 text-xs leading-relaxed text-sky-700 dark:text-sky-300">{description}</p>
            </div>
        </div>
    );
}
