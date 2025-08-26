// Components/Layout/AuthenticatedLayout.jsx
import ApplicationLogo from '@/Components/ApplicationLogo';
import ResponsiveNavLink from '@/Components/ResponsiveNavLink';
import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    NavigationMenu,
    NavigationMenuList,
    NavigationMenuItem,
    NavigationMenuLink,
} from '@/Components/ui/navigation-menu.jsx';

// Importamos los submenús
import DocumentationMenu from './Dropdowns/DocumentacionMenu.jsx';
import AdminMenu from './Dropdowns/AdminMenu.jsx';
import UserMenu from './Dropdowns/UserMenu.jsx';  // sin llaves
import ControlCalidadMenu from './Dropdowns/ControlCalidadMenu.jsx';

const navLinkClasses =
    "group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange focus:outline-none disabled:pointer-events-none disabled:opacity-50 text-greenex-white";

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    return (
        <div className="min-h-screen bg-greenex-white">
            <nav className="bg-greenex-dark-green border-b border-greenex-vibrant-green shadow-md">
                <div className="mx-auto max-w-7xl px-6 sm:px-8 lg:px-10">
                    <div className="flex h-16 justify-between">
                        {/* Logo */}
                        <div className="flex">
                            <div className="flex shrink-0 items-center">
                                <Link href="/">
                                    <ApplicationLogo className="block h-9 w-auto fill-current text-greenex-orange" />
                                </Link>
                            </div>

                            {/* Menú principal */}
                            <div className="hidden sm:-my-px sm:ms-10 sm:flex sm:items-center">
                                <NavigationMenu>
                                    <NavigationMenuList className="flex space-x-6">
                                        <NavigationMenuItem>
                                            <NavigationMenuLink asChild className={navLinkClasses}>
                                                <Link href={route('dashboard')}>Inicio</Link>
                                            </NavigationMenuLink>
                                        </NavigationMenuItem>

                                        <NavigationMenuItem>
                                            <NavigationMenuLink asChild className={navLinkClasses}>
                                                <Link href={route('recepciones.index')}>Recepciones</Link>
                                            </NavigationMenuLink>
                                        </NavigationMenuItem>

                                        <NavigationMenuItem>
                                            <NavigationMenuLink asChild className={navLinkClasses}>
                                                <Link href={route('procesos.index')}>Procesos</Link>
                                            </NavigationMenuLink>
                                        </NavigationMenuItem>



                                       <ControlCalidadMenu />
                                        <NavigationMenuItem>
                                            <NavigationMenuLink asChild className={navLinkClasses}>
                                                <Link href={route('sag.index')}>Módulo SAG</Link>
                                            </NavigationMenuLink>
                                        </NavigationMenuItem>

                                        <NavigationMenuItem>
                                            <NavigationMenuLink asChild className={navLinkClasses}>
                                                <Link href={route('contracts.index')}>Contratos</Link>
                                            </NavigationMenuLink>
                                        </NavigationMenuItem>

                                        {/* Submenús extraídos */}
                                        <DocumentationMenu />
                                        {user.roles?.some(role => role.name === 'Administrador') && <AdminMenu />}
                                        <UserMenu user={user} />
                                    </NavigationMenuList>
                                </NavigationMenu>
                            </div>
                        </div>

                        {/* Botón móvil */}
                        <div className="-me-2 flex items-center sm:hidden">
                            <button
                                onClick={() => setShowingNavigationDropdown(prev => !prev)}
                                className="inline-flex items-center justify-center rounded-md p-2 text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange focus:outline-none"
                            >
                                <svg className="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                                    <path
                                        className={!showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M4 6h16M4 12h16M4 18h16"
                                    />
                                    <path
                                        className={showingNavigationDropdown ? 'inline-flex' : 'hidden'}
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth="2"
                                        d="M6 18L18 6M6 6l12 12"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Menú móvil (responsive) - igual que antes */}
                <div className={`${showingNavigationDropdown ? 'block' : 'hidden'} sm:hidden`}>
                    {/* ... (tu menú móvil actual) ... */}
                </div>
            </nav>

            {header && (
                <header className="bg-greenex-white shadow">
                    <div className="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">{header}</div>
                </header>
            )}

            <main>{children}</main>
        </div>
    );
}
