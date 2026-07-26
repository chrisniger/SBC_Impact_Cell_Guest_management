import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Setting { id: number; action: string; recipient_email: string; enabled: boolean; }

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
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                Notification Settings
            </h2>
        }>
            <Head title="Notification Settings" />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            <form onSubmit={handleSubmit} className="mb-6 flex flex-wrap items-end gap-3">
                                <div>
                                    <label className="block text-xs font-medium text-gray-500">Action</label>
                                    <select value={form.action} onChange={e => setForm(p => ({ ...p, action: e.target.value }))}
                                        className="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                        <option value="WEEKLY_REPORT_SUBMITTED">Weekly Report Submitted</option>
                                        <option value="GUEST_ASSIGNED">Guest Assigned</option>
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-xs font-medium text-gray-500">Recipient Email</label>
                                    <input type="email" required value={form.recipient_email} onChange={e => setForm(p => ({ ...p, recipient_email: e.target.value }))}
                                        className="mt-1 block w-72 rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                                </div>
                                <button type="submit" disabled={submitting}
                                    className="rounded-md bg-gray-800 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                                    {submitting ? 'Adding…' : 'Add Rule'}
                                </button>
                            </form>

                            {settings.length === 0 ? (
                                <p className="text-sm text-gray-500">No notification rules configured.</p>
                            ) : (
                                <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead>
                                        <tr>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Action</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Email</th>
                                            <th className="px-3 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                            <th className="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                        {settings.map(s => (
                                            <tr key={s.id}>
                                                <td className="px-3 py-2 text-sm">{s.action}</td>
                                                <td className="px-3 py-2 text-sm">{s.recipient_email}</td>
                                                <td className="px-3 py-2 text-sm">{s.enabled ? 'Enabled' : 'Disabled'}</td>
                                                <td className="px-3 py-2 text-right">
                                                    <button onClick={() => router.delete(`/notification-settings/${s.id}`, { preserveScroll: true })}
                                                        className="text-sm text-red-600 hover:underline">Remove</button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
