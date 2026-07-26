import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

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
    follow_up_contacts: unknown[] | null;
    follow_officer_id: string | null;
    created_at: string | null;
    updated_at: string | null;
    deleted_at: string | null;
}

export default function Show({ guest }: { guest: GuestDetail }) {
    const Row = ({ label, value }: { label: string; value: string | null | undefined }) => (
        <div className="flex justify-between border-b border-gray-100 py-2 text-sm dark:border-gray-700">
            <dt className="font-medium text-gray-600 dark:text-gray-400">{label}</dt>
            <dd className="text-gray-900 dark:text-gray-100">{value ?? '—'}</dd>
        </div>
    );

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                        {guest.guest_name}
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Guest details — column-level edit forms land in Phase 05.
                    </p>
                </div>
            }
        >
            <Head title={guest.guest_name} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <section className="mb-8">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Core
                                </h3>
                                <dl>
                                    <Row label="Date" value={guest.date?.slice(0, 10)} />
                                    <Row label="Event" value={guest.event} />
                                    <Row label="Event other" value={guest.event_other} />
                                    <Row label="Source" value={guest.source} />
                                </dl>
                            </section>

                            <section className="mb-8">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Follow Up Officer group
                                </h3>
                                <dl>
                                    <Row label="Gender" value={guest.gender} />
                                    <Row label="Marital status" value={guest.marital_status} />
                                    <Row label="Age" value={guest.age} />
                                    <Row label="Phone" value={guest.phone} />
                                    <Row label="Address" value={guest.address} />
                                    <Row label="Contacted status" value={guest.contacted_status} />
                                    <Row label="Join when" value={guest.join_when} />
                                    <Row label="Days available" value={guest.days_available} />
                                    <Row label="Visited" value={guest.visited ? 'Yes' : 'No'} />
                                    <Row label="Visited at" value={guest.visited_at} />
                                    <Row label="Indicated to join" value={guest.indicated_to_join} />
                                    <Row label="Visitation status" value={guest.visitation_status} />
                                    <Row label="Feedback" value={guest.feedback} />
                                    <Row label="Comments" value={guest.comments} />
                                </dl>
                            </section>

                            <section className="mb-8">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Impact Cell group
                                </h3>
                                <dl>
                                    <Row label="Nearest Impact Cell" value={guest.nearest_impact_cell_id} />
                                    <Row label="Impact status" value={guest.impact_status} />
                                </dl>
                            </section>

                            <section className="mb-8">
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Follow Up Team group
                                </h3>
                                <dl>
                                    <Row label="Follow Up status" value={guest.follow_up_status} />
                                    <Row
                                        label="Follow Up contacts"
                                        value={guest.follow_up_contacts ? `${guest.follow_up_contacts.length} section(s)` : null}
                                    />
                                </dl>
                            </section>

                            <section>
                                <h3 className="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Assignment
                                </h3>
                                <dl>
                                    <Row label="Follow Officer ID" value={guest.follow_officer_id} />
                                    <Row label="Created" value={guest.created_at?.slice(0, 19)} />
                                    <Row label="Updated" value={guest.updated_at?.slice(0, 19)} />
                                    {guest.deleted_at !== null && (
                                        <Row label="Deleted" value={guest.deleted_at.slice(0, 19)} />
                                    )}
                                </dl>
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
