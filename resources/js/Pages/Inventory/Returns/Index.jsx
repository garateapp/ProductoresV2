import { useState, useRef } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import SearchableSelect from '@/Components/SearchableSelect'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { Plus, Eye, CheckCircle, XCircle, ArrowRightLeft, Printer, Search, Filter } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

export default function ReturnIndex({ returns, filters = {}, locations = [], materials = [], statuses = [], userAssignedLocationIds = [] }) {
  const { props } = usePage()
  const [selectedReturn, setSelectedReturn] = useState(null)
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    estado: filters.estado || '',
    location_id: filters.location_id || '',
    material_id: filters.material_id || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
  })

  const locationOptions = locations.map((l) => ({ value: String(l.id), label: l.nombre }))
  const materialOptions = materials.map((m) => ({ value: String(m.id), label: `${m.codigo} · ${m.nombre}` }))
  const statusOptions = statuses.map((s) => ({ value: s, label: s }))

  const applyFilters = (e) => {
    e.preventDefault()
    router.get(route('inventory.returns.index'), filterData, { preserveState: true, preserveScroll: true })
  }

  const handleUpdateStatus = (returnId, status) => {
    if (confirm(`¿Estás seguro de cambiar el estado a ${status}?`)) {
      router.patch(route('inventory.returns.update-status', returnId), {
        estado: status
      }, {
        onSuccess: () => {
          setSelectedReturn(null)
          toast.success(`Estado actualizado a ${status}`)
        }
      })
    }
  }

  const handleGenerateTransfer = (returnId) => {
    if (confirm('¿Estás seguro de generar el traslado? Se validará el stock y se crearán los movimientos correspondientes.')) {
      router.post(route('inventory.returns.generate-transfer', returnId), {}, {
        onSuccess: () => {
          setSelectedReturn(null)
          toast.success('Devolución procesada correctamente')
        },
        onError: (errors) => {
          if (errors.stock) {
            toast.error(errors.stock)
          } else {
            toast.error('Ocurrió un error al procesar la devolución')
          }
        }
      })
    }
  }

  const printReturn = (ret) => {
    const win = window.open('', '_blank', 'width=800,height=600')
    if (!win) return

    const itemsHtml = ret.items.map((item) => {
      const lpn = item.position?.logistic_unit?.license_plate_number || item.stock_positions?.[0]?.lpn || '—'
      return `<tr>
        <td style="padding:6px 10px;border:1px solid #ccc">${item.material.codigo} · ${item.material.nombre}</td>
        <td style="padding:6px 10px;border:1px solid #ccc">${lpn}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${Number(item.cantidad_devuelta).toLocaleString('es-CL', { maximumFractionDigits: 4 })}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${item.stock_actual !== undefined ? Number(item.stock_actual).toLocaleString('es-CL', { maximumFractionDigits: 4 }) : '—'}</td>
        <td style="padding:6px 10px;border:1px solid #ccc;text-align:right">${item.notas || '-'}</td>
      </tr>`
    }).join('')

    win.document.open()
    win.document.write(`
      <html><head><title>Devolución ${ret.codigo}</title>
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
      <h1>Devolución ${ret.codigo}</h1>
      <div class="sub">Creado por ${ret.creator?.name} el ${new Date(ret.fecha_solicitud).toLocaleString()}</div>
      <div class="info">
        <div><strong>Origen</strong>${ret.origin_location?.nombre || '—'}</div>
        <div><strong>Destino</strong>${ret.destination_location?.nombre || '—'}</div>
        ${ret.fecha_requerida ? `<div><strong>Fecha Requerida</strong>${new Date(ret.fecha_requerida).toLocaleDateString()}</div>` : ''}
        <div><strong>Estado</strong>${ret.estado}</div>
      </div>
      ${ret.observacion ? `<p style="margin-bottom:16px;font-size:12px;background:#fafafa;padding:10px;border-radius:4px"><strong>Observación:</strong> ${ret.observacion}</p>` : ''}
      <table>
        <thead><tr>
          <th>Material</th>
          <th>LPN</th>
          <th style="text-align:right">Cant. Devuelta</th>
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

  const canApprove = (ret) => {
    return userAssignedLocationIds.includes(ret.destination_location_id)
  }

  const getStatusBadge = (status) => {
    const variants = {
      pendiente: 'outline',
      aprobado: 'success',
      rechazado: 'destructive',
      completado: 'default',
    }
    return <Badge variant={variants[status] || 'outline'}>{status}</Badge>
  }

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Devoluciones de Materiales</h2>}
    >
      <Head title="Devoluciones" />
      <Toaster position="top-right" richColors />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
          <Card>
            <CardHeader>
              <div className="flex justify-between items-center">
                <CardTitle>Listado de Devoluciones</CardTitle>
                <Link href={route('inventory.returns.create')}>
                  <Button>
                    <Plus className="w-4 h-4 mr-2" /> Nueva Devolución
                  </Button>
                </Link>
              </div>
            </CardHeader>
            <CardContent>
              {/* Filtros */}
              <form onSubmit={applyFilters} className="flex flex-wrap gap-3 mb-4">
                <div className="min-w-[200px] flex-1">
                  <Input
                    placeholder="Buscar por código u observación..."
                    value={filterData.q}
                    onChange={(e) => setFilterData({ ...filterData, q: e.target.value })}
                  />
                </div>
                <div className="w-40">
                  <SearchableSelect
                    options={statusOptions}
                    value={statusOptions.find((s) => s.value === filterData.estado) || null}
                    onChange={(opt) => setFilterData({ ...filterData, estado: opt?.value || '' })}
                    placeholder="Todos los estados"
                  />
                </div>
                <div className="w-48">
                  <SearchableSelect
                    options={locationOptions}
                    value={locationOptions.find((l) => l.value === filterData.location_id) || null}
                    onChange={(opt) => setFilterData({ ...filterData, location_id: opt?.value || '' })}
                    placeholder="Ubicación origen"
                  />
                </div>
                <div className="w-48">
                  <SearchableSelect
                    options={materialOptions}
                    value={materialOptions.find((m) => m.value === filterData.material_id) || null}
                    onChange={(opt) => setFilterData({ ...filterData, material_id: opt?.value || '' })}
                    placeholder="Material"
                  />
                </div>
                <div className="w-40">
                  <Input
                    type="date"
                    value={filterData.date_from}
                    onChange={(e) => setFilterData({ ...filterData, date_from: e.target.value })}
                    placeholder="Desde"
                  />
                </div>
                <div className="w-40">
                  <Input
                    type="date"
                    value={filterData.date_to}
                    onChange={(e) => setFilterData({ ...filterData, date_to: e.target.value })}
                    placeholder="Hasta"
                  />
                </div>
                <Button type="submit" variant="secondary">
                  <Search className="w-4 h-4 mr-1" /> Filtrar
                </Button>
              </form>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Código</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Creado por</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Destino</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {returns.data?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-sm text-gray-500 py-8">
                        No hay devoluciones registradas.
                      </TableCell>
                    </TableRow>
                  )}
                  {returns.data?.map((ret) => (
                    <TableRow key={ret.id}>
                      <TableCell className="font-mono text-sm">{ret.codigo}</TableCell>
                      <TableCell>{new Date(ret.fecha_solicitud).toLocaleDateString()}</TableCell>
                      <TableCell>{ret.creator?.name}</TableCell>
                      <TableCell>{ret.origin_location?.nombre}</TableCell>
                      <TableCell>{ret.destination_location?.nombre}</TableCell>
                      <TableCell>{getStatusBadge(ret.estado)}</TableCell>
                      <TableCell className="text-right space-x-2">
                        {ret.estado === 'pendiente' && canApprove(ret) && (
                          <>
                            <Button
                              variant="ghost"
                              size="sm"
                              title="Aprobar"
                              onClick={() => handleUpdateStatus(ret.id, 'aprobado')}
                            >
                              <CheckCircle className="w-4 h-4 text-green-500" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              title="Rechazar"
                              onClick={() => handleUpdateStatus(ret.id, 'rechazado')}
                            >
                              <XCircle className="w-4 h-4 text-red-500" />
                            </Button>
                          </>
                        )}
                        {ret.estado === 'aprobado' && (
                          <Button
                            variant="ghost"
                            size="sm"
                            title="Generar Traslado"
                            onClick={() => handleGenerateTransfer(ret.id)}
                          >
                            <ArrowRightLeft className="w-4 h-4 text-blue-500" />
                          </Button>
                        )}
                        <Button variant="ghost" size="sm" title="Ver Detalle" onClick={() => setSelectedReturn(ret)}>
                          <Eye className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>

              {returns?.links?.length ? (
                <div className="flex justify-between text-sm text-gray-600 mt-4">
                  <div>Mostrando {returns.from ?? 0} a {returns.to ?? 0} de {returns.total ?? 0}</div>
                  <div className="flex gap-1">
                    {returns.links.map((link, index) => (
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

      {/* Detail Dialog */}
      <Dialog open={Boolean(selectedReturn)} onOpenChange={(open) => !open && setSelectedReturn(null)}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Detalle de Devolución {selectedReturn?.codigo}</DialogTitle>
            <DialogDescription>
              Creado por {selectedReturn?.creator?.name} el {selectedReturn && new Date(selectedReturn.fecha_solicitud).toLocaleString()}
            </DialogDescription>
          </DialogHeader>

          {selectedReturn && (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Ubicación Origen</span>
                  <span className="text-lg">{selectedReturn.origin_location?.nombre}</span>
                </div>
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Ubicación Destino</span>
                  <span className="text-lg">{selectedReturn.destination_location?.nombre}</span>
                </div>
                {selectedReturn.fecha_requerida && (
                  <div>
                    <span className="font-semibold block text-slate-500 uppercase text-xs">Fecha Requerida</span>
                    <span>{new Date(selectedReturn.fecha_requerida).toLocaleDateString()}</span>
                  </div>
                )}
                <div>
                  <span className="font-semibold block text-slate-500 uppercase text-xs">Estado Actual</span>
                  {getStatusBadge(selectedReturn.estado)}
                </div>
              </div>

              {selectedReturn.observacion && (
                <div className="bg-slate-50 p-3 rounded text-sm border">
                  <span className="font-semibold block text-slate-500 uppercase text-xs mb-1">Observación</span>
                  {selectedReturn.observacion}
                </div>
              )}

              <div className="border rounded-lg overflow-hidden">
                <Table>
                  <TableHeader className="bg-slate-50">
                    <TableRow>
                      <TableHead>Material</TableHead>
                      <TableHead>LPN / Posición</TableHead>
                      <TableHead className="text-right">Cant. Devuelta</TableHead>
                      <TableHead className="text-right">Stock en Origen</TableHead>
                      <TableHead>Ubicaciones / LPN</TableHead>
                      <TableHead>Notas</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedReturn.items.map((item) => {
                      const positionLpn = item.position?.logistic_unit?.license_plate_number || item.stock_positions?.[0]?.lpn || '—'
                      return (
                      <TableRow key={item.id}>
                        <TableCell className="font-medium">
                          {item.material.codigo} · {item.material.nombre}
                        </TableCell>
                        <TableCell className="font-mono text-xs">
                          {positionLpn}
                          {item.position_id && (
                            <span className="text-slate-400 ml-1">(#{(item.position_id)})</span>
                          )}
                        </TableCell>
                        <TableCell className="text-right">
                          {Number(item.cantidad_devuelta).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                        </TableCell>
                        <TableCell className="text-right">
                          {item.stock_actual !== undefined ? (
                            <span className={item.stock_actual >= item.cantidad_devuelta ? 'text-green-600 font-medium' : 'text-red-600 font-medium'}>
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
                    )})}
                  </TableBody>
                </Table>
              </div>

              <div className="flex justify-end gap-2 pt-4">
                {selectedReturn.estado === 'pendiente' && canApprove(selectedReturn) && (
                  <>
                    <Button variant="outline" className="text-red-600 hover:bg-red-50 border-red-200" onClick={() => {
                      handleUpdateStatus(selectedReturn.id, 'rechazado')
                    }}>
                      Rechazar Devolución
                    </Button>
                    <Button className="bg-green-600 hover:bg-green-700" onClick={() => {
                      handleUpdateStatus(selectedReturn.id, 'aprobado')
                    }}>
                      Aprobar Devolución
                    </Button>
                  </>
                )}
                {selectedReturn.estado === 'aprobado' && (
                  <Button className="bg-blue-600 hover:bg-blue-700" onClick={() => {
                    handleGenerateTransfer(selectedReturn.id)
                  }}>
                    <ArrowRightLeft className="w-4 h-4 mr-2" />
                    Generar Traslado
                  </Button>
                )}
                <Button variant="outline" onClick={() => printReturn(selectedReturn)}>
                  <Printer className="w-4 h-4 mr-2" />
                  Imprimir
                </Button>
                <Button variant="secondary" onClick={() => setSelectedReturn(null)}>Cerrar</Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
