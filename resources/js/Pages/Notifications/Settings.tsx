import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import EmptyState from '@/Components/EmptyState';
import StatusPill from '@/Components/StatusPill';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Setting { id: number; action: string; recipient_email: string; enabled: boolean; }

const bellIconPath = <><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9" /><path d="M13.73 21a2 2 0 0 1-3.46 0" /></>;

export default function Settings() {
    const { props } = usePage<any>();
    const settings: Setting[] = props.settings ?? [];
    const [form, setForm] = useState({ action: 'WEEKLY_REPORT_SUBMITTED', recipient_email: '', enabled: true });
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        router.post('/notification-settings', form, {
            preserveScroll: true,
            onFinish: () => { setSubmitting(false); setForm(prev => ({ ...prev, recipient_email: '' })); },
        });
    };

    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Settings
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    Notification Settings
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Wire up email alerts for follow-up events and member changes.
                </p>
            </div>
        }>
            <Head title="Notification Settings" />

            <div className="space-y-6">
                <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-add-rule">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {bellIconPath}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Add Rule</h3>
                    </header>
                    <form onSubmit={handleSubmit} className="flex flex-wrap items-end gap-4 p-5">
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Action</label>
                            <select
                                value={form.action}
                                onChange={e => setForm(p => ({ ...p, action: e.target.value }))}
                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                data-testid="rule-action"
                            >
                                <option value="WEEKLY_REPORT_SUBMITTED">Weekly Report Submitted</option>
                                <option value="GUEST_ASSIGNED">Guest Assigned</option>
                            </select>
                        </div>
                        <div className="flex-1 min-w-[16rem]">
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Recipient Email</label>
                            <input
                                type="email"
                                required
                                value={form.recipient_email}
                                onChange={e => setForm(p => ({ ...p, recipient_email: e.target.value }))}
                                className="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                data-testid="rule-email"
                            />
                        </div>
                        <button
                            type="submit"
                            disabled={submitting}
                            className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            data-testid="rule-submit"
                        >
                            {submitting ? 'Adding…' : 'Add Rule'}
                        </button>
                    </form>
                </section>

                <section className="motion-safe:animate-[fadeIn_0.4s_ease-out] overflow-hidden rounded-xl border border-gray-200 bg-white shadow-[0_4px_20px_rgba(0,0,0,0.03)] dark:border-gray-700 dark:bg-gray-800" data-testid="card-rules-list">
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">Active Rules</h3>
                        <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">{settings.length}</span>
                    </header>
                    {settings.length === 0 ? (
                        <EmptyState
                            title="No notification rules configured"
                            description="Add a rule above to start receiving alerts for follow-up events."
                            iconPath={bellIconPath}
                            className="rounded-none border-0 shadow-none"
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50/80 dark:bg-gray-900/60">
                                    <tr>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Action</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Email</th>
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Status</th>
                                        <th className="px-4 py-3"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {settings.map(s => (
                                        <tr key={s.id} className="transition-colors hover:bg-indigo-50/40 dark:hover:bg-gray-700/40">
                                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{s.action}</td>
                                            <td className="px-4 py-3 text-sm font-mono text-gray-700 dark:text-gray-300">{s.recipient_email}</td>
                                            <td className="px-4 py-3">
                                                <StatusPill tone={s.enabled ? 'success' : 'neutral'} dot>
                                                    {s.enabled ? 'Enabled' : 'Disabled'}
                                                </StatusPill>
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => router.delete(`/notification-settings/${s.id}`, { preserveScroll: true })}
                                                    className="text-xs font-semibold text-rose-600 transition-colors hover:text-rose-700 dark:text-rose-400 dark:hover:text-rose-300"
                                                >
                                                    Remove
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
