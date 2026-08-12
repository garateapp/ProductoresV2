import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-gradient-to-br from-[#2f5a1e] via-[#5c9733] to-[#90C95D] px-4 py-10">
            {/* Acentos decorativos */}
            <div className="pointer-events-none absolute -top-28 -right-24 h-96 w-96 rounded-full bg-greenex-orange/25 blur-3xl" />
            <div className="pointer-events-none absolute -bottom-32 -left-28 h-[26rem] w-[26rem] rounded-full bg-white/15 blur-3xl" />
            <div className="pointer-events-none absolute right-1/4 top-1/4 h-64 w-64 rounded-full bg-greenex-vibrant-green/40 blur-3xl" />

            <div className="relative z-10 mb-8">
                <Link href="/">
                    <ApplicationLogo className="h-20 w-auto drop-shadow-lg" />
                </Link>
            </div>

            <div className="relative z-10 w-full max-w-md">
                {children}
            </div>

            <p className="relative z-10 mt-8 text-sm font-medium text-white/75">
                © {new Date().getFullYear()} Gárate Hermanos · Portal de Producción
            </p>
        </div>
    );
}
