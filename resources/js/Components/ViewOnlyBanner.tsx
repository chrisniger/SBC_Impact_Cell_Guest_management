interface ViewOnlyBannerProps {
    role: string | null;
}

export default function ViewOnlyBanner({ role }: ViewOnlyBannerProps) {
    if (role !== 'Follow_UP_View_Only') return null;

    return (
        <div className="mb-4 rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
            <div className="flex items-center gap-2">
                <svg className="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4.5c-.77-.833-2.694-.833-3.464 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                </svg>
                <span>
                    View-only mode. Your <span className="font-mono font-semibold">{role}</span> role
                    can browse data but cannot make changes. Ask a team member with edit permissions
                    to update records.
                </span>
            </div>
        </div>
    );
}
