import InputError from '@/Components/InputError';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Lock, LogIn, User } from 'lucide-react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        user: '',
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
        <GuestLayout>
            <Head title="Iniciar sesión" />

            <div className="rounded-2xl border border-white/70 bg-white/95 p-8 shadow-2xl shadow-greenex-dark-green/40 backdrop-blur-sm">
                <div className="mb-6 text-center">
                    <div className="mx-auto mb-4 inline-flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-greenex-dark-green to-greenex-vibrant-green shadow-md">
                        <LogIn className="h-6 w-6 text-white" />
                    </div>
                    <h2 className="text-2xl font-bold tracking-tight text-gray-900">
                        Portal de Producción
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        Ingresa tus credenciales para acceder
                    </p>
                </div>

                {status && (
                    <div className="mb-4 rounded-lg bg-greenex-vibrant-green/15 px-3 py-2 text-sm font-medium text-greenex-dark-green">
                        {status}
                    </div>
                )}

                <form onSubmit={submit} className="space-y-5">
                    <div>
                        <Label htmlFor="user" className="text-sm font-medium text-gray-700">
                            Usuario
                        </Label>
                        <div className="relative mt-1">
                            <User className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                id="user"
                                type="text"
                                name="user"
                                value={data.user}
                                className="h-11 pl-10"
                                autoComplete="username"
                                autoFocus
                                onChange={(e) => setData('user', e.target.value)}
                            />
                        </div>
                        <InputError message={errors.user} className="mt-2" />
                    </div>

                    <div>
                        <Label htmlFor="password" className="text-sm font-medium text-gray-700">
                            Password
                        </Label>
                        <div className="relative mt-1">
                            <Lock className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <Input
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="h-11 pl-10"
                                autoComplete="current-password"
                                onChange={(e) => setData('password', e.target.value)}
                            />
                        </div>
                        <InputError message={errors.password} className="mt-2" />
                    </div>

                    <div className="flex items-center justify-between">
                        <label className="flex cursor-pointer select-none items-center gap-2">
                            <input
                                type="checkbox"
                                name="remember"
                                checked={data.remember}
                                onChange={(e) => setData('remember', e.target.checked)}
                                className="h-4 w-4 rounded border-gray-300 text-greenex-orange focus:ring-greenex-orange"
                            />
                            <span className="text-sm text-gray-600">Recordarme</span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-sm font-medium text-greenex-dark-green underline-offset-2 hover:text-greenex-orange hover:underline"
                            >
                                ¿Olvidaste tu contraseña?
                            </Link>
                        )}
                    </div>

                    <Button className="w-full" disabled={processing}>
                        {processing ? 'Ingresando...' : 'Ingresar'}
                    </Button>

                    <div className="text-center">
                        <Link
                            href="/privacy"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-xs text-gray-400 underline hover:text-greenex-dark-green"
                        >
                            Política de privacidad
                        </Link>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}
