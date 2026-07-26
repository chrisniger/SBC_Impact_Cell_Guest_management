import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import ContactsTimeline from '@/Components/ContactsTimeline';
import { Head, Link, useForm } from '@inertiajs/react';
import React, { FormEventHandler } from 'react';

/**
 * /guests/{id}/edit — Phase 05 follow-up.
 *
 * Render strategy is the source-of-truth pattern:
 *   - The server's `GuestController::edit()` runs every conceivable
 *     writable field through `RoleHelper::stripDisallowed()` and
 *     passes the surviving keys as `editableFields`.
 *   - We only render an `<input>` if `editableFields.includes(field)`.
 *   - The server-side `GuestRequest::prepareForValidation()` re-strips
 *     anything the role can't write — so this React-side filter is
 *     mirroring, not the security boundary. Backend is authoritative.
 *
 * The form pre-fills with the full guest resource (including
 * non-editable fields like `guest_name` for read-context display only);
 * if the officer clicks Save without touching anything, those fields
 * travel in the PUT body with their CURRENT values, and the server
 * strips them via `stripDisallowed`. That's defensive — a client that
 * tampers input still can't bypass the matrix.
 */

interface GuestDetail {
    [key: string]: any;
}

interface ImpactCellRow {
    id: string;
    name: string;
}

interface EditProps {
    guest: GuestDetail;
    editableFields: string[];
    impactCells: ImpactCellRow[];
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
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm';
const labelCls = 'block text-sm font-medium text-gray-700 dark:text-gray-300';

/**
 * Defense-in-depth against data loss for non-canonical enum values.
 *
 * If a guest's existing `contacted_status` (or `gender`, `marital_status`)
 * is a legacy / out-of-list value — e.g. `'NotInterested'` or
 * `'CallLater'` — a hardcoded `<select>` would render no matching
 * `<option>`, the browser would silently auto-select the first option
 * (typically ''), and clicking Save would overwrite the real value with
 * null/'/' after the server's nullable rule accepts that.
 *
 * Prepend the current value to the option list if it's not already
 * present, so the dropdown visually shows the EXISTING value as a
 * top option. The user must explicitly change it to be affected.
 */
function withCurrent(
    options: Array<{ value: string; label: string }>,
    currentValue: string | null,
): Array<{ value: string; label: string }> {
    if (!currentValue) return options;
    if (options.some((o) => o.value === currentValue)) return options;
    return [{ value: currentValue, label: `${currentValue} (legacy)` }, ...options];
}

export default function Edit({ guest, editableFields, impactCells, activeRole }: EditProps) {
    const { data, setData, put, processing, errors } = useForm<GuestDetail>({ ...guest });

    const canEdit = (field: string): boolean => editableFields.includes(field);
    const fieldErr = (field: string): React.ReactNode =>
        errors[field] ? (
            <p className="mt-1 text-sm text-red-600 dark:text-red-400">{errors[field]}</p>
        ) : null;

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // Defense-in-depth: send ONLY the columns THIS role can write.
        // The server's `GuestRequest::prepareForValidation()` re-strips via
        // `RoleHelper::stripDisallowed` — this is the FE-side mirror so a
        // patch to one side can't accidentally regress the other.
        const allowed = Object.fromEntries(
            Object.entries(data).filter(([key]) => canEdit(key)),
        );
        put(route('guests.update', guest.id), allowed);
    };

    const nothingEditable = editableFields.length === 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                            Edit: {guest.guest_name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Editing as <span className="font-mono">{activeRole ?? '—'}</span> &middot;{' '}
                            {nothingEditable
                                ? <span className="text-amber-600 dark:text-amber-400">no columns owned</span>
                                : <span>{editableFields.length} writable column{editableFields.length === 1 ? '' : 's'}</span>}
                            . Server strips anything you can't write.
                        </p>
                    </div>
                </div>
            }
        >
            <Head title={`Edit ${guest.guest_name}`} />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm dark:bg-gray-800 sm:rounded-lg">

                        {nothingEditable && (
                            <div className="mb-6 rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-200">
                                Your active role does not own any guest columns for write. The view on{' '}
                                <Link href={route('guests.show', guest.id)} className="underline">/guests/{guest.id}</Link>{' '}
                                is read-only for you.
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-6">

                            {/* ── CORE (Admin) ────────────────────────────────────────── */}
                            {canEdit('guest_name') && (
                                <div>
                                    <label htmlFor="guest_name" className={labelCls}>Guest name</label>
                                    <input id="guest_name" type="text" value={data.guest_name ?? ''}
                                        onChange={(e) => setData('guest_name', e.target.value)} className={inputCls} />
                                    {fieldErr('guest_name')}
                                </div>
                            )}
                            {canEdit('date') && (
                                <div>
                                    <label htmlFor="date" className={labelCls}>Date</label>
                                    <input id="date" type="date" value={(data.date ?? '').slice(0, 10)}
                                        onChange={(e) => setData('date', e.target.value)} className={inputCls} />
                                    {fieldErr('date')}
                                </div>
                            )}
                            {canEdit('event') && (
                                <div>
                                    <label htmlFor="event" className={labelCls}>Event</label>
                                    <input id="event" type="text" value={data.event ?? ''}
                                        onChange={(e) => setData('event', e.target.value)} className={inputCls} />
                                    {fieldErr('event')}
                                </div>
                            )}
                            {canEdit('source') && (
                                <div>
                                    <label htmlFor="source" className={labelCls}>Source</label>
                                    <input id="source" type="text" value={data.source ?? ''}
                                        onChange={(e) => setData('source', e.target.value)} className={inputCls} />
                                    {fieldErr('source')}
                                </div>
                            )}
                            {canEdit('event_other') && (
                                <div>
                                    <label htmlFor="event_other" className={labelCls}>Event other</label>
                                    <input id="event_other" type="text" value={data.event_other ?? ''}
                                        onChange={(e) => setData('event_other', e.target.value)} className={inputCls} />
                                    {fieldErr('event_other')}
                                </div>
                            )}

                            {/* ── FOLLOW UP OFFICER GROUP ──────────────────────────────── */}
                            <fieldset className="border-t border-gray-200 pt-6 dark:border-gray-700">
                                <legend className="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                    Follow-up officer group
                                </legend>
                                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">

                                    {canEdit('gender') && (
                                        <div>
                                            <label htmlFor="gender" className={labelCls}>Gender</label>
                                            <select id="gender" value={data.gender ?? ''}
                                                onChange={(e) => setData('gender', e.target.value || null)} className={inputCls}>
                                                {withCurrent(GENDER_OPTIONS, data.gender).map((o) => (
                                                    <option key={o.value} value={o.value}>{o.label}</option>
                                                ))}
                                            </select>
                                            {fieldErr('gender')}
                                        </div>
                                    )}
                                    {canEdit('marital_status') && (
                                        <div>
                                            <label htmlFor="marital_status" className={labelCls}>Marital status</label>
                                            <select id="marital_status" value={data.marital_status ?? ''}
                                                onChange={(e) => setData('marital_status', e.target.value || null)} className={inputCls}>
                                                {withCurrent(MARITAL_OPTIONS, data.marital_status).map((o) => (
                                                    <option key={o.value} value={o.value}>{o.label}</option>
                                                ))}
                                            </select>
                                            {fieldErr('marital_status')}
                                        </div>
                                    )}
                                    {canEdit('age') && (
                                        <div>
                                            <label htmlFor="age" className={labelCls}>Age</label>
                                            <input id="age" type="text" value={data.age ?? ''}
                                                onChange={(e) => setData('age', e.target.value)} className={inputCls} />
                                            {fieldErr('age')}
                                        </div>
                                    )}
                                    {canEdit('phone') && (
                                        <div>
                                            <label htmlFor="phone" className={labelCls}>Phone</label>
                                            <input id="phone" type="tel" value={data.phone ?? ''}
                                                onChange={(e) => setData('phone', e.target.value)} className={inputCls} />
                                            {fieldErr('phone')}
                                        </div>
                                    )}
                                    {canEdit('join_when') && (
                                        <div>
                                            <label htmlFor="join_when" className={labelCls}>Join when</label>
                                            <input id="join_when" type="text" value={data.join_when ?? ''}
                                                onChange={(e) => setData('join_when', e.target.value)} className={inputCls} />
                                            {fieldErr('join_when')}
                                        </div>
                                    )}
                                    {canEdit('days_available') && (
                                        <div>
                                            <label htmlFor="days_available" className={labelCls}>Days available</label>
                                            <input id="days_available" type="text" value={data.days_available ?? ''}
                                                onChange={(e) => setData('days_available', e.target.value)} className={inputCls} />
                                            {fieldErr('days_available')}
                                        </div>
                                    )}
                                    {canEdit('contacted_status') && (
                                        <div className="sm:col-span-2">
                                            <label htmlFor="contacted_status" className={labelCls}>Contacted status</label>
                                            <select id="contacted_status" value={data.contacted_status ?? ''}
                                                onChange={(e) => setData('contacted_status', e.target.value || null)} className={inputCls}>
                                                {withCurrent(CONTACTED_STATUS_OPTIONS, data.contacted_status).map((o) => (
                                                    <option key={o.value} value={o.value}>{o.label}</option>
                                                ))}
                                            </select>
                                            {fieldErr('contacted_status')}
                                        </div>
                                    )}
                                    {canEdit('address') && (
                                        <div className="sm:col-span-2">
                                            <label htmlFor="address" className={labelCls}>Address</label>
                                            <textarea id="address" rows={3} value={data.address ?? ''}
                                                onChange={(e) => setData('address', e.target.value)} className={inputCls} />
                                            {fieldErr('address')}
                                        </div>
                                    )}
                                    {canEdit('comments') && (
                                        <div className="sm:col-span-2">
                                            <label htmlFor="comments" className={labelCls}>Comments</label>
                                            <textarea id="comments" rows={3} value={data.comments ?? ''}
                                                onChange={(e) => setData('comments', e.target.value)} className={inputCls} />
                                            {fieldErr('comments')}
                                        </div>
                                    )}
                                    {canEdit('visited') && (
                                        <div>
                                            <label className="flex items-center">
                                                <input type="checkbox" checked={!!data.visited}
                                                    onChange={(e) => setData('visited', e.target.checked)}
                                                    className="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:border-gray-700 dark:bg-gray-900" />
                                                <span className="ml-2 text-sm text-gray-700 dark:text-gray-300">Visited</span>
                                            </label>
                                            {fieldErr('visited')}
                                        </div>
                                    )}
                                    {canEdit('visited_at') && (
                                        <div>
                                            <label htmlFor="visited_at" className={labelCls}>Visited at</label>
                                            <input id="visited_at" type="text" value={data.visited_at ?? ''}
                                                onChange={(e) => setData('visited_at', e.target.value)} className={inputCls} />
                                            {fieldErr('visited_at')}
                                        </div>
                                    )}
                                    {canEdit('indicated_to_join') && (
                                        <div>
                                            <label htmlFor="indicated_to_join" className={labelCls}>Indicated to join</label>
                                            <input id="indicated_to_join" type="text" value={data.indicated_to_join ?? ''}
                                                onChange={(e) => setData('indicated_to_join', e.target.value)} className={inputCls} />
                                            {fieldErr('indicated_to_join')}
                                        </div>
                                    )}
                                    {canEdit('visitation_status') && (
                                        <div>
                                            <label htmlFor="visitation_status" className={labelCls}>Visitation status</label>
                                            <input id="visitation_status" type="text" value={data.visitation_status ?? ''}
                                                onChange={(e) => setData('visitation_status', e.target.value)} className={inputCls} />
                                            {fieldErr('visitation_status')}
                                        </div>
                                    )}
                                    {canEdit('feedback') && (
                                        <div className="sm:col-span-2">
                                            <label htmlFor="feedback" className={labelCls}>Feedback</label>
                                            <textarea id="feedback" rows={4} value={data.feedback ?? ''}
                                                onChange={(e) => setData('feedback', e.target.value)} className={inputCls} />
                                            {fieldErr('feedback')}
                                        </div>
                                    )}
                                </div>
                            </fieldset>

                            {/* ── IMPACT CELL GROUP ────────────────────────────────────── */}
                            {(canEdit('nearest_impact_cell_id') || canEdit('impact_status')) && (
                                <fieldset className="border-t border-gray-200 pt-6 dark:border-gray-700">
                                    <legend className="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                        Impact cell group
                                    </legend>
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                        {canEdit('nearest_impact_cell_id') && (
                                            <div>
                                                <label htmlFor="nearest_impact_cell_id" className={labelCls}>Nearest impact cell</label>
                                                <select id="nearest_impact_cell_id"
                                                    value={data.nearest_impact_cell_id ?? ''}
                                                    onChange={(e) => setData('nearest_impact_cell_id', e.target.value || null)}
                                                    className={inputCls}>
                                                    <option value="">— (unset) —</option>
                                                    {impactCells.map((c) => (
                                                        <option key={c.id} value={c.id}>{c.name}</option>
                                                    ))}
                                                </select>
                                                {fieldErr('nearest_impact_cell_id')}
                                            </div>
                                        )}
                                        {canEdit('impact_status') && (
                                            <div>
                                                <label htmlFor="impact_status" className={labelCls}>Impact status</label>
                                                <input id="impact_status" type="text" value={data.impact_status ?? ''}
                                                    onChange={(e) => setData('impact_status', e.target.value)} className={inputCls} />
                                                {fieldErr('impact_status')}
                                            </div>
                                        )}
                                    </div>
                                </fieldset>
                            )}

                            {/* ── FOLLOW UP TEAM GROUP ─────────────────────────────────── */}
                            {(canEdit('follow_up_status') || canEdit('follow_up_contacts')) && (
                                <fieldset className="border-t border-gray-200 pt-6 dark:border-gray-700">
                                    <legend className="mb-4 text-sm font-semibold uppercase tracking-wider text-gray-500">
                                        Follow-up team group
                                    </legend>
                                    <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                        {canEdit('follow_up_status') && (
                                            <div>
                                                <label htmlFor="follow_up_status" className={labelCls}>Follow-up status</label>
                                                <select id="follow_up_status" value={data.follow_up_status ?? ''}
                                                    onChange={(e) => setData('follow_up_status', e.target.value || null)} className={inputCls}>
                                                    <option value="">— (unset) —</option>
                                                    <option value="NOT CONTACTED">Not Contacted</option>
                                                    <option value="CONTACTED">Contacted</option>
                                                    <option value="WRONG NUMBER">Wrong Number</option>
                                                    <option value="NOT REACHABLE">Not Reachable</option>
                                                </select>
                                                {fieldErr('follow_up_status')}
                                            </div>
                                        )}
                                    </div>
                                    {canEdit('follow_up_contacts') && (
                                        <div className="mt-4 border-t border-gray-100 pt-4 dark:border-gray-700">
                                            <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
                                                Contact Sections
                                            </h4>
                                            <ContactsTimeline
                                                contacts={data.follow_up_contacts ?? []}
                                                editable={true}
                                                onChange={(updated) => setData('follow_up_contacts', updated)}
                                            />
                                        </div>
                                    )}
                                </fieldset>
                            )}

                            {/* ── ACTIONS ──────────────────────────────────────────────── */}
                            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4 dark:border-gray-700">
                                <Link
                                    href={route('guests.show', guest.id)}
                                    className="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                                    Cancel
                                </Link>
                                <button
                                    type="submit"
                                    disabled={processing || nothingEditable}
                                    className="inline-flex items-center rounded-md border border-transparent bg-gray-800 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm ring-gray-300 transition duration-150 ease-in-out hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-25 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-white dark:focus:ring-offset-gray-800">
                                    {processing ? 'Saving…' : 'Save'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
