// Components/Layout/Dropdowns/AdminMenu.jsx
import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";
 const hasRole = (roleName) => user.roles?.some(role => role.name === roleName);

    const hasAnyRole = (roles) => roles.some(role => hasRole(role));


export default function AdminMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange transition-colors">
        Administración
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="start" // 👈 cambia a "end" si quieres alinearlo al lado derecho del trigger
        className="w-64 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >
        <DropdownMenuItem asChild>
          <Link href={route("users.index")} className="w-full">
            Usuarios
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("producers.index")} className="w-full">
            Productores
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("roles.index")} className="w-full">
            Roles
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("permissions.index")} className="w-full">
            Permisos
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("services.index")} className="w-full">
            Servicios
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("field-management.index")} className="w-full">
            Gestion de Campo
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("mass-communications.create")} className="w-full">
            Envío masivo
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("weekly-harvest-estimates.index")} className="w-full">
            Estimaciones Semanales
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("producer-groups.index")} className="w-full">
            Grupos de Productores
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("contracts.index")} className="w-full">
            Contratos
          </Link>
        </DropdownMenuItem>

          <DropdownMenuItem asChild>
            <Link href={route("prospectos-productores.index")} className="w-full">
              Prospectos productores
            </Link>
          </DropdownMenuItem>
      <DropdownMenuItem asChild>
          <Link href={route("continents.index")} className="w-full">
            Continentes
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("countries.index")} className="w-full">
            Países
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("notification-logs.index")} className="w-full">
            Logs de notificaciones
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("system-logs.index")} className="w-full">
            Logs del sistema
          </Link>
        </DropdownMenuItem>

        <DropdownMenuItem asChild>
          <Link href={route("login-activity.index")} className="w-full">
            Eventos de login
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
