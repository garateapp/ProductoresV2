import { useState, useRef } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import SearchableSelect from '@/Components/SearchableSelect'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { Plus, Eye, CheckCircle, XCircle, ArrowRightLeft, Upload, Search, Filter, Printer, Pencil, Save, X } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

export default function MaterialRequestIndex({ requests, filters = {}, locations = [], materials = [], statuses = [], userAssignedLocationIds = [] }) {
  const { props } = usePage()
  const [selectedRequest, setSelectedRequest] = useState(null)
  const [showImportDialog, setShowImportDialog] = useState(false)
  const [importing, setImporting] = useState(false)
  const fileInputRef = useRef(null)
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    estado: filters.estado || '',
    location_id: filters.location_id || '',
    material_id: filters.material_id || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
  })

  const [editingItemId, setEditingItemId] = useState(null)
  const [editCantidad, setEditCantidad] = useState('')
  const [editMotivo, setEditMotivo] = useState('')
  const [savingItem, setSavingItem] = useState(false)

  const locationOptions = locations.map((l) => ({ value: String(l.id), label: l.nombre }))
  const materialOptions = materials.map((m) => ({ value: String(m.id), label: `${m.codigo} · ${m.nombre}` }))
  const statusOptions = statuses.map((s) => ({ value: s, label: s }))

  const applyFilters = (e) => {
    e.preventDefault()
    router.get(route('inventory.material-requests.index'), filterData, { preserveState: true, preserveScroll: true })
  }

  const handleImportCsv = (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    setImporting(true)
    const formData = new FormData()
    formData.append('file', file)

    router.post(route('inventory.materials.import-csv'), formData, {
      onSuccess: () => {
        setShowImportDialog(false)
        setImporting(false)
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
      onError: (errors) => {
        setImporting(false)
        toast.error(errors.file || 'Error al importar el archivo')
      },
      onFinish: () => {
        setImporting(false)
        if (fileInputRef.current) fileInputRef.current.value = ''
      }
    })
  }

  const handleUpdateStatus = (requestId, status) => {
    if (confirm(`¿Estás seguro de cambiar el estado a ${status}?`)) {
      router.patch(route('inventory.material-requests.update-status', requestId), {
        estado: status
      }, {
        onSuccess: () => {
          setSelectedRequest(null)
          toast.success(`Estado actualizado a ${status}`)
        }
      })
    }
  }

  const handleGenerateTransfer = (requestId) => {
    if (confirm('¿Estás seguro de generar el traslado? Se validará el stock y se crearán los movimientos correspondientes.')) {
      router.post(route('inventory.material-requests.generate-transfer', requestId), {}, {
        onSuccess: () => {
          setSelectedRequest(null)
          toast.success('Traslado generado correctamente')
        },
        onError: (errors) => {
          if (errors.stock) {
            toast.error(errors.stock)
          } else {
            toast.error('Ocurrió un error al generar el traslado')
          }
        }
      })
    }
  }

  const printRequest = (request) => {
    const win = window.open('', '_blank', 'width=800,height=600')
    if (!win) return

    const itemsHtml = request.items.map((item) =>
      `<tr>
        <td style="padding:6px 10px;border:1px solid #ccc">${item.material.codigo} · ${item.material.nombre}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${Number(item.cantidad_solicitada).toLocaleString('es-CL', { maximumFractionDigits: 4 })}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${item.stock_actual !== undefined ? Number(item.stock_actual).toLocaleString('es-CL', { maximumFractionDigits: 4 }) : '—'}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${item.notas || '-'}</td>
      </tr>`
    ).join('')

    win.document.open()
    win.document.write(`
      <html><head><title>Solicitud ${request.codigo}</title>
      <style>
        body { font-family: Arial, sans-serif; font-size: 13px; padding: 30px; color: #222; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        .sub { color: #666; font-size: 12px; margin-bottom: 20px; }
        .info { display: flex; gap: 30px; margin-bottom: 20px; }
        .info div { font-size: 12px; }
        .info strong { display: block; color: #888; text-transform: uppercase; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f5f5f5; padding: 6px 10px; border: 1px solid #ccc; text-align: left; font-size: 11px; text-transform: uppercase; color: #555; }
        td { padding: 6px 10px; border: 1px solid #ccc; }
        .footer { margin-top: 30px; font-size: 11px; color: #999; text-align: center; }
      </style></head><body>
      <h1>Solicitud ${request.codigo}</h1>
      <div class="sub">Solicitado por ${request.creator?.name} el ${new Date(request.fecha_solicitud).toLocaleString()}</div>
      <div class="info">
        <div><strong>Origen</strong>${request.origin_location?.nombre || '—'}</div>
        <div><strong>Destino</strong>${request.destination_location?.nombre || '—'}</div>
        ${request.fecha_requerida ? `<div><strong>Fecha Requerida</strong>${new Date(request.fecha_requerida).toLocaleDateString()}</div>` : ''}
        <div><strong>Estado</strong>${request.estado}</div>
      </div>
      ${request.observacion ? `<p style="margin-bottom:16px;font-size:12px;background:#fafafa;padding:10px;border-radius:4px"><strong>Observación:</strong> ${request.observacion}</p>` : ''}
      <table>
        <thead><tr>
          <th>Material</th>
          <th style="text-align:right">Cant. Solicitada</th>
          <th style="text-align:right">Stock Origen</th>
          <th style="text-align:right">Notas</th>
        </tr></thead>
        <tbody>${itemsHtml}</tbody>
      </table>
      <div class="footer">Generado el ${new Date().toLocaleString()}</div>
    </body></html>`)
    win.document.close()
    win.focus()
    win.setTimeout(() => win.print(), 300)
  }

  const startEditQuantity = (item) => {
    setEditingItemId(item.id)
    setEditCantidad(String(Number(item.cantidad_solicitada).toFixed(4))
      .replace(/\.?0+$/, ''))
    setEditMotivo('')
  }

  const cancelEditQuantity = () => {
    setEditingItemId(null)
    setEditCantidad('')
    setEditMotivo('')
  }

  const handleSaveQuantity = (itemId) => {
    if (!editCantidad || Number(editCantidad) <= 0) {
      toast.error('La cantidad debe ser mayor a cero.')
      return
    }
    if (!editMotivo.trim()) {
      toast.error('Debes indicar el motivo del cambio.')
      return
    }

    setSavingItem(true)
    router.patch(route('inventory.material-requests.update-item-quantity', {
      material_request: selectedRequest.id,
      item: itemId,
    }), {
      cantidad: editCantidad,
      motivo_cambio: editMotivo,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setSelectedRequest((prev) => {
          if (!prev) return prev
          return {
            ...prev,
            items: prev.items.map((item) =>
              item.id === itemId
                ? { ...item, cantidad_solicitada: Number(editCantidad) }
                : item
            ),
          }
        })
        cancelEditQuantity()
        setSavingItem(false)
        toast.success('Cantidad actualizada correctamente.')
      },
      onError: (errors) => {
        setSavingItem(false)
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al actualizar la cantidad.')
      },
    })
  }

  const canApprove = (request) => {
    return userAssignedLocationIds.includes(request.origin_location_id)
  }

  const getStatusBadge = (status) => {
    const variants = {
      pendiente: 'outline',
      aprobado: 'success',
      rechazado: 'destructive',
      completado: 'default',
    }
    return <Badge variant={variants[status] || 'default'}>{status.toUpperCase()}</Badge>
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Solicitudes de Materiales</h2>
          <div className="flex gap-2">
            <Button variant="outline" onClick={() => setShowImportDialog(true)}>
              <Upload className="w-4 h-4 mr-2" /> Importar Materiales
            </Button>
            <Link href={route('inventory.material-requests.create')}>
              <Button>
                <Plus className="w-4 h-4 mr-2" /> Nueva Solicitud
              </Button>
            </Link>
          </div>
        </div>
      }
    >
      <Head title="Solicitudes" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Historial de Solicitudes</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={applyFilters} className="grid gap-3 rounded border p-4 mb-6 md:grid-cols-6">
                <Input
                  value={filterData.q}
                  onChange={(e) => setFilterData({ ...filterData, q: e.target.value })}
                  placeholder="Buscar folio..."
                />
                <SearchableSelect
                  options={statusOptions}
                  value={statusOptions.find((s) => s.value === filterData.estado) || null}
                  onChange={(opt) => setFilterData({ ...filterData, estado: opt?.value || '' })}
                  placeholder="Todos los estados"
                />
                <SearchableSelect
                  options={locationOptions}
                  value={locationOptions.find((l) => l.value === filterData.location_id) || null}
                  onChange={(opt) => setFilterData({ ...filterData, location_id: opt?.value || '' })}
                  placeholder="Todas las ubicaciones"
                />
                <SearchableSelect
                  options={materialOptions}
                  value={materialOptions.find((m) => m.value === filterData.material_id) || null}
                  onChange={(opt) => setFilterData({ ...filterData, material_id: opt?.value || '' })}
                  placeholder="Todos los materiales"
                />
                <Input
                  type="date"
                  value={filterData.date_from}
                  onChange={(e) => setFilterData({ ...filterData, date_from: e.target.value })}
                  placeholder="Desde"
                />
                <div className="flex gap-2">
                  <Input
                    type="date"
                    value={filterData.date_to}
                    onChange={(e) => setFilterData({ ...filterData, date_to: e.target.value })}
                    placeholder="Hasta"
                  />
                  <Button type="submit"><Search className="w-4 h-4" /></Button>
                </div>
              </form>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Folio</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Solicitante</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Destino</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {requests.data.map((request) => (
                    <TableRow key={request.id}>
                      <TableCell className="font-mono">{request.codigo}</TableCell>
                      <TableCell>{new Date(request.fecha_solicitud).toLocaleDateString()}</TableCell>
                      <TableCell>{request.creator.name}</TableCell>
                      <TableCell>{request.origin_location.nombre}</TableCell>
                      <TableCell>{request.destination_location.nombre}</TableCell>
                      <TableCell>{getStatusBadge(request.estado)}</TableCell>
                      <TableCell className="text-right space-x-2">
                        {request.estado === 'pendiente' && canApprove(request) && (
                          <>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              title="Aprobar"
                              onClick={() => handleUpdateStatus(request.id, 'aprobado')}
                            >
                              <CheckCircle className="w-4 h-4 text-green-500" />
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              title="Rechazar"
                              onClick={() => handleUpdateStatus(request.id, 'rechazado')}
                            >
                              <XCircle className="w-4 h-4 text-red-500" />
                            </Button>
                          </>
                        )}
                        {request.estado === 'aprobado' && (
                          <Button 
                            variant="ghost" 
                            size="sm" 
                            title="Generar Traslado"
                            onClick={() => handleGenerateTransfer(request.id)}
                          >
                            <ArrowRightLeft className="w-4 h-4 text-blue-500" />
                          </Button>
                        )}
                        <Button variant="ghost" size="sm" title="Ver Detalle" onClick={() => setSelectedRequest(request)}>
                          <Eye className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {requests?.links?.length ? (
                <div className="flex justify-between text-sm text-gray-600 mt-4">
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
      </div>

      <Dialog open={Boolean(selectedRequest)} onOpenChange={(open) => !open && setSelectedRequest(null)}>
        <DialogContent className="max-w-3xl max-h-[85vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Detalle de Solicitud {selectedRequest?.codigo}</DialogTitle>
            <DialogDescription>
              Solicitado por {selectedRequest?.creator?.name} el {selectedRequest && new Date(selectedRequest.fecha_solicitud).toLocaleString()}
            </DialogDescription>
          </DialogHeader>

          {selectedRequest && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Ubicación Origen</span>
                  <span className="text-lg">{selectedRequest.origin_location.nombre}</span>
                </div>
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Ubicación Destino</span>
                  <span className="text-lg">{selectedRequest.destination_location.nombre}</span>
                </div>
                {selectedRequest.fecha_requerida && (
                  <div>
                    <span className="font-semibold block text-slate-500 uppercase text-xs">Fecha Requerida</span>
                    <span>{new Date(selectedRequest.fecha_requerida).toLocaleDateString()}</span>
                  </div>
                )}
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Estado Actual</span>
                  {getStatusBadge(selectedRequest.estado)}
                </div>
              </div>

              {selectedRequest.observacion && (
                <div className="bg-slate-50 p-3 rounded text-sm border">
                  <span className="font-semibold block text-slate-500 uppercase text-xs mb-1">Observación</span>
                  {selectedRequest.observacion}
                </div>
              )}

              <div className="border rounded-lg overflow-hidden">
                <Table>
                  <TableHeader className="bg-slate-50">
                    <TableRow>
                      <TableHead>Material</TableHead>
                      <TableHead className="text-right">Cantidad Solicitada</TableHead>
                      <TableHead className="text-right">Stock en Origen</TableHead>
                      <TableHead>Ubicaciones / LPN</TableHead>
                      <TableHead>Notas</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedRequest.items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell className="font-medium">
                          {item.material.codigo} · {item.material.nombre}
                        </TableCell>
                        <TableCell className="text-right min-w-[180px]">
                          {editingItemId === item.id ? (
                            <div className="flex flex-col gap-1">
                              <Input
                                type="number"
                                step="0.0001"
                                size="sm"
                                value={editCantidad}
                                onChange={(e) => setEditCantidad(e.target.value)}
                                className="h-7 text-xs"
                              />
                              <Input
                                placeholder="Motivo del cambio *"
                                size="sm"
                                value={editMotivo}
                                onChange={(e) => setEditMotivo(e.target.value)}
                                className="h-7 text-xs"
                              />
                              <div className="flex gap-1 justify-end">
                                <Button type="button" variant="ghost" size="sm" className="h-6 w-6 p-0" onClick={cancelEditQuantity} disabled={savingItem}>
                                  <X className="w-3 h-3" />
                                </Button>
                                <Button type="button" variant="default" size="sm" className="h-6 text-xs px-2" onClick={() => handleSaveQuantity(item.id)} disabled={savingItem}>
                                  <Save className="w-3 h-3 mr-1" />
                                  {savingItem ? '...' : 'Guardar'}
                                </Button>
                              </div>
                            </div>
                          ) : (
                            <div className="flex items-center justify-end gap-1">
                              <span>{Number(item.cantidad_solicitada).toLocaleString('es-CL', { maximumFractionDigits: 4 })}</span>
                              {selectedRequest?.estado === 'pendiente' && canApprove(selectedRequest) && (
                                <Button type="button" variant="ghost" size="sm" className="h-5 w-5 p-0" onClick={() => startEditQuantity(item)} title="Editar cantidad">
                                  <Pencil className="w-3 h-3 text-slate-400" />
                                </Button>
                              )}
                            </div>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          {item.stock_actual !== undefined ? (
                            <span className={item.stock_actual >= item.cantidad_solicitada ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
                              {Number(item.stock_actual).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                            </span>
                          ) : (
                            <span className="text-slate-400">—</span>
                          )}
                        </TableCell>
                        <TableCell className="max-w-[200px]">
                          {item.stock_positions?.length ? (
                            <div className="flex flex-wrap gap-1">
                              {item.stock_positions.map((sp) => (
                                <span key={sp.id} className="inline-block rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-700 font-mono">
                                  {sp.license_plate || `#${sp.id}`}
                                  <span className="text-slate-400 ml-1">{Number(sp.quantity).toLocaleString('es-CL', { maximumFractionDigits: 2 })}</span>
                                </span>
                              ))}
                            </div>
                          ) : (
                            <span className="text-xs text-slate-400">—</span>
                          )}
                        </TableCell>
                        <TableCell className="text-sm text-slate-600 italic">
                          {item.notas || '-'}
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>

              <div className="flex justify-end gap-2 pt-4">
                {selectedRequest.estado === 'pendiente' && canApprove(selectedRequest) && (
                  <>
                    <Button variant="outline" className="text-red-600 hover:bg-red-50 border-red-200" onClick={() => {
                      handleUpdateStatus(selectedRequest.id, 'rechazado')
                    }}>
                      Rechazar Solicitud
                    </Button>
                    <Button className="bg-green-600 hover:bg-green-700" onClick={() => {
                      handleUpdateStatus(selectedRequest.id, 'aprobado')
                    }}>
                      Aprobar Solicitud
                    </Button>
                  </>
                )}
                {selectedRequest.estado === 'aprobado' && (
                  <Button className="bg-blue-600 hover:bg-blue-700" onClick={() => {
                    handleGenerateTransfer(selectedRequest.id)
                  }}>
                    <ArrowRightLeft className="w-4 h-4 mr-2" />
                    Generar Traslado
                  </Button>
                )}
                <Button variant="outline" onClick={() => printRequest(selectedRequest)}>
                  <Printer className="w-4 h-4 mr-2" />
                  Imprimir
                </Button>
                <Button variant="secondary" onClick={() => setSelectedRequest(null)}>Cerrar</Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>

      <Dialog open={showImportDialog} onOpenChange={setShowImportDialog}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Importar Materiales desde CSV</DialogTitle>
            <DialogDescription>
              Sube un archivo CSV con los campos: codigo, nombre, unidad, tipo. El campo servicio es opcional.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div className="rounded border border-dashed border-slate-300 p-6 text-center">
              <Upload className="w-8 h-8 mx-auto mb-2 text-slate-400" />
              <p className="text-sm text-slate-600 mb-2">Selecciona un archivo CSV</p>
              <input
                ref={fileInputRef}
                type="file"
                accept=".csv"
                onChange={handleImportCsv}
                className="hidden"
                id="csv-upload"
              />
              <label htmlFor="csv-upload">
                <Button type="button" variant="outline" asChild>
                  <span>Buscar archivo</span>
                </Button>
              </label>
            </div>
            {importing && (
              <div className="text-center text-sm text-slate-500">Importando...</div>
            )}
            <div className="text-xs text-slate-500">
              <p className="font-medium mb-1">Formato esperado:</p>
              <code className="block bg-slate-100 p-2 rounded">codigo,nombre,unidad,tipo,servicio</code>
            </div>
          </div>
        </DialogContent>
      </Dialog>

      {props?.flash?.success && (
        <div className="fixed bottom-4 right-4 rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-lg">
          {props.flash.success}
        </div>
      )}
      {props?.flash?.error && (
        <div className="fixed bottom-4 right-4 rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 shadow-lg">
          {props.flash.error}
        </div>
      )}
    </AuthenticatedLayout>
  )
}
