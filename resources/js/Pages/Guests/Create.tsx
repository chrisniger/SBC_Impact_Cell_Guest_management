import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, ReactNode } from 'react';

interface GuestData {
    [key: string]: any;
}

interface ImpactCellRow {
    id: string;
    name: string;
}

interface OfficerRow {
    id: string;
    name: string;
}

interface CreateProps {
    editableFields: string[];
    impactCells: ImpactCellRow[];
    /** Admin-only roster of follow-up officers (empty for non-admins — they self-assign). */
    officers: OfficerRow[];
    /** Phase 39 — canonical impact_status values (server passes Guest::IMPACT_STATUSES). */
    impactStatusOptions?: string[];
    activeRole: string | null;
}

const CONTACTED_STATUS_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '',                    label: '— (unspecified) —' },
    { value: 'No',                  label: 'No (declined / unreachable)' },
    { value: 'Contacted',           label: 'Contacted' },
    { value: 'AvailableForVisit',   label: 'Available For Visit' },
    { value: 'Visited',             label: 'Visited' },
];

const GENDER_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '',       label: '—' },
    { value: 'Male',   label: 'Male' },
    { value: 'Female', label: 'Female' },
];

const MARITAL_OPTIONS: Array<{ value: string; label: string }> = [
    { value: '',         label: '—' },
    { value: 'Single',   label: 'Single' },
    { value: 'Married',  label: 'Married' },
    { value: 'Divorced', label: 'Divorced' },
    { value: 'Widowed',  label: 'Widowed' },
];

const inputCls =
    'mt-1 block w-full rounded-lg border-gray-300 bg-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm';
const labelCls = 'block text-sm font-semibold text-gray-700 dark:text-gray-200';

function FormField({
    label,
    id,
    children,
    error,
}: {
    label: string;
    id: string;
    children: ReactNode;
    error?: string;
}) {
    return (
        <div>
            <label htmlFor={id} className={labelCls}>{label}</label>
            {children}
            {error && <p className="mt-1 text-sm text-red-600 dark:text-red-400">{error}</p>}
        </div>
    );
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
            <div className="px-5 py-5">{children}</div>
        </section>
    );
}

const userIcon = <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /></>;
const phoneIcon = <><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z" /></>;
const cellIcon = <><path d="M3 3h18v18H3z" /><path d="M3 9h18M9 21V9" /></>;
const teamIcon = <><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>;

export default function Create({ editableFields, impactCells, officers, impactStatusOptions, activeRole }: CreateProps) {
    const { data, setData, post, processing, errors } = useForm<GuestData>({});

    const canEdit = (field: string): boolean => editableFields.includes(field);
    const fieldErr = (field: string): string | undefined => errors[field] as string | undefined;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        const allowed = Object.fromEntries(
            Object.entries(data).filter(([key]) => canEdit(key)),
        );
        post(route('guests.store'), allowed);
    };

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        New Guest
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Add Guest
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Creating as <span className="font-mono">{activeRole ?? '—'}</span> ·{' '}
                        {editableFields.length} writable field{editableFields.length === 1 ? '' : 's'}
                        {' '}· <span className="text-gray-500">only fields your role owns are shown</span>
                    </p>
                </div>
            }
        >
            <Head title="Add Guest" />

            <form onSubmit={submit} className="space-y-6">
                {(canEdit('guest_name') || canEdit('date') || canEdit('event') || canEdit('event_other')) && (
                    <Card title="Core" iconPath={userIcon} testId="card-create-core">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            {canEdit('guest_name') && (
                                <FormField label="Guest name" id="guest_name" error={fieldErr('guest_name')}>
                                    <input id="guest_name" type="text" value={data.guest_name ?? ''}
                                        onChange={(e) => setData('guest_name', e.target.value)} className={inputCls} />
                                </FormField>
                            )}
                            {canEdit('date') && (
                                <FormField label="Date" id="date" error={fieldErr('date')}>
                                    <input id="date" type="date" value={(data.date ?? '').slice(0, 10)}
                                        onChange={(e) => setData('date', e.target.value)} className={inputCls} />
                                </FormField>
                            )}
                            {canEdit('event') && (
                                <FormField label="Event" id="event" error={fieldErr('event')}>
                                    <input id="event" type="text" value={data.event ?? ''}
                                        onChange={(e) => setData('event', e.target.value)} className={inputCls} />
                                </FormField>
                            )}
                            {canEdit('event_other') && (
                                <FormField label="Event other" id="event_other" error={fieldErr('event_other')}>
                                    <input id="event_other" type="text" value={data.event_other ?? ''}
                                        onChange={(e) => setData('event_other', e.target.value)} className={inputCls} />
                                </FormField>
                            )}
                        </div>
                    </Card>
                )}

                <Card title="Follow-Up Officer" iconPath={phoneIcon} testId="card-create-officer">
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        {officers.length > 0 && (
                            <FormField label="Assign follow-up officer" id="follow_officer_id" error={fieldErr('follow_officer_id')}>
                                <select id="follow_officer_id" value={data.follow_officer_id ?? ''}
                                    onChange={(e) => setData('follow_officer_id', e.target.value || null)} className={inputCls}>
                                    <option value="">— (unassigned) —</option>
                                    {officers.map((o) => (
                                        <option key={o.id} value={o.id}>{o.name}</option>
                                    ))}
                                </select>
                            </FormField>
                        )}
                        {canEdit('gender') && (
                            <FormField label="Gender" id="gender" error={fieldErr('gender')}>
                                <select id="gender" value={data.gender ?? ''}
                                    onChange={(e) => setData('gender', e.target.value || null)} className={inputCls}>
                                    {GENDER_OPTIONS.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                            </FormField>
                        )}
                        {canEdit('marital_status') && (
                            <FormField label="Marital status" id="marital_status" error={fieldErr('marital_status')}>
                                <select id="marital_status" value={data.marital_status ?? ''}
                                    onChange={(e) => setData('marital_status', e.target.value || null)} className={inputCls}>
                                    {MARITAL_OPTIONS.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                            </FormField>
                        )}
                        {canEdit('age') && (
                            <FormField label="Age" id="age" error={fieldErr('age')}>
                                <input id="age" type="text" value={data.age ?? ''}
                                    onChange={(e) => setData('age', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('phone') && (
                            <FormField label="Phone" id="phone" error={fieldErr('phone')}>
                                <input id="phone" type="tel" value={data.phone ?? ''}
                                    onChange={(e) => setData('phone', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('email') && (
                            <FormField label="Email" id="email" error={fieldErr('email')}>
                                <input id="email" type="email" value={data.email ?? ''}
                                    onChange={(e) => setData('email', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('join_when') && (
                            <FormField label="Join when" id="join_when" error={fieldErr('join_when')}>
                                <input id="join_when" type="text" value={data.join_when ?? ''}
                                    onChange={(e) => setData('join_when', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('days_available') && (
                            <FormField label="Days available" id="days_available" error={fieldErr('days_available')}>
                                <input id="days_available" type="text" value={data.days_available ?? ''}
                                    onChange={(e) => setData('days_available', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('contacted_status') && (
                            <FormField label="Contacted status" id="contacted_status" error={fieldErr('contacted_status')}>
                                <select id="contacted_status" value={data.contacted_status ?? ''}
                                    onChange={(e) => setData('contacted_status', e.target.value || null)} className={inputCls}>
                                    {CONTACTED_STATUS_OPTIONS.map((o) => (
                                        <option key={o.value} value={o.value}>{o.label}</option>
                                    ))}
                                </select>
                            </FormField>
                        )}
                        {canEdit('address') && (
                            <FormField label="Address" id="address" error={fieldErr('address')}>
                                <textarea id="address" rows={3} value={data.address ?? ''}
                                    onChange={(e) => setData('address', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('comments') && (
                            <FormField label="Comments" id="comments" error={fieldErr('comments')}>
                                <textarea id="comments" rows={3} value={data.comments ?? ''}
                                    onChange={(e) => setData('comments', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('visited') && (
                            <div className="flex items-center gap-2 pt-5">
                                <input id="visited" type="checkbox" checked={!!data.visited}
                                    onChange={(e) => setData('visited', e.target.checked)}
                                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-gray-700 dark:bg-gray-900" />
                                <label htmlFor="visited" className="text-sm text-gray-700 dark:text-gray-300">Visited</label>
                            </div>
                        )}
                        {canEdit('visited_at') && (
                            <FormField label="Visited at" id="visited_at" error={fieldErr('visited_at')}>
                                <input id="visited_at" type="text" value={data.visited_at ?? ''}
                                    onChange={(e) => setData('visited_at', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('indicated_to_join') && (
                            <FormField label="Indicated to join" id="indicated_to_join" error={fieldErr('indicated_to_join')}>
                                <input id="indicated_to_join" type="text" value={data.indicated_to_join ?? ''}
                                    onChange={(e) => setData('indicated_to_join', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('visitation_status') && (
                            <FormField label="Visitation status" id="visitation_status" error={fieldErr('visitation_status')}>
                                <input id="visitation_status" type="text" value={data.visitation_status ?? ''}
                                    onChange={(e) => setData('visitation_status', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                        {canEdit('feedback') && (
                            <FormField label="Feedback" id="feedback" error={fieldErr('feedback')}>
                                <textarea id="feedback" rows={4} value={data.feedback ?? ''}
                                    onChange={(e) => setData('feedback', e.target.value)} className={inputCls} />
                            </FormField>
                        )}
                    </div>
                </Card>

                {(canEdit('nearest_impact_cell_id') || canEdit('impact_status')) && (
                    <Card title="Impact Cell" iconPath={cellIcon} testId="card-create-cell">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            {canEdit('nearest_impact_cell_id') && (
                                <FormField label="Nearest impact cell" id="nearest_impact_cell_id" error={fieldErr('nearest_impact_cell_id')}>
                                    <select id="nearest_impact_cell_id"
                                        value={data.nearest_impact_cell_id ?? ''}
                                        onChange={(e) => setData('nearest_impact_cell_id', e.target.value || null)}
                                        className={inputCls}>
                                        <option value="">— (unset) —</option>
                                        {impactCells.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                </FormField>
                            )}
                            {canEdit('impact_status') && (
                                <FormField label="Impact status" id="impact_status" error={fieldErr('impact_status')}>
                                    <select id="impact_status" value={data.impact_status ?? ''}
                                        onChange={(e) => setData('impact_status', e.target.value || null)} className={inputCls}>
                                        <option value="">— (unset) —</option>
                                        {(impactStatusOptions ?? ['Contacted', 'Not Contacted', 'Not Reachable']).map((v) => (
                                            <option key={v} value={v}>{v}</option>
                                        ))}
                                    </select>
                                </FormField>
                            )}
                        </div>
                    </Card>
                )}

                {canEdit('follow_up_status') && (
                    <Card title="Follow-Up Team" iconPath={teamIcon} testId="card-create-team">
                        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                            {canEdit('follow_up_status') && (
                                <FormField label="Follow-up status" id="follow_up_status" error={fieldErr('follow_up_status')}>
                                    <select id="follow_up_status" value={data.follow_up_status ?? ''}
                                        onChange={(e) => setData('follow_up_status', e.target.value || null)} className={inputCls}>
                                        <option value="">— (unset) —</option>
                                        <option value="NOT CONTACTED">Not Contacted</option>
                                        <option value="CONTACTED">Contacted</option>
                                        <option value="WRONG NUMBER">Wrong Number</option>
                                        <option value="NOT REACHABLE">Not Reachable</option>
                                    </select>
                                </FormField>
                            )}
                        </div>
                    </Card>
                )}

                {/* Sticky save bar — mirrors Edit.tsx */}
                <div className="flex items-center justify-end gap-3 sticky bottom-0 z-10 rounded-xl border border-gray-200 bg-white/90 p-4 shadow-[0_-4px_20px_rgba(0,0,0,0.03)] backdrop-blur-md dark:border-gray-700 dark:bg-gray-800/90">
                    <Link
                        href={route('guests.index')}
                        className="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                    >
                        Cancel
                    </Link>
                    <button
                        type="submit"
                        disabled={processing}
                        data-testid="create-guest-button"
                        className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing ? (
                            <>
                                <svg viewBox="0 0 24 24" fill="none" className="h-4 w-4 animate-spin" aria-hidden="true">
                                    <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                    <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                                </svg>
                                Saving…
                            </>
                        ) : (
                            <>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    <path d="M12 5v14M5 12h14" />
                                </svg>
                                Create Guest
                            </>
                        )}
                    </button>
                </div>
            </form>
        </AdminDashboardLayout>
    );
}
