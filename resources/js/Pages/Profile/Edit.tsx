import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head } from '@inertiajs/react';
import { ReactNode } from 'react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

const userIcon = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>;
const lockIcon = <><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></>;
const warnIcon = <><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></>;

function ProfileSection({ title, iconPath, children, testId }: { title: string; iconPath: ReactNode; children: ReactNode; testId?: string }) {
    return (
        <section
            className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800"
            data-testid={testId}
        >
            <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                        {iconPath}
                    </svg>
                </span>
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">{title}</h3>
            </header>
            <div className="p-5 sm:p-6">{children}</div>
        </section>
    );
}

export default function Edit({ mustVerifyEmail, status }: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Account
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Profile
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Manage your account information, password, and access.
                </p>
            </div>
        }>
            <Head title="Profile" />

            <div className="space-y-6">
                <ProfileSection title="Profile Information" iconPath={userIcon} testId="profile-info">
                    <UpdateProfileInformationForm
                        mustVerifyEmail={mustVerifyEmail}
                        status={status}
                        className="max-w-xl"
                    />
                </ProfileSection>

                <ProfileSection title="Update Password" iconPath={lockIcon} testId="profile-password">
                    <UpdatePasswordForm className="max-w-xl" />
                </ProfileSection>

                <ProfileSection title="Delete Account" iconPath={warnIcon} testId="profile-delete">
                    <DeleteUserForm className="max-w-xl" />
                </ProfileSection>
            </div>
        </AuthenticatedLayout>
    );
}
