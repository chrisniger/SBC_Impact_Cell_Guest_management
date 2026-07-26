import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Log in" />

            <div className="space-y-6">
                {/* Heading */}
                <div className="space-y-1.5">
                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Welcome back
                    </h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Sign in to continue to your dashboard.
                    </p>
                </div>

                {/* Status banner */}
                {status && (
                    <div
                        role="status"
                        className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700 dark:border-green-900/50 dark:bg-green-900/20 dark:text-green-300"
                    >
                        <svg
                            className="mt-0.5 h-4 w-4 shrink-0"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fillRule="evenodd"
                                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                clipRule="evenodd"
                            />
                        </svg>
                        <span>{status}</span>
                    </div>
                )}

                {/* Form */}
                <form onSubmit={submit} className="space-y-5">
                    {/* Email — with envelope prefix */}
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="email" value="Email address" />
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 dark:text-gray-500">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="2"
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    className="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <rect
                                        x="2.5"
                                        y="4.5"
                                        width="19"
                                        height="15"
                                        rx="2"
                                    />
                                    <path d="m3 7 9 6.5 9-6.5" />
                                </svg>
                            </span>
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="block w-full pl-10"
                                autoComplete="username"
                                isFocused={true}
                                placeholder="you@impact.test"
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                        </div>
                        <InputError
                            message={errors.email}
                            className="mt-1.5"
                        />
                    </div>

                    {/* Password — with lock prefix + eye toggle */}
                    <div className="space-y-1.5">
                        <div className="flex items-center justify-between">
                            <InputLabel
                                htmlFor="password"
                                value="Password"
                            />
                            {canResetPassword && (
                                <Link
                                    href={route('password.request')}
                                    className="text-xs font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    Forgot password?
                                </Link>
                            )}
                        </div>
                        <div className="relative">
                            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400 dark:text-gray-500">
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="currentColor"
                                    className="h-4 w-4"
                                    aria-hidden="true"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                            </span>
                            <TextInput
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                name="password"
                                value={data.password}
                                className="block w-full pl-10 pr-10"
                                autoComplete="current-password"
                                placeholder="Enter your password"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                            {/*
                                `type="button"` is critical: without it browsers
                                treat the toggle as `type="submit"` and pressing
                                Enter inside the password field would flip
                                visibility instead of submitting the form.
                            */}
                            <button
                                type="button"
                                onClick={() =>
                                    setShowPassword((s) => !s)
                                }
                                className="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 transition-colors hover:text-gray-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-2 dark:text-gray-500 dark:hover:text-gray-300 rounded"
                                aria-label={
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                }
                            >
                                {showPassword ? (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        className="h-4 w-4"
                                        aria-hidden="true"
                                    >
                                        <path d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.244 7.244L21 21m-3.878-3.878-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                ) : (
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        strokeWidth="2"
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        className="h-4 w-4"
                                        aria-hidden="true"
                                    >
                                        <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z" />
                                        <circle
                                            cx="12"
                                            cy="12"
                                            r="3"
                                        />
                                    </svg>
                                )}
                            </button>
                        </div>
                        <InputError
                            message={errors.password}
                            className="mt-1.5"
                        />
                    </div>

                    {/* Remember me + kbd hint */}
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <label className="flex cursor-pointer select-none items-center gap-2.5">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData(
                                        'remember',
                                        (e.target.checked ||
                                            false) as false,
                                    )
                                }
                            />
                            <span className="text-sm text-gray-700 dark:text-gray-300">
                                Keep me signed in
                            </span>
                        </label>
                        <span className="hidden items-center gap-1.5 text-xs text-gray-400 dark:text-gray-500 sm:inline-flex">
                            press{' '}
                            <kbd className="rounded border border-gray-200 bg-white px-1.5 py-0.5 font-mono text-[10px] text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                ↵
                            </kbd>
                        </span>
                    </div>

                    {/* Submit */}
                    {/*
                       Note on `!important` modifiers (e.g. `!bg-indigo-600`):
                       PrimaryButton's base classes (`text-xs uppercase tracking-widest
                       bg-gray-800 hover:bg-gray-700 …`) are plain Tailwind utilities
                       with no guaranteed source-order precedence over our overrides.
                       Without `!`, JIT compilation order could let the component
                       defaults silently win and ship the old tiny all-caps gray
                       button. `!` forces our design to render reliably.
                    */}
                    <PrimaryButton
                        className="w-full justify-center !bg-indigo-600 !px-4 !py-3 !text-sm !tracking-wide !normal-case shadow-sm transition-all !hover:bg-indigo-700 !focus:bg-indigo-700 !focus:shadow-md !active:bg-indigo-800 dark:!bg-indigo-500 dark:!hover:bg-indigo-400 dark:!focus:bg-indigo-400"
                        disabled={processing}
                    >
                        {processing ? (
                            <span className="inline-flex items-center gap-2">
                                <svg
                                    className="h-4 w-4 animate-spin"
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"
                                    />
                                </svg>
                                Signing you in…
                            </span>
                        ) : (
                            'Sign in'
                        )}
                    </PrimaryButton>
                </form>

                {/* Helper footer */}
                <div className="rounded-lg border border-gray-200/80 bg-gray-50/80 p-4 dark:border-gray-700/60 dark:bg-gray-800/40">
                    <div className="flex items-start gap-3">
                        <svg
                            className="mt-0.5 h-5 w-5 shrink-0 text-indigo-500 dark:text-indigo-400"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fillRule="evenodd"
                                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z"
                                clipRule="evenodd"
                            />
                        </svg>
                        <div className="space-y-0.5 text-sm">
                            <p className="font-semibold text-gray-900 dark:text-white">
                                Need access?
                            </p>
                            <p className="text-gray-600 dark:text-gray-400">
                                Contact your zonal coordinator, or email{' '}
                                <a
                                    href="mailto:admin@impact.test"
                                    className="font-medium text-indigo-600 underline-offset-2 transition-colors hover:text-indigo-700 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                >
                                    admin@impact.test
                                </a>
                                .
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
