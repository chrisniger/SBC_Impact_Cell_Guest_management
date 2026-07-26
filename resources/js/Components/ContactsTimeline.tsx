interface ContactSection {
    date: string | null;
    contact: string | null;
    comments: string | null;
}

interface ContactsTimelineProps {
    contacts: ContactSection[] | null;
    editable?: boolean;
    onChange?: (contacts: ContactSection[]) => void;
    readOnly?: boolean;
}

const CONTACT_LABELS: Record<string, string> = {
    '1st Contact': '1st Contact',
    '2nd Contact': '2nd Contact',
    '3rd Contact': '3rd Contact',
};

export default function ContactsTimeline({ contacts, editable, onChange, readOnly }: ContactsTimelineProps) {
    const sections = contacts ?? [];

    const handleUpdate = (index: number, field: keyof ContactSection, value: string) => {
        if (!onChange) return;
        const updated = [...sections];
        updated[index] = { ...updated[index], [field]: value || null };
        onChange(updated);
    };

    const handleRemove = (index: number) => {
        if (!onChange) return;
        onChange(sections.filter((_, i) => i !== index));
    };

    const handleAdd = () => {
        if (!onChange || sections.length >= 3) return;
        const nextNum = sections.length + 1;
        const label = `${nextNum}${getOrdinalSuffix(nextNum)} Contact`;
        onChange([...sections, { date: new Date().toISOString().slice(0, 10), contact: label, comments: null }]);
    };

    if (!editable && sections.length === 0) {
        return (
            <div className="py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                No contact sections recorded yet.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {sections.length > 0 && (
                <div className="relative ml-2 space-y-6 before:absolute before:bottom-2 before:left-3.5 before:top-2 before:w-0.5 before:bg-gray-300 before:dark:bg-gray-600">
                    {sections.map((section, i) => (
                        <div key={i} className="relative pl-10">
                            <span className="absolute left-0 top-1 flex h-7 w-7 items-center justify-center rounded-full bg-red-100 text-xs font-bold text-red-700 ring-2 ring-white dark:bg-red-900/50 dark:text-red-300 dark:ring-gray-800">
                                {i + 1}
                            </span>
                            <div className="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/50">
                                {editable && !readOnly ? (
                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between gap-2">
                                            <input
                                                type="date"
                                                value={section.date ?? ''}
                                                onChange={(e) => handleUpdate(i, 'date', e.target.value)}
                                                className="block w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            />
                                            <select
                                                value={section.contact ?? ''}
                                                onChange={(e) => handleUpdate(i, 'contact', e.target.value)}
                                                className="block w-44 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                            >
                                                <option value="">— Select —</option>
                                                {[1, 2, 3].map((n) => {
                                                    const label = `${n}${getOrdinalSuffix(n)} Contact`;
                                                    return <option key={label} value={label}>{label}</option>;
                                                })}
                                            </select>
                                            <button
                                                type="button"
                                                onClick={() => handleRemove(i)}
                                                className="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <textarea
                                            rows={2}
                                            value={section.comments ?? ''}
                                            onChange={(e) => handleUpdate(i, 'comments', e.target.value)}
                                            placeholder="Contact comments…"
                                            className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        />
                                    </div>
                                ) : (
                                    <div>
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-medium text-gray-900 dark:text-gray-100">
                                                {section.contact ? CONTACT_LABELS[section.contact] ?? section.contact : 'Contact'}
                                            </span>
                                            <span className="text-gray-500 dark:text-gray-400">
                                                {section.date ?? '—'}
                                            </span>
                                        </div>
                                        {section.comments && (
                                            <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                {section.comments}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {editable && !readOnly && sections.length < 3 && (
                <button
                    type="button"
                    onClick={handleAdd}
                    className="inline-flex items-center gap-1 text-sm font-medium text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300"
                >
                    <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                    </svg>
                    Add Contact Section ({sections.length}/3)
                </button>
            )}

            {readOnly && sections.length > 0 && (
                <p className="text-xs text-gray-400 dark:text-gray-500">
                    Read-only view.
                </p>
            )}
        </div>
    );
}

function getOrdinalSuffix(n: number): string {
    if (n === 1) return 'st';
    if (n === 2) return 'nd';
    if (n === 3) return 'rd';
    return 'th';
}
