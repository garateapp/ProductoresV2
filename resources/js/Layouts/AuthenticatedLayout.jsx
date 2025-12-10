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
import SagMenu from './Dropdowns/SagMenu.jsx';

const navLinkClasses =
    "group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange focus:outline-none disabled:pointer-events-none disabled:opacity-50 text-greenex-white";

const mobileNavLinkClasses =
    "text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange border-greenex-vibrant-green";

const mobileNavLinkProps = {
    variant: 'dark',
    className: mobileNavLinkClasses,
};
export default function AuthenticatedLayout({ header, children }) {
    const { user } = usePage().props.auth;
    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    const hasRole = (roleName) => user.roles?.some(role => role.name === roleName);

    const hasAnyRole = (roles) => roles.some(role => hasRole(role));

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

                                        {hasAnyRole(['Administrador', 'Productor', 'Gerencia']) && (
                                            <NavigationMenuItem>
                                                <NavigationMenuLink asChild className={navLinkClasses}>
                                                    <Link href={route('recepciones.index')}>Recepciones</Link>
                                                </NavigationMenuLink>
                                            </NavigationMenuItem>
                                        )}

                                        {hasAnyRole(['Administrador', 'Productor', 'Gerencia']) && (
                                            <NavigationMenuItem>
                                                <NavigationMenuLink asChild className={navLinkClasses}>
                                                    <Link href={route('procesos.index')}>Procesos</Link>
                                                </NavigationMenuLink>
                                            </NavigationMenuItem>
                                        )}

                                        {hasAnyRole(['Administrador', 'Calidad']) && <ControlCalidadMenu />}

                                        {hasAnyRole(['Administrador', 'Agronomo']) && (
                                            <NavigationMenuItem>
                                                <NavigationMenuLink asChild className={navLinkClasses}>
                                                    <Link href={route('field-visits.index')}>Visitas</Link>
                                                </NavigationMenuLink>
                                            </NavigationMenuItem>
                                        )}

                                        {hasAnyRole(['Administrador', 'Agronomo', 'Sag']) && <SagMenu/>}

                                        {hasAnyRole(['Administrador',  'Gerencia', 'Contrato']) && (
                                            <NavigationMenuItem>
                                                <NavigationMenuLink asChild className={navLinkClasses}>
                                                    <Link href={route('contracts.index')}>Contratos</Link>
                                                </NavigationMenuLink>
                                            </NavigationMenuItem>
                                        )}

                                        {/* Submenús extraídos */}
                                        {hasAnyRole(['Administrador', 'Gerencia', 'Agronomo', 'Sag']) && <DocumentationMenu />}
                                        {hasRole('Administrador') && <AdminMenu />}
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

                {/* Menú móvil (responsive) */}
                <div className={`${showingNavigationDropdown ? 'block' : 'hidden'} sm:hidden bg-greenex-dark-green text-greenex-white`}>
                    <div className="space-y-1 border-t border-greenex-vibrant-green pb-3 pt-2">
                        <ResponsiveNavLink
                            {...mobileNavLinkProps}
                            href={route('dashboard')}
                            active={route().current('dashboard')}
                            onClick={() => setShowingNavigationDropdown(false)}
                        >
                            Inicio
                        </ResponsiveNavLink>

                        {hasAnyRole(['Administrador', 'Productor', 'Gerencia']) && (
                            <>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('recepciones.index')}
                                    active={route().current('recepciones.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Recepciones
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('procesos.index')}
                                    active={route().current('procesos.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Procesos
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('contracts.index')}
                                    active={route().current('contracts.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Contratos
                                </ResponsiveNavLink>
                            </>
                        )}

                        {hasAnyRole(['Administrador', 'Agronomo']) && (
                            <ResponsiveNavLink
                                {...mobileNavLinkProps}
                                href={route('field-visits.index')}
                                active={route().current('field-visits.*')}
                                onClick={() => setShowingNavigationDropdown(false)}
                            >
                                Visitas
                            </ResponsiveNavLink>
                        )}

                        {hasAnyRole(['Administrador', 'Calidad']) && (
                            <>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('control-calidad.index')}
                                    active={route().current('control-calidad.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Control de Calidad
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('reporteria.calidad')}
                                    active={route().current('reporteria.calidad')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Reportería de Calidad
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('processed-fruit-quality.index')}
                                    active={route().current('processed-fruit-quality.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Producto Terminado
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('mrl-samples.index')}
                                    active={route().current('mrl-samples.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    MRL - Gestión de Muestras
                                </ResponsiveNavLink>
                            </>
                        )}

                        {hasAnyRole(['Administrador', 'Agronomo', 'Sag']) && (
                            <>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('sag.index')}
                                    active={route().current('sag.index')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Certificaciones SAG
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('sdp-sites.index')}
                                    active={route().current('sdp-sites.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    SDP (Sitios)
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('producer-certifications.index')}
                                    active={route().current('producer-certifications.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Certificaciones Internacionales
                                </ResponsiveNavLink>
                            </>
                        )}

                        {hasAnyRole(['Administrador', 'Gerencia', 'Agronomo', 'Sag']) && (
                            <>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('authorization-types.index')}
                                    active={route().current('authorization-types.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Tipos de Autorización
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('certifying-houses.index')}
                                    active={route().current('certifying-houses.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Casas Certificadoras
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('certificate-types.index')}
                                    active={route().current('certificate-types.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Tipos de Certificado
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('markets.index')}
                                    active={route().current('markets.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Mercados
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('login-activity.index')}
                                    active={route().current('login-activity.index')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Uso del Portal
                                </ResponsiveNavLink>
                            </>
                        )}

                        {hasRole('Administrador') && (
                            <>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('users.index')}
                                    active={route().current('users.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Usuarios
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('producers.index')}
                                    active={route().current('producers.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Productores
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('roles.index')}
                                    active={route().current('roles.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Roles
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('permissions.index')}
                                    active={route().current('permissions.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Permisos
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('services.index')}
                                    active={route().current('services.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Servicios
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('weekly-harvest-estimates.index')}
                                    active={route().current('weekly-harvest-estimates.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Estimaciones Semanales
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('producer-groups.index')}
                                    active={route().current('producer-groups.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Grupos de Productores
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('continents.index')}
                                    active={route().current('continents.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Continentes
                                </ResponsiveNavLink>
                                <ResponsiveNavLink
                                    {...mobileNavLinkProps}
                                    href={route('countries.index')}
                                    active={route().current('countries.*')}
                                    onClick={() => setShowingNavigationDropdown(false)}
                                >
                                    Países
                                </ResponsiveNavLink>
                            </>
                        )}
                    </div>

                    <div className="border-t border-greenex-vibrant-green pb-4 pt-4">
                        <div className="px-4 text-sm text-greenex-white">
                            <div>{user.name}</div>
                            <div className="text-xs opacity-75">{user.email}</div>
                        </div>
                        <div className="mt-3 space-y-1">
                            <ResponsiveNavLink
                                {...mobileNavLinkProps}
                                href={route('profile.edit')}
                                active={route().current('profile.edit')}
                                onClick={() => setShowingNavigationDropdown(false)}
                            >
                                Perfil
                            </ResponsiveNavLink>
                            <ResponsiveNavLink
                                method="post"
                                href={route('logout')}
                                as="button"
                                {...mobileNavLinkProps}
                                onClick={() => setShowingNavigationDropdown(false)}
                            >
                                Cerrar Sesión
                            </ResponsiveNavLink>
                        </div>
                    </div>
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
