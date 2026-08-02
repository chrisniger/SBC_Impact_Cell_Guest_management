import AdminDashboardLayout from '@/Layouts/AdminDashboardLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

/**
 * Phase 33 — Admin Settings page (SMTP configuration + Backup & Restore).
 *
 * SMTP card:
 *   - Reads current values from the server payload (`smtp`), password is
 *     masked (`password_set` only).
 *   - "Save SMTP" POSTs to /admin/settings/smtp (writes .env server-side).
 *   - "Send test email" POSTs the CURRENT FORM values to
 *     /admin/settings/smtp/test so the admin can verify a candidate
 *     config WITHOUT saving it first (server applies on-the-fly).
 *
 * Backup card:
 *   - Four download buttons (Full / Impact Cell / Follow Up Officer /
 *     Follow Up Team) that trigger a GET streamed JSON download.
 *   - Restore accepts ONLY a full backup archive; requires typing
 *     RESTORE to arm the upload (destructive, wipes business tables).
 */

interface SmtpPayload {
    mailer: string;
    host: string;
    port: string;
    username: string;
    password_set: boolean;
    scheme: string;
    from_address: string;
    from_name: string;
}

const mailIcon = <><rect x="2.5" y="4.5" width="19" height="15" rx="2" /><path d="m3 7 9 6.5 9-6.5" /></>;
const serverIcon = <><rect x="2" y="3" width="20" height="7" rx="2" /><rect x="2" y="14" width="20" height="7" rx="2" /><line x1="6" y1="6.5" x2="6.01" y2="6.5" /><line x1="6" y1="17.5" x2="6.01" y2="17.5" /></>;
const downloadIcon = <><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><polyline points="7 10 12 15 17 10" /><line x1="12" y1="15" x2="12" y2="3" /></>;
const restoreIcon = <><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" /><path d="M3 3v5h5" /></>;
const shieldIcon = <><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></>;

const inputClass =
    'mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300';

export default function Settings() {
    // `any` matches the existing page convention (Notifications/Settings.tsx)
    // and avoids the Inertia PageProps `auth`-required constraint.
    const { props } = usePage<any>();
    const smtp = props.smtp ?? { mailer: 'log', host: '', port: '', username: '', password_set: false, scheme: '', from_address: '', from_name: '' };
    const mailConfigured: boolean = props.mailConfigured ?? false;
    const backupScopes: string[] = props.backupScopes ?? [];

    const [form, setForm] = useState({
        mailer: smtp.mailer,
        host: smtp.host,
        port: smtp.port,
        username: smtp.username,
        password: '',
        scheme: smtp.scheme,
        from_address: smtp.from_address,
        from_name: smtp.from_name,
    });

    const [saving, setSaving] = useState(false);
    const [testing, setTesting] = useState(false);
    const [testRecipient, setTestRecipient] = useState('');
    const [testResult, setTestResult] = useState<{ sent: boolean; message: string } | null>(null);
    const [confirmText, setConfirmText] = useState('');
    const [restoring, setRestoring] = useState(false);
    const [restoreResult, setRestoreResult] = useState<{ ok: boolean; message: string } | null>(null);

    const set = <K extends keyof typeof form>(key: K, value: (typeof form)[K]) =>
        setForm(prev => ({ ...prev, [key]: value }));

    const handleSave: FormEventHandler = e => {
        e.preventDefault();
        if (saving) return;
        setSaving(true);
        router.post('/admin/settings/smtp', form, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    const csrfToken = () =>
        (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null)?.content ?? '';

    const handleTestEmail = async () => {
        if (testing) return;
        if (!testRecipient) {
            setTestResult({ sent: false, message: 'Enter a recipient email to send the test to.' });
            return;
        }
        setTesting(true);
        setTestResult(null);
        try {
            const res = await fetch('/admin/settings/smtp/test', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body: JSON.stringify({ ...form, recipient: testRecipient }),
            });
            const body = await res.json().catch(() => ({ sent: false, message: 'Bad response.' }));
            setTestResult(body);
        } catch (err) {
            setTestResult({ sent: false, message: 'Network error: ' + String(err) });
        } finally {
            setTesting(false);
        }
    };

    const handleDownload = (scope: string) => {
        window.location.href = `/admin/settings/backup?scope=${encodeURIComponent(scope)}`;
    };

    const handleRestore = async (file: File | null) => {
        if (!file || restoring) return;
        setRestoring(true);
        setRestoreResult(null);
        try {
            const fd = new FormData();
            fd.append('backup_file', file);
            const res = await fetch('/admin/settings/restore', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken(), Accept: 'application/json' },
                body: fd,
            });
            const body = await res.json().catch(() => ({ message: 'Unexpected response.' }));
            setRestoreResult({ ok: res.ok, message: body.message ?? (res.ok ? 'Restore completed.' : 'Restore failed.') });
        } catch (err) {
            setRestoreResult({ ok: false, message: 'Network error: ' + String(err) });
        } finally {
            setRestoring(false);
        }
    };

    return (
        <AdminDashboardLayout
            header={
                <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600 dark:text-indigo-400">
                        Administrator
                    </p>
                    <h2 className="mt-1 text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Settings
                    </h2>
                    <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Configure the email system and manage data backups.
                    </p>
                </div>
            }
        >
            <Head title="Settings · Admin" />

            <div className="space-y-6">
                {/* ─────────────── SMTP card ─────────────── */}
                <section
                    className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="card-smtp"
                >
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {mailIcon}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                            SMTP Settings
                        </h3>
                        <span
                            data-testid="smtp-status-badge"
                            className={`ml-auto inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wider ${mailConfigured ? 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:ring-emerald-800' : 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:ring-amber-800'}`}
                        >
                            {mailConfigured ? '✓ SMTP configured' : '⚠ Mail not fully configured'}
                        </span>
                    </header>

                    <form onSubmit={handleSave} className="grid gap-5 p-5 sm:grid-cols-2">
                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Mail driver</label>
                            <select
                                value={form.mailer}
                                onChange={e => set('mailer', e.target.value)}
                                className={inputClass}
                                data-testid="smtp-mailer"
                            >
                                <option value="smtp">SMTP</option>
                                <option value="log">Log (dev)</option>
                            </select>
                            <p className="mt-1 text-[11px] text-gray-400 dark:text-gray-500">
                                SMTP sends real email; Log writes to storage/logs (development).
                            </p>
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Host</label>
                            <input
                                type="text"
                                value={form.host}
                                onChange={e => set('host', e.target.value)}
                                placeholder="smtp.gmail.com"
                                className={inputClass}
                                data-testid="smtp-host"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Port</label>
                            <input
                                type="number"
                                value={form.port}
                                onChange={e => set('port', e.target.value)}
                                placeholder="587"
                                className={inputClass}
                                data-testid="smtp-port"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Encryption</label>
                            <select
                                value={form.scheme}
                                onChange={e => set('scheme', e.target.value)}
                                className={inputClass}
                                data-testid="smtp-scheme"
                            >
                                <option value="">None</option>
                                <option value="tls">TLS</option>
                                <option value="ssl">SSL</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Username</label>
                            <input
                                type="text"
                                value={form.username}
                                onChange={e => set('username', e.target.value)}
                                placeholder="you@impact.test"
                                className={inputClass}
                                data-testid="smtp-username"
                                autoComplete="off"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">Password</label>
                            <input
                                type="password"
                                value={form.password}
                                onChange={e => set('password', e.target.value)}
                                placeholder={smtp.password_set ? '•••••••• (leave blank to keep)' : 'SMTP password'}
                                className={inputClass}
                                data-testid="smtp-password"
                                autoComplete="new-password"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">From address</label>
                            <input
                                type="email"
                                value={form.from_address}
                                onChange={e => set('from_address', e.target.value)}
                                placeholder="no-reply@impact.test"
                                className={inputClass}
                                data-testid="smtp-from-address"
                            />
                        </div>

                        <div>
                            <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">From name</label>
                            <input
                                type="text"
                                value={form.from_name}
                                onChange={e => set('from_name', e.target.value)}
                                placeholder="Impact Cell | Guest Portal"
                                className={inputClass}
                                data-testid="smtp-from-name"
                            />
                        </div>

                        {/* Test email row */}
                        <div className="sm:col-span-2 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-gray-50/70 p-4 dark:border-gray-700 dark:bg-gray-900/40">
                            <div className="min-w-[14rem] flex-1">
                                <label className="block text-xs font-medium uppercase tracking-wider text-gray-500">
                                    Test recipient
                                </label>
                                <input
                                    type="email"
                                    value={testRecipient}
                                    onChange={e => setTestRecipient(e.target.value)}
                                    placeholder="you@impact.test"
                                    className={inputClass}
                                    data-testid="smtp-test-recipient"
                                />
                            </div>
                            <button
                                type="button"
                                onClick={handleTestEmail}
                                disabled={testing}
                                className="inline-flex items-center gap-2 rounded-md border border-indigo-200 bg-white px-4 py-2 text-sm font-semibold text-indigo-700 transition-colors hover:bg-indigo-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-indigo-800 dark:bg-gray-900 dark:text-indigo-300 dark:hover:bg-indigo-900/40"
                                data-testid="smtp-test-send"
                            >
                                {testing ? 'Sending…' : 'Send test email'}
                            </button>
                            {testResult && (
                                <span
                                    data-testid="smtp-test-result"
                                    className={`w-full text-xs ${testResult.sent ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}
                                >
                                    {testResult.message}
                                </span>
                            )}
                        </div>

                        <div className="sm:col-span-2 flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={saving}
                                className="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                data-testid="smtp-save"
                            >
                                {saving ? 'Saving…' : 'Save SMTP settings'}
                            </button>
                            <p className="text-[11px] text-gray-400 dark:text-gray-500">
                                Writes MAIL_* keys to .env and refreshes mail configuration.
                            </p>
                        </div>
                    </form>
                </section>

                {/* ─────────────── Backup & Restore card ─────────────── */}
                <section
                    className="motion-safe:animate-fade-in overflow-hidden rounded-xl border border-gray-200 bg-white shadow-card dark:border-gray-700 dark:bg-gray-800"
                    data-testid="card-backup"
                >
                    <header className="flex items-center gap-3 border-b border-gray-100 bg-gray-50/50 px-5 py-4 dark:border-gray-700 dark:bg-gray-900/40">
                        <span className="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-900/40 dark:text-indigo-300">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                {serverIcon}
                            </svg>
                        </span>
                        <h3 className="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-200">
                            Backup &amp; Restore
                        </h3>
                    </header>

                    <div className="space-y-6 p-5">
                        {/* Download section */}
                        <div>
                            <h4 className="flex items-center gap-2 text-sm font-semibold text-gray-800 dark:text-gray-100">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4 text-indigo-500 dark:text-indigo-400" aria-hidden="true">
                                    {downloadIcon}
                                </svg>
                                Download a backup
                            </h4>
                            <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Backups are JSON archives. Segment backups cover a single domain; a Full backup
                                covers everything and is the only archive Restore accepts.
                            </p>
                            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                <button
                                    type="button"
                                    onClick={() => handleDownload('full')}
                                    className="group flex items-center gap-3 rounded-lg border border-indigo-200 bg-indigo-50/60 p-4 text-left transition-colors hover:border-indigo-300 hover:bg-indigo-50 dark:border-indigo-800 dark:bg-indigo-900/20 dark:hover:bg-indigo-900/40"
                                    data-testid="backup-download-full"
                                >
                                    <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-indigo-600 text-white shadow-sm transition-transform group-hover:scale-105">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                            {downloadIcon}
                                        </svg>
                                    </span>
                                    <span>
                                        <span className="block text-sm font-semibold text-gray-900 dark:text-white">Full Backup</span>
                                        <span className="block text-[11px] text-gray-500 dark:text-gray-400">Everything — restorable</span>
                                    </span>
                                </button>

                                {backupScopes
                                    .filter(s => s !== 'full')
                                    .map(scope => (
                                        <button
                                            key={scope}
                                            type="button"
                                            onClick={() => handleDownload(scope)}
                                            className="group flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-4 text-left transition-colors hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-900 dark:hover:bg-indigo-900/30"
                                            data-testid={`backup-download-${scope}`}
                                        >
                                            <span className="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100 text-gray-600 transition-transform group-hover:scale-105 dark:bg-gray-800 dark:text-gray-300">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                                    {downloadIcon}
                                                </svg>
                                            </span>
                                            <span>
                                                <span className="block text-sm font-semibold text-gray-900 dark:text-white">
                                                    {scope === 'impact_cell' ? 'Impact Cell' : scope === 'follow_up_officer' ? 'Follow Up Officer' : 'Follow Up Team'}
                                                </span>
                                                <span className="block text-[11px] text-gray-500 dark:text-gray-400">Segment backup</span>
                                            </span>
                                        </button>
                                    ))}
                            </div>
                        </div>

                        {/* Restore section */}
                        <div className="rounded-lg border border-rose-200 bg-rose-50/60 p-4 dark:border-rose-900/50 dark:bg-rose-900/10">
                            <h4 className="flex items-center gap-2 text-sm font-semibold text-rose-700 dark:text-rose-300">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-4 w-4" aria-hidden="true">
                                    {restoreIcon}
                                </svg>
                                Restore from a Full backup
                            </h4>
                            <p className="mt-1 text-xs text-rose-600/90 dark:text-rose-300/80">
                                <span className="inline-flex items-center gap-1 font-semibold">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" className="h-3.5 w-3.5" aria-hidden="true">
                                        {shieldIcon}
                                    </svg>
                                    Destructive:
                                </span>{' '}
                                restoring replaces ALL current business data (users, guests, cells, submissions,
                                notification rules) with the contents of the uploaded archive.
                            </p>
                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                <label className="flex-1 min-w-[12rem]">
                                    <span className="block text-[11px] font-medium uppercase tracking-wider text-rose-600/80 dark:text-rose-300/70">
                                        Type RESTORE to arm, then choose file
                                    </span>
                                    <input
                                        type="text"
                                        value={confirmText}
                                        onChange={e => setConfirmText(e.target.value)}
                                        placeholder="RESTORE"
                                        className={`mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-rose-500 focus:ring-rose-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 ${confirmText === 'RESTORE' ? 'border-rose-400' : ''}`}
                                        data-testid="restore-confirm-input"
                                    />
                                </label>
                                <input
                                    type="file"
                                    accept="application/json,.json"
                                    disabled={confirmText !== 'RESTORE' || restoring}
                                    onChange={e => handleRestore(e.target.files?.[0] ?? null)}
                                    className="block w-full text-xs text-gray-500 file:mr-3 file:rounded-md file:border-0 file:bg-rose-600 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50 dark:text-gray-400 sm:w-auto"
                                    data-testid="restore-file-input"
                                />
                            </div>
                            {restoring && (
                                <p className="mt-2 text-xs font-medium text-rose-600 dark:text-rose-300">
                                    Restoring backup… this may take a moment.
                                </p>
                            )}
                            {restoreResult && (
                                <p
                                    data-testid="restore-result"
                                    className={`mt-2 text-xs font-medium ${restoreResult.ok ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'}`}
                                >
                                    {restoreResult.message}
                                </p>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </AdminDashboardLayout>
    );
}
