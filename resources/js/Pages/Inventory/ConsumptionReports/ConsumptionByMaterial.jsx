import { useState } from 'react'
import { router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Download, Filter, Search, Play, X } from 'lucide-react'

const number = (value) => Number(value || 0).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 4 })

export default function ConsumptionByMaterialIndex({
  filters = {},
  rows = [],
  materials = [],
}) {
  const [filterData, setFilterData] = useState({
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    material_id: filters.material_id || '',
    embalaje_codigo: filters.embalaje_codigo || '',
    embalaje_nombre: filters.embalaje_nombre || '',
    etiqueta: filters.etiqueta || '',
    q: filters.q || '',
  })

  const [autoConsumeResults, setAutoConsumeResults] = useState(null)
  const [autoConsumeLoading, setAutoConsumeLoading] = useState(false)
  const [autoConsumeError, setAutoConsumeError] = useState(null)
  const [showAutoConsumeResults, setShowAutoConsumeResults] = useState(false)

  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))

  const setField = (field, value) => setFilterData((current) => ({ ...current, [field]: value }))

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.consumption-reports.materiales'), filterData, { preserveScroll: true, preserveState: true })
  }

  const clearFilters = () => {
    const next = {
      date_from: '',
      date_to: '',
      material_id: '',
      embalaje_codigo: '',
      embalaje_nombre: '',
      etiqueta: '',
      q: '',
    }
    setFilterData(next)
    router.get(route('inventory.consumption-reports.materiales'), next, { preserveScroll: true, preserveState: true })
  }

  const exportUrl = () => {
    const params = new URLSearchParams()
    Object.entries(filterData).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) params.set(key, String(value))
    })
    return `${route('inventory.consumption-reports.materiales.export-excel')}?${params.toString()}`
  }

  const runAutoConsume = async () => {
    setAutoConsumeLoading(true)
    setAutoConsumeError(null)
    setAutoConsumeResults(null)

    try {
      const response = await fetch(route('inventory.consumption-reports.auto-consume'), {
        method: 'POST',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
          'Accept': 'application/json',
        },
      })

      if (!response.ok) {
        throw new Error('Error al ejecutar el consumo automático')
      }

      const data = await response.json()
      setAutoConsumeResults(data)
      setShowAutoConsumeResults(true)
    } catch (error) {
      setAutoConsumeError(error.message)
    } finally {
      setAutoConsumeLoading(false)
    }
  }

  return (
    <div className="mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle>Consumo de material por embalaje</CardTitle>
              <p className="mt-1 text-sm text-slate-600">
                Consumo agregado por material con su embalaje y etiqueta de ficha técnica.
              </p>
            </div>
            <div className="flex gap-2">
              <Button type="button" variant="outline" asChild>
                <a href={exportUrl()}><Download className="mr-2 h-4 w-4" /> Excel</a>
              </Button>
              <Button type="button" variant="outline" onClick={runAutoConsume} disabled={autoConsumeLoading}>
                {autoConsumeLoading ? 'Ejecutando...' : <><Play className="mr-2 h-4 w-4" /> Ejecutar consumo automático</>}
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <form onSubmit={applyFilters} className="grid gap-3 rounded border bg-slate-50 p-4 md:grid-cols-10">
            <div>
              <Label>Desde</Label>
              <Input type="date" value={filterData.date_from} onChange={(e) => setField('date_from', e.target.value)} />
            </div>
            <div>
              <Label>Hasta</Label>
              <Input type="date" value={filterData.date_to} onChange={(e) => setField('date_to', e.target.value)} />
            </div>
            <div className="md:col-span-2">
              <Label>Material</Label>
              <SearchableSelect
                options={materialOptions}
                value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
                onChange={(option) => setField('material_id', option?.value || '')}
                placeholder="Todos los materiales"
              />
            </div>
            <div>
              <Label>Código embalaje</Label>
              <Input value={filterData.embalaje_codigo} onChange={(e) => setField('embalaje_codigo', e.target.value)} placeholder="..." />
            </div>
            <div>
              <Label>Nombre embalaje</Label>
              <Input value={filterData.embalaje_nombre} onChange={(e) => setField('embalaje_nombre', e.target.value)} placeholder="..." />
            </div>
            <div>
              <Label>Etiqueta</Label>
              <Input value={filterData.etiqueta} onChange={(e) => setField('etiqueta', e.target.value)} placeholder="..." />
            </div>
            <div className="relative md:col-span-3">
              <Search className="pointer-events-none absolute left-2 top-2 h-4 w-4 text-slate-400" />
              <Input className="pl-8" value={filterData.q} onChange={(e) => setField('q', e.target.value)} placeholder="Buscar cualquier columna" />
            </div>
            <div className="flex items-end gap-2 md:col-span-2">
              <Button type="submit"><Filter className="mr-2 h-4 w-4" /> Filtrar</Button>
              <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
          </form>

          {autoConsumeError && (
            <div className="rounded bg-red-50 border border-red-200 p-4">
              <h3 className="text-sm font-semibold text-red-900">Error</h3>
              <p className="mt-1 text-xs text-red-700">{autoConsumeError}</p>
            </div>
          )}

          {autoConsumeResults && showAutoConsumeResults && (
            <Card className="rounded border bg-white">
              <CardHeader className="flex items-center justify-between">
                <div>
                  <CardTitle>Resultado consumo automático</CardTitle>
                  <p className="mt-1 text-sm text-slate-600">
                    {autoConsumeResults.dry_run ? '(DRY RUN) Nada se persistió.' : 'Proceso completado.'}
                  </p>
                </div>
                <Button type="button" variant="ghost" size="sm" onClick={() => setShowAutoConsumeResults(false)}>
                  <X className="h-4 w-4" />
                </Button>
              </CardHeader>
              <CardContent className="space-y-3">
                <div className="grid grid-cols-3 gap-4 text-sm">
                  <div className="p-3 rounded bg-green-50 border border-green-200">
                    <div className="text-xs font-medium text-green-700 uppercase">Aplicado</div>
                    <div className="text-xl font-bold text-green-900">{autoConsumeResults.summary.applied}</div>
                  </div>
                  <div className="p-3 rounded bg-amber-50 border border-amber-200">
                    <div className="text-xs font-medium text-amber-700 uppercase">Borrador</div>
                    <div className="text-xl font-bold text-amber-900">{autoConsumeResults.summary.borrador}</div>
                  </div>
                  <div className="p-3 rounded bg-red-50 border border-red-200">
                    <div className="text-xs font-medium text-red-700 uppercase">Rechazado</div>
                    <div className="text-xl font-bold text-red-900">{autoConsumeResults.summary.rejected}</div>
                  </div>
                </div>
                <div className="p-3 rounded bg-slate-50 border border-slate-200">
                  <div className="text-xs font-medium text-slate-700 uppercase">Total procesado</div>
                  <div className="text-lg font-bold text-slate-900">{autoConsumeResults.summary.total}</div>
                </div>
                {autoConsumeResults.results.length > 0 && (
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Folio</TableHead>
                          <TableHead>Embalaje</TableHead>
                          <TableHead className="text-right">Cajas</TableHead>
                          <TableHead>Línea</TableHead>
                          <TableHead>Turno</TableHead>
                          <TableHead>Estado</TableHead>
                          <TableHead>Detalle</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {autoConsumeResults.results.map((r, i) => (
                          <TableRow key={i}>
                            <TableCell className="font-mono text-xs">{r.folio}</TableCell>
                            <TableCell className="font-mono text-xs">{r.c_embalaje || '-'}</TableCell>
                            <TableCell className="text-right text-xs">{Number(r.cantidad || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className="text-xs">{r.linea || '-'}</TableCell>
                            <TableCell className="text-xs">{r.turno || '-'}</TableCell>
                            <TableCell className="font-mono text-xs">{r.estado}</TableCell>
                            <TableCell className="text-xs text-slate-500">{r.detalle_estado || '-'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                )}
                {autoConsumeResults.results.length === 0 && (
                  <p className="text-xs text-center text-slate-500">No hay folios nuevos por procesar.</p>
                )}
              </CardContent>
            </Card>
          )}

          <div className="rounded border bg-white">
            <div className="border-b px-4 py-3">
              <h3 className="text-sm font-semibold text-slate-900">Detalle de consumo</h3>
              <p className="mt-0.5 text-xs text-slate-500">{rows.length} registro{rows.length !== 1 ? 's' : ''}</p>
            </div>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Servicio</TableHead>
                  <TableHead>Código material</TableHead>
                  <TableHead>Nombre material</TableHead>
                  <TableHead className="text-right">Cantidad consumida</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Material original</TableHead>
                  <TableHead>Código embalaje</TableHead>
                  <TableHead>Nombre embalaje</TableHead>
                  <TableHead>Etiqueta</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((row) => (
                  <TableRow key={`${row.material_codigo}-${row.embalaje_codigo}-${row.etiqueta}-${row.service_name}`}>
                    <TableCell className="font-mono text-xs text-slate-500">{row.service_name || '-'}</TableCell>
                    <TableCell className="font-mono text-xs text-slate-500">{row.material_codigo}</TableCell>
                    <TableCell>{row.material_nombre}</TableCell>
                    <TableCell className="text-right font-semibold">{Math.round(Number(row.cantidad_consumida || 0)).toLocaleString('es-CL')}</TableCell>
                    <TableCell className="font-mono text-xs">{row.material_es_semielaborado ? 'Semielaborado' : 'Original'}</TableCell>
                    <TableCell className="font-mono text-xs text-slate-500">{row.material_es_semielaborado ? `${row.material_original_codigo} · ${row.material_original_nombre}` : '-'}</TableCell>
                    <TableCell className="font-mono text-xs">{row.embalaje_codigo}</TableCell>
                    <TableCell>{row.embalaje_nombre}</TableCell>
                    <TableCell className="font-mono text-xs">{row.etiqueta}</TableCell>
                  </TableRow>
                ))}
                {!rows.length ? (
                  <TableRow>
                    <TableCell colSpan={9} className="py-8 text-center text-sm text-slate-500">
                      Sin datos para los filtros seleccionados.
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

ConsumptionByMaterialIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Consumo por material y embalaje</h2>}
  />
)
