import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions, Transition } from '@headlessui/react';
import { Fragment, useState } from 'react';
import { router } from '@inertiajs/react';

/**
 * Phase 06d.2 — Global Search (HeadlessUI Combobox).
 *
 * Replaces the disabled `<input>` placeholder wired in Phase 06d.0 inside
 * AdminDashboardLayout's topbar. The component is presentational; the search
 * INDEX is supplied by the parent (Dashboard.tsx → AdminDashboardLayout)
 * from `props.globalSearchIndex`, which the controller builds via
 * `buildGlobalSearchIndex()` (latest 5 guests + 5 cells + 5 submissions +
 * 5 users = max 20 items).
 *
 * Filtering is client-side (`includes(q)`). Arrow-key + Enter keyboard
 * navigation comes for free from HeadlessUI Combobox.
 *
 * data-testid anchors: admin-global-search / -input / -options / -option-{cat}-{id}
 */

export type SearchResult = {
    id: string;
    category: 'guest' | 'cell' | 'submission' | 'user';
    label: string;
    subtitle?: string;
    href: string;
};

const CATEGORY_LABEL: Record<SearchResult['category'], string> = {
    guest:      'GUESTS',
    cell:       'CELLS',
    submission: 'SUBMISSION',
    user:       'USER',
};

const CATEGORY_COLOR: Record<SearchResult['category'], string> = {
    guest:      'text-indigo-600 dark:text-indigo-300',
    cell:       'text-emerald-600 dark:text-emerald-300',
    submission: 'text-amber-600 dark:text-amber-300',
    user:       'text-blue-600 dark:text-blue-300',
};

export default function GlobalSearch({ results }: { results: SearchResult[] }) {
    const [query, setQuery] = useState<string>('');
    const [selected, setSelected] = useState<SearchResult | null>(null);

    const filtered = query === ''
        ? results.slice(0, 8)
        : results.filter((r) => {
              const q = query.toLowerCase();
              return r.label.toLowerCase().includes(q)
                  || (r.subtitle ?? '').toLowerCase().includes(q);
          }).slice(0, 8);

    const handleSelect = (r: SearchResult | null) => {
        if (r) router.visit(r.href);
    };

    return (
        <Combobox value={selected} onChange={handleSelect} nullable>
            <div className="relative" data-testid="admin-global-search">
                <div className="relative">
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                        aria-hidden="true"
                    >
                        <circle cx="11" cy="11" r="7" />
                        <line x1="20" y1="20" x2="16.65" y2="16.65" />
                    </svg>
                    <ComboboxInput
                        className="w-72 rounded-lg border border-gray-200 bg-white/70 py-1.5 pl-9 pr-3 text-sm text-gray-700 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500/40 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200 dark:placeholder-gray-500"
                        placeholder="Search guests, cells, reports…"
                        displayValue={(r: SearchResult | null) => r?.label ?? query}
                        onChange={(e) => setQuery(e.currentTarget.value)}
                        data-testid="admin-global-search-input"
                    />
                </div>
                <Transition
                    as={Fragment}
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                    afterLeave={() => setQuery('')}
                >
                    <ComboboxOptions
                        className="absolute z-40 mt-1 max-h-72 w-72 overflow-auto rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-card-hover dark:border-gray-700 dark:bg-gray-800"
                        data-testid="admin-global-search-options"
                    >
                        {query !== '' && filtered.length === 0 ? (
                            <div className="px-3 py-2 text-gray-500 dark:text-gray-400">
                                Nothing found for &ldquo;{query}&rdquo;.
                            </div>
                        ) : (
                            filtered.map((r) => (
                                <ComboboxOption
                                    key={`${r.category}-${r.id}`}
                                    value={r}
                                    data-testid={`admin-global-search-option-${r.category}-${r.id}`}
                                    className="cursor-pointer px-3 py-2 data-[focus]:bg-indigo-50 dark:data-[focus]:bg-indigo-900/30"
                                >
                                    <span
                                        className={`text-[11px] font-semibold uppercase tracking-wide ${CATEGORY_COLOR[r.category]}`}
                                    >
                                        {CATEGORY_LABEL[r.category]}
                                    </span>
                                    <p className="truncate font-medium text-gray-900 dark:text-white">
                                        {r.label}
                                    </p>
                                    {r.subtitle && (
                                        <p className="truncate text-xs text-gray-500 dark:text-gray-400">
                                            {r.subtitle}
                                        </p>
                                    )}
                                </ComboboxOption>
                            ))
                        )}
                    </ComboboxOptions>
                </Transition>
            </div>
        </Combobox>
    );
}
