import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import NavLink from '@/Components/NavLink';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import RoleBadge from '@/Components/RoleBadge';
import RoleSwitcher from '@/Components/RoleSwitcher';
import { Link, router, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

// AuthPageProps satisfies Inertia's `usePage<T extends PageProps>` constraint.
//
// Inertia's PageProps is declared as `{ [key: string]: any }` so that any
// shared prop key (added by HandleInertiaRequests::share() or by individual
// pages) is assignable to `props`. To extend it with strongly-typed auth data
// we intersect with `Record<string, any>` — that's the standard escape hatch.
//
// At runtime, the `auth` middleware guarantees auth.user is non-null on every
// route that renders this layout; hence the `auth.user!` non-null assertion below.
type AuthPageProps = Record<string, any> & {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            activeRole: string | null;
            activeGroup: string | null;
            roles: string[];
            hasMultipleRoles: boolean;
        };
    };
};

// Phase 05 — role-aware nav. The active role's 3-group key drives this.
//
//   Administrator            → full admin nav (Dashboard, Guests, Impact Cells, Profile)
//   Supervisor               → Dashboard, Guests (read-only), Profile
//   followUpOfficer group    → Dashboard, My Guests, Profile
//   followUpTeam group       → Dashboard, My Guests, Profile  (Team Queue hides via /guests subset)
//   impactCell group         → Dashboard, My Guests, Profile  (Cell Leader view lands in Phase 07)
//
// Hidden fields stay hidden — admin links are completely absent for non-admin
// active roles (not just visually muted).
type NavItem = { label: string; href: string; routeName: string };

function navItemsFor(activeRole: string | null, activeGroup: string | null): NavItem[] {
    if (activeRole === 'Administrator') {
        return [
            { label: 'Dashboard',    href: route('dashboard'),            routeName: 'dashboard' },
            { label: 'Guests',       href: route('guests.index'),         routeName: 'guests.index' },
            { label: 'Impact Cells', href: route('impact-cells.index'),   routeName: 'impact-cells.index' },
        ];
    }
    // Officer / Team / Cell Leader — keep nav minimal + role-scoped.
    if (
        activeGroup === 'followUpOfficer'
        || activeGroup === 'followUpTeam'
        || activeGroup === 'impactCell'
        || activeRole === 'Supervisor'
    ) {
        return [
            { label: 'Dashboard',   href: route('dashboard'),    routeName: 'dashboard' },
            { label: 'My Guests',   href: route('guests.index'), routeName: 'guests.index' },
        ];
    }
    // Last-resort fallback (no role, null active role, multi-role out of scope).
    return [
        { label: 'Dashboard', href: route('dashboard'), routeName: 'dashboard' },
    ];
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { auth } = usePage<AuthPageProps>().props;
    const user = auth.user!;

    const [showingNavigationDropdown, setShowingNavigationDropdown] =
        useState(false);

    const navItems = navItemsFor(user.activeRole, user.activeGroup);
    const currentRoute = route().current();

    return (
        <div className="min-h-screen bg-gray-100 dark:bg-gray-900">
            <nav className="border-b border-gray-100 bg-white dark:border-gray-700 dark:bg-gray-800">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="flex h-16 justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                                </Link>
                            </div>

                            <div className="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                                {navItems.map((item) => (
                                    <NavLink
                                        key={item.routeName}
                                        href={item.href}
                                        active={currentRoute === item.routeName}
                                        data-testid={`nav-${item.routeName}`}
                                    >
                                        {item.label}
                                    </NavLink>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {/* Phase 02 — role switcher (visible only for multi-role users) */}
                            {user.hasMultipleRoles && (
                                <RoleSwitcher
                                    roles={user.roles}
                                    activeRole={user.activeRole}
                                />
                            )}

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-md">
                                            <button
                                                type="button"
                                                className="inline-flex items-center rounded-md border border-transparent bg-white px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out hover:text-gray-700 focus:outline-none dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-300"
                                            >
                                                <RoleBadge role={user.activeRole} />
                                                <span className="ms-2">{user.name}</span>

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
                                                    xmlns="http://www.w3.org/2000/svg"
                                                    viewBox="0 0 20 20"
                                                    fill="currentColor"
                                                >
                                                    <path
                                                        fillRule="evenodd"
                                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                                        clipRule="evenodd"
                                                    />
                                                </svg>
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <div className="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                            Signed in as <span className="font-semibold">{user.email}</span>
                                        </div>
                                        <Dropdown.Link
                                            href={route('profile.edit')}
                                        >
                                            Profile
                                        </Dropdown.Link>
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            Log Out
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            </div>
                        </div>

                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() =>
                                    setShowingNavigationDropdown(
                                        (previousState) => !previousState,
                                    )
                                }
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-gray-100 hover:text-gray-500 focus:bg-gray-100 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900 dark:hover:text-gray-400 dark:focus:bg-gray-900 dark:focus:bg-gray-400"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        className={
                                            !showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={
                                            showingNavigationDropdown
                                                ? 'inline-flex'
                                                : 'hidden'
                                        }
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div
                    className={
                        (showingNavigationDropdown ? 'block' : 'hidden') +
                        ' sm:hidden'
                    }
                >
                    <div className="space-y-1 pb-3 pt-2">
                        {navItems.map((item) => (
                            <ResponsiveNavLink
                                key={item.routeName}
                                href={item.href}
                                active={currentRoute === item.routeName}
                            >
                                {item.label}
                            </ResponsiveNavLink>
                        ))}
                    </div>

                    {/* Phase 02 — mobile menu shows role badge + (for multi-role) a role list */}
                    <div className="border-t border-gray-200 pb-1 pt-4 dark:border-gray-600">
                        <div className="px-4">
                            <div className="flex items-center gap-2 text-base font-medium text-gray-800 dark:text-gray-200">
                                {user.name}
                                <RoleBadge role={user.activeRole} />
                            </div>
                            <div className="text-sm font-medium text-gray-500">
                                {user.email}
                            </div>
                        </div>

                        {user.hasMultipleRoles && (
                            <div className="mt-3 space-y-1 px-4">
                                <p className="text-xs uppercase tracking-wide text-gray-400">Switch role</p>
                                {user.roles.map((role) =>
                                    role === user.activeRole ? (
                                        // Plain span for the active role — NOT a link/button,
                                        // so clicking it does nothing (no scroll-to-top).
                                        // Visually distinct via font-semibold + darker text.
                                        <span
                                            key={role}
                                            className="block w-full px-4 py-2 text-start text-sm leading-5 font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {role} ✓
                                        </span>
                                    ) : (
                                        <button
                                            key={role}
                                            type="button"
                                            onClick={() => router.post('/auth/switch-role', { role })}
                                            className="block w-full px-4 py-2 text-start text-sm leading-5 text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800 dark:focus:bg-gray-800"
                                            data-testid={`mobile-role-switch-${role}`}
                                        >
                                            {role}
                                        </button>
                                    ),
                                )}
                            </div>
                        )}

                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink href={route('profile.edit')}>
                                Profile
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                            >
                                Log Out
                            </ResponsiveNavLink>
                        </div>
                    </div>
                </div>
            </nav>

            {header && (
                <header className="bg-white shadow dark:bg-gray-800">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
