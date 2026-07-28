type Props = {
    fullName: string;
    activeRole: string | null;
};

/**
 * Phase 06d.0 — greeting block.
 *
 *  - Time-aware: Good morning (<12), afternoon (12-17), evening (>=17).
 *  - First name extract: splits full name on whitespace, capitalises first token.
 *  - Subtitle: active role + "Full System Access" badge when Administrator.
 */
export default function Greeting({ fullName, activeRole }: Props) {
    const hour = new Date().getHours();
    const partOfDay = hour < 12 ? 'morning' : hour < 17 ? 'afternoon' : 'evening';
    const firstName = (fullName || '').trim().split(/\s+/)[0] || 'there';

    return (
        <div data-testid="admin-greeting">
            <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                {`Good ${partOfDay},`}
            </p>
            <h1 className="mt-1 text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                {firstName}
            </h1>
            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Active role: <span className="font-mono text-gray-700 dark:text-gray-300">{activeRole ?? '—'}</span>
                {activeRole === 'Administrator' && (
                    <span className="ml-2 inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300">
                        Full System Access
                    </span>
                )}
            </p>
        </div>
    );
}
