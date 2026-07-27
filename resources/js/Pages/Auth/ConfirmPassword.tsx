import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function ConfirmPassword() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Confirm Password" />

            <div className="space-y-6">
                {/* Icon + heading */}
                <div className="flex flex-col items-center space-y-3 text-center">
                    <span className="flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 ring-1 ring-amber-100 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-500/20">
                        <svg
                            className="h-7 w-7"
                            viewBox="0 0 24 24"
                            fill="currentColor"
                            aria-hidden="true"
                        >
                            <path
                                fillRule="evenodd"
                                d="M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v-3a3.75 3.75 0 1 0-7.5 0v3h7.5Z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </span>
                    <div className="space-y-1.5">
                        <h2 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Confirm your password
                        </h2>
                        <p className="mx-auto max-w-sm text-sm text-gray-600 dark:text-gray-400">
                            This is a secure area of the application. Please
                            confirm your password before continuing.
                        </p>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="password" value="Password" />
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
                                isFocused={true}
                                placeholder="Enter your password"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
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
                                        <circle cx="12" cy="12" r="3" />
                                    </svg>
                                )}
                            </button>
                        </div>
                        <InputError
                            message={errors.password}
                            className="mt-1.5"
                        />
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
                                Confirming…
                            </span>
                        ) : (
                            'Confirm'
                        )}
                    </PrimaryButton>
                </form>
            </div>
        </GuestLayout>
    );
}
