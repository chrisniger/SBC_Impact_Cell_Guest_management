import { Listbox, ListboxButton, ListboxOption, ListboxOptions, Transition } from '@headlessui/react';
import { Fragment, useEffect, useState } from 'react';

/**
 * Phase 06d.2 — Language Switcher (HeadlessUI Listbox).
 *
 * Replaces the disabled `<button>` placeholder wired in Phase 06d.0 inside
 * AdminDashboardLayout's topbar (data-testid="admin-language-switcher"
 * preserved so the existing verifier [2]-testid check stays green).
 *
 * Persists the choice to `localStorage['cgms.lang']` for future i18n work.
 * Does NOT change the actual app locale yet (placeholder for i18n wiring
 * in a later phase). Just a stub that surfaces the picker.
 */

type Lang = { code: string; label: string; flag: string };

const LANGS: Lang[] = [
    { code: 'EN', label: 'English',    flag: '🇬🇧' },
    { code: 'FR', label: 'Français',   flag: '🇫🇷' },
];

export default function LanguageSwitcher() {
    const [selected, setSelected] = useState<Lang>(LANGS[0]);

    useEffect(() => {
        try {
            const stored = window.localStorage.getItem('cgms.lang');
            if (stored) {
                const found = LANGS.find((l) => l.code === stored);
                if (found) setSelected(found);
            }
        } catch (_) { /* localStorage may throw in private modes */ }
    }, []);

    const handleChange = (lang: Lang) => {
        setSelected(lang);
        try {
            window.localStorage.setItem('cgms.lang', lang.code);
        } catch (_) { /* ignore */ }
    };

    return (
        <Listbox value={selected} onChange={handleChange}>
            <div className="relative">
                <ListboxButton
                    className="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-white/70 px-2.5 py-1 text-xs font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800/70 dark:text-gray-200"
                    data-testid="admin-language-switcher"
                >
                    <span className="font-mono">{selected.code}</span>
                    <svg
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.6"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        className="h-3 w-3"
                        aria-hidden="true"
                    >
                        <polyline points="6 9 12 15 18 9" />
                    </svg>
                </ListboxButton>
                <Transition
                    as={Fragment}
                    leave="transition ease-in duration-100"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <ListboxOptions
                        className="absolute right-0 z-40 mt-1 min-w-[160px] overflow-auto rounded-lg border border-gray-200 bg-white py-1 text-sm shadow-card-hover dark:border-gray-700 dark:bg-gray-800"
                        data-testid="admin-language-switcher-options"
                    >
                        {LANGS.map((lang) => (
                            <ListboxOption
                                key={lang.code}
                                value={lang}
                                className="cursor-pointer px-3 py-2 data-[focus]:bg-indigo-50 dark:data-[focus]:bg-indigo-900/30"
                                data-testid={`admin-language-switcher-option-${lang.code.toLowerCase()}`}
                            >
                                <span className="mr-2">{lang.flag}</span>
                                <span>{lang.label}</span>
                            </ListboxOption>
                        ))}
                    </ListboxOptions>
                </Transition>
            </div>
        </Listbox>
    );
}
