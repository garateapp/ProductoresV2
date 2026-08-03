import { Fragment, useState } from 'react'
import { Link, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { ChevronDown, ChevronRight, Filter, PackageCheck, Route, Search, Warehouse } from 'lucide-react'

const number = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

const progressClass = (value) => {
  if (value >= 100) return 'bg-emerald-600'
  if (value >= 50) return 'bg-amber-500'
  return 'bg-slate-400'
}

const statusVariant = (status) => {
  if (status === 'completado') return 'default'
  if (status === 'rechazado') return 'destructive'
  return 'outline'
}

export default function TraceabilityReportIndex({ requests, filters = {}, statuses = [], materials = [] }) {
  const [expanded, setExpanded] = useState(new Set())
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    estado: filters.estado || '',
    material_id: filters.material_id || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
  })

  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const statusOptions = statuses.map((item) => ({ value: item, label: item }))

  const toggle = (id) => {
    const next = new Set(expanded)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setExpanded(next)
  }

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.traceability-report.index'), filterData, { preserveScroll: true, preserveState: true })
  }

  const clearFilters = () => {
    const next = { q: '', estado: '', material_id: '', date_from: '', date_to: '' }
    setFilterData(next)
    router.get(route('inventory.traceability-report.index'), next, { preserveScroll: true, preserveState: true })
  }

  return (
    <div className="mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <div className="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle>Reporte de trazabilidad</CardTitle>
              <p className="mt-1 text-sm text-slate-600">Seguimiento desde solicitud de materiales hasta consumo final registrado en ledger.</p>
            </div>
            <Badge variant="outline" className="w-fit">{requests?.total ?? 0} solicitudes</Badge>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <form onSubmit={applyFilters} className="grid gap-3 rounded border bg-slate-50 p-4 md:grid-cols-7">
            <div className="md:col-span-2">
              <Label>Folio u observación</Label>
              <div className="relative">
                <Search className="pointer-events-none absolute left-2 top-2.5 h-4 w-4 text-slate-400" />
                <Input className="pl-8" value={filterData.q} onChange={(e) => setFilterData((current) => ({ ...current, q: e.target.value }))} placeholder="SOL-000001" />
              </div>
            </div>
            <div>
              <Label>Estado</Label>
              <SearchableSelect
                options={statusOptions}
                value={statusOptions.find((item) => item.value === String(filterData.estado)) || null}
                onChange={(option) => setFilterData((current) => ({ ...current, estado: option?.value || '' }))}
                placeholder="Todos"
              />
            </div>
            <div className="md:col-span-2">
              <Label>Material</Label>
              <SearchableSelect
                options={materialOptions}
                value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
                onChange={(option) => setFilterData((current) => ({ ...current, material_id: option?.value || '' }))}
                placeholder="Todos los materiales"
              />
            </div>
            <div>
              <Label>Desde</Label>
              <Input type="date" value={filterData.date_from} onChange={(e) => setFilterData((current) => ({ ...current, date_from: e.target.value }))} />
            </div>
            <div>
              <Label>Hasta</Label>
              <Input type="date" value={filterData.date_to} onChange={(e) => setFilterData((current) => ({ ...current, date_to: e.target.value }))} />
            </div>
            <div className="flex gap-2 md:col-span-7">
              <Button type="submit"><Filter className="mr-2 h-4 w-4" /> Filtrar</Button>
              <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[40px]"></TableHead>
                <TableHead>Solicitud</TableHead>
                <TableHead>Ruta</TableHead>
                <TableHead>Avance consumo</TableHead>
                <TableHead className="text-right">Solicitado</TableHead>
                <TableHead className="text-right">Trasladado</TableHead>
                <TableHead className="text-right">Consumido</TableHead>
                <TableHead>Estado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(requests?.data || []).map((request) => (
                <Fragment key={request.id}>
                  <TableRow key={request.id} className="cursor-pointer hover:bg-slate-50" onClick={() => toggle(request.id)}>
                    <TableCell>{expanded.has(request.id) ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}</TableCell>
                    <TableCell>
                      <div className="font-mono font-semibold">{request.codigo}</div>
                      <div className="text-xs text-slate-500">{request.fecha_solicitud || '-'} · {request.creator || '-'}</div>
                    </TableCell>
                    <TableCell>
                      <div className="text-sm">{request.origin || '-'}</div>
                      <div className="text-xs text-slate-500">a {request.destination || '-'}</div>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center justify-between text-xs">
                        <span>{number(request.summary.progress)}%</span>
                        <span className="text-slate-500">pend. {number(request.summary.pending_consumption)}</span>
                      </div>
                      <div className="mt-1 h-2 rounded bg-slate-200">
                        <div className={`h-2 rounded ${progressClass(request.summary.progress)}`} style={{ width: `${Math.min(request.summary.progress, 100)}%` }} />
                      </div>
                    </TableCell>
                    <TableCell className="text-right">{number(request.summary.requested)}</TableCell>
                    <TableCell className="text-right">{number(request.summary.transferred)}</TableCell>
                    <TableCell className="text-right font-semibold">{number(request.summary.consumed)}</TableCell>
                    <TableCell><Badge variant={statusVariant(request.estado)}>{request.estado}</Badge></TableCell>
                  </TableRow>

                  {expanded.has(request.id) ? (
                    <TableRow key={`${request.id}-detail`} className="bg-slate-50/70">
                      <TableCell colSpan={8} className="p-4">
                        <div className="grid gap-4 xl:grid-cols-3">
                          <TraceSection title="Materiales" icon={PackageCheck}>
                            <Table>
                              <TableHeader>
                                <TableRow>
                                  <TableHead>Material</TableHead>
                                  <TableHead className="text-right">Solic.</TableHead>
                                  <TableHead className="text-right">Cons.</TableHead>
                                  <TableHead className="text-right">Pend.</TableHead>
                                </TableRow>
                              </TableHeader>
                              <TableBody>
                                {request.items.map((item) => (
                                  <TableRow key={item.id}>
                                    <TableCell className="text-xs">{item.material}</TableCell>
                                    <TableCell className="text-right text-xs">{number(item.requested)}</TableCell>
                                    <TableCell className="text-right text-xs">{number(item.consumed)}</TableCell>
                                    <TableCell className="text-right text-xs">{number(item.pending)}</TableCell>
                                  </TableRow>
                                ))}
                              </TableBody>
                            </Table>
                          </TraceSection>

                          <TraceSection title="Pallets trazados" icon={Warehouse}>
                            <div className="space-y-2">
                              {request.pallets.length ? request.pallets.map((pallet) => (
                                <div key={pallet.id} className="rounded border bg-white p-3 text-xs">
                                  <div className="flex items-center justify-between gap-2">
                                    <span className="font-mono font-semibold">{pallet.lpn}</span>
                                    <Badge variant="outline">{pallet.status}</Badge>
                                  </div>
                                  <div className="mt-1 text-slate-600">{pallet.location || '-'}{pallet.spatial_position ? ` · ${pallet.spatial_position}` : ''}</div>
                                  <div className="mt-2 grid grid-cols-3 gap-2 text-slate-700">
                                    <span>Vinc. {number(pallet.linked_quantity)}</span>
                                    <span>Cons. {number(pallet.consumed_quantity)}</span>
                                    <span>Disp. {number(pallet.available_quantity)}</span>
                                  </div>
                                </div>
                              )) : <div className="rounded border bg-white p-3 text-sm text-slate-500">Sin pallets vinculados.</div>}
                            </div>
                          </TraceSection>

                          <TraceSection title="Línea de tiempo" icon={Route}>
                            <div className="space-y-2">
                              {request.timeline.map((event, index) => (
                                <div key={`${request.id}-event-${index}`} className="rounded border bg-white p-3 text-xs">
                                  <div className="flex items-start justify-between gap-2">
                                      <div>
                                        <div className="font-semibold">{event.title}</div>
                                        <div className="text-slate-600">{event.detail}</div>
                                        <div className="mt-1 text-slate-500">Usuario: {event.actor || '-'}</div>
                                      </div>
                                    <Badge variant="outline">{event.type}</Badge>
                                  </div>
                                  <div className="mt-2 flex justify-between text-slate-500">
                                    <span>{event.date || '-'}</span>
                                    <span>{number(event.quantity)}</span>
                                  </div>
                                </div>
                              ))}
                            </div>
                          </TraceSection>
                        </div>
                      </TableCell>
                    </TableRow>
                  ) : null}
                </Fragment>
              ))}
              {!(requests?.data || []).length ? (
                <TableRow>
                  <TableCell colSpan={8} className="py-8 text-center text-sm text-slate-500">Sin solicitudes para los filtros seleccionados.</TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>

          {requests?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {requests.from ?? 0} a {requests.to ?? 0} de {requests.total ?? 0}</div>
              <div className="flex gap-1">
                {requests.links.map((link, index) => (
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

function TraceSection({ title, icon: Icon, children }) {
  return (
    <div className="rounded border bg-white p-3">
      <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-slate-900">
        <Icon className="h-4 w-4 text-slate-500" />
        {title}
      </div>
      {children}
    </div>
  )
}

TraceabilityReportIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Reporte de trazabilidad</h2>}
  />
)
