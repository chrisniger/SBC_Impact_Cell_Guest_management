/**
 * Phase 06d.2 — Footer Card.
 *
 * Renders inside AdminDashboardLayout's footer slot via parent Dashboard.tsx.
 * Shows: copyright (year + app name) on left; environment pill + version
 * on right. Tone of environment badge is color-coded:
 *   - production → emerald
 *   - staging    → amber
 *   - local      → gray
 *   - other      → gray fallback
 *
 * data-testid anchors: admin-footer-card / admin-footer-env / admin-footer-version.
 */

type Props = {
    appName: string;
    appEnv: string;
    appVersion: string;
    year: number;
};

const ENV_TONE: Record<string, string> = {
    production: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300',
    staging:    'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300',
    local:      'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
};

export default function FooterCard({ appName, appEnv, appVersion, year }: Props) {
    const envLower = (appEnv ?? '').toLowerCase();
    const tone = ENV_TONE[envLower] ?? ENV_TONE.local;

    return (
        <div
            className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            data-testid="admin-footer-card"
        >
            <p className="text-xs text-gray-500 dark:text-gray-400">
                © {year} {appName}. All rights reserved.
            </p>
            <div className="flex items-center gap-3 text-xs">
                <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 font-mono uppercase ${tone}`}
                    data-testid="admin-footer-env"
                >
                    {appEnv}
                </span>
                {appVersion && (
                    <span
                        className="font-mono text-gray-500 dark:text-gray-400"
                        data-testid="admin-footer-version"
                    >
                        v{appVersion}
                    </span>
                )}
            </div>
        </div>
    );
}
