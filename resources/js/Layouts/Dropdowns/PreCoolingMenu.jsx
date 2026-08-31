import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Link } from '@inertiajs/react'

export default function PreCoolingMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange">
        Prefrío
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-64 rounded-md border border-greenex-vibrant-green bg-greenex-dark-green shadow-lg">
        <DropdownMenuLabel className="text-xs text-greenex-orange uppercase">Prefrío</DropdownMenuLabel>
        <DropdownMenuSeparator className="bg-greenex-vibrant-green" />
        <DropdownMenuItem asChild><Link href={route('prefrio.dashboard.index')} className="w-full">Dashboard</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.planta')} className="w-full">Layout de Planta</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.loads.index')} className="w-full">Procesos</Link></DropdownMenuItem>
        <DropdownMenuSeparator className="bg-greenex-vibrant-green" />
        <DropdownMenuItem asChild><Link href={route('prefrio.matriz.tunel')} className="w-full">Matriz de túneles</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.matriz.camara')} className="w-full">Matriz de cámaras</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.reportes.index')} className="w-full">Reportes</Link></DropdownMenuItem>
        <DropdownMenuSeparator className="bg-greenex-vibrant-green" />
        <DropdownMenuItem asChild><Link href={route('prefrio.tipos-procesos.index')} className="w-full">Tipos de Proceso</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.tuneles.index')} className="w-full">Túneles</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.camaras.index')} className="w-full">Cámaras</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('prefrio.atributos.index')} className="w-full">Atributos</Link></DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
