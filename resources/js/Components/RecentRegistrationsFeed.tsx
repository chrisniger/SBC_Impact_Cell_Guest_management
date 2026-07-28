import { Link } from '@inertiajs/react';
import RelativeTime from '@/Components/RelativeTime';

/**
 * Phase 06d.2 — Recent Registrations Feed.
 *
 * 3 latest items (mixed from guests / users / submissions), sorted by
 * createdAt desc server-side. Each item renders with avatar initials,
 * label, subtitle, and a relative-time badge via RelativeTime.
 *
 * data-testid anchors: card-{type-id} on each card.
 */

export type RegistrationItem = {
    id: string;            // e.g. 'guest-123' (composite for stable key)
    label: string;         // guest_name | user name | submission preview
    subtitle: string;      // phone | email | cell name
    href: string;
    initials: string;      // 2-char uppercase
    color: 'indigo' | 'emerald' | 'amber';
    createdAt: string | null;
};

const COLOR_BG: Record<RegistrationItem['color'], string> = {
    indigo:  'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-600 dark:text-indigo-300',
    emerald: 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-600 dark:text-emerald-300',
    amber:   'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-300',
};

/**
 * Pure initials helper — exported for the verifier and the controller-side
 * `buildRecentRegistrations` mirror so the JS fallback matches the
 * server-side computation.
 */
export function getInitials(name: string | null | undefined): string {
    if (!name) return '?';
    const parts = name.trim().split(/\s+/);
    if (parts.length >= 2) {
        return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return parts[0].slice(0, 2).toUpperCase();
}

export default function RecentRegistrationsFeed({ items }: { items: RegistrationItem[] }) {
    return (
        <section className="motion-safe:animate-fade-in" data-testid="recent-registrations-feed">
            <h3 className="mb-3 text-base font-semibold text-gray-900 dark:text-white">Recent Registrations</h3>
            <div
                className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3"
                data-testid="recent-registrations-cards"
            >
                {items.length === 0 ? (
                    <div
                        className="col-span-full rounded-xl border border-dashed border-gray-300 bg-white p-6 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                        data-testid="recent-registrations-empty"
                    >
                        No recent registrations yet.
                    </div>
                ) : (
                    items.map((item) => (
                        <Link
                            key={item.id}
                            href={item.href}
                            className="group flex items-center gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-card transition-all duration-200 hover:-translate-y-0.5 hover:border-indigo-300 hover:shadow-card-hover dark:border-gray-700 dark:bg-gray-800 dark:hover:border-indigo-500"
                            data-testid={`recent-registration-card-${item.id}`}
                        >
                            <span
                                className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-bold ${COLOR_BG[item.color]}`}
                                data-testid={`recent-registration-initials-${item.id}`}
                            >
                                {item.initials || getInitials(item.label)}
                            </span>
                            <div className="min-w-0 flex-1">
                                <p className="truncate text-sm font-semibold text-gray-900 dark:text-white">
                                    {item.label}
                                </p>
                                <p className="truncate text-xs text-gray-500 dark:text-gray-400">
                                    {item.subtitle}
                                </p>
                            </div>
                            <RelativeTime
                                date={item.createdAt}
                                className="shrink-0 text-xs text-gray-400 dark:text-gray-500"
                            />
                        </Link>
                    ))
                )}
            </div>
        </section>
    );
}
