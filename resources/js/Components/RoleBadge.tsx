interface RoleBadgeProps {
    role: string | null;
}

const ROLE_BADGE_CLASSES: Record<string, string> = {
    Administrator: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
    Supervisor: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    FollowUpOfficer: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    Follow_UP: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    Follow_UP_Admin: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    Follow_UP_View_Only: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
    Impact_Leaders: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    Impact_Cell_Admin: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    Impact_Cell_Report: 'bg-teal-100 text-teal-800 dark:bg-teal-900 dark:text-teal-200',
};

export default function RoleBadge({ role }: RoleBadgeProps) {
    if (!role) {
        return (
            <span
                className="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                title="No active role assigned"
                data-testid="role-badge-none"
            >
                No role
            </span>
        );
    }

    const classes = ROLE_BADGE_CLASSES[role] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';

    return (
        <span
            className={`inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ${classes}`}
            title={`Active role: ${role}`}
            data-testid="role-badge"
            data-role={role}
        >
            {role}
        </span>
    );
}