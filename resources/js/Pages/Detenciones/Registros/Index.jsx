import { useEffect, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { toast, Toaster } from 'sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/Components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Plus, Pencil, Play, Search, Trash2, CircleOff, X, Loader2 } from 'lucide-react'

const toDateTimeLocal = (value) => (value ? String(value).replace(' ', 'T') : '')

const fmtMin = (min = 0) => {
  const m = Number(min || 0)
  const h = Math.floor(m / 60)
  const rest = m % 60
  return (h > 0 ? `${h}h ${rest}m` : `${rest}m`)
}

export default function RegistrosIndex({ turnos, maquinas, motivos, filtros }) {
  const [showNuevoTurno, setShowNuevoTurno] = useState(false)
  const [detencionTarget, setDetencionTarget] = useState(null)
  const [cerrarTarget, setCerrarTarget] = useState(null)
  const [editTarget, setEditTarget] = useState(null)

  const [filtroMaquina, setFiltroMaquina] = useState(filtros?.maquina_id ? String(filtros.maquina_id) : '')
  const [filtroDesde, setFiltroDesde] = useState(filtros?.fecha_desde || '')
  const [filtroHasta, setFiltroHasta] = useState(filtros?.fecha_hasta || '')

  const turnoForm = useForm({
    maquina_id: '',
    fecha: '',
    hora_inicio_turno: '',
    operador: '',
    observaciones: '',
  })

  const detencionForm = useForm({
    tipo_id: '',
    motivo_causa_id: '',
    hora_detencion: '',
    hora_reinicio: '',
    observaciones: '',
  })

  const cerrarForm = useForm({ hora_fin_turno: '' })

  useEffect(() => {
    if (detencionTarget) {
      detencionForm.reset()
      detencionForm.setData({
        tipo_id: '',
        motivo_causa_id: '',
        hora_detencion: toDateTimeLocal(new Date().toISOString().slice(0, 16)),
        hora_reinicio: '',
        observaciones: '',
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [detencionTarget])

  useEffect(() => {
    if (editTarget) {
      const causa = motivos.flatMap((t) => t.causas).find((c) => c.id === editTarget.motivo_causa_id)
      const tipoId = motivos.find((t) => t.causas.some((c) => c.id === editTarget.motivo_causa_id))?.id || ''
      detencionForm.setData({
        tipo_id: String(tipoId || ''),
        motivo_causa_id: String(editTarget.motivo_causa_id),
        hora_detencion: toDateTimeLocal(editTarget.hora_detencion),
        hora_reinicio: editTarget.hora_reinicio ? toDateTimeLocal(editTarget.hora_reinicio) : '',
        observaciones: editTarget.observaciones || '',
      })
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [editTarget])

  useEffect(() => {
    if (cerrarTarget) {
      cerrarForm.setData('hora_fin_turno', toDateTimeLocal(new Date().toISOString().slice(0, 16)))
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [cerrarTarget])

  const causasDeTipo = motivoTipoId => {
    const tipo = motivos.find(t => String(t.id) === String(motivoTipoId))
    return tipo ? tipo.causas : []
  }

  const aplicarFiltros = () => {
    router.get(
      route('detenciones.registros.index'),
      {
        maquina_id: filtroMaquina || undefined,
        fecha_desde: filtroDesde || undefined,
        fecha_hasta: filtroHasta || undefined,
      },
      { preserveState: false, preserveScroll: true }
    )
  }

  const submitTurno = (e) => {
    e.preventDefault()
    turnoForm.post(route('detenciones.turnos.store'), {
      onSuccess: () => {
        setShowNuevoTurno(false)
        toast.success('Turno iniciado correctamente')
      },
    })
  }

  const submitDetencion = (e) => {
    e.preventDefault()
    const isEdit = !!editTarget
    const onSuccess = () => {
      setDetencionTarget(null)
      setEditTarget(null)
      toast.success(isEdit ? 'Detención actualizada' : 'Detención registrada')
    }
    if (isEdit) {
      detencionForm.patch(route('detenciones.detenciones.update', editTarget.id), { onSuccess })
    } else {
      detencionForm.post(route('detenciones.turnos.detenciones.store', detencionTarget.id), { onSuccess })
    }
  }

  const submitCerrar = (e) => {
    e.preventDefault()
    cerrarForm.patch(route('detenciones.turnos.close', cerrarTarget.id), {
      onSuccess: () => {
        setCerrarTarget(null)
        toast.success('Turno cerrado correctamente')
      },
    })
  }

  const eliminarDetencion = (detencion) => {
    if (!confirm('¿Eliminar esta detención?')) return
    router.delete(route('detenciones.detenciones.destroy', detencion.id), {
      onSuccess: () => toast.success('Detención eliminada'),
    })
  }

  const errorMsg = (form) => Object.values(form.errors)[0]

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Registro de Detenciones</h2>
          <Button onClick={() => setShowNuevoTurno(true)}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Turno
          </Button>
        </div>
      }
    >
      <Head title="Detenciones · Registro" />
      <Toaster />

      <div className="py-12">
        <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
          <Card>
            <CardContent className="pt-6">
              <div className="flex flex-wrap items-end gap-3">
                <div>
                  <Label className="text-xs text-gray-500">Máquina</Label>
                  <Select value={filtroMaquina} onValueChange={(v) => setFiltroMaquina(v)}>
                    <SelectTrigger className="w-56">
                      <SelectValue placeholder="Todas las máquinas" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="0">Todas las máquinas</SelectItem>
                      {(filtros?.maquinas || maquinas).map((m) => (
                        <SelectItem key={m.id} value={String(m.id)}>{m.codigo} · {m.nombre}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label className="text-xs text-gray-500">Desde</Label>
                  <Input type="date" className="w-40" value={filtroDesde} onChange={(e) => setFiltroDesde(e.target.value)} />
                </div>
                <div>
                  <Label className="text-xs text-gray-500">Hasta</Label>
                  <Input type="date" className="w-40" value={filtroHasta} onChange={(e) => setFiltroHasta(e.target.value)} />
                </div>
                <Button variant="secondary" onClick={aplicarFiltros}>
                  <Search className="w-4 h-4 mr-2" /> Filtrar
                </Button>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Turno</TableHead>
                    <TableHead>Máquina</TableHead>
                    <TableHead>Inicio</TableHead>
                    <TableHead>Fin</TableHead>
                    <TableHead>Trabajado</TableHead>
                    <TableHead>Detenciones</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {turnos.map((turno) => {
                    const abierto = !turno.cerrado
                    return (
                      <TableRow key={turno.id} className="align-top">
                        <TableCell className="py-3">
                          <div className="font-medium">{turno.fecha}</div>
                          <div className="text-xs text-gray-500">{turno.operador || 'Sin operador'}</div>
                        </TableCell>
                        <TableCell className="py-3">
                          <div className="font-medium">{turno.maquina?.nombre}</div>
                          <div className="font-mono text-xs text-gray-500">{turno.maquina?.codigo}</div>
                        </TableCell>
                        <TableCell className="py-3 font-mono text-xs">{turno.hora_inicio_turno}</TableCell>
                        <TableCell className="py-3 font-mono text-xs">{turno.hora_fin_turno || <span className="text-rose-600">en curso</span>}</TableCell>
                        <TableCell className="py-3 text-xs">{turno.minutos_trabajados != null ? fmtMin(turno.minutos_trabajados) : '—'}</TableCell>
                        <TableCell className="py-3">
                          <div className="space-y-1">
                            {turno.registros.map((r) => (
                              <div key={r.id} className="rounded border border-gray-100 bg-gray-50 px-2 py-1 text-xs">
                                <div className="flex items-center gap-2">
                                  <span className="font-semibold">{r.hora_detencion}</span>
                                  {r.en_curso
                                    ? <Badge variant="destructive" className="text-[10px]">en curso</Badge>
                                    : <span className="text-gray-600">→ {r.hora_reinicio}</span>}
                                  {!r.en_curso && <Badge variant="secondary" className="text-[10px]">{fmtMin(r.minutos)}</Badge>}
                                  {abierto && (
                                    <span className="ml-auto flex gap-1">
                                      <button type="button" title="Editar" onClick={() => setEditTarget(r)} className="text-gray-500 hover:text-gray-800">
                                        <Pencil className="h-3.5 w-3.5" />
                                      </button>
                                      <button type="button" title="Eliminar" onClick={() => eliminarDetencion(r)} className="text-gray-500 hover:text-rose-600">
                                        <Trash2 className="h-3.5 w-3.5" />
                                      </button>
                                    </span>
                                  )}
                                </div>
                                <div className="mt-0.5 text-gray-600">
                                  <span className="font-semibold">{r.tipo?.nombre}</span> / {r.causa?.nombre}
                                  {r.observaciones && <span> — {r.observaciones}</span>}
                                </div>
                              </div>
                            ))}
                            {turno.registros.length === 0 && <span className="text-xs text-gray-400">Sin detenciones</span>}
                          </div>
                        </TableCell>
                        <TableCell className="py-3">
                          <Badge variant={abierto ? 'secondary' : 'default'} className={abierto ? 'bg-amber-100 text-amber-800' : ''}>
                            {abierto ? 'En curso' : 'Cerrado'}
                          </Badge>
                        </TableCell>
                        <TableCell className="py-3 text-right">
                          <div className="flex justify-end gap-1">
                            {abierto && (
                              <>
                                <Button size="sm" variant="ghost" title="Registrar detención" onClick={() => setDetencionTarget(turno)}>
                                  <CircleOff className="w-4 h-4" />
                                </Button>
                                <Button size="sm" variant="ghost" title="Cerrar turno" onClick={() => setCerrarTarget(turno)}>
                                  <X className="w-4 h-4" />
                                </Button>
                              </>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                  {turnos.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={8} className="py-10 text-center text-gray-500">
                        Sin turnos registrados para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Nuevo turno */}
      <Dialog open={showNuevoTurno} onOpenChange={setShowNuevoTurno}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Nuevo Turno</DialogTitle>
            <DialogDescription>Define el inicio del turno de la máquina.</DialogDescription>
          </DialogHeader>
          <form onSubmit={submitTurno} className="space-y-4">
            <div>
              <Label>Máquina</Label>
              <Select value={turnoForm.data.maquina_id} onValueChange={(v) => turnoForm.setData('maquina_id', v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Seleccionar máquina" />
                </SelectTrigger>
                <SelectContent>
                  {maquinas.map((m) => (
                    <SelectItem key={m.id} value={String(m.id)}>{m.codigo} · {m.nombre}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {turnoForm.errors.maquina_id && <p className="text-red-500 text-xs mt-1">{turnoForm.errors.maquina_id}</p>}
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <Label>Fecha</Label>
                <Input type="date" value={turnoForm.data.fecha} onChange={(e) => turnoForm.setData('fecha', e.target.value)} required />
              </div>
              <div>
                <Label>Hora inicio turno</Label>
                <Input type="datetime-local" value={turnoForm.data.hora_inicio_turno} onChange={(e) => turnoForm.setData('hora_inicio_turno', e.target.value)} required />
              </div>
            </div>
            <div>
              <Label>Operador</Label>
              <Input value={turnoForm.data.operador} onChange={(e) => turnoForm.setData('operador', e.target.value)} placeholder="Nombre del operador" />
            </div>
            <div>
              <Label>Observaciones</Label>
              <Textarea value={turnoForm.data.observaciones} onChange={(e) => turnoForm.setData('observaciones', e.target.value)} />
            </div>
            {errorMsg(turnoForm) && !turnoForm.errors.maquina_id && <p className="text-red-500 text-xs">{errorMsg(turnoForm)}</p>}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setShowNuevoTurno(false)}>Cancelar</Button>
              <Button type="submit" disabled={turnoForm.processing}>
                {turnoForm.processing ? <Loader2 className="w-4 h-4 animate-spin" /> : <Play className="w-4 h-4 mr-1" />}
                Iniciar turno
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Registrar / editar detención */}
      {(detencionTarget || editTarget) && (
        <Dialog open onOpenChange={() => { setDetencionTarget(null); setEditTarget(null) }}>
          <DialogContent className="max-w-md">
            <DialogHeader>
              <DialogTitle>{editTarget ? 'Editar detención' : 'Registrar detención'}</DialogTitle>
              <DialogDescription>
                {detencionTarget?.maquina?.nombre || editTarget ? 'Asigna el motivo y las horas.' : ''}
              </DialogDescription>
            </DialogHeader>
            <form onSubmit={submitDetencion} className="space-y-4">
              <div>
                <Label>Tipo de motivo</Label>
                <Select
                  value={detencionForm.data.tipo_id}
                  onValueChange={(v) => detencionForm.setData({ tipo_id: v, motivo_causa_id: '' })}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccionar tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    {motivos.map((t) => (
                      <SelectItem key={t.id} value={String(t.id)}>{t.nombre}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Causa</Label>
                <Select
                  value={detencionForm.data.motivo_causa_id}
                  onValueChange={(v) => detencionForm.setData('motivo_causa_id', v)}
                  disabled={!detencionForm.data.tipo_id}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={detencionForm.data.tipo_id ? 'Seleccionar causa' : 'Primero elige el tipo'} />
                  </SelectTrigger>
                  <SelectContent>
                    {causasDeTipo(detencionForm.data.tipo_id).map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>{c.codigo ? `${c.codigo} · ` : ''}{c.nombre}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {detencionForm.errors.motivo_causa_id && <p className="text-red-500 text-xs mt-1">{detencionForm.errors.motivo_causa_id}</p>}
              </div>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                  <Label>Hora de detención</Label>
                  <Input type="datetime-local" value={detencionForm.data.hora_detencion} onChange={(e) => detencionForm.setData('hora_detencion', e.target.value)} required />
                </div>
                <div>
                  <Label>Hora de reinicio</Label>
                  <Input
                    type="datetime-local"
                    value={detencionForm.data.hora_reinicio}
                    onChange={(e) => detencionForm.setData('hora_reinicio', e.target.value)}
                    placeholder="Dejar vacío si sigue detenida"
                  />
                </div>
              </div>
              <div>
                <Label>Observaciones</Label>
                <Textarea value={detencionForm.data.observaciones} onChange={(e) => detencionForm.setData('observaciones', e.target.value)} />
              </div>
              {errorMsg(detencionForm) && !detencionForm.errors.motivo_causa_id && <p className="text-red-500 text-xs">{errorMsg(detencionForm)}</p>}
              <DialogFooter>
                <Button type="button" variant="secondary" onClick={() => { setDetencionTarget(null); setEditTarget(null) }}>Cancelar</Button>
                <Button type="submit" disabled={detencionForm.processing || !detencionForm.data.motivo_causa_id}>
                  {detencionForm.processing && <Loader2 className="w-4 h-4 animate-spin" />}
                  Guardar
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}

      {/* Cerrar turno */}
      {cerrarTarget && (
        <Dialog open onOpenChange={() => setCerrarTarget(null)}>
          <DialogContent className="max-w-sm">
            <DialogHeader>
              <DialogTitle>Cerrar turno</DialogTitle>
              <DialogDescription>{cerrarTarget.maquina?.nombre} · iniciado {cerrarTarget.hora_inicio_turno}</DialogDescription>
            </DialogHeader>
            <form onSubmit={submitCerrar} className="space-y-4">
              <div>
                <Label>Hora de fin de turno</Label>
                <Input type="datetime-local" value={cerrarForm.data.hora_fin_turno} onChange={(e) => cerrarForm.setData('hora_fin_turno', e.target.value)} required />
                {cerrarForm.errors.hora_fin_turno && <p className="text-red-500 text-xs mt-1">{cerrarForm.errors.hora_fin_turno}</p>}
              </div>
              <DialogFooter>
                <Button type="button" variant="secondary" onClick={() => setCerrarTarget(null)}>Cancelar</Button>
                <Button type="submit" disabled={cerrarForm.processing}>
                  {cerrarForm.processing && <Loader2 className="w-4 h-4 animate-spin" />}
                  Cerrar turno
                </Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </AuthenticatedLayout>
  )
}