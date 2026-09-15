import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Link } from '@inertiajs/react'
import { Wrench } from 'lucide-react'

export default function DetencionesMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center gap-2 rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange">
        <Wrench className="h-4 w-4" />
        Detenciones
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-64 rounded-md border border-greenex-vibrant-green bg-greenex-dark-green shadow-lg">
        <DropdownMenuLabel className="text-xs text-greenex-orange uppercase">Detenciones de Máquinas</DropdownMenuLabel>
        <DropdownMenuSeparator className="bg-greenex-vibrant-green" />
        <DropdownMenuItem asChild><Link href={route('detenciones.dashboard.index')} className="w-full">Dashboard</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('detenciones.registros.index')} className="w-full">Registro de Detenciones</Link></DropdownMenuItem>
        <DropdownMenuSeparator className="bg-greenex-vibrant-green" />
        <DropdownMenuItem asChild><Link href={route('detenciones.maquinas.index')} className="w-full">Máquinas</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('detenciones.motivos.index')} className="w-full">Motivos (Tipos y Causas)</Link></DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}