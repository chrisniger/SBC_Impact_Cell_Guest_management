import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ContactsTimeline from '@/Components/ContactsTimeline';
import StatusPill from '@/Components/StatusPill';
import ViewOnlyBanner from '@/Components/ViewOnlyBanner';
import { Head, Link } from '@inertiajs/react';
import { ReactNode } from 'react';

interface GuestDetail {
    id: string;
    date: string | null;
    event: string | null;
    event_other: string | null;
    guest_name: string;
    source: string | null;
    gender: string | null;
    marital_status: string | null;
    age: string | null;
    phone: string | null;
    address: string | null;
    nearest_impact_cell_id: string | null;
    impact_status: string | null;
    contacted_status: string | null;
    join_when: string | null;
    days_available: string | null;
    comments: string | null;
    visited: boolean;
    visited_at: string | null;
    indicated_to_join: string | null;
    visitation_status: string | null;
    feedback: string | null;
    follow_up_status: string | null;
    follow_up_contacts: { date: string | null; contact: string | null; comments: string | null }[] | null;
    follow_officer_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

const CONTACTED_TONE = {
    'Visited': 'success',
    'AvailableForVisit': 'brand',
    'Contacted': 'info',
} as const;

function ContactedPill({ value }: { value: string | null }) {
    if (!value) return <span className="text-gray-400 dark:text-gray-500">—</span>;
    const tone = (CONTACTED_TONE as any)[value] ?? 'neutral';
    return <StatusPill tone={tone} dot>{value}</StatusPill>;
}

function FollowUpPill({ value }: { value: string | null }) {
    if (!value) return <span className="text-gray-400 dark:text-gray-500">—</span>;
    const tone =
        value === 'CONTACTED' ? 'success' :
        value === 'NOT CONTACTED' ? 'warn' :
        (value === 'WRONG NUMBER' || value === 'NOT REACHABLE') ? 'danger' :
        'neutral';
    return <StatusPill tone={tone} dot>{value}</StatusPill>;
}

function Card({
    title,
    iconPath,
    children,
    testId,
}: {
    title: string;
    iconPath: ReactNode;
    children: ReactNode;
    testId?: string;
}) {
    return (
        <section
            className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
            data-testid={testId}
        >
            <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                        {iconPath}
                    </svg>
                </span>
                <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                    {title}
                </h3>
            </header>
            <div className="px-5 py-4">{children}</div>
        </section>
    );
}

function DisplayField({ label, value, mono }: { label: string; value: string | null | undefined; mono?: boolean }) {
    return (
        <div className="flex items-baseline justify-between border-b border-gray-100 py-2 last:border-b-0 dark:border-gray-700">
            <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">{label}</dt>
            <dd className={`text-sm text-gray-900 dark:text-gray-100 ${mono ? 'font-mono' : ''}`}>{value ?? '—'}</dd>
        </div>
    );
}

export default function Show({ guest, editableFields, activeRole }: { guest: GuestDetail; editableFields?: string[]; activeRole?: string | null }) {
    const canEdit = (editableFields ?? []).length > 0;

    const userIcon = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>;
    const phoneIcon = <><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" /></>;
    const cellIcon = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
    const visitIcon = <><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z" /><circle cx="12" cy="10" r="3" /></>;
    const teamIcon = <><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>;
    const metaIcon = <><circle cx="12" cy="12" r="10" /><polyline points="12 6 12 12 16 14" /></>;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Guest Profile
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            {guest.guest_name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Read-only view. Use the edit page to make changes.
                        </p>
                    </div>
                    {canEdit && (
                        <Link
                            href={route('guests.edit', guest.id)}
                            className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            data-testid="edit-link"
                        >
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z" />
                            </svg>
                            Edit
                        </Link>
                    )}
                </div>
            }
        >
            <Head title={guest.guest_name} />
            <ViewOnlyBanner role={activeRole ?? null} />

            <div className="space-y-6">
                {/* Core + Contact Info */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card title="Core" iconPath={userIcon} testId="card-core">
                        <dl>
                            <DisplayField label="Date" value={guest.date?.slice(0, 10)} />
                            <DisplayField label="Event" value={guest.event} />
                            <DisplayField label="Event other" value={guest.event_other} />
                            <DisplayField label="Source" value={guest.source} />
                        </dl>
                    </Card>

                    <Card title="Contact Info" iconPath={phoneIcon} testId="card-contact">
                        <dl>
                            <DisplayField label="Gender" value={guest.gender} />
                            <DisplayField label="Marital status" value={guest.marital_status} />
                            <DisplayField label="Age" value={guest.age} />
                            <DisplayField label="Phone" value={guest.phone} mono />
                            <div className="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700">
                                <div className="flex items-baseline justify-between py-1">
                                    <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Address</dt>
                                </div>
                                <dd className="text-sm text-gray-900 dark:text-gray-100">{guest.address ?? '—'}</dd>
                            </div>
                        </dl>
                    </Card>
                </div>

                {/* Follow Up Officer */}
                <Card title="Follow Up Officer" iconPath={userIcon} testId="card-officer">
                    <dl>
                        <DisplayField label="Contacted status" value={guest.contacted_status} />
                        <DisplayField label="Join when" value={guest.join_when} />
                        <DisplayField label="Days available" value={guest.days_available} />
                        <DisplayField label="Visited" value={guest.visited ? 'Yes' : 'No'} />
                        <DisplayField label="Visited at" value={guest.visited_at} />
                        <DisplayField label="Indicated to join" value={guest.indicated_to_join} />
                        <DisplayField label="Visitation status" value={guest.visitation_status} />
                        <div className="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700">
                            <div className="flex items-baseline justify-between py-1">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Comments</dt>
                            </div>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">{guest.comments ?? '—'}</dd>
                        </div>
                        <div className="mt-2 border-t border-gray-100 pt-2 dark:border-gray-700">
                            <div className="flex items-baseline justify-between py-1">
                                <dt className="text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Feedback</dt>
                            </div>
                            <dd className="text-sm text-gray-900 dark:text-gray-100">{guest.feedback ?? '—'}</dd>
                        </div>
                    </dl>
                </Card>

                {/* Visit + Impact Cell */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card title="Visit" iconPath={visitIcon} testId="card-visit">
                        <dl>
                            <DisplayField label="Visited" value={guest.visited ? 'Yes' : 'No'} />
                            <DisplayField label="Visited at" value={guest.visited_at} />
                            <DisplayField label="Visitation status" value={guest.visitation_status} />
                        </dl>
                    </Card>

                    <Card title="Impact Cell" iconPath={cellIcon} testId="card-cell">
                        <dl>
                            <DisplayField label="Nearest Impact Cell" value={guest.nearest_impact_cell_id} mono />
                            <DisplayField label="Impact status" value={guest.impact_status} />
                        </dl>
                    </Card>
                </div>

                {/* Follow Up Team */}
                <Card title="Follow Up Team" iconPath={teamIcon} testId="card-team">
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                        <dl>
                            <DisplayField label="Follow Up status" value={guest.follow_up_status} />
                            <DisplayField label="Follow Officer ID" value={guest.follow_officer_id} mono />
                        </dl>
                        <div>
                            <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                Contact Timeline
                            </h4>
                            <ContactsTimeline contacts={guest.follow_up_contacts} readOnly={true} />
                        </div>
                    </div>
                </Card>

                {/* Assignment / Metadata */}
                <Card title="Metadata" iconPath={metaIcon} testId="card-meta">
                    <dl>
                        <DisplayField label="Created" value={guest.created_at?.slice(0, 19)} mono />
                        <DisplayField label="Updated" value={guest.updated_at?.slice(0, 19)} mono />
                        {guest.deleted_at !== null && (
                            <DisplayField label="Deleted" value={guest.deleted_at.slice(0, 19)} mono />
                        )}
                    </dl>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
