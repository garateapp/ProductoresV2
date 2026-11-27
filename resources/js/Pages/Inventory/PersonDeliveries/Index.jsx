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
import { Download, Eye, FileText, Plus, RotateCcw, Search } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

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

const emptyReversalModal = {
  open: false,
  delivery: null,
  reason: '',
}

export default function PersonDeliveriesIndex({ deliveries, filters = {}, materials = [], people = [], areas = [] }) {
  const [filterData, setFilterData] = useState({
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    material_id: filters.material_id || '',
    area: filters.area || '',
    person_id: filters.person_id || '',
  })
  const [reversalModal, setReversalModal] = useState(emptyReversalModal)

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

  const submitReversal = (event) => {
    event.preventDefault()
    if (!reversalModal.delivery) return
    if (reversalModal.reason.trim().length < 5) {
      toast.error('Ingresa un motivo de al menos 5 caracteres.')
      return
    }

    router.post(
      route('inventory.person-deliveries.reverse', reversalModal.delivery.id),
      { reason: reversalModal.reason.trim() },
      {
        preserveScroll: true,
        onSuccess: () => {
          setReversalModal(emptyReversalModal)
          toast.success('Entrega revertida y material devuelto al stock.')
        },
        onError: (errors) => {
          const message = Object.values(errors).flat().join(', ')
          toast.error(message || 'No se pudo revertir la entrega.')
        },
      }
    )
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
      <Toaster position="top-right" richColors />

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
                            {delivery.reversal ? (
                              <Badge variant="secondary" title={delivery.reversal.reason || ''}>
                                Revertida · {delivery.reversal.folio}
                              </Badge>
                            ) : null}
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
                          {delivery.reversible ? (
                            <Button
                              variant="ghost"
                              size="sm"
                              title="Reversar entrega"
                              onClick={() => setReversalModal({ open: true, delivery, reason: '' })}
                            >
                              <RotateCcw className="w-4 h-4" />
                            </Button>
                          ) : null}
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

      <Dialog
        open={reversalModal.open}
        onOpenChange={(open) => setReversalModal(open ? reversalModal : emptyReversalModal)}
      >
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Reversar entrega de materiales</DialogTitle>
            <DialogDescription>
              Se devolverán al stock todos los materiales de esta acta y quedará registrado un movimiento de reversión.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitReversal} className="space-y-4">
            <div className="space-y-1 rounded-md border bg-slate-50 px-3 py-2 text-sm">
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Acta</span>
                <span className="font-mono font-medium">{reversalModal.delivery?.numero_referencia ?? reversalModal.delivery?.codigo}</span>
              </div>
              <div className="flex justify-between gap-3">
                <span className="text-slate-500">Persona</span>
                <span className="font-medium">{reversalModal.delivery?.person_name}</span>
              </div>
              <div className="mt-2 space-y-1">
                <p className="font-semibold uppercase tracking-wide text-xs text-slate-500">Materiales a devolver</p>
                {reversalModal.delivery?.items?.map((item) => (
                  <div key={item.id} className="flex justify-between gap-3">
                    <span>{item.material?.codigo} · {item.material?.nombre}</span>
                    <span className="font-medium">{formatQuantity(item.cantidad)}</span>
                  </div>
                ))}
              </div>
            </div>
            <div className="space-y-2">
              <Label>Motivo de la reversión *</Label>
              <Input
                value={reversalModal.reason}
                onChange={(event) => setReversalModal((current) => ({ ...current, reason: event.target.value }))}
                placeholder="Ej: Entrega registrada por error"
                autoFocus
              />
              <p className="text-xs text-slate-500">Mínimo 5 caracteres.</p>
            </div>
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => setReversalModal(emptyReversalModal)}>
                Cancelar
              </Button>
              <Button type="submit" className="bg-amber-600 hover:bg-amber-700">
                Reversar entrega
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
