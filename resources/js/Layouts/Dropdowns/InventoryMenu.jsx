import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'
import { Link } from '@inertiajs/react'

export default function InventoryMenu() {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger className="group inline-flex h-9 w-max items-center justify-center rounded-md bg-greenex-dark-green px-4 py-2 text-sm font-medium text-greenex-white transition-colors hover:bg-greenex-vibrant-green hover:text-greenex-orange focus:bg-greenex-vibrant-green focus:text-greenex-orange">
        Inventario
      </DropdownMenuTrigger>

      <DropdownMenuContent align="start" className="w-64 rounded-md border border-greenex-vibrant-green bg-greenex-dark-green shadow-lg">
        <DropdownMenuItem asChild><Link href={route('inventory.dashboard')} className="w-full">Resumen</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.stocks.index')} className="w-full">Stock por ubicación</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.movements.index')} className="w-full">Movimientos</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.workflows.scan')} className="w-full">Escaneo operativo</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.waste.index')} className="w-full">Mermas por ubicación</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.logistic-units.index')} className="w-full">Pallets / LPN</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.material-requests.index')} className="w-full">Solicitudes de Materiales</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.returns.index')} className="w-full">Devoluciones</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.traceability-report.index')} className="w-full">Reporte de Trazabilidad</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.planning-simulator.index')} className="w-full">Simulador de planificación</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.person-deliveries.index')} className="w-full">Entrega a Personas</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.tech-equipment-deliveries.index')} className="w-full">Equipos tecnológicos · Actas de entrega</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.tech-equipment-deliveries.history')} className="w-full">Equipos · Historial</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.guide')} className="w-full">Instructivo de uso</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.productions.index')} className="w-full">Producción</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.auto-consumption-folios.index')} className="w-full">Consumo Automático</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.manual-consumptions.index')} className="w-full">Consumo Manual</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.consumption-reports.index')} className="w-full">Consumo por servicio</Link></DropdownMenuItem>

        <DropdownMenuSeparator />
        <DropdownMenuItem asChild><Link href={route('inventory.materials.index')} className="w-full">Materiales</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.packagings.index')} className="w-full">Embalajes</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.locations.index')} className="w-full">Ubicaciones</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.consumption-origins.index')} className="w-full">Orígenes de consumo</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.tech-equipment.index')} className="w-full">Equipos tecnológicos (mantenedor)</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.technical-sheets.index')} className="w-full">Fichas técnicas</Link></DropdownMenuItem>
        <DropdownMenuItem asChild><Link href={route('inventory.waste-types.index')} className="w-full">Tipos de Merma</Link></DropdownMenuItem>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
