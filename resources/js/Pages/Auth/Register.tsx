import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function Register() {
    const [showPassword, setShowPassword] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Register" />

            <div className="space-y-6">
                {/* Heading */}
                <div className="space-y-1.5">
                    <h2 className="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        Create your account
                    </h2>
                    <p className="text-sm text-gray-600 dark:text-gray-400">
                        Get started — it only takes a minute to register.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-5">
                    {/* Name */}
                    <div className="space-y-1.5">
                        <InputLabel htmlFor="name" value="Full name" />
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
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                                    <circle cx="12" cy="7" r="4" />
                                </svg>
                            </span>
                            <TextInput
                                id="name"
                                name="name"
                                value={data.name}
                                className="block w-full pl-10"
                                autoComplete="name"
                                isFocused={true}
                                placeholder="Jane Doe"
                                onChange={(e) =>
                                    setData('name', e.target.value)
                                }
                                required
                            />
                        </div>
                        <InputError message={errors.name} className="mt-1.5" />
                    </div>

                    {/* Email */}
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
                                placeholder="you@impact.test"
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                                required
                            />
                        </div>
                        <InputError
                            message={errors.email}
                            className="mt-1.5"
                        />
                    </div>

                    {/* Password */}
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
                                autoComplete="new-password"
                                placeholder="Choose a strong password"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                                required
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

                    {/* Confirm password */}
                    <div className="space-y-1.5">
                        <InputLabel
                            htmlFor="password_confirmation"
                            value="Confirm password"
                        />
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
                                id="password_confirmation"
                                type={
                                    showPassword ? 'text' : 'password'
                                }
                                name="password_confirmation"
                                value={data.password_confirmation}
                                className="block w-full pl-10 pr-10"
                                autoComplete="new-password"
                                placeholder="Re-enter your password"
                                onChange={(e) =>
                                    setData(
                                        'password_confirmation',
                                        e.target.value,
                                    )
                                }
                                required
                            />
                        </div>
                        <InputError
                            message={errors.password_confirmation}
                            className="mt-1.5"
                        />
                    </div>

                    {/* Footer + Submit */}
                    <div className="flex items-center justify-between gap-3 pt-1">
                        <Link
                            href={route('login')}
                            className="text-sm font-medium text-indigo-600 transition-colors hover:text-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:text-indigo-400 dark:hover:text-indigo-300"
                        >
                            Already registered? Sign in
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
                                Creating account…
                            </span>
                        ) : (
                            'Create account'
                        )}
                    </PrimaryButton>
                </form>
            </div>
        </GuestLayout>
    );
}
