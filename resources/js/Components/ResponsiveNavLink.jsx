import { Link } from '@inertiajs/react';

export default function ResponsiveNavLink({
    active = false,
    className = '',
    children,
    variant = 'light',
    ...props
}) {
    const activeClasses =
        variant === 'dark'
            ? 'border-greenex-orange bg-greenex-vibrant-green text-greenex-dark-green focus:border-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-dark-green'
            : 'border-indigo-400 bg-indigo-50 text-indigo-700 focus:border-indigo-700 focus:bg-indigo-100 focus:text-indigo-800';

    const inactiveClasses =
        variant === 'dark'
            ? 'border-transparent text-greenex-white hover:border-greenex-orange hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:border-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange'
            : 'border-transparent text-gray-600 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-800 focus:border-gray-300 focus:bg-gray-50 focus:text-gray-800';

    return (
        <Link
            {...props}
            className={`flex w-full items-start border-l-4 py-2 pe-4 ps-3 ${
                active ? activeClasses : inactiveClasses
            } text-base font-medium transition duration-150 ease-in-out focus:outline-none ${className}`}
        >
            {children}
        </Link>
    );
}
