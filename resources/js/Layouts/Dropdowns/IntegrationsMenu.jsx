import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Link } from '@inertiajs/react'

export default function IntegrationsMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange">
        Integraciones
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-64 rounded-md border border-greenex-vibrant-green bg-greenex-dark-green shadow-lg">
        <DropdownMenuItem asChild><Link href={route('integrations.dashboard')} className="w-full">Dashboard</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.clients.index')} className="w-full">Clientes</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.profiles.index')} className="w-full">Perfiles</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.source-adapters.index')} className="w-full">Source Adapters</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.runs.index')} className="w-full">Ejecuciones</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.pending-mappings.index')} className="w-full">Pendientes de Homologación</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.failures.index')} className="w-full">Fallos</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.simulator.index')} className="w-full">Simulador</Link></DropdownMenuItem>

        <DropdownMenuSeparator />
        <DropdownMenuItem asChild><Link href={route('integrations.compare.index')} className="w-full">Comparar Versiones</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.exports.index')} className="w-full">Exportaciones</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('integrations.audit.index')} className="w-full">Auditoría</Link></DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
