import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import StatusPill from '@/Components/StatusPill';
import { Head, Link, useForm } from '@inertiajs/react';

/**
 * Phase 17 + Phase 35 — /impact-cells/create Inertia page (Administrator only).
 * Phase 35 made Impact_Cell_Admin read-only on the Impact Cells surface,
 * so the "Add new cell" entry point (and this page) is Administrator-only.
 * The route itself is gated server-side by ImpactCellPolicy::create.
 *
 * Standard admin form for adding a new impact cell. The user picks at
 * creation time whether this cell is a primary standalone cell OR a
 * sub-cell that reports to an existing primary. When `is_primary` is
 * false, the parent_picker dropdown is rendered (filtered client-side
 * from `primaries` already smart-loaded server-side).
 *
 * On submit, POSTs to `/impact-cells` (existing ImpactCellController::store
 * route). Server runs `validateCell()` + `hierarchyRulesOrThrow()` and
 * redirects to `/impact-cells/{newId}` so admin lands on the freshly
 * created cell's Show page.
 *
 * Edge case: when no primary cells exist yet, the page forces
 * `is_primary=true` and hides the toggle — admin must create at least
 * one primary before they can start attaching sub-cells. The empty-
 * picker warning keeps the form self-explanatory.
 */

interface PrimaryCellRef {
    id: string;
    name: string;
}

interface CreatePayload {
    name: string;
    phone: string;
    address: string;
    is_primary: boolean;
    parent_cell_id: string;
    order: number;
}

const plusIcon = <><path d="M12 5v14" /><path d="M5 12h14" /></>;
const arrowLeftIcon = <><path d="m12 19-7-7 7-7" /><path d="M19 12H5" /></>;

export default function ImpactCellsCreate({ primaries, activeRole }: { primaries: PrimaryCellRef[]; activeRole: string | null; }) {
    const noPrimaries = primaries.length === 0;
    const form = useForm<CreatePayload>({
        name: '',
        phone: '',
        address: '',
        // Force primary when no primaries exist yet — sub-cell creation is
        // a no-op without a parent to attach to. Admin can flip the toggle
        // back to false once they've created at least one primary and
        // refreshed this page.
        is_primary: noPrimaries ? true : true,
        parent_cell_id: '',
        order: 0,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/impact-cells');
    };

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <nav className="flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                        <Link href={route('impact-cells.index')} className="transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">
                            Impact Cells
                        </Link>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-3 w-3" aria-hidden="true">
                            {arrowLeftIcon}
                        </svg>
                        <span className="text-gray-700 dark:text-gray-300">Add new cell</span>
                    </nav>
                    <div className="mt-2 flex items-center gap-3">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Add Impact Cell</h2>
                    </div>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Active role: <span className="font-mono">{activeRole ?? '—'}</span>
                    </p>
                </div>
            }
        >
            <Head title="Add Impact Cell" />

            <div className="mx-auto max-w-3xl space-y-6">
                {noPrimaries && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700/50 dark:bg-amber-900/20 dark:text-amber-200">
                        <p className="font-medium">No primary cells exist yet.</p>
                        <p className="mt-1">This form will create the first cell as a primary. Once at least one primary is saved, return here and you can flip the toggle to add sub-cells under it.</p>
                    </div>
                )}
                <form onSubmit={submit} className="space-y-6 rounded-xl border border-gray-200 bg-white p-6 shadow-card dark:border-gray-700 dark:bg-gray-800" data-testid="impact-cell-create-form">
                    <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                        <Field label="Cell name" id="name" form={form} required maxLength={255} />
                        <Field label="Phone" id="phone" form={form} maxLength={32} placeholder="Optional" />
                    </div>
                    <Field label="Address" id="address" form={form} maxLength={255} placeholder="Optional" />

                    <div className="space-y-2">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <InputLabel htmlFor="is_primary" value="Cell type" />
                                <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Primary cells are root anchors. Sub-cells report to a primary cell (their parent).
                                </p>
                            </div>
                            <div className="flex items-center gap-2">
                                <StatusPill tone="brand" dot>{form.data.is_primary ? 'Primary' : 'Sub-cell'}</StatusPill>
                                <label className="inline-flex cursor-pointer items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.is_primary}
                                        disabled={noPrimaries}
                                        onChange={(e) => form.setData('is_primary', e.target.checked)}
                                        className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:cursor-not-allowed disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800"
                                        data-testid="impact-cell-create-is-primary-toggle"
                                    />
                                    <span className="text-xs font-medium text-gray-700 dark:text-gray-300">{form.data.is_primary ? 'Primary' : 'Sub-cell'}</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    {! form.data.is_primary && (
                        <div className="space-y-1.5">
                            <InputLabel htmlFor="parent_cell_id" value="Parent primary cell" />
                            <select
                                id="parent_cell_id"
                                name="parent_cell_id"
                                value={form.data.parent_cell_id}
                                onChange={(e) => form.setData('parent_cell_id', e.target.value)}
                                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                data-testid="impact-cell-create-parent-select"
                                required
                            >
                                <option value="">— Select parent —</option>
                                {primaries.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name}</option>
                                ))}
                            </select>
                            <InputError message={form.errors.parent_cell_id} />
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                Sub-cells report up to exactly one primary cell. Admins can re-parent later from the Show page.
                            </p>
                        </div>
                    )}

                    <div className="max-w-xs">
                        <Field label="Order" id="order" type="number" form={form} />
                        <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">Lower numbers render first. 0 = append.</p>
                    </div>

                    <div className="flex items-center justify-end gap-2 border-t border-gray-100 pt-5 dark:border-gray-700">
                        <Link href={route('impact-cells.index')} className="inline-flex items-center rounded-md border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            Cancel
                        </Link>
                        <PrimaryButton type="submit" disabled={form.processing} data-testid="impact-cell-create-submit">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="mr-1.5 h-3.5 w-3.5" aria-hidden="true">
                                {plusIcon}
                            </svg>
                            {form.processing ? 'Saving…' : 'Create cell'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AdminDashboardLayout>
    );
}

/**
 * Inline editable text-input row bound to the page's `form` (useForm).
 * Mirrors the Show.tsx Leadership Team pattern, including InputError +
 * typed setData call (no casts).
 */
function Field({ label, id, form, type = 'text', required = false, maxLength, placeholder }: {
    label: string;
    id: keyof CreatePayload;
    form: ReturnType<typeof useForm<CreatePayload>>;
    type?: 'text' | 'number';
    required?: boolean;
    maxLength?: number;
    placeholder?: string;
}) {
    const value = form.data[id];
    const error = form.errors[id];
    return (
        <div className="space-y-1.5">
            <InputLabel htmlFor={id} value={label} />
            <input
                id={id}
                name={id}
                type={type}
                value={value as string | number}
                onChange={(e) => form.setData(id, type === 'number' ? Number(e.target.value) : e.target.value)}
                className="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                data-testid={`impact-cell-create-${id}`}
                required={required}
                maxLength={maxLength}
                placeholder={placeholder}
                min={type === 'number' ? 0 : undefined}
            />
            <InputError message={error} />
        </div>
    );
}

/** Re-export so the bundler keeps the import tree-shaken correctly. */
export { SecondaryButton };
