import { ReactNode } from 'react';

type Props = {
    /** Title shown prominently */
    title: string;
    /** Sub-message below the title */
    description?: string;
    /** Optional Heroicons-style SVG path (24x24 viewBox, currentColor) */
    iconPath?: ReactNode;
    /** Optional CTA slot (Link, button, etc.) */
    action?: ReactNode;
    /** Optional className override */
    className?: string;
};

/**
 * Reusable empty state — Phase 06b.
 *
 * Used wherever a list/page has no data. Replaces ad-hoc inline divs.
 * Style matches the polished card surface (rounded-xl + soft shadow +
 * dashed border + subtle scale-in animation).
 */
export default function EmptyState({
    title,
    description,
    iconPath,
    action,
    className = '',
}: Props) {
    return (
        <div
            data-testid="empty-state"
            className={`motion-safe:animate-[fadeIn_0.4s_ease-out] rounded-xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-600 dark:bg-gray-800 ${className}`}
        >
            {iconPath && (
                <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 dark:bg-indigo-900/30 dark:text-indigo-300">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-6 w-6"
                        aria-hidden="true"
                    >
                        {iconPath}
                    </svg>
                </div>
            )}
            <p className="text-base font-semibold text-gray-900 dark:text-gray-100">{title}</p>
            {description && (
                <p className="mx-auto mt-1 max-w-md text-sm text-gray-500 dark:text-gray-400">
                    {description}
                </p>
            )}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}
