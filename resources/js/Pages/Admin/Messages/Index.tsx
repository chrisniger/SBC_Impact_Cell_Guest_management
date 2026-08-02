import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import Modal from '@/Components/Modal';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/**
 * Phase 34 — Admin Messages: in-app announcement board.
 *
 * Replaces the Phase 06d.0 "Coming soon" stub with a real board:
 *
 *   - Compose card (title + body) → POST /admin/messages
 *   - Announcement list (newest first) with author + relative date
 *   - Delete per announcement (confirm) → DELETE /admin/messages/{id}
 *
 * Announcements are in-app only — no email. Every authenticated user
 * sees them on their dashboard (DashboardController adds `announcements`
 * to every variant's payload). Only Administrator can post/delete here.
 */

interface AnnouncementRow {
    id: number;
    title: string;
    body: string;
    authorName: string;
    createdAt: string | null;
}

const msgIcon = <><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z" /></>;
const megaphoneIcon = <><path d="M3 11l18-6v14L3 13v-2z" /><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6" /></>;
const plusIcon = <><path d="M12 5v14" /><path d="M5 12h14" /></>;

function relativeTime(iso: string | null): string {
    if (!iso) return '—';
    const then = new Date(iso).getTime();
    const now = Date.now();
    const diff = Math.max(0, now - then);
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hours = Math.floor(mins / 60);
    if (hours < 24) return `${hours}h ago`;
    const days = Math.floor(hours / 24);
    if (days < 7) return `${days}d ago`;
    return new Date(iso).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function AdminMessagesIndex() {
    const { props } = usePage<any>();
    const announcements: AnnouncementRow[] = props.announcements ?? [];
    const flash = props.flash;

    const [composeOpen, setComposeOpen] = useState(false);

    return (
        <AdminDashboardLayout
            header={
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                            Administrator · Messages
                        </p>
                        <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Messages
                        </h2>
                        <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Post in-app announcements — every user sees them on their dashboard.
                        </p>
                    </div>
                    <PrimaryButton
                        type="button"
                        onClick={() => setComposeOpen(true)}
                        data-testid="messages-compose-open"
                        className="inline-flex items-center gap-2"
                    >
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                            {plusIcon}
                        </svg>
                        New announcement
                    </PrimaryButton>
                </div>
            }
        >
            <Head title="Messages · Admin" />

            {flash?.success && (
                <div className="mb-4 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800 dark:border-green-800/40 dark:bg-green-900/30 dark:text-green-200" role="status" data-testid="messages-flash-success">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true">
                        <path d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{flash.success}</span>
                </div>
            )}

            <div className="space-y-6">
                {/* ─────────── Announcement list ─────────── */}
                <section
                    className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="messages-list-card"
                >
                    <header className="flex flex-wrap items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {megaphoneIcon}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                            Announcements
                        </h3>
                        <span className="ml-auto text-xs text-gray-500 dark:text-gray-400">
                            {announcements.length} {announcements.length === 1 ? 'announcement' : 'announcements'}
                        </span>
                    </header>

                    {announcements.length === 0 ? (
                        <div className="p-10 text-center" data-testid="messages-empty">
                            <span className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-indigo-50 text-indigo-500 dark:bg-indigo-900/40 dark:text-indigo-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-6 w-6" aria-hidden="true">
                                    {msgIcon}
                                </svg>
                            </span>
                            <h3 className="mt-4 text-sm font-semibold text-gray-900 dark:text-white">No announcements yet</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                Post the first one — it will appear at the top of every user's dashboard.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y divide-gray-100 dark:divide-gray-700/60">
                            {announcements.map((a) => (
                                <li
                                    key={a.id}
                                    className="flex items-start gap-4 px-5 py-4 transition-colors hover:bg-indigo-50/30 dark:hover:bg-gray-700/30"
                                    data-testid={`announcement-row-${a.id}`}
                                >
                                    <span className="mt-0.5 inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                            {msgIcon}
                                        </svg>
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                            <h4 className="text-sm font-semibold text-gray-900 dark:text-white" data-testid={`announcement-title-${a.id}`}>
                                                {a.title}
                                            </h4>
                                            <span className="text-xs text-gray-400 dark:text-gray-500">
                                                {a.authorName} · {relativeTime(a.createdAt)}
                                            </span>
                                        </div>
                                        <p className="mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-600 dark:text-gray-400" data-testid={`announcement-body-${a.id}`}>
                                            {a.body}
                                        </p>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => {
                                            if (confirm(`Delete announcement "${a.title}"?`)) {
                                                router.delete(
                                                    `/admin/messages/${a.id}`,
                                                    { preserveScroll: true },
                                                );
                                            }
                                        }}
                                        title="Delete announcement"
                                        className="shrink-0 rounded-md border border-red-200 bg-white p-2 text-red-600 transition-colors hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500/40 dark:border-red-800/50 dark:bg-gray-800 dark:text-red-300 dark:hover:bg-red-900/30"
                                        data-testid={`announcement-delete-${a.id}`}
                                    >
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                            <path d="M3 6h18" />
                                            <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6" />
                                            <path d="M10 11v6" />
                                            <path d="M14 11v6" />
                                        </svg>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>

            <ComposeModal show={composeOpen} onClose={() => setComposeOpen(false)} dataTestId="messages-compose-modal" />
        </AdminDashboardLayout>
    );
}

/**
 * Compose-announcement modal: title + body → POST /admin/messages.
 */
function ComposeModal({
    show,
    onClose,
    dataTestId,
}: {
    show: boolean;
    onClose: () => void;
    dataTestId?: string;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        body: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/admin/messages', {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="2xl" dataTestId={dataTestId}>
            <form onSubmit={submit} className="space-y-5 p-6" data-testid="messages-compose-form">
                <div>
                    <h3 className="text-lg font-semibold text-gray-900 dark:text-white">New announcement</h3>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        This will appear at the top of every user's dashboard. No email is sent.
                    </p>
                </div>

                <div className="space-y-1.5">
                    <InputLabel htmlFor="announcement-title" value="Title" />
                    <TextInput
                        id="announcement-title"
                        value={data.title}
                        onChange={(e) => setData('title', e.target.value)}
                        className="block w-full"
                        placeholder="e.g. Cell leader meeting — Sunday 4pm"
                        required
                        data-testid="announcement-title-input"
                    />
                    <InputError message={errors.title} />
                </div>

                <div className="space-y-1.5">
                    <InputLabel htmlFor="announcement-body" value="Message" />
                    <textarea
                        id="announcement-body"
                        value={data.body}
                        onChange={(e) => setData('body', e.target.value)}
                        rows={6}
                        placeholder="Write the announcement…"
                        required
                        data-testid="announcement-body-input"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                    />
                    <InputError message={errors.body} />
                </div>

                <div className="flex items-center justify-end gap-2 pt-2">
                    <SecondaryButton type="button" onClick={onClose} disabled={processing}>Cancel</SecondaryButton>
                    <PrimaryButton type="submit" disabled={processing || !data.title.trim() || !data.body.trim()} data-testid="messages-compose-submit">
                        {processing ? 'Posting…' : 'Post announcement'}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
