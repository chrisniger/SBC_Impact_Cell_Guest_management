import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEventHandler, ReactNode, useState } from 'react';

interface Cell { id: string; name: string; }
interface CreatePageProps extends Record<string, any> { type: string; cells: Cell[]; activeRole: string | null; errors: Record<string, string>; }

const TABS = ['member', 'report', 'childbirth', 'soul'] as const;
const LABELS: Record<string, string> = {
    member: 'Members Data',
    report: 'Cell Report',
    childbirth: 'Childbirth',
    soul: 'Soul',
};

const TAB_ICONS: Record<string, ReactNode> = {
    member: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>,
    report: <><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" /><polyline points="14 2 14 8 20 8" /></>,
    childbirth: <><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" /><circle cx="12" cy="7" r="4" /></>,
    soul: <><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" /></>,
};

export default function Create() {
    const { props } = usePage<any>();
    const { type, cells, activeRole } = props;

    return (
        <AuthenticatedLayout header={
            <div>
                <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                    Outreach
                </p>
                <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                    New Submission — {LABELS[type] ?? type}
                </h2>
                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                </p>
            </div>
        }>
            <Head title="New Submission" />

            <div className="space-y-6">
                {/* Tabs */}
                <nav className="flex flex-wrap gap-2" aria-label="Submission type">
                    {TABS.map(t => {
                        const active = t === type;
                        return (
                            <a
                                key={t}
                                href={`/impact-submissions/create?type=${t}`}
                                className={`inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-semibold transition-all ${
                                    active
                                        ? 'bg-indigo-600 text-white shadow-sm'
                                        : 'bg-white text-gray-700 ring-1 ring-gray-200 hover:bg-indigo-50 hover:text-indigo-700 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700 dark:hover:bg-indigo-900/30 dark:hover:text-indigo-300'
                                }`}
                                data-testid={`tab-${t}`}
                            >
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {TAB_ICONS[t]}
                                </svg>
                                {LABELS[t]}
                            </a>
                        );
                    })}
                </nav>

                <div className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800">
                    <div className="p-6">
                        {type === 'member' && <MembersDataForm cells={cells} />}
                        {type === 'report' && <SubmitReportForm cells={cells} />}
                        {type === 'childbirth' && <ChildbirthNoticeForm cells={cells} />}
                        {type === 'soul' && <SoulsRegistrationForm cells={cells} />}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

const inputCls =
    'mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm transition-colors';
const labelCls = 'block text-sm font-semibold text-gray-700 dark:text-gray-200';

function FormField({ label, id, required, children, error }: { label: string; id: string; required?: boolean; children: ReactNode; error?: string }) {
    return (
        <div>
            <label htmlFor={id} className={labelCls}>
                {label}{required && <span className="ml-0.5 text-amber-500">*</span>}
            </label>
            {children}
            {error && <p className="mt-1 text-xs text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
}

function FormShell({ children, onSubmit, submitting, submitLabel }: { children: ReactNode; onSubmit: FormEventHandler; submitting: boolean; submitLabel: string }) {
    return (
        <form onSubmit={onSubmit} className="space-y-4">
            {children}
            <div className="flex justify-end pt-4 sticky bottom-0 z-10 -mx-6 -mb-6 rounded-b-xl border-t border-gray-100 bg-white/90 px-6 py-4 backdrop-blur-md dark:border-gray-700 dark:bg-gray-800/90">
                <button
                    type="submit"
                    disabled={submitting}
                    className="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {submitting ? (
                        <>
                            <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 animate-spin" aria-hidden="true">
                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                            </svg>
                            Saving…
                        </>
                    ) : submitLabel}
                </button>
            </div>
        </form>
    );
}

function MembersDataForm({ cells }: { cells: Cell[] }) {
    const [form, setForm] = useState({
        impact_cell_id: '', full_name: '', phone: '', email: '', gender: '', date_of_birth: '',
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
            impact_cell_id: form.impact_cell_id, type: 'member', data: form,
        }, {
            preserveScroll: true,
            onSuccess: () => setSubmitting(false),
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <FormShell onSubmit={handleSubmit} submitting={submitting} submitLabel="Save Member">
            {serverError && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Impact Cell" id="member-cell" required>
                    <select id="member-cell" value={form.impact_cell_id} onChange={set('impact_cell_id')} required className={inputCls}>
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </FormField>
                <FormField label="Full Name" id="member-name" required>
                    <input id="member-name" type="text" value={form.full_name} onChange={set('full_name')} required className={inputCls} />
                </FormField>
                <FormField label="Phone" id="member-phone" required>
                    <input id="member-phone" type="text" value={form.phone} onChange={set('phone')} required className={inputCls} />
                </FormField>
                <FormField label="Email" id="member-email">
                    <input id="member-email" type="email" value={form.email} onChange={set('email')} className={inputCls} />
                </FormField>
                <FormField label="Gender" id="member-gender">
                    <select id="member-gender" value={form.gender} onChange={set('gender')} className={inputCls}>
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </FormField>
                <FormField label="Date of Birth" id="member-dob">
                    <input id="member-dob" type="date" value={form.date_of_birth} onChange={set('date_of_birth')} className={inputCls} />
                </FormField>
                <FormField label="Occupation" id="member-occupation">
                    <input id="member-occupation" type="text" value={form.occupation} onChange={set('occupation')} className={inputCls} />
                </FormField>
                <FormField label="Marital Status" id="member-marital">
                    <select id="member-marital" value={form.marital_status} onChange={set('marital_status')} className={inputCls}>
                        <option value="">—</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                    </select>
                </FormField>
                <div className="sm:col-span-2">
                    <FormField label="Address" id="member-address">
                        <textarea id="member-address" value={form.address} onChange={set('address')} rows={2} className={inputCls} />
                    </FormField>
                </div>
                <FormField label="Centre" id="member-centre">
                    <input id="member-centre" type="text" value={form.centre} onChange={set('centre')} className={inputCls} />
                </FormField>
                <FormField label="Member Status" id="member-status">
                    <select id="member-status" value={form.member_status} onChange={set('member_status')} className={inputCls}>
                        <option value="">—</option>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                        <option value="Visitor">Visitor</option>
                    </select>
                </FormField>
                <FormField label="Department" id="member-dept">
                    <input id="member-dept" type="text" value={form.department} onChange={set('department')} className={inputCls} />
                </FormField>
                <FormField label="Joined Date" id="member-joined">
                    <input id="member-joined" type="date" value={form.joined_date} onChange={set('joined_date')} className={inputCls} />
                </FormField>
            </div>
        </FormShell>
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
            onSuccess: () => setSubmitting(false),
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <FormShell onSubmit={handleSubmit} submitting={submitting} submitLabel="Submit Report">
            {serverError && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Impact Cell" id="report-cell" required>
                    <select id="report-cell" value={form.impact_cell_id} onChange={set('impact_cell_id')} required className={inputCls}>
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </FormField>
                <FormField label="Fellowship Date" id="report-date" required>
                    <input id="report-date" type="date" value={form.fellowship_date} onChange={set('fellowship_date')} required className={inputCls} />
                </FormField>
                <FormField label="Adults" id="report-adults" required>
                    <input id="report-adults" type="number" min="0" value={form.adults} onChange={set('adults')} required className={inputCls} />
                </FormField>
                <FormField label="Children" id="report-children">
                    <input id="report-children" type="number" min="0" value={form.children} onChange={set('children')} className={inputCls} />
                </FormField>
                <FormField label="First Timers" id="report-first">
                    <input id="report-first" type="number" min="0" value={form.first_timers} onChange={set('first_timers')} className={inputCls} />
                </FormField>
                <FormField label="New Members" id="report-new">
                    <input id="report-new" type="number" min="0" value={form.new_members} onChange={set('new_members')} className={inputCls} />
                </FormField>
                <FormField label="Offering (HQ)" id="report-offer-hq">
                    <input id="report-offer-hq" type="number" min="0" step="0.01" value={form.offering_hq} onChange={set('offering_hq')} className={inputCls} />
                </FormField>
                <FormField label="Offering (Centre)" id="report-offer-c">
                    <input id="report-offer-c" type="number" min="0" step="0.01" value={form.offering_centre} onChange={set('offering_centre')} className={inputCls} />
                </FormField>
            </div>
        </FormShell>
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
            onSuccess: () => setSubmitting(false),
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <FormShell onSubmit={handleSubmit} submitting={submitting} submitLabel="Save Childbirth Notice">
            {serverError && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Impact Cell" id="cb-cell" required>
                    <select id="cb-cell" value={form.impact_cell_id} onChange={set('impact_cell_id')} required className={inputCls}>
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </FormField>
                <FormField label="Child Name" id="cb-name" required>
                    <input id="cb-name" type="text" value={form.child_name} onChange={set('child_name')} required className={inputCls} />
                </FormField>
                <FormField label="Parent Name" id="cb-parent" required>
                    <input id="cb-parent" type="text" value={form.parent_name} onChange={set('parent_name')} required className={inputCls} />
                </FormField>
                <FormField label="Date of Birth" id="cb-dob" required>
                    <input id="cb-dob" type="date" value={form.date_of_birth} onChange={set('date_of_birth')} required className={inputCls} />
                </FormField>
                <FormField label="Gender" id="cb-gender" required>
                    <select id="cb-gender" value={form.gender} onChange={set('gender')} required className={inputCls}>
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </FormField>
                <FormField label="Phone" id="cb-phone">
                    <input id="cb-phone" type="text" value={form.phone} onChange={set('phone')} className={inputCls} />
                </FormField>
            </div>
        </FormShell>
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
            onSuccess: () => setSubmitting(false),
            onError: (errs) => { setSubmitting(false); setServerError(Object.values(errs).join(', ')); },
        });
    };

    const set = (k: string) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
        setForm(prev => ({ ...prev, [k]: e.target.value }));

    return (
        <FormShell onSubmit={handleSubmit} submitting={submitting} submitLabel="Save Soul">
            {serverError && <p className="rounded-md bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300">{serverError}</p>}
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <FormField label="Impact Cell" id="soul-cell" required>
                    <select id="soul-cell" value={form.impact_cell_id} onChange={set('impact_cell_id')} required className={inputCls}>
                        <option value="">Select cell</option>
                        {cells.map(c => <option key={c.id} value={c.id}>{c.name}</option>)}
                    </select>
                </FormField>
                <FormField label="Full Name" id="soul-name" required>
                    <input id="soul-name" type="text" value={form.full_name} onChange={set('full_name')} required className={inputCls} />
                </FormField>
                <FormField label="Phone" id="soul-phone" required>
                    <input id="soul-phone" type="text" value={form.phone} onChange={set('phone')} required className={inputCls} />
                </FormField>
                <FormField label="Gender" id="soul-gender">
                    <select id="soul-gender" value={form.gender} onChange={set('gender')} className={inputCls}>
                        <option value="">—</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </FormField>
                <FormField label="Occupation" id="soul-occupation">
                    <input id="soul-occupation" type="text" value={form.occupation} onChange={set('occupation')} className={inputCls} />
                </FormField>
                <FormField label="Marital Status" id="soul-marital">
                    <select id="soul-marital" value={form.marital_status} onChange={set('marital_status')} className={inputCls}>
                        <option value="">—</option>
                        <option value="Single">Single</option>
                        <option value="Married">Married</option>
                        <option value="Divorced">Divorced</option>
                        <option value="Widowed">Widowed</option>
                    </select>
                </FormField>
                <div className="sm:col-span-2">
                    <FormField label="Prayer Request" id="soul-prayer">
                        <textarea id="soul-prayer" value={form.prayer_request} onChange={set('prayer_request')} rows={3} className={inputCls} />
                    </FormField>
                </div>
            </div>
        </FormShell>
    );
}
