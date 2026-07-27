import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function ForgotPassword({ status }: { status?: string }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Forgot Password" />

            <div className="space-y-6">
                {/* Heading */}
                <div className="space-y-1.5">
                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Forgot your password?
                    </h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        No problem. Just let us know your email address and
                        we'll email you a password reset link that will let you
                        choose a new one.
                    </p>
                </div>

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

                <form onSubmit={submit} className="space-y-5">
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

                    <div className="flex items-center justify-between gap-3 pt-1">
                        <Link
                            href={route('login')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Back to sign in
                        </Link>
                    </div>

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
                                Sending link…
                            </span>
                        ) : (
                            'Email password reset link'
                        )}
                    </PrimaryButton>
                </form>
            </div>
        </GuestLayout>
    );
}
