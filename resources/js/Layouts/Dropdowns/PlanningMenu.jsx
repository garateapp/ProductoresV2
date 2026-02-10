import {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from "@/Components/ui/dropdown-menu";
import { Link } from "@inertiajs/react";

export default function PlanningMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange transition-colors">
        Planificación
      </DropdownMenuTrigger>

      <DropdownMenuContent
        align="start"
        className="w-64 bg-greenex-dark-green border border-greenex-vibrant-green rounded-md shadow-lg"
      >
        <DropdownMenuItem asChild>
          <Link href={route("planning.fruit-flow.index")} className="w-full">
            Flujo de fruta
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.processes.index")} className="w-full">
            Procesos
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.cameras.index")} className="w-full">
            Cámaras (monitoreo)
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.batches.index")} className="w-full">
            Plan semanal
          </Link>
        </DropdownMenuItem>

        <DropdownMenuSeparator />

        <DropdownMenuItem asChild>
          <Link href={route("planning.settings.lines.index")} className="w-full">
            Configuración · Líneas/Cámaras
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.settings.shifts.index")} className="w-full">
            Configuración · Turnos
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.settings.capacities.index")} className="w-full">
            Configuración · Capacidades
          </Link>
        </DropdownMenuItem>
        <DropdownMenuItem asChild>
          <Link href={route("planning.settings.packaging-matrix.index")} className="w-full">
            Configuración · Matriz Embalajes
          </Link>
        </DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
