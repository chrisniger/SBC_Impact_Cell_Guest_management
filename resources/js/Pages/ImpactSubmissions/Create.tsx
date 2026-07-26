import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

interface Cell { id: string; name: string; }
interface CreatePageProps extends Record<string, any> { type: string; cells: Cell[]; activeRole: string | null; errors: Record<string, string>; }

export default function Create() {
    const { props } = usePage<any>();
    const { type, cells, activeRole, errors } = props;

    const tabs = ['member', 'report', 'childbirth', 'soul'];
    const labels: Record<string, string> = { member: 'Members Data', report: 'Cell Report', childbirth: 'Childbirth', soul: 'Soul' };

    return (
        <AuthenticatedLayout header={
            <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                New Submission — {labels[type] ?? type}
            </h2>
        }>
            <Head title="New Submission" />
            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="mb-4 flex gap-2 overflow-x-auto">
                        {tabs.map(t => (
                            <a
                                key={t}
                                href={`/impact-submissions/create?type=${t}`}
                                className={`whitespace-nowrap rounded-md px-3 py-1.5 text-sm font-medium ${
                                    t === type
                                        ? 'bg-gray-800 text-white'
                                        : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300'
                                }`}
                            >
                                {labels[t]}
                            </a>
                        ))}
                    </div>
                    <div className="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg">
                        <div className="p-6">
                            {type === 'member' && <MembersDataForm cells={cells} />}
                            {type === 'report' && <SubmitReportForm cells={cells} />}
                            {type === 'childbirth' && <ChildbirthNoticeForm cells={cells} />}
                            {type === 'soul' && <SoulsRegistrationForm cells={cells} />}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function MembersDataForm({ cells }: { cells: Cell[] }) {
    const [form, setForm] = useState({
        impact_cell_id: '',
        full_name: '', phone: '', email: '', gender: '', date_of_birth: '',
        occupation: '', marital_status: '', address: '', centre: '',
        member_status: '', department: '', joined_date: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [serverError, setServerError] = useState('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setServerError('');
        router.post('/impact-submissions', {
            impact_cell_id: form.impact_cell_id,
            type: 'member',
            data: form,
        }, {
            preserveScroll: true,
            onSuccess: () => { setSubmitting(false); },
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {serverError && <p className="text-sm text-red-600">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Impact Cell *</label>
                    <select value={form.impact_cell_id} onChange={set('impact_cell_id')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name *</label>
                    <input type="text" value={form.full_name} onChange={set('full_name')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone *</label>
                    <input type="text" value={form.phone} onChange={set('phone')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                    <input type="email" value={form.email} onChange={set('email')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                    <select value={form.gender} onChange={set('gender')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Birth</label>
                    <input type="date" value={form.date_of_birth} onChange={set('date_of_birth')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                    <input type="text" value={form.occupation} onChange={set('occupation')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Marital Status</label>
                    <select value={form.marital_status} onChange={set('marital_status')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                    </select>
                </div>
                <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                    <textarea value={form.address} onChange={set('address')} rows={2}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Centre</label>
                    <input type="text" value={form.centre} onChange={set('centre')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Member Status</label>
                    <select value={form.member_status} onChange={set('member_status')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Visitor">Visitor</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                    <input type="text" value={form.department} onChange={set('department')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Joined Date</label>
                    <input type="date" value={form.joined_date} onChange={set('joined_date')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
            </div>
            <div className="flex justify-end pt-4">
                <button type="submit" disabled={submitting}
                    className="rounded-md bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                    {submitting ? 'Saving…' : 'Save Member'}
                </button>
            </div>
        </form>
    );
}

function SubmitReportForm({ cells }: { cells: Cell[] }) {
    const [form, setForm] = useState({
        impact_cell_id: '', fellowship_date: '',
        adults: '0', children: '0', first_timers: '0', new_members: '0',
        offering_hq: '0', offering_centre: '0',
    });
    const [submitting, setSubmitting] = useState(false);
    const [serverError, setServerError] = useState('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setServerError('');
        router.post('/impact-submissions', {
            impact_cell_id: form.impact_cell_id,
            type: 'report',
            fellowship_date_key: form.fellowship_date,
            data: {
                fellowship_date: form.fellowship_date,
                adults: parseInt(form.adults) || 0,
                children: parseInt(form.children) || 0,
                first_timers: parseInt(form.first_timers) || 0,
                new_members: parseInt(form.new_members) || 0,
                offering_hq: parseFloat(form.offering_hq) || 0,
                offering_centre: parseFloat(form.offering_centre) || 0,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => { setSubmitting(false); },
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {serverError && <p className="text-sm text-red-600">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Impact Cell *</label>
                    <select value={form.impact_cell_id} onChange={set('impact_cell_id')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Fellowship Date *</label>
                    <input type="date" value={form.fellowship_date} onChange={set('fellowship_date')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Adults *</label>
                    <input type="number" min="0" value={form.adults} onChange={set('adults')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Children</label>
                    <input type="number" min="0" value={form.children} onChange={set('children')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">First Timers</label>
                    <input type="number" min="0" value={form.first_timers} onChange={set('first_timers')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">New Members</label>
                    <input type="number" min="0" value={form.new_members} onChange={set('new_members')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Offering (HQ)</label>
                    <input type="number" min="0" step="0.01" value={form.offering_hq} onChange={set('offering_hq')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Offering (Centre)</label>
                    <input type="number" min="0" step="0.01" value={form.offering_centre} onChange={set('offering_centre')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
            </div>
            <div className="flex justify-end pt-4">
                <button type="submit" disabled={submitting}
                    className="rounded-md bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                    {submitting ? 'Saving…' : 'Submit Report'}
                </button>
            </div>
        </form>
    );
}

function ChildbirthNoticeForm({ cells }: { cells: Cell[] }) {
    const [form, setForm] = useState({
        impact_cell_id: '', child_name: '', parent_name: '', date_of_birth: '',
        gender: '', phone: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [serverError, setServerError] = useState('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setServerError('');
        router.post('/impact-submissions', {
            impact_cell_id: form.impact_cell_id,
            type: 'childbirth',
            data: {
                child_name: form.child_name, parent_name: form.parent_name,
                date_of_birth: form.date_of_birth, gender: form.gender, phone: form.phone,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => { setSubmitting(false); },
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {serverError && <p className="text-sm text-red-600">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Impact Cell *</label>
                    <select value={form.impact_cell_id} onChange={set('impact_cell_id')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Child Name *</label>
                    <input type="text" value={form.child_name} onChange={set('child_name')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Parent Name *</label>
                    <input type="text" value={form.parent_name} onChange={set('parent_name')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Date of Birth *</label>
                    <input type="date" value={form.date_of_birth} onChange={set('date_of_birth')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender *</label>
                    <select value={form.gender} onChange={set('gender')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                    <input type="text" value={form.phone} onChange={set('phone')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
            </div>
            <div className="flex justify-end pt-4">
                <button type="submit" disabled={submitting}
                    className="rounded-md bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                    {submitting ? 'Saving…' : 'Save Childbirth Notice'}
                </button>
            </div>
        </form>
    );
}

function SoulsRegistrationForm({ cells }: { cells: Cell[] }) {
    const [form, setForm] = useState({
        impact_cell_id: '', full_name: '', phone: '', gender: '',
        occupation: '', marital_status: '', prayer_request: '',
    });
    const [submitting, setSubmitting] = useState(false);
    const [serverError, setServerError] = useState('');

    const handleSubmit: FormEventHandler = (e) => {
        e.preventDefault();
        if (submitting) return;
        setSubmitting(true);
        setServerError('');
        router.post('/impact-submissions', {
            impact_cell_id: form.impact_cell_id,
            type: 'soul',
            data: {
                full_name: form.full_name, phone: form.phone, gender: form.gender,
                occupation: form.occupation, marital_status: form.marital_status,
                prayer_request: form.prayer_request,
            },
        }, {
            preserveScroll: true,
            onSuccess: () => { setSubmitting(false); },
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {serverError && <p className="text-sm text-red-600">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Impact Cell *</label>
                    <select value={form.impact_cell_id} onChange={set('impact_cell_id')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Full Name *</label>
                    <input type="text" value={form.full_name} onChange={set('full_name')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone *</label>
                    <input type="text" value={form.phone} onChange={set('phone')} required
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Gender</label>
                    <select value={form.gender} onChange={set('gender')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Occupation</label>
                    <input type="text" value={form.occupation} onChange={set('occupation')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
                <div>
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Marital Status</label>
                    <select value={form.marital_status} onChange={set('marital_status')}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        <option value="">—</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                    </select>
                </div>
                <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">Prayer Request</label>
                    <textarea value={form.prayer_request} onChange={set('prayer_request')} rows={3}
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300" />
                </div>
            </div>
            <div className="flex justify-end pt-4">
                <button type="submit" disabled={submitting}
                    className="rounded-md bg-gray-800 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-700 disabled:opacity-50 dark:bg-gray-200 dark:text-gray-800">
                    {submitting ? 'Saving…' : 'Save Soul'}
                </button>
            </div>
        </form>
    );
}
