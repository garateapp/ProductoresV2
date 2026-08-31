import { useState } from 'react'
import { Link, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

const estadoMeta = {
  aplicado: { variant: 'default', label: 'Aplicado' },
  temporal: { variant: 'outline', label: 'Temporal', className: 'text-violet-600 border-violet-200' },
  borrador: { variant: 'outline', label: 'Borrador', className: 'text-amber-600 border-amber-200' },
  sin_embalaje: { variant: 'outline', label: 'Sin embalaje', className: 'text-orange-600 border-orange-200' },
  sin_ficha: { variant: 'outline', label: 'Sin ficha', className: 'text-red-600 border-red-200' },
}

const estadoOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'aplicado', label: 'Aplicado' },
  { value: 'temporal', label: 'Temporal' },
  { value: 'borrador', label: 'Borrador' },
  { value: 'sin_embalaje', label: 'Sin embalaje' },
  { value: 'sin_ficha', label: 'Sin ficha técnica' },
]

function EstadoBadge({ estado }) {
  const meta = estadoMeta[estado] || { variant: 'outline', label: estado }
  return <Badge variant={meta.variant} className={meta.className}>{meta.label}</Badge>
}

export default function AutoConsumptionFolios({ folios, filters = {} }) {
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    estado: filters.estado || '',
  })
  const { processing } = useForm({})

  const applyFilters = (event) => {
    event?.preventDefault()
    router.get(route('inventory.auto-consumption-folios.index'), filterData, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const clearFilters = () => {
    setFilterData({ q: '', estado: '' })
    router.get(route('inventory.auto-consumption-folios.index'), {}, {
      preserveState: true,
      replace: true,
    })
  }

  const retry = (folioId, folio) => {
    if (!window.confirm(`¿Reintentar el folio ${folio}?`)) return
    router.post(route('inventory.auto-consumption-folios.retry', folioId), {}, { preserveScroll: true })
  }

  const retriable = ['borrador', 'sin_embalaje', 'sin_ficha']
  const folioItems = folios?.data || []

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-6">
          <CardTitle className="text-2xl font-bold">Consumo Automático</CardTitle>
          <Link href={route('inventory.consumption-origins.index')}>
            <Button variant="outline">Orígenes de consumo</Button>
          </Link>
        </CardHeader>
        <CardContent className="space-y-6">
          <p className="text-sm text-slate-500">
            Folios de producción procesados por el proceso automático (cada 5 minutos desde la vista de salidas).
            Los folios que terminan en <b>T</b> se registran como <b>consumo temporal</b> (producto terminado pendiente de completado, reembalaje o reclasificación).
            Los casos en <b>borrador</b> o sin ficha pueden reintentarse cuando se corrija la configuración o el stock.
          </p>

          <form onSubmit={applyFilters} className="grid gap-4 md:grid-cols-3 items-end rounded-lg border bg-slate-50/50 p-4">
            <div>
              <Label htmlFor="q">Buscar folio o embalaje</Label>
              <Input id="q" value={filterData.q} onChange={(e) => setFilterData((prev) => ({ ...prev, q: e.target.value }))} className="mt-1" />
            </div>
            <div>
              <Label htmlFor="estado">Estado</Label>
              <SearchableSelect
                options={estadoOptions}
                value={estadoOptions.find((option) => option.value === filterData.estado) || estadoOptions[0]}
                onChange={(option) => setFilterData((prev) => ({ ...prev, estado: option?.value || '' }))}
                placeholder="Todos los estados"
                isClearable={false}
              />
            </div>
            <div className="flex gap-2">
              <Button type="submit" disabled={processing}>Filtrar</Button>
              <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Folio</TableHead>
                <TableHead>Embalaje</TableHead>
                <TableHead>Cajas</TableHead>
                <TableHead>Línea</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Origen</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {folioItems.length === 0 && (
                <TableRow>
                  <TableCell colSpan={9} className="text-center text-slate-400 py-8">No hay folios procesados.</TableCell>
                </TableRow>
              )}
              {folioItems.map((item) => (
                <TableRow key={item.id}>
                  <TableCell className="font-medium">
                    {item.folio}
                    {item.es_temporal && (
                      <Badge variant="outline" className="ml-2 text-violet-600 border-violet-200">Temporal</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <div>{item.n_embalaje || '—'}</div>
                    <div className="text-xs text-slate-400">{item.c_embalaje}</div>
                  </TableCell>
                  <TableCell>{Number(item.cantidad)}</TableCell>
                  <TableCell>{item.n_linea_proceso || '—'}</TableCell>
                  <TableCell>{item.n_turno || '—'}</TableCell>
                  <TableCell>{item.fecha_produccion}</TableCell>
                  <TableCell>{item.origin_location || '—'}</TableCell>
                  <TableCell>
                    <EstadoBadge estado={item.estado} />
                    {item.detalle_estado && <div className="mt-1 max-w-[260px] text-xs text-slate-500" title={item.detalle_estado}>{item.detalle_estado}</div>}
                  </TableCell>
                  <TableCell className="text-right flex justify-end gap-2">
                    {retriable.includes(item.estado) && (
                      <Button variant="ghost" size="sm" onClick={() => retry(item.id, item.folio)}>Reintentar</Button>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {folios?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {folios.from ?? 0} a {folios.to ?? 0} de {folios.total ?? 0}</div>
              <div className="flex gap-1">
                {folios.links.map((link, index) => (
                  <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    preserveScroll
                    preserveState
                    className={`rounded border px-3 py-1 ${link.active ? 'bg-indigo-50 text-indigo-700' : 'bg-white'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}

AutoConsumptionFolios.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Consumo Automático</h2>}
  />
)