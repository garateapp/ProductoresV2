import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Download, Eye, FileText, Plus, Search } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })
  : '-'

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

const emptyFilters = {
  date_from: '',
  date_to: '',
  material_id: '',
  area: '',
  person_id: '',
}

export default function PersonDeliveriesIndex({ deliveries, filters = {}, materials = [], people = [], areas = [] }) {
  const [filterData, setFilterData] = useState({
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    material_id: filters.material_id || '',
    area: filters.area || '',
    person_id: filters.person_id || '',
  })

  const materialOptions = materials.map((item) => ({
    value: String(item.id),
    label: `${item.codigo} · ${item.nombre}`,
  }))
  const personOptions = people.map((item) => ({
    value: String(item.id),
    label: item.area ? `${item.nombre} (${item.area})` : item.nombre,
  }))
  const areaOptions = areas.map((value) => ({ value, label: value }))

  const setField = (field, value) => setFilterData((current) => ({ ...current, [field]: value }))

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.person-deliveries.index'), filterData, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const clearFilters = () => {
    setFilterData(emptyFilters)
    router.get(route('inventory.person-deliveries.index'), emptyFilters, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const exportUrl = () => {
    const params = new URLSearchParams()
    Object.entries(filterData).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) params.set(key, String(value))
    })
    return `${route('inventory.person-deliveries.export-excel')}?${params.toString()}`
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Entrega de Materiales a Personas</h2>
          <Link href={route('inventory.person-deliveries.create')}>
            <Button>
              <Plus className="w-4 h-4 mr-2" />
              Nueva Entrega
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Entrega de Materiales a Personas" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
          <Card>
            <CardHeader>
              <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div>
                  <CardTitle>Reporte de entregas</CardTitle>
                  <p className="mt-1 text-sm text-slate-600">
                    Filtra por fecha, material, área y persona. El Excel incluye una hoja consolidada por área y otra con el detalle.
                  </p>
                </div>
                <Button type="button" variant="outline" asChild>
                  <a href={exportUrl()}>
                    <Download className="w-4 h-4 mr-2" />
                    Descargar Excel
                  </a>
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              <form onSubmit={applyFilters} className="grid gap-3 rounded border bg-slate-50 p-4 md:grid-cols-6">
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
                <div className="md:col-span-2">
                  <Label>Persona</Label>
                  <SearchableSelect
                    options={personOptions}
                    value={personOptions.find((item) => item.value === String(filterData.person_id)) || null}
                    onChange={(option) => setField('person_id', option?.value || '')}
                    placeholder="Todas"
                  />
                </div>
                <div className="flex items-end gap-2 md:col-span-4">
                  <Button type="submit" className="flex-1">
                    <Search className="w-4 h-4 mr-2" />
                    Filtrar
                  </Button>
                  <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Actas Generadas</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-24">N° Ref.</TableHead>
                    <TableHead>Acta</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Persona</TableHead>
                    <TableHead>Cargo</TableHead>
                    <TableHead>Área</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Centro de costo</TableHead>
                    <TableHead>Movimiento</TableHead>
                    <TableHead>Materiales</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {deliveries.data.map((delivery) => (
                    <TableRow key={delivery.id}>
                      <TableCell className="text-lg font-bold tabular-nums">
                        {delivery.numero_referencia ?? '-'}
                      </TableCell>
                      <TableCell className="font-mono text-xs">{delivery.codigo}</TableCell>
                      <TableCell>{formatDate(delivery.delivered_at)}</TableCell>
                      <TableCell className="font-medium">{delivery.person_name}</TableCell>
                      <TableCell>{delivery.person_position}</TableCell>
                      <TableCell>{delivery.person_area || '-'}</TableCell>
                      <TableCell>{delivery.origin_location?.nombre || '-'}</TableCell>
                      <TableCell className="text-sm">
                        {delivery.cost_center
                          ? `${delivery.cost_center.codigo} · ${delivery.cost_center.nombre}`
                          : '-'}
                      </TableCell>
                      <TableCell>
                        {delivery.movement ? (
                          <div className="space-y-1">
                            <div className="font-mono text-xs">{delivery.movement.folio}</div>
                            <Badge variant="outline">{delivery.movement.estado}</Badge>
                          </div>
                        ) : '-'}
                      </TableCell>
                      <TableCell>
                        <div className="max-w-sm space-y-1">
                          {delivery.items.map((item) => (
                            <div key={item.id} className="text-sm text-slate-700">
                              {item.material?.codigo} · {formatQuantity(item.cantidad)}
                            </div>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          <Link href={route('inventory.person-deliveries.show', delivery.id)}>
                            <Button variant="ghost" size="sm" title="Ver acta">
                              <Eye className="w-4 h-4" />
                            </Button>
                          </Link>
                          <a href={route('inventory.person-deliveries.pdf', delivery.id)} target="_blank" rel="noopener noreferrer">
                            <Button variant="ghost" size="sm" title="Ver PDF">
                              <FileText className="w-4 h-4" />
                            </Button>
                          </a>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {!deliveries.data.length ? (
                    <TableRow>
                      <TableCell colSpan={11} className="py-8 text-center text-sm text-slate-500">
                        Sin entregas para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>

              {deliveries?.links?.length ? (
                <div className="mt-4 flex justify-between text-sm text-gray-600">
                  <div>Mostrando {deliveries.from ?? 0} a {deliveries.to ?? 0} de {deliveries.total ?? 0}</div>
                  <div className="flex flex-wrap gap-1">
                    {deliveries.links.map((link, index) => (
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
      </div>
    </AuthenticatedLayout>
  )
}
