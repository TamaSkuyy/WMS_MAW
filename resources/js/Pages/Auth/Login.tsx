import React, { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthLayout from '../../Tailadmin/pages/AuthPages/AuthPageLayout';
import Label from '../../Tailadmin/components/form/Label';
import Input from '../../Tailadmin/components/form/input/InputField';
import Checkbox from '../../Tailadmin/components/form/input/Checkbox';
import Button from '../../Tailadmin/components/ui/button/Button';
import { EyeCloseIcon, EyeIcon } from '../../Tailadmin/icons';

export default function Login({ status, canResetPassword }) {
    const [showPassword, setShowPassword] = useState(false);
    
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Sign In" />
            <AuthLayout>
                <div className="flex flex-col flex-1">
                    <div className="w-full max-w-md pt-10 mx-auto">
                        <Link
                            href="/"
                            className="inline-flex items-center text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                        >
                            <svg width="20" height="20" className="mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fillRule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clipRule="evenodd" />
                            </svg>
                            Back to website
                        </Link>
                    </div>
                    <div className="flex flex-col justify-center flex-1 w-full max-w-md mx-auto">
                        <div>
                            <div className="mb-5 sm:mb-8">
                                <h1 className="mb-2 font-semibold text-gray-800 text-title-sm dark:text-white/90 sm:text-title-md">
                                    Sign In
                                </h1>
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Enter your email and password to sign in!
                                </p>
                            </div>
                            
                            {status && (
                                <div className="mb-4 text-sm font-medium text-green-600">
                                    {status}
                                </div>
                            )}

                            <div>
                                <form onSubmit={submit}>
                                    <div className="space-y-6">
                                        <div>
                                            <Label>
                                                Email <span className="text-error-500">*</span>{" "}
                                            </Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder="info@gmail.com"
                                                error={!!errors.email}
                                                hint={errors.email}
                                            />
                                        </div>
                                        <div>
                                            <Label>
                                                Password <span className="text-error-500">*</span>{" "}
                                            </Label>
                                            <div className="relative">
                                                <Input
                                                    id="password"
                                                    type={showPassword ? "text" : "password"}
                                                    name="password"
                                                    value={data.password}
                                                    onChange={(e) => setData('password', e.target.value)}
                                                    placeholder="Enter your password"
                                                    error={!!errors.password}
                                                    hint={errors.password}
                                                />
                                                <span
                                                    onClick={() => setShowPassword(!showPassword)}
                                                    className="absolute z-30 -translate-y-1/2 cursor-pointer right-4 top-1/2"
                                                >
                                                    {showPassword ? (
                                                        <EyeIcon className="fill-gray-500 dark:fill-gray-400 size-5" />
                                                    ) : (
                                                        <EyeCloseIcon className="fill-gray-500 dark:fill-gray-400 size-5" />
                                                    )}
                                                </span>
                                            </div>
                                        </div>
                                        <div className="flex items-center justify-between">
                                            <div className="flex items-center gap-3">
                                                <Checkbox
                                                    checked={data.remember}
                                                    onChange={(checked) => setData('remember', checked)}
                                                />
                                                <span className="block font-normal text-gray-700 text-theme-sm dark:text-gray-400">
                                                    Keep me logged in
                                                </span>
                                            </div>
                                            {canResetPassword && (
                                                <Link
                                                    href={route('password.request')}
                                                    className="text-sm text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                                >
                                                    Forgot password?
                                                </Link>
                                            )}
                                        </div>
                                        <div>
                                            <Button className="w-full" size="sm" disabled={processing}>
                                                Sign in
                                            </Button>
                                        </div>
                                    </div>
                                </form>
                                <div className="mt-5">
                                    <p className="text-sm font-normal text-center text-gray-700 dark:text-gray-400 sm:text-start">
                                        Don't have an account? {""}
                                        <Link
                                            href={route('register')}
                                            className="text-brand-500 hover:text-brand-600 dark:text-brand-400"
                                        >
                                            Sign Up
                                        </Link>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </AuthLayout>
        </>
    );
}
