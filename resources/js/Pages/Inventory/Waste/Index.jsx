import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import SearchableSelect from '@/Components/SearchableSelect'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

const REVIEWABLE_STATUSES = [
  { value: 'approved', label: 'approved' },
  { value: 'review_pending', label: 'review_pending' },
  { value: 'reversed', label: 'reversed' },
]

export default function InventoryWasteIndex({ wastes, summary, filters = {}, locations = [], materials = [], reasons = [], statuses = [] }) {
  const { props } = usePage()
  const [activeDialog, setActiveDialog] = useState(null)
  const [selectedWaste, setSelectedWaste] = useState(null)
  const [loadingWaste, setLoadingWaste] = useState(false)
  const form = useForm({
    q: filters.q || '',
    detected_location_id: filters.detected_location_id || '',
    quarantine_location_id: filters.quarantine_location_id || '',
    material_id: filters.material_id || '',
    waste_reason_id: filters.waste_reason_id || '',
    status: filters.status || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
  })
  const reviewForm = useForm({
    status: 'approved',
    notes: '',
  })
  const quarantineForm = useForm({
    quarantine_location_id: '',
  })
  const disposeForm = useForm({
    notes: '',
  })

  const locationOptions = locations.map((item) => ({ value: String(item.id), label: `${item.path_code || item.codigo} · ${item.nombre}` }))
  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const reasonOptions = reasons.map((item) => ({ value: String(item.id), label: item.nombre }))
  const statusOptions = statuses.map((item) => ({ value: item, label: item }))

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.waste.index'), form.data, { preserveState: true, preserveScroll: true })
  }

  const clearFilters = () => {
    const next = {
      q: '',
      detected_location_id: '',
      quarantine_location_id: '',
      material_id: '',
      waste_reason_id: '',
      status: '',
      date_from: '',
      date_to: '',
    }
    form.setData(next)
    router.get(route('inventory.waste.index'), next, { preserveState: true, preserveScroll: true })
  }

  const closeDialog = () => {
    setActiveDialog(null)
    setSelectedWaste(null)
    setLoadingWaste(false)
    reviewForm.reset()
    quarantineForm.reset()
    disposeForm.reset()
    reviewForm.clearErrors()
    quarantineForm.clearErrors()
    disposeForm.clearErrors()
  }

  const openDialog = async (type, item) => {
    setLoadingWaste(true)
    try {
      const response = await window.axios.get(route('inventory.waste.show', item.id))
      const waste = response.data

      setSelectedWaste(waste)
      setActiveDialog(type)

      if (type === 'review') {
        reviewForm.setData({
          status: waste.status === 'review_pending' ? 'approved' : waste.status,
          notes: waste.notes || '',
        })
      }

      if (type === 'quarantine') {
        quarantineForm.setData({
          quarantine_location_id: waste.quarantine_location_id ? String(waste.quarantine_location_id) : '',
        })
      }

      if (type === 'dispose') {
        disposeForm.setData({
          notes: waste.notes || '',
        })
      }
    } finally {
      setLoadingWaste(false)
    }
  }

  const submitReview = (event) => {
    event.preventDefault()
    if (!selectedWaste) return

    reviewForm.post(route('inventory.waste.review', selectedWaste.id), {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
  }

  const submitQuarantine = (event) => {
    event.preventDefault()
    if (!selectedWaste) return

    quarantineForm.post(route('inventory.waste.send-to-quarantine', selectedWaste.id), {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
  }

  const submitDispose = (event) => {
    event.preventDefault()
    if (!selectedWaste) return

    disposeForm.post(route('inventory.waste.dispose', selectedWaste.id), {
      preserveScroll: true,
      onSuccess: () => closeDialog(),
    })
  }

  const selectedQuarantineLocation = locationOptions.find((item) => item.value === String(quarantineForm.data.quarantine_location_id)) || null

  return (
    <div className="mx-auto py-10 space-y-4 px-10">
      <div className="grid gap-4 md:grid-cols-4">
        <Card><CardContent className="pt-6"><div className="text-sm text-gray-500">Merma total</div><div className="text-2xl font-semibold">{Number(summary?.total_quantity || 0).toLocaleString('es-CL')}</div></CardContent></Card>
        <Card><CardContent className="pt-6"><div className="text-sm text-gray-500">Pendientes revisión</div><div className="text-2xl font-semibold">{summary?.pending_review || 0}</div></CardContent></Card>
        <Card><CardContent className="pt-6"><div className="text-sm text-gray-500">Registros hoy</div><div className="text-2xl font-semibold">{summary?.reported_today || 0}</div></CardContent></Card>
        <Card><CardContent className="pt-6"><div className="text-sm text-gray-500">Top ubicación</div><div className="text-sm font-semibold">{summary?.top_locations?.[0]?.location || '-'}</div></CardContent></Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Mermas por ubicación</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}
          <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            Revisa el ciclo completo de merma: registro, revisión, cuarentena y disposición. Si el movimiento origen tuvo posición explícita, la tabla la muestra para mantener trazabilidad física desde la ubicación origen.
          </div>

          <div className="rounded border bg-white p-4 space-y-3">
            <div>
              <div className="font-medium text-slate-900">Filtros operativos</div>
              <div className="text-xs text-slate-500">Acota por ubicación, motivo, estado o ventana de tiempo para revisar la merma correcta.</div>
            </div>
            <form onSubmit={applyFilters} className="grid gap-3 md:grid-cols-4">
              <Input value={form.data.q} onChange={(e) => form.setData('q', e.target.value)} placeholder="Código o nota" />
              <SearchableSelect options={locationOptions} value={locationOptions.find((item) => item.value === String(form.data.detected_location_id)) || null} onChange={(option) => form.setData('detected_location_id', option?.value || '')} placeholder="Ubicación de ocurrencia" />
              <SearchableSelect options={materialOptions} value={materialOptions.find((item) => item.value === String(form.data.material_id)) || null} onChange={(option) => form.setData('material_id', option?.value || '')} placeholder="Material" />
              <div className="flex gap-2">
                <SearchableSelect options={reasonOptions} value={reasonOptions.find((item) => item.value === String(form.data.waste_reason_id)) || null} onChange={(option) => form.setData('waste_reason_id', option?.value || '')} placeholder="Motivo" />
                <Button type="submit">Filtrar</Button>
              </div>
              <SearchableSelect options={locationOptions} value={locationOptions.find((item) => item.value === String(form.data.quarantine_location_id)) || null} onChange={(option) => form.setData('quarantine_location_id', option?.value || '')} placeholder="Ubicación de cuarentena" />
              <SearchableSelect options={statusOptions} value={statusOptions.find((item) => item.value === String(form.data.status)) || null} onChange={(option) => form.setData('status', option?.value || '')} placeholder="Estado" />
              <Input type="date" value={form.data.date_from} onChange={(e) => form.setData('date_from', e.target.value)} />
              <div className="flex gap-2">
                <Input type="date" value={form.data.date_to} onChange={(e) => form.setData('date_to', e.target.value)} />
                <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
              </div>
            </form>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Ubicación ocurrencia</TableHead>
                <TableHead>Material</TableHead>
                <TableHead>Pallet / LPN</TableHead>
                <TableHead>Motivo</TableHead>
                <TableHead>Cuarentena</TableHead>
                <TableHead>Cantidad</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(wastes?.data || []).map((item) => (
                <TableRow key={item.id}>
                  <TableCell>
                    <div className="font-medium">{item.code}</div>
                    <div className="text-xs text-gray-500">{item.reported_at}</div>
                    {item.position_label ? <div className="text-xs text-slate-500">{item.position_label}</div> : null}
                  </TableCell>
                  <TableCell>{item.detected_location || '-'}</TableCell>
                  <TableCell>{item.material}</TableCell>
                  <TableCell>{item.logistic_unit || '-'}</TableCell>
                  <TableCell>{item.reason || '-'}</TableCell>
                  <TableCell>{item.quarantine_location || '-'}</TableCell>
                  <TableCell>{Number(item.quantity).toLocaleString('es-CL')}</TableCell>
                  <TableCell>
                    <div>{item.status}</div>
                    {item.requires_supervisor_review ? <div className="text-xs text-amber-700">Requiere revisión</div> : null}
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex justify-end gap-2">
                      <Button type="button" variant="outline" size="sm" onClick={() => openDialog('view', item)}>
                        Ver
                      </Button>
                      {(item.status === 'review_pending' || item.status === 'reported') ? <Button type="button" size="sm" onClick={() => openDialog('review', item)}>Revisar</Button> : null}
                      {item.status === 'approved' ? <Button type="button" variant="outline" size="sm" onClick={() => openDialog('quarantine', item)}>Enviar a cuarentena</Button> : null}
                      {['approved', 'sent_to_quarantine', 'reported'].includes(item.status) ? <Button type="button" variant="outline" size="sm" onClick={() => openDialog('dispose', item)}>Disponer</Button> : null}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {wastes?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {wastes.from ?? 0} a {wastes.to ?? 0} de {wastes.total ?? 0}</div>
              <div className="flex gap-1">
                {wastes.links.map((link, index) => (
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

      <Dialog open={Boolean(activeDialog)} onOpenChange={(open) => !open && closeDialog()}>
        <DialogContent className="sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {activeDialog === 'view' ? 'Detalle de merma' : null}
              {activeDialog === 'review' ? 'Revisar merma' : null}
              {activeDialog === 'quarantine' ? 'Enviar merma a cuarentena' : null}
              {activeDialog === 'dispose' ? 'Disponer merma' : null}
            </DialogTitle>
            <DialogDescription>
              {selectedWaste ? `${selectedWaste.code} · ${selectedWaste.material} · ${Number(selectedWaste.quantity).toLocaleString('es-CL')}` : ''}
            </DialogDescription>
          </DialogHeader>

          {loadingWaste ? (
            <div className="py-6 text-sm text-slate-500">Cargando detalle de merma...</div>
          ) : selectedWaste ? (
            <div className="space-y-4">
              <div className="grid gap-3 md:grid-cols-2">
                <div className="rounded border bg-slate-50 p-3 text-sm">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Ubicación detectada</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedWaste.detected_location || '-'}</div>
                </div>
                <div className="rounded border bg-slate-50 p-3 text-sm">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Pallet / LPN y posición</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedWaste.logistic_unit || 'Sin pallet / LPN'}</div>
                  {selectedWaste.position_label ? <div className="mt-1 text-xs text-slate-600">{selectedWaste.position_label}</div> : null}
                </div>
              </div>

              {selectedWaste.notes ? (
                <div className="rounded border bg-amber-50 p-3 text-sm text-amber-900">
                  <div className="font-medium">Notas registradas</div>
                  <div className="mt-1 whitespace-pre-wrap">{selectedWaste.notes}</div>
                </div>
              ) : null}

              {activeDialog === 'view' ? (
                <DialogFooter>
                  {selectedWaste.status === 'disposed' && (
                    <a
                      href={route('inventory.waste.act-pdf', selectedWaste.id)}
                      target="_blank"
                      className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-10 px-4 py-2 mr-2"
                    >
                      Ver Acta
                    </a>
                  )}
                  <Button type="button" variant="outline" onClick={closeDialog}>Cerrar</Button>
                  {selectedWaste.status === 'review_pending' ? <Button type="button" onClick={() => setActiveDialog('review')}>Abrir revisión</Button> : null}
                  {selectedWaste.status === 'approved' ? <Button type="button" onClick={() => setActiveDialog('quarantine')}>Mover a cuarentena</Button> : null}
                  {['approved', 'sent_to_quarantine'].includes(selectedWaste.status) ? <Button type="button" onClick={() => setActiveDialog('dispose')}>Ir a disposición</Button> : null}
                </DialogFooter>
              ) : null}

              {activeDialog === 'review' ? (
                <form onSubmit={submitReview} className="space-y-4">
                  <div>
                    <Label>Resultado de revisión</Label>
                    <SearchableSelect
                      options={REVIEWABLE_STATUSES}
                      value={REVIEWABLE_STATUSES.find((item) => item.value === String(reviewForm.data.status)) || null}
                      onChange={(option) => reviewForm.setData('status', option?.value || 'approved')}
                      placeholder="Selecciona resultado"
                    />
                    {reviewForm.errors.status ? <div className="mt-1 text-sm text-red-600">{reviewForm.errors.status}</div> : null}
                  </div>
                  <div>
                    <Label>Notas de revisión</Label>
                    <Textarea value={reviewForm.data.notes} onChange={(e) => reviewForm.setData('notes', e.target.value)} />
                    {reviewForm.errors.notes ? <div className="mt-1 text-sm text-red-600">{reviewForm.errors.notes}</div> : null}
                  </div>
                  <DialogFooter>
                    <Button type="button" variant="outline" onClick={closeDialog}>Cancelar</Button>
                    <Button type="submit" disabled={reviewForm.processing}>{reviewForm.processing ? 'Guardando...' : 'Guardar revisión'}</Button>
                  </DialogFooter>
                </form>
              ) : null}

              {activeDialog === 'quarantine' ? (
                <form onSubmit={submitQuarantine} className="space-y-4">
                  <div>
                    <Label>Ubicación de cuarentena</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={selectedQuarantineLocation}
                      onChange={(option) => quarantineForm.setData('quarantine_location_id', option?.value || '')}
                      placeholder="Selecciona ubicación"
                    />
                    {quarantineForm.errors.quarantine_location_id ? <div className="mt-1 text-sm text-red-600">{quarantineForm.errors.quarantine_location_id}</div> : null}
                  </div>
                  <DialogFooter>
                    <Button type="button" variant="outline" onClick={closeDialog}>Cancelar</Button>
                    <Button type="submit" disabled={quarantineForm.processing}>{quarantineForm.processing ? 'Guardando...' : 'Enviar a cuarentena'}</Button>
                  </DialogFooter>
                </form>
              ) : null}

              {activeDialog === 'dispose' ? (
                <form onSubmit={submitDispose} className="space-y-4">
                  <div>
                    <Label>Notas de disposición</Label>
                    <Textarea value={disposeForm.data.notes} onChange={(e) => disposeForm.setData('notes', e.target.value)} />
                    {disposeForm.errors.notes ? <div className="mt-1 text-sm text-red-600">{disposeForm.errors.notes}</div> : null}
                  </div>
                  <DialogFooter>
                    <Button type="button" variant="outline" onClick={closeDialog}>Cancelar</Button>
                    <Button type="submit" disabled={disposeForm.processing}>{disposeForm.processing ? 'Guardando...' : 'Confirmar disposición'}</Button>
                  </DialogFooter>
                </form>
              ) : null}
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  )
}

InventoryWasteIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Mermas</h2>}
  />
)
