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
//   Administrator            → full admin nav
//   Supervisor               → Dashboard, Guests (read-only), Profile
//   followUpOfficer group    → Dashboard, My Guests, Profile
//   followUpTeam group       → Dashboard, My Guests, Profile
//   impactCell group         → Dashboard, My Reports, Soul Search
//   Impact_Zonal_Cordinator  → Dashboard, Impact Cells, Guests, Reports, Export CSV
//
// Hidden fields stay hidden — admin links are completely absent for non-admin
// active roles (not just visually muted).
type NavItem = { label: string; href: string; routeName: string };

function navItemsFor(activeRole: string | null, activeGroup: string | null): NavItem[] {
    if (activeRole === 'Administrator') {
        return [
            { label: 'Dashboard',      href: route('dashboard'),                       routeName: 'dashboard' },
            { label: 'Guests',         href: route('guests.index'),                    routeName: 'guests.index' },
            { label: 'Impact Cells',   href: route('impact-cells.index'),              routeName: 'impact-cells.index' },
            { label: 'Reports',        href: route('reports.index'),                   routeName: 'reports.index' },
            { label: 'CSV Import',     href: route('csv.import'),                      routeName: 'csv.import' },
            { label: 'Notifications',  href: route('notification-settings.index'),     routeName: 'notification-settings.index' },
            { label: 'Audit Log',      href: route('audit.index'),                     routeName: 'audit.index' },
            { label: 'Leadership Board', href: route('leadership.index'),               routeName: 'leadership.index' },
        ];
    }
    if (activeRole === 'Impact_Zonal_Cordinator') {
        return [
            { label: 'Dashboard',    href: route('dashboard'),                       routeName: 'dashboard' },
            { label: 'Impact Cells', href: route('impact-cells.index'),              routeName: 'impact-cells.index' },
            { label: 'Guests',       href: route('guests.index'),                    routeName: 'guests.index' },
            { label: 'Reports',      href: route('reports.index'),                   routeName: 'reports.index' },
            { label: 'Export CSV',   href: route('csv.export'),                      routeName: 'csv.export' },
        ];
    }
    if (activeRole === 'Supervisor') {
        return [
            { label: 'Dashboard',   href: route('dashboard'),    routeName: 'dashboard' },
            { label: 'Guests',      href: route('guests.index'), routeName: 'guests.index' },
            { label: 'Reports',     href: route('reports.index'), routeName: 'reports.index' },
        ];
    }
    if (activeGroup === 'followUpOfficer' || activeGroup === 'followUpTeam') {
        return [
            { label: 'Dashboard',   href: route('dashboard'),    routeName: 'dashboard' },
            { label: 'My Guests',   href: route('guests.index'), routeName: 'guests.index' },
            { label: 'Export CSV',  href: route('csv.export'),   routeName: 'csv.export' },
        ];
    }
    if (activeGroup === 'impactCell') {
        return [
            { label: 'Dashboard',          href: route('dashboard'),                          routeName: 'dashboard' },
        { label: 'Members Data',       href: '/impact-submissions/create?type=member',    routeName: 'impact-submissions.create' },
        { label: 'Submit Report',      href: '/impact-submissions/create?type=report',    routeName: 'impact-submissions.create' },
        { label: 'Childbirth Notice',  href: '/impact-submissions/create?type=childbirth', routeName: 'impact-submissions.create' },
        { label: 'Souls Registration', href: '/impact-submissions/create?type=soul',      routeName: 'impact-submissions.create' },
            { label: 'Soul Search',        href: route('impact-submissions.soul-search'),     routeName: 'impact-submissions.soul-search' },
            { label: 'My Reports',         href: route('impact-submissions.my-reports'),      routeName: 'impact-submissions.my-reports' },
            { label: 'Leadership Board',   href: route('leadership.index'),                  routeName: 'leadership.index' },
        ];
    }
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
        <div className="min-h-screen bg-gradient-to-br from-slate-50 via-white to-indigo-50/30 dark:from-gray-950 dark:via-gray-900 dark:to-slate-950">
            {/* Top-bar — glassmorphism, sticky */}
            <nav className="sticky top-0 z-30 border-b border-gray-200/60 bg-white/80 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/80">
                <div className="mx-auto max-w-[88rem] px-4 sm:px-6 lg:px-8">
                    <div className="flex h-[60px] justify-between">
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/" className="group">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-gray-800 transition-transform duration-200 group-hover:scale-105 dark:text-gray-200" />
                                </Link>
                            </div>

                            <div className="hidden space-x-2 sm:-my-px sm:ms-10 sm:flex">                                    {navItems.map((item) => (
                                        <NavLink
                                            key={item.label}
                                            href={item.href}
                                            active={currentRoute === item.routeName}
                                            data-testid={`nav-${item.label.toLowerCase().replace(/\s+/g, '-')}`}
                                        className="relative inline-flex items-center px-3 py-1 text-sm font-medium leading-5 text-gray-600 transition duration-150 ease-in-out hover:text-gray-900 focus:outline-none dark:text-gray-300 dark:hover:text-white"
                                    >
                                        {item.label}
                                        {currentRoute === item.routeName && (
                                            <span
                                                aria-hidden="true"
                                                className="absolute inset-x-2 -bottom-[1px] h-0.5 rounded-full bg-indigo-500"
                                            />
                                        )}
                                    </NavLink>
                                ))}
                            </div>
                        </div>

                        <div className="hidden sm:ms-6 sm:flex sm:items-center">
                            {user.hasMultipleRoles && (
                                <RoleSwitcher
                                    roles={user.roles}
                                    activeRole={user.activeRole}
                                />
                            )}

                            <div className="relative ms-3">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-full">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2 rounded-full border border-gray-200/80 bg-white/80 px-2.5 py-1.5 text-sm font-medium leading-4 text-gray-700 shadow-card transition duration-150 ease-in-out hover:border-gray-300 hover:shadow-card-hover focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-800/80 dark:text-gray-200 dark:hover:bg-gray-800"
                                            >
                                                <RoleBadge role={user.activeRole} />
                                                <span className="hidden truncate max-w-[10rem] sm:inline">{user.name}</span>
                                                <svg
                                                    className="h-4 w-4 text-gray-400"
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
                                            </button>
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <div className="px-4 py-2 text-xs text-gray-500 dark:text-gray-400">
                                            Signed in as <span className="font-semibold">{user.email}</span>
                                        </div>
                                        <Dropdown.Link href={route('profile.edit')}>
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
                                onClick={() => setShowingNavigationDropdown((p) => !p)}
                                className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 transition duration-150 ease-in-out hover:bg-white/60 hover:text-gray-500 focus:bg-white/60 focus:text-gray-500 focus:outline-none dark:text-gray-500 dark:hover:bg-gray-900/60 dark:hover:text-gray-400"
                            >
                                <svg
                                    className="h-6 w-6"
                                    stroke="currentColor"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    aria-hidden="true"
                                >
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
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
                    className={(showingNavigationDropdown ? 'block' : 'hidden') + ' sm:hidden'}
                >
                    <div className="space-y-1 border-t border-gray-200/60 bg-white/90 px-4 pb-3 pt-2 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/90">
                        {navItems.map((item) => (
                            <ResponsiveNavLink
                                key={item.label}
                                href={item.href}
                                active={currentRoute === item.routeName}
                            >
                                {item.label}
                            </ResponsiveNavLink>
                        ))}
                    </div>

                    <div className="border-t border-gray-200/60 bg-white/90 pb-3 pt-4 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/90">
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
                                        <span
                                            key={role}
                                            className="block w-full px-4 py-2 text-start text-sm font-semibold text-gray-900 dark:text-gray-100"
                                        >
                                            {role} ✓
                                        </span>
                                    ) : (
                                        <button
                                            key={role}
                                            type="button"
                                            onClick={() => router.post('/auth/switch-role', { role })}
                                            className="block w-full px-4 py-2 text-start text-sm text-gray-700 transition duration-150 ease-in-out hover:bg-gray-100 focus:bg-gray-100 focus:outline-none dark:text-gray-300 dark:hover:bg-gray-800"
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

            {/* Header band — glassmorphism */}
            {header && (
                <header className="sticky top-[60px] z-20 border-b border-gray-200/60 bg-white/70 backdrop-blur-md dark:border-gray-800/60 dark:bg-gray-900/70">
                    <div className="mx-auto max-w-[88rem] px-4 py-6 sm:px-6 lg:px-8">
                        {header}
                    </div>
                </header>
            )}

            {/* Main content — motion-safe fade-in, max-w-[88rem] on 2xl+ */}
            <main className="motion-safe:animate-fade-in">
                <div className="mx-auto max-w-[88rem] px-4 py-8 sm:px-6 sm:py-10 lg:px-8 lg:py-12">
                    {children}
                </div>
            </main>

            {/* Phase 06b — fadeIn keyframe moved to tailwind.config.js
                (theme.extend.keyframes.fadeIn) + theme.extend.animation['fade-in'].
                AuthenticatedLayout no longer needs an inline @keyframes copy:
                Tailwind's JIT emits a global `@keyframes fadeIn` from the
                keyframes token, so arbitrary-value `animate-[fadeIn_...]`
                classes used by Pages still resolve. GuestLayout.tsx still
                keeps its own inline @keyframes block for now — 06c will fold
                the auth-flow pages onto `motion-safe:animate-fade-in` and
                drop that copy too. */}
        </div>
    );
}
