import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function VerifyEmail({ status }: { status?: string }) {
    const { post, processing } = useForm({});

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Email Verification" />

            <div className="space-y-6">
                {/* Icon + heading */}
                <div className="flex flex-col items-center space-y-3 text-center">
                    <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 ring-1 ring-indigo-100 dark:bg-indigo-500/10 dark:text-indigo-400 dark:ring-indigo-500/20">
                        <svg
                            className="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            strokeWidth="2"
                            strokeLinecap="round"
                            strokeLinejoin="round"
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
                    <div className="space-y-1.5">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Verify your email
                        </h2>
                        <p className="mx-auto max-w-sm text-sm text-gray-600 dark:text-gray-400">
                            Thanks for signing up! Before getting started,
                            could you verify your email address by clicking on
                            the link we just emailed to you?
                        </p>
                    </div>
                </div>

                {status === 'verification-link-sent' && (
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
                        <span>
                            A new verification link has been sent to the
                            email address you provided during registration.
                        </span>
                    </div>
                )}

                <form onSubmit={submit} className="space-y-4">
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
                                Sending…
                            </span>
                        ) : (
                            'Resend verification email'
                        )}
                    </PrimaryButton>

                    <div className="flex items-center justify-center">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-sm font-medium text-gray-600 underline-offset-4 transition-colors hover:text-gray-900 hover:underline focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-gray-400 dark:hover:text-gray-100"
                        >
                            Log out
                        </Link>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}
