import { useState } from 'react'
import { router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Download, Filter, Search } from 'lucide-react'

const number = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

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
                    <TableCell className="text-right font-semibold">{number(row.cantidad_consumida)}</TableCell>
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
