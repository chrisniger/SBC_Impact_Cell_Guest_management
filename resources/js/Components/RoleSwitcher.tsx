import { router } from '@inertiajs/react';

interface RoleSwitcherProps {
    roles: string[];
    activeRole: string | null;
}

export default function RoleSwitcher({ roles, activeRole }: RoleSwitcherProps) {
    if (roles.length < 2) {
        return null;
    }

    const handleSwitch = (role: string) => {
        if (role === activeRole) {
            return;
        }
        router.post(
            '/auth/switch-role',
            { role },
            {
                preserveScroll: true,
                preserveState: false,
                onError: (errors) => {
                    console.error('Role switch failed', errors);
                    alert(`Could not switch to ${role}. Check console for details.`);
                },
            },
        );
    };

    return (
        <div className="relative ms-3" data-testid="role-switcher">
            <details className="relative">
                <summary
                    className="inline-flex cursor-pointer select-none items-center gap-1.5 rounded-md border border-indigo-200 bg-indigo-50/70 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition duration-150 ease-in-out hover:border-indigo-300 hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:border-indigo-700/50 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50"
                    aria-label="Switch active role"
                    title="Switch your active role (changes the dashboard view)"
                    data-testid="role-switcher-trigger"
                >
                    <svg
                        className="h-3.5 w-3.5"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <rect x="2" y="2" width="20" height="20" rx="5" />
                        <path d="M7 9l5 5 5-5" />
                    </svg>
                    <span data-testid="role-switcher-active">{activeRole}</span>
                    <svg
                        className="h-3.5 w-3.5 opacity-60"
                        xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            fillRule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clipRule="evenodd"
                        />
                    </svg>
                </summary>
                <div className="absolute right-0 z-50 mt-2 w-56 origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none dark:bg-gray-800">
                    <div className="py-1">
                        {roles.map((role) => {
                            const isActive = role === activeRole;
                            return (
                                <button
                                    key={role}
                                    type="button"
                                    onClick={() => handleSwitch(role)}
                                    className={`flex w-full items-center justify-between px-4 py-2 text-left text-sm transition hover:bg-gray-100 dark:hover:bg-gray-700 ${
                                        isActive
                                            ? 'font-semibold text-gray-900 dark:text-gray-100'
                                            : 'text-gray-700 dark:text-gray-300'
                                    }`}
                                    data-testid={`role-switch-option-${role}`}
                                    disabled={isActive}
                                >
                                    <span>{role}</span>
                                    {isActive && (
                                        <svg
                                            className="h-4 w-4 text-green-500"
                                            xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20"
                                            fill="currentColor"
                                        >
                                            <path
                                                fillRule="evenodd"
                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                clipRule="evenodd"
                                            />
                                        </svg>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                </div>
            </details>
        </div>
    );
}