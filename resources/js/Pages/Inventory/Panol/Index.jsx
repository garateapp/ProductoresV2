import { Fragment, useState } from 'react'
import { router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Switch } from '@/Components/ui/switch'
import { ChevronDown, ChevronRight, Download, Search } from 'lucide-react'

const number = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

export default function PanolIndex({ filters = {}, rows = [], totals = {}, people = [], cargos = [], areas = [] }) {
  const [filterData, setFilterData] = useState({
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    producto: filters.producto || '',
    persona: filters.persona || '',
    cargo: filters.cargo || '',
    area: filters.area || '',
    solo_con_stock: filters.solo_con_stock ?? true,
  })
  const [expanded, setExpanded] = useState(new Set())

  const personOptions = people.map((item) => ({ value: String(item.id), label: item.nombre }))
  const cargoOptions = cargos.map((value) => ({ value, label: value }))
  const areaOptions = areas.map((value) => ({ value, label: value }))

  const setField = (field, value) => setFilterData((current) => ({ ...current, [field]: value }))

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.panol.index'), filterData, { preserveScroll: true, preserveState: true })
  }

  const clearFilters = () => {
    const next = { date_from: '', date_to: '', producto: '', persona: '', cargo: '', area: '', solo_con_stock: true }
    setFilterData(next)
    setExpanded(new Set())
    router.get(route('inventory.panol.index'), next, { preserveScroll: true, preserveState: true })
  }

  const toggleRow = (materialId) => {
    setExpanded((current) => {
      const next = new Set(current)
      if (next.has(materialId)) next.delete(materialId)
      else next.add(materialId)
      return next
    })
  }

  const exportUrl = () => {
    const params = new URLSearchParams()
    Object.entries(filterData).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) params.set(key, String(value))
    })
    return `${route('inventory.panol.export-excel')}?${params.toString()}`
  }

  return (
    <div className="mx-auto space-y-4 py-10">
      <Card>
        <CardHeader>
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle>Control del Pañol · Entrega a Personas</CardTitle>
              <p className="mt-1 text-sm text-slate-600">
                Stock en línea de Bodega Central y entregas por material (códigos 61xx), según los filtros seleccionados.
              </p>
            </div>
            <Button type="button" variant="outline" asChild>
              <a href={exportUrl()}><Download className="mr-2 h-4 w-4" /> Excel</a>
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <form onSubmit={applyFilters} className="grid gap-3 rounded border bg-slate-50 p-4 md:grid-cols-8">
            <div>
              <Label>Desde</Label>
              <Input type="date" value={filterData.date_from} onChange={(e) => setField('date_from', e.target.value)} />
            </div>
            <div>
              <Label>Hasta</Label>
              <Input type="date" value={filterData.date_to} onChange={(e) => setField('date_to', e.target.value)} />
            </div>
            <div className="relative md:col-span-2">
              <Search className="pointer-events-none absolute left-2 top-2 h-4 w-4 text-slate-400" />
              <Input className="pl-8" value={filterData.producto} onChange={(e) => setField('producto', e.target.value)} placeholder="Buscar producto" />
            </div>
            <div className="md:col-span-2">
              <Label>Persona</Label>
              <SearchableSelect
                options={personOptions}
                value={personOptions.find((item) => item.value === String(filterData.persona)) || null}
                onChange={(option) => setField('persona', option?.value || '')}
                placeholder="Todas"
              />
            </div>
            <div>
              <Label>Cargo</Label>
              <SearchableSelect
                options={cargoOptions}
                value={cargoOptions.find((item) => item.value === String(filterData.cargo)) || null}
                onChange={(option) => setField('cargo', option?.value || '')}
                placeholder="Todos"
              />
            </div>
            <div>
              <Label>Área</Label>
              <SearchableSelect
                options={areaOptions}
                value={areaOptions.find((item) => item.value === String(filterData.area)) || null}
                onChange={(option) => setField('area', option?.value || '')}
                placeholder="Todas"
              />
            </div>
            <div className="flex items-end gap-2 md:col-span-2">
              <Button type="submit" className="flex-1">Filtrar</Button>
              <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
            <div className="flex items-end gap-2 md:col-span-2">
              <label className="flex items-center gap-2 text-sm text-slate-700">
                <Switch
                  checked={filterData.solo_con_stock}
                  onCheckedChange={(checked) => setField('solo_con_stock', checked)}
                />
                Solo con stock
              </label>
            </div>
          </form>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <SummaryBox label="Materiales" value={number(totals.materiales)} />
            <SummaryBox label="Stock pañol (unid.)" value={number(totals.stock_total)} />
            <SummaryBox label="Total entregado" value={number(totals.total_entregado)} />
            <SummaryBox label="N° entregas" value={number(totals.num_entregas)} />
          </div>

          <div className="rounded-md border bg-white">
            <div className="border-b px-4 py-3">
              <h3 className="text-sm font-semibold text-slate-900">Resumen por material</h3>
              <p className="mt-0.5 text-xs text-slate-500">Una fila por material con stock en línea y entregas según filtros.</p>
            </div>
            <div className="overflow-x-auto p-4">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-10"></TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Producto</TableHead>
                    <TableHead>Unidad</TableHead>
                    <TableHead className="text-right">Stock pañol</TableHead>
                    <TableHead className="text-right">Total entregado</TableHead>
                    <TableHead className="text-right">N° entregas</TableHead>
                    <TableHead className="text-right">Última entrega</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row) => {
                    const isOpen = expanded.has(row.material_id)
                    return (
                      <Fragment key={row.material_id}>
                        <TableRow
                          className="cursor-pointer"
                          onClick={() => toggleRow(row.material_id)}
                        >
                          <TableCell className="text-slate-400">
                            <button
                              type="button"
                              onClick={(event) => {
                                event.stopPropagation()
                                toggleRow(row.material_id)
                              }}
                              className="hover:text-slate-700"
                            >
                              {isOpen ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                            </button>
                          </TableCell>
                          <TableCell className="font-mono text-xs text-slate-500">{row.material_codigo}</TableCell>
                          <TableCell className="font-medium">
                            <span className="inline-flex items-center gap-2">
                              {row.material_nombre}
                              {row.consumo_inmediato ? <span className="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-700">Consumo inmediato</span> : null}
                            </span>
                          </TableCell>
                          <TableCell className="text-sm text-slate-500">{row.unit_codigo || '-'}</TableCell>
                          <TableCell className="text-right font-semibold">{number(row.stock_actual)}</TableCell>
                          <TableCell className="text-right">{number(row.total_entregado)}</TableCell>
                          <TableCell className="text-right">{row.num_entregas}</TableCell>
                          <TableCell className="text-right text-sm text-slate-500">
                            {row.ultima_entrega ? String(row.ultima_entrega).slice(0, 10) : '-'}
                          </TableCell>
                        </TableRow>
                        {isOpen ? (
                          <TableRow className="bg-slate-50">
                            <TableCell colSpan={8} className="p-0">
                              <div className="px-6 py-3">
                                {row.deliveries.length ? (
                                  <div className="overflow-hidden rounded-md border border-slate-200">
                                    <Table>
                                      <TableHeader>
                                        <TableRow className="bg-white">
                                          <TableHead>Fecha</TableHead>
                                          <TableHead>N° acta</TableHead>
                                          <TableHead>Persona</TableHead>
                                          <TableHead>Cargo</TableHead>
                                          <TableHead>Área</TableHead>
                                          <TableHead className="text-right">Cantidad</TableHead>
                                        </TableRow>
                                      </TableHeader>
                                      <TableBody>
                                        {row.deliveries.map((delivery) => (
                                          <TableRow key={delivery.delivery_id}>
                                            <TableCell className="text-sm">
                                              {String(delivery.delivered_at).slice(0, 16)}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs text-slate-500">
                                              {delivery.delivery_codigo}
                                            </TableCell>
                                            <TableCell className="font-medium">{delivery.person_name}</TableCell>
                                            <TableCell className="text-sm">{delivery.person_position || '-'}</TableCell>
                                            <TableCell className="text-sm">{delivery.person_area || '-'}</TableCell>
                                            <TableCell className="text-right font-semibold">
                                              {number(delivery.cantidad)}
                                            </TableCell>
                                          </TableRow>
                                        ))}
                                      </TableBody>
                                    </Table>
                                  </div>
                                ) : (
                                  <p className="py-2 text-sm text-slate-500">Sin entregas para los filtros seleccionados.</p>
                                )}
                              </div>
                            </TableCell>
                          </TableRow>
                        ) : null}
                      </Fragment>
                    )
                  })}
                  {!rows.length ? (
                    <TableRow>
                      <TableCell colSpan={8} className="py-8 text-center text-sm text-slate-500">
                        Sin datos para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

function SummaryBox({ label, value }) {
  return (
    <div className="rounded-md border bg-white p-4 shadow-sm">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className="mt-1 text-xl font-bold text-slate-900">{value}</div>
    </div>
  )
}

PanolIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventario · Control del Pañol</h2>}
  />
)