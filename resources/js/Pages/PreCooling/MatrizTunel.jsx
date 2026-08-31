import { useState, lazy, Suspense } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import axios from 'axios'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'
import { Plus, Search, Play, LogOut, Trash2, Repeat, Info, Pencil, Map, Box } from 'lucide-react'
import SalidaDialog from '@/Components/PreCooling/SalidaDialog'
const TunnelScene3D = lazy(() => import('@/Components/PreCooling/TunnelScene3D'))
const Estiba3D = lazy(() => import('@/Components/PreCooling/Estiba3D'))

const ESTADOS = {
  ingresado: { label: 'Ingresado', className: 'bg-amber-100 text-amber-800' },
  iniciado: { label: 'Iniciado', className: 'bg-green-100 text-green-800' },
  salido: { label: 'Salido', className: 'bg-slate-200 text-slate-700' },
}

const VACIOS_ATRIBUTOS = {}
export default function MatrizTunel({ tuneles, tunel, parametros, cargaActiva, folios, camaras, tiposProcesos, atributos }) {
  const [altura, setAltura] = useState('')
  const [viewMode, setViewMode] = useState('2d')
  const [dialog, setDialog] = useState(null)
  const [cellInfo, setCellInfo] = useState(null)
  const [lookup, setLookup] = useState(null)
  const [lookupBusy, setLookupBusy] = useState(false)
  const [lookupError, setLookupError] = useState(null)

  const alturas = parametros?.altura || []
  const alturaActual = alturas.includes(altura) ? altura : alturas[0] || ''

  const ahoraLocal = () => {
    const d = new Date()
    const pad = (n) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
  }

  const nuevaCargaForm = useForm({
    tunel_id: tunel?.id || '',
    tipo_proceso_id: '',
    fecha_hora_inicio: ahoraLocal(),
    camara_destino_id: '',
    temperatura_objetivo: '',
    atributos: VACIOS_ATRIBUTOS,
  })

  const editarProcesoForm = useForm({
    tipo_proceso_id: '',
    camara_destino_id: '',
    fecha_hora_inicio: '',
    fecha_hora_inversion: '',
    fecha_hora_termino: '',
    fecha_hora_descarga: '',
    temperatura_objetivo: '',
    observaciones: '',
    atributos: VACIOS_ATRIBUTOS,
  })

  const folioForm = useForm({
    folio: '',
    banda: '',
    posicion: '',
    altura: '',
    nivel: '',
    cajas: '',
    pallets: '',
    temperatura_inicial: '',
    especie: '',
    variedad: '',
    productor: '',
    exportadora: '',
    embalaje: '',
    categoria: '',
    calibre: '',
  })

  const iniciarForm = useForm({
    temperatura_ambiente_inicio: '',
    temperaturas_folios: {},
  })
  const inversionForm = useForm({
    fecha_hora_inversion: '',
    temperatura_ambiente_inversion: '',
    temperaturas_folios: {},
  })
  const quitarForm = useForm({})

  const handleTunelChange = (value) => {
    if (!value) return
    router.get(route('prefrio.matriz.tunel'), { tunel_id: value }, { preserveState: true, preserveScroll: true })
  }

  const openCrearProceso = () => {
    nuevaCargaForm.setData({
      tunel_id: tunel.id,
      tipo_proceso_id: '',
      fecha_hora_inicio: ahoraLocal(),
      camara_destino_id: '',
      temperatura_objetivo: '',
      atributos: VACIOS_ATRIBUTOS,
    })
    setDialog('nuevaCarga')
  }

  const openEditarProceso = () => {
    if (!cargaActiva) return
    editarProcesoForm.setData({
      tipo_proceso_id: cargaActiva.tipo_proceso_id,
      camara_destino_id: cargaActiva.camara_destino_id || '',
      fecha_hora_inicio: cargaActiva.fecha_hora_inicio || '',
      fecha_hora_inversion: cargaActiva.fecha_hora_inversion || '',
      fecha_hora_termino: cargaActiva.fecha_hora_termino || '',
      fecha_hora_descarga: cargaActiva.fecha_hora_descarga || '',
      temperatura_objetivo: cargaActiva.temperatura_objetivo || '',
      observaciones: cargaActiva.observaciones || '',
      atributos: { ...(cargaActiva.atributos || {}) },
    })
    setDialog('editarProceso')
  }

  const submitNuevaCarga = (e) => {
    e.preventDefault()
    nuevaCargaForm.post(route('prefrio.loads.store'), {
      onSuccess: () => {
        setDialog(null)
        toast.success('Proceso creado correctamente')
      },
    })
  }

  const submitEditarProceso = (e) => {
    e.preventDefault()
    editarProcesoForm.patch(route('prefrio.loads.update', cargaActiva.id), {
      onSuccess: () => {
        setDialog(null)
        toast.success('Proceso actualizado correctamente')
      },
    })
  }

  const openAsignarFolio = (banda, posicion, nivelPreferido = null) => {
    const disponibles = nivelesDisponiblesEnCelda(banda, posicion, alturaActual)
    if (disponibles.length === 0) {
      toast.info('Todos los niveles de esta posición ya están ocupados')
      return
    }
    const nivelDestino = nivelPreferido && disponibles.includes(String(nivelPreferido))
      ? String(nivelPreferido)
      : disponibles[0]

    folioForm.reset()
    folioForm.setData({
      folio: '',
      banda,
      posicion,
      altura: alturaActual,
      nivel: nivelDestino,
      cajas: '',
      pallets: '',
      temperatura_inicial: '',
      especie: '',
      variedad: '',
      productor: '',
      exportadora: '',
      embalaje: '',
      categoria: '',
      calibre: '',
    })
    setLookup(null)
    setLookupError(null)
    setCellInfo({ banda, posicion, altura: alturaActual, nivelesDisponibles: disponibles })
    setDialog('asignarFolio')
  }

  const openDetalleFolio = (folio) => {
    setCellInfo(folio)
    setDialog('detalleFolio')
  }

  const buscarFolio = async () => {
    const folio = folioForm.data.folio.trim()
    if (!folio) return
    setLookupBusy(true)
    setLookupError(null)
    try {
      const response = await axios.post(route('prefrio.folios.lookup'), { folio })
      if (response.data.found) {
        const d = response.data.data
        folioForm.setData({
          ...folioForm.data,
          folio: d.folio || folio,
          exportadora: d.exportadora || '',
          productor: d.productor || '',
          especie: d.especie || '',
          variedad: d.variedad || '',
          embalaje: d.embalaje || '',
          categoria: d.categoria || '',
          calibre: d.calibre || '',
          cajas: d.cajas != null ? String(d.cajas) : '',
        })
        setLookup(d)
      } else {
        setLookup(null)
        setLookupError('Folio no encontrado en el sistema. Complete los datos manualmente.')
      }
    } catch {
      setLookupError('Error consultando el folio. Verifique la conexión con el sistema de producción.')
    } finally {
      setLookupBusy(false)
    }
  }

  const setAtributoProceso = (codigo, value, form = 'nueva') => {
    const target = form === 'editar' ? editarProcesoForm : nuevaCargaForm
    target.setData('atributos', { ...(target.data.atributos || {}), [codigo]: value === 'none' ? '' : value })
  }

  const submitAsignarFolio = (e) => {
    e.preventDefault()
    folioForm.post(route('prefrio.loads.folios.store', cargaActiva.id), {
      onSuccess: () => {
        setDialog(null)
        toast.success('Folio asignado a la celda')
      },
    })
  }

  const quitarFolio = (folio) => {
    if (!confirm('¿Quitar este folio de la celda?')) return
    quitarForm.delete(route('prefrio.loads.folios.destroy', { load: cargaActiva.id, folio: folio.id }), {
      onSuccess: () => {
        toast.success('Folio retirado de la celda')
        setDialog(null)
      },
    })
  }

  const openIniciarDialog = () => {
    iniciarForm.setData({
      temperatura_ambiente_inicio: cargaActiva?.temperatura_ambiente_inicio || '',
      temperaturas_folios: Object.fromEntries(folios.map((folio) => [folio.id, {
        temperatura_inicio: folio.temperatura_inicio || '',
      }])),
    })
    setDialog('iniciar')
  }

  const openInversionDialog = () => {
    inversionForm.setData({
      fecha_hora_inversion: ahoraLocal(),
      temperatura_ambiente_inversion: cargaActiva?.temperatura_ambiente_inversion || '',
      temperaturas_folios: Object.fromEntries(folios.map((folio) => [folio.id, {
        temperatura_inversion_interior: folio.temperatura_inversion_interior || '',
        temperatura_inversion_exterior: folio.temperatura_inversion_exterior || '',
      }])),
    })
    setDialog('inversion')
  }

  const setTemperaturaInicioFolio = (folioId, value) => {
    iniciarForm.setData('temperaturas_folios', {
      ...iniciarForm.data.temperaturas_folios,
      [folioId]: { temperatura_inicio: value },
    })
  }

  const setTemperaturaInversionFolio = (folioId, campo, value) => {
    inversionForm.setData('temperaturas_folios', {
      ...inversionForm.data.temperaturas_folios,
      [folioId]: {
        ...(inversionForm.data.temperaturas_folios[folioId] || {}),
        [campo]: value,
      },
    })
  }

  const openSalidaDialog = () => {
    setDialog('salida')
  }

  const submitIniciar = (e) => {
    e.preventDefault()
    iniciarForm.post(route('prefrio.loads.iniciar', cargaActiva.id), {
      onSuccess: () => {
        setDialog(null)
        toast.success('Pre-enfriado iniciado')
      },
    })
  }

  const submitInversion = (e) => {
    e.preventDefault()
    inversionForm.post(route('prefrio.loads.registrar-inversion', cargaActiva.id), {
      onSuccess: () => {
        setDialog(null)
        toast.success('Inversión del flujo registrada')
      },
    })
  }

  const bandas = parametros?.banda || []
  const posiciones = parametros?.posicion || []
  const niveles = parametros?.nivel || []

  const foliosPorCelda = (b, p, a) => folios.filter((folio) => (
    String(folio.banda) === String(b)
    && String(folio.posicion) === String(p)
    && String(folio.altura) === String(a)
  ))

  const nivelesDisponiblesEnCelda = (b, p, a) => {
    const ocupados = new Set(foliosPorCelda(b, p, a).map((folio) => String(folio.nivel)))
    return niveles.map(String).filter((nivel) => !ocupados.has(nivel))
  }

  const camaraActiva = cargaActiva?.camara_destino_id
    ? camaras.find((c) => String(c.id) === String(cargaActiva.camara_destino_id))
    : null

  const foliosConProceso = folios.map((f) => ({
    ...f,
    tunel: [tunel?.codigo, tunel?.nombre].filter(Boolean).join(' · '),
    camara: [camaraActiva?.codigo, camaraActiva?.nombre].filter(Boolean).join(' · ')
      || cargaActiva?.camara_destino_codigo,
    fecha_hora_inicio: cargaActiva?.fecha_hora_inicio,
    temperatura_objetivo: cargaActiva?.temperatura_objetivo,
    estado: cargaActiva?.estado,
    observaciones: cargaActiva?.observaciones,
  }))
  const foliosEnAltura = foliosConProceso.filter((f) => f.altura === alturaActual)

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Matriz de túneles</h2>
        </div>
      }
    >
      <Head title="Prefrío · Matriz de túneles" />
      <Toaster />

      <div className="py-12">
        <div className="mx-auto sm:px-6 lg:px-8 space-y-4">
          <Card>
            <CardContent className="pt-6 flex flex-col sm:flex-row items-start sm:items-end gap-4">
              <div className="w-full sm:w-80">
                <Label className="mb-1 block">Túnel</Label>
                <Select value={tunel ? String(tunel.id) : ''} onValueChange={handleTunelChange}>
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccione un túnel" />
                  </SelectTrigger>
                  <SelectContent>
                    {tuneles.map((t) => (
                      <SelectItem key={t.id} value={String(t.id)} disabled={!t.activo}>
                        {t.codigo} · {t.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              {alturas.length > 0 && (
                <div className="w-full sm:w-56">
                  <Label className="mb-1 block">Altura</Label>
                  <Select value={alturaActual} onValueChange={setAltura}>
                    <SelectTrigger>
                      <SelectValue placeholder="Altura" />
                    </SelectTrigger>
                    <SelectContent>
                      {alturas.map((a) => (
                        <SelectItem key={a} value={a}>
                          {a}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
              <div className="flex items-center gap-1 ml-auto border rounded-md p-0.5">
                <Button
                  variant={viewMode === '2d' ? 'default' : 'ghost'}
                  size="sm"
                  className="h-7 px-2"
                  onClick={() => setViewMode('2d')}
                >
                  <Map className="w-3.5 h-3.5 mr-1" /> 2D
                </Button>
                <Button
                  variant={viewMode === '3d' ? 'default' : 'ghost'}
                  size="sm"
                  className="h-7 px-2"
                  onClick={() => setViewMode('3d')}
                >
                  <Box className="w-3.5 h-3.5 mr-1" /> 3D
                </Button>
              </div>
            </CardContent>
          </Card>

          {!tunel ? (
            <Card>
              <CardContent className="pt-6 text-center text-gray-500 py-10">
                Seleccione un túnel para visualizar su matriz.
              </CardContent>
            </Card>
          ) : bandas.length === 0 ? (
            <Card>
              <CardContent className="pt-6 text-center text-gray-500 py-10">
                El túnel no tiene parametrización. Configúrela desde el módulo de Túneles.
              </CardContent>
            </Card>
          ) : (
            <>
              {cargaActiva ? (
                <Card>
                  <CardContent className="pt-6">
                    <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-3 flex-wrap">
                          <span className="font-mono text-lg font-bold">{cargaActiva.numero}</span>
                          <Badge className={ESTADOS[cargaActiva.estado]?.className}>
                            {ESTADOS[cargaActiva.estado]?.label}
                          </Badge>
                          <span className="text-sm text-gray-600">{cargaActiva.tipo_proceso_nombre}</span>
                          <Button variant="ghost" size="sm" onClick={openEditarProceso} className="h-7 px-2">
                            <Pencil className="w-3 h-3" />
                          </Button>
                        </div>
                        <div className="text-xs text-gray-500 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                          {cargaActiva.fecha_hora_inicio && (
                            <span>Inicio: {cargaActiva.fecha_hora_inicio.replace('T', ' ')}</span>
                          )}
                          {cargaActiva.fecha_hora_inversion && (
                            <span>Inversión: {cargaActiva.fecha_hora_inversion.replace('T', ' ')}</span>
                          )}
                          {cargaActiva.fecha_hora_termino && (
                            <span>Término: {cargaActiva.fecha_hora_termino.replace('T', ' ')}</span>
                          )}
                          {cargaActiva.fecha_hora_descarga && (
                            <span>Descarga: {cargaActiva.fecha_hora_descarga.replace('T', ' ')}</span>
                          )}
                          {cargaActiva.temperatura_objetivo != null && (
                            <span>Temp. objetivo: {cargaActiva.temperatura_objetivo} °C</span>
                          )}
                          {cargaActiva.atributos && Object.keys(cargaActiva.atributos).length > 0 && (
                            <span className="flex flex-wrap gap-1">
                              {Object.entries(cargaActiva.atributos).map(([k, v]) =>
                                v ? <span key={k} className="px-1.5 py-0.5 rounded bg-blue-50 text-[10px] text-blue-700">{k}: {v}</span> : null
                              )}
                            </span>
                          )}
                          {camaraActiva && (
                            <span>Cámara: {camaraActiva.codigo} · {camaraActiva.nombre}</span>
                          )}
                          <span>{folios.length} folio(s) · {foliosEnAltura.length} en esta altura</span>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        {cargaActiva.estado === 'ingresado' && (
                          <Button size="sm" onClick={openIniciarDialog}>
                            <Play className="w-4 h-4 mr-1" /> Iniciar
                          </Button>
                        )}
                        {cargaActiva.estado === 'iniciado' && (
                          <>
                            {!cargaActiva.fecha_hora_inversion && (
                              <Button size="sm" variant="secondary" onClick={openInversionDialog}>
                                <Repeat className="w-4 h-4 mr-1" /> Inversión
                              </Button>
                            )}
                            <Button size="sm" onClick={openSalidaDialog}>
                              <LogOut className="w-4 h-4 mr-1" /> Salir
                            </Button>
                          </>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>
              ) : (
                <Card>
                  <CardContent className="pt-6 flex items-center justify-between">
                    <div>
                      <p className="text-gray-600">No hay un proceso activo en este túnel.</p>
                      <p className="text-xs text-gray-400">Cree un proceso para comenzar a asignar pallets.</p>
                    </div>
                    <Button onClick={openCrearProceso}>
                      <Plus className="w-4 h-4 mr-1" /> Crear proceso
                    </Button>
                  </CardContent>
                </Card>
              )}

              <Card>
                <CardContent className="pt-6 overflow-x-auto">
                   {viewMode === '3d' ? (
                    <Suspense fallback={<div className="text-center text-gray-400 py-10">Cargando vista 3D...</div>}>
                      <TunnelScene3D
                        parametros={parametros}
                        folios={foliosConProceso}
                        tipoTunel={tunel?.tipo}
                        alturaActual={alturaActual}
                        canAssign={Boolean(cargaActiva)}
                        processStarted={cargaActiva?.estado === 'iniciado'}
                        processInversion={cargaActiva?.estado === 'iniciado' && Boolean(cargaActiva?.fecha_hora_inversion)}
                        codigo={tunel?.codigo}
                        titulo={tunel?.nombre}
                        mode="tunel"
                        onFolioSelect={openDetalleFolio}
                        onEmptyPositionSelect={openAsignarFolio}
                      />
                    </Suspense>
                  ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-20">Posición</TableHead>
                        {bandas.map((b) => (
                          <TableHead key={b} className="text-center">
                            Banda {b}
                          </TableHead>
                        ))}
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {posiciones.map((p) => (
                        <TableRow key={p}>
                          <TableCell className="font-semibold">Pos. {p}</TableCell>
                          {bandas.map((b) => {
                            return (
                          <TableCell key={b} className="p-1 min-w-[140px]">
                                <div className="space-y-1.5">
                                  {niveles.map((nivel) => {
                                    const folioNivel = foliosPorCelda(b, p, alturaActual)
                                      .find((item) => String(item.nivel) === String(nivel))

                                    return folioNivel ? (
                                      <button
                                        key={nivel}
                                        type="button"
                                        onClick={() => openDetalleFolio(folioNivel)}
                                        className="w-full min-h-[68px] flex flex-col items-start gap-1 rounded-md border-2 p-1.5 text-left transition-colors bg-green-50 border-green-300 hover:border-green-500"
                                      >
                                        <div className="flex w-full items-center justify-between gap-1">
                                          <span className="font-mono text-xs font-bold truncate">{folioNivel.folio}</span>
                                          <Badge variant="secondary" className="shrink-0 text-[9px]">{nivel}</Badge>
                                        </div>
                                        <span className="text-[10px] text-gray-600 truncate w-full">
                                          {[folioNivel.especie, folioNivel.variedad].filter(Boolean).join(' · ') || 'Sin detalle'}
                                        </span>
                                        {folioNivel.cajas > 0 && (
                                          <span className="text-[10px] text-gray-500">{folioNivel.cajas} cajas</span>
                                        )}
                                      </button>
                                    ) : cargaActiva ? (
                                      <button
                                        key={nivel}
                                        type="button"
                                        onClick={() => openAsignarFolio(b, p, nivel)}
                                        className="w-full min-h-[52px] flex items-center justify-center gap-1.5 rounded-md border-2 border-dashed border-gray-200 text-gray-400 hover:border-greenex-vibrant-green hover:bg-greenex-vibrant-green/5 hover:text-greenex-vibrant-green transition-colors"
                                      >
                                        <Plus className="w-4 h-4" />
                                        <span className="text-[10px]">Nivel {nivel} · Asignar</span>
                                      </button>
                                    ) : (
                                      <div key={nivel} className="w-full min-h-[52px] flex items-center justify-center rounded-md border border-gray-100 bg-gray-50 text-gray-300">
                                        <span className="text-[10px]">Nivel {nivel} · Vacío</span>
                                      </div>
                                    )
                                  })}
                                </div>
                              </TableCell>
                            )
                          })}
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                  )}
                </CardContent>
              </Card>

              <div className="flex flex-wrap gap-3 text-xs text-gray-600">
                <span className="inline-flex items-center gap-1.5">
                  <span className="w-3 h-3 rounded bg-green-50 border border-green-300" /> Folio asignado
                </span>
                {cargaActiva && (
                  <span className="inline-flex items-center gap-1.5">
                    <span className="w-3 h-3 rounded border-2 border-dashed border-gray-200" /> Vacío (asignar)
                  </span>
                )}
                {!cargaActiva && (
                  <span className="inline-flex items-center gap-1.5">
                    <span className="w-3 h-3 rounded border border-gray-100 bg-gray-50" /> Sin proceso
                  </span>
                )}
              </div>
            </>
          )}
        </div>
      </div>

      {/* Dialog: Crear proceso */}
      <Dialog open={dialog === 'nuevaCarga'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Crear proceso de pre-frío</DialogTitle>
            <DialogDescription>
              Se creará un proceso único para todo el túnel. Complete los datos del proceso y luego asignará los pallets.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitNuevaCarga} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Tipo de proceso *</Label>
                <Select
                  value={nuevaCargaForm.data.tipo_proceso_id}
                  onValueChange={(v) => nuevaCargaForm.setData('tipo_proceso_id', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccione tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    {tiposProcesos.map((tp) => (
                      <SelectItem key={tp.id} value={String(tp.id)}>
                        {tp.codigo} · {tp.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {nuevaCargaForm.errors.tipo_proceso_id && (
                  <p className="text-red-500 text-xs mt-1">{nuevaCargaForm.errors.tipo_proceso_id}</p>
                )}
              </div>
              <div>
                <Label>Fecha y hora de inicio *</Label>
                <Input
                  type="datetime-local"
                  value={nuevaCargaForm.data.fecha_hora_inicio}
                  onChange={(e) => nuevaCargaForm.setData('fecha_hora_inicio', e.target.value)}
                  required
                />
                {nuevaCargaForm.errors.fecha_hora_inicio && (
                  <p className="text-red-500 text-xs mt-1">{nuevaCargaForm.errors.fecha_hora_inicio}</p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Cámara de destino</Label>
                <Select
                  value={nuevaCargaForm.data.camara_destino_id}
                  onValueChange={(v) => nuevaCargaForm.setData('camara_destino_id', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Opcional" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Sin asignar</SelectItem>
                    {camaras.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.codigo} · {c.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {nuevaCargaForm.errors.camara_destino_id && (
                  <p className="text-red-500 text-xs mt-1">{nuevaCargaForm.errors.camara_destino_id}</p>
                )}
              </div>
              <div>
                <Label>Temperatura objetivo (°C)</Label>
                <Input
                  type="number"
                  step="0.1"
                  value={nuevaCargaForm.data.temperatura_objetivo}
                  onChange={(e) => nuevaCargaForm.setData('temperatura_objetivo', e.target.value)}
                  placeholder="Ej: 2.0"
                />
                {nuevaCargaForm.errors.temperatura_objetivo && (
                  <p className="text-red-500 text-xs mt-1">{nuevaCargaForm.errors.temperatura_objetivo}</p>
                )}
              </div>
            </div>
            {nuevaCargaForm.errors.tunel_id && (
              <p className="text-red-500 text-xs">{nuevaCargaForm.errors.tunel_id}</p>
            )}
            {atributos.length > 0 && (
              <div className="space-y-3">
                <Label>Atributos del proceso</Label>
                {atributos.map((attr) => (
                  <div key={attr.id}>
                    <Label className="text-xs">
                      {attr.nombre}
                      {attr.requerido ? ' *' : ''}
                    </Label>
                    {attr.tipo_dato === 'select' ? (
                      (attr.opciones || []).length <= 2 ? (
                        <div className="flex items-center gap-3 mt-1">
                          {(attr.opciones || []).map((o) => (
                            <label key={o} className="flex items-center gap-2 text-sm cursor-pointer">
                              <Switch
                                checked={nuevaCargaForm.data.atributos?.[attr.codigo] === o}
                                onCheckedChange={(checked) => setAtributoProceso(attr.codigo, checked ? o : '', 'nueva')}
                              />
                              {o}
                            </label>
                          ))}
                        </div>
                      ) : (
                        <Select
                          value={nuevaCargaForm.data.atributos?.[attr.codigo] || ''}
                          onValueChange={(v) => setAtributoProceso(attr.codigo, v, 'nueva')}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Seleccione" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">—</SelectItem>
                            {(attr.opciones || []).map((o) => (
                              <SelectItem key={o} value={o}>{o}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )
                    ) : (
                      <Input
                        type={attr.tipo_dato === 'numero' ? 'number' : attr.tipo_dato === 'fecha' ? 'date' : 'text'}
                        value={nuevaCargaForm.data.atributos?.[attr.codigo] || ''}
                        onChange={(e) => setAtributoProceso(attr.codigo, e.target.value, 'nueva')}
                      />
                    )}
                  </div>
                ))}
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setDialog(null)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={nuevaCargaForm.processing}>
                Crear proceso
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Dialog: Editar proceso */}
      <Dialog open={dialog === 'editarProceso'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Editar proceso · {cargaActiva?.numero}</DialogTitle>
            <DialogDescription>
              Modifique los datos de cabecera del proceso. Los cambios se guardan inmediatamente.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitEditarProceso} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Tipo de proceso</Label>
                <Select
                  value={editarProcesoForm.data.tipo_proceso_id}
                  onValueChange={(v) => editarProcesoForm.setData('tipo_proceso_id', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccione tipo" />
                  </SelectTrigger>
                  <SelectContent>
                    {tiposProcesos.map((tp) => (
                      <SelectItem key={tp.id} value={String(tp.id)}>
                        {tp.codigo} · {tp.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {editarProcesoForm.errors.tipo_proceso_id && (
                  <p className="text-red-500 text-xs mt-1">{editarProcesoForm.errors.tipo_proceso_id}</p>
                )}
              </div>
              <div>
                <Label>Cámara de destino</Label>
                <Select
                  value={editarProcesoForm.data.camara_destino_id}
                  onValueChange={(v) => editarProcesoForm.setData('camara_destino_id', v)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="Sin asignar" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">Sin asignar</SelectItem>
                    {camaras.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)}>
                        {c.codigo} · {c.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {editarProcesoForm.errors.camara_destino_id && (
                  <p className="text-red-500 text-xs mt-1">{editarProcesoForm.errors.camara_destino_id}</p>
                )}
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Temperatura objetivo (°C)</Label>
                <Input
                  type="number"
                  step="0.1"
                  value={editarProcesoForm.data.temperatura_objetivo}
                  onChange={(e) => editarProcesoForm.setData('temperatura_objetivo', e.target.value)}
                  placeholder="Ej: 2.0"
                />
              </div>
              <div>
                <Label>Fecha y hora de inicio</Label>
                <Input
                  type="datetime-local"
                  value={editarProcesoForm.data.fecha_hora_inicio}
                  onChange={(e) => editarProcesoForm.setData('fecha_hora_inicio', e.target.value)}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Fecha y hora de inversión</Label>
                <Input
                  type="datetime-local"
                  value={editarProcesoForm.data.fecha_hora_inversion}
                  onChange={(e) => editarProcesoForm.setData('fecha_hora_inversion', e.target.value)}
                />
              </div>
              <div>
                <Label>Fecha y hora de término</Label>
                <Input
                  type="datetime-local"
                  value={editarProcesoForm.data.fecha_hora_termino}
                  onChange={(e) => editarProcesoForm.setData('fecha_hora_termino', e.target.value)}
                />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <Label>Fecha y hora de descarga</Label>
                <Input
                  type="datetime-local"
                  value={editarProcesoForm.data.fecha_hora_descarga}
                  onChange={(e) => editarProcesoForm.setData('fecha_hora_descarga', e.target.value)}
                />
              </div>
              <div />
            </div>
            <div>
              <Label>Observaciones</Label>
              <Textarea
                value={editarProcesoForm.data.observaciones}
                onChange={(e) => editarProcesoForm.setData('observaciones', e.target.value)}
                placeholder="Notas sobre el proceso..."
                rows={2}
              />
            </div>
            {atributos.length > 0 && (
              <div className="space-y-3">
                <Label>Atributos del proceso</Label>
                {atributos.map((attr) => (
                  <div key={attr.id}>
                    <Label className="text-xs">
                      {attr.nombre}
                      {attr.requerido ? ' *' : ''}
                    </Label>
                    {attr.tipo_dato === 'select' ? (
                      (attr.opciones || []).length <= 2 ? (
                        <div className="flex items-center gap-3 mt-1">
                          {(attr.opciones || []).map((o) => (
                            <label key={o} className="flex items-center gap-2 text-sm cursor-pointer">
                              <Switch
                                checked={editarProcesoForm.data.atributos?.[attr.codigo] === o}
                                onCheckedChange={(checked) => setAtributoProceso(attr.codigo, checked ? o : '', 'editar')}
                              />
                              {o}
                            </label>
                          ))}
                        </div>
                      ) : (
                        <Select
                          value={editarProcesoForm.data.atributos?.[attr.codigo] || ''}
                          onValueChange={(v) => setAtributoProceso(attr.codigo, v, 'editar')}
                        >
                          <SelectTrigger>
                            <SelectValue placeholder="Seleccione" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="none">—</SelectItem>
                            {(attr.opciones || []).map((o) => (
                              <SelectItem key={o} value={o}>{o}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )
                    ) : (
                      <Input
                        type={attr.tipo_dato === 'numero' ? 'number' : attr.tipo_dato === 'fecha' ? 'date' : 'text'}
                        value={editarProcesoForm.data.atributos?.[attr.codigo] || ''}
                        onChange={(e) => setAtributoProceso(attr.codigo, e.target.value, 'editar')}
                      />
                    )}
                  </div>
                ))}
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setDialog(null)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={editarProcesoForm.processing}>
                Guardar cambios
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Dialog: Asignar folio */}
      <Dialog open={dialog === 'asignarFolio'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-lg">
          <DialogHeader>
            <DialogTitle>Asignar folio a celda</DialogTitle>
            <DialogDescription>
              {cellInfo && `Banda ${cellInfo.banda} · Posición ${cellInfo.posicion} · Altura ${folioForm.data.altura}`}
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitAsignarFolio} className="space-y-4">
            <div className="flex gap-2">
              <Input
                autoFocus
                value={folioForm.data.folio}
                onChange={(e) => folioForm.setData('folio', e.target.value)}
                placeholder="Escanee o ingrese el folio"
                className="font-mono"
              />
              <Button type="button" variant="secondary" onClick={buscarFolio} disabled={lookupBusy}>
                <Search className="w-4 h-4 mr-1" /> Buscar
              </Button>
            </div>
            {folioForm.errors.folio && <p className="text-red-500 text-xs">{folioForm.errors.folio}</p>}
            {folioForm.errors.estado && <p className="text-red-500 text-xs">{folioForm.errors.estado}</p>}
            {folioForm.errors.posicion && <p className="text-red-500 text-xs">{folioForm.errors.posicion}</p>}
            {lookup && <p className="text-green-600 text-xs">Datos del folio cargados correctamente.</p>}
            {lookupError && <p className="text-amber-600 text-xs">{lookupError}</p>}

            <div>
              <Label>Nivel</Label>
              <Select value={folioForm.data.nivel} onValueChange={(v) => folioForm.setData('nivel', v)}>
                <SelectTrigger>
                  <SelectValue placeholder="Seleccione nivel" />
                </SelectTrigger>
                <SelectContent>
                  {(cellInfo?.nivelesDisponibles || niveles).map((n) => (
                    <SelectItem key={n} value={n}>
                      {n}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {cellInfo?.nivelesDisponibles?.length > 1 && (
                <p className="mt-1 text-xs text-gray-500">
                  Esta posición todavía admite {cellInfo.nivelesDisponibles.length} niveles.
                </p>
              )}
              {folioForm.errors.nivel && <p className="text-red-500 text-xs mt-1">{folioForm.errors.nivel}</p>}
            </div>

            <div className="grid grid-cols-3 gap-3">
              <div>
                <Label>Cajas</Label>
                <Input
                  type="number"
                  min="0"
                  value={folioForm.data.cajas}
                  onChange={(e) => folioForm.setData('cajas', e.target.value)}
                />
              </div>
              <div>
                <Label>Pallets</Label>
                <Input
                  type="number"
                  min="0"
                  value={folioForm.data.pallets}
                  onChange={(e) => folioForm.setData('pallets', e.target.value)}
                />
              </div>
              <div>
                <Label>Temp. inicial</Label>
                <Input
                  type="number"
                  step="0.1"
                  value={folioForm.data.temperatura_inicial}
                  onChange={(e) => folioForm.setData('temperatura_inicial', e.target.value)}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <Label>Especie</Label>
                <Input value={folioForm.data.especie} onChange={(e) => folioForm.setData('especie', e.target.value)} />
              </div>
              <div>
                <Label>Variedad</Label>
                <Input value={folioForm.data.variedad} onChange={(e) => folioForm.setData('variedad', e.target.value)} />
              </div>
              <div>
                <Label>Productor</Label>
                <Input value={folioForm.data.productor} onChange={(e) => folioForm.setData('productor', e.target.value)} />
              </div>
              <div>
                <Label>Exportadora</Label>
                <Input value={folioForm.data.exportadora} onChange={(e) => folioForm.setData('exportadora', e.target.value)} />
              </div>
              <div>
                <Label>Embalaje</Label>
                <Input value={folioForm.data.embalaje} onChange={(e) => folioForm.setData('embalaje', e.target.value)} />
              </div>
              <div>
                <Label>Categoría</Label>
                <Input value={folioForm.data.categoria} onChange={(e) => folioForm.setData('categoria', e.target.value)} />
              </div>
            </div>

            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setDialog(null)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={folioForm.processing}>
                Asignar folio
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Dialog: Detalle folio */}
      <Dialog open={dialog === 'detalleFolio'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Folio · {cellInfo?.folio}</DialogTitle>
            <DialogDescription>
              {cellInfo && `Banda ${cellInfo.banda} · Posición ${cellInfo.posicion} · Altura ${cellInfo.altura} · Nivel ${cellInfo.nivel}`}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            {cellInfo?.especie && <p className="text-sm"><span className="font-medium">Especie:</span> {cellInfo.especie}</p>}
            {cellInfo?.variedad && <p className="text-sm"><span className="font-medium">Variedad:</span> {cellInfo.variedad}</p>}
            {cellInfo?.productor && <p className="text-sm"><span className="font-medium">Productor:</span> {cellInfo.productor}</p>}
            {cellInfo?.exportadora && <p className="text-sm"><span className="font-medium">Exportadora:</span> {cellInfo.exportadora}</p>}
            {cellInfo?.embalaje && <p className="text-sm"><span className="font-medium">Embalaje:</span> {cellInfo.embalaje}</p>}
            {cellInfo?.cajas != null && <p className="text-sm"><span className="font-medium">Cajas:</span> {cellInfo.cajas}</p>}
            {cellInfo?.pallets != null && <p className="text-sm"><span className="font-medium">Pallets:</span> {cellInfo.pallets}</p>}
            {cellInfo?.temperatura_inicial != null && (
              <p className="text-sm"><span className="font-medium">Temp. inicial:</span> {cellInfo.temperatura_inicial} °C</p>
            )}
            <div className="mt-3 border-t border-gray-200 pt-3">
              <p className="mb-2 text-[10px] font-semibold uppercase tracking-wider text-gray-400">Proceso</p>
              <div className="grid grid-cols-2 gap-x-3 gap-y-2 text-sm">
                <p><span className="font-medium">Túnel:</span> {cellInfo?.tunel || '—'}</p>
                <p><span className="font-medium">Cámara:</span> {cellInfo?.camara || '—'}</p>
                <p><span className="font-medium">Inicio:</span> {cellInfo?.fecha_hora_inicio?.replace('T', ' ') || '—'}</p>
                <p>
                  <span className="font-medium">Temperatura:</span>{' '}
                  {cellInfo?.temperatura_objetivo != null ? `${cellInfo.temperatura_objetivo} °C` : '—'}
                </p>
              </div>
            </div>
            {cellInfo?.atributos && Object.keys(cellInfo.atributos).length > 0 && (
              <div className="flex flex-wrap gap-1 mt-2">
                {Object.entries(cellInfo.atributos).map(([k, v]) =>
                  v ? (
                    <span key={k} className="px-1.5 py-0.5 rounded bg-gray-100 text-[10px] text-gray-600">
                      {k}: {v}
                    </span>
                  ) : null
                )}
              </div>
            )}
          </div>
          <DialogFooter>
            {cargaActiva?.estado === 'ingresado' && (
              <Button variant="destructive" size="sm" onClick={() => quitarFolio(cellInfo)}>
                <Trash2 className="w-4 h-4 mr-1" /> Quitar
              </Button>
            )}
            <Button variant="ghost" size="sm" onClick={() => setDialog(null)}>
              Cerrar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Dialog: Iniciar */}
      <Dialog open={dialog === 'iniciar'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Iniciar pre-enfriado</DialogTitle>
            <DialogDescription>
              Esta acción es irreversible e inicia el proceso para todo el túnel.
              Se registrará con la fecha y hora establecida al crear el proceso.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4">
            <div>
              <Label>Temperatura ambiente al inicio (°C)</Label>
              <Input
                type="number"
                step="0.01"
                value={iniciarForm.data.temperatura_ambiente_inicio}
                onChange={(e) => iniciarForm.setData('temperatura_ambiente_inicio', e.target.value)}
              />
              {iniciarForm.errors.temperatura_ambiente_inicio && (
                <p className="mt-1 text-xs text-red-500">{iniciarForm.errors.temperatura_ambiente_inicio}</p>
              )}
            </div>
            <div className="max-h-64 space-y-2 overflow-y-auto">
              {folios.map((folio) => (
                <div key={folio.id} className="grid grid-cols-2 items-end gap-3 rounded-md border p-3">
                  <div className="font-mono text-sm font-semibold">{folio.folio}</div>
                  <div>
                    <Label className="text-xs">T° inicio fruta (°C)</Label>
                    <Input
                      type="number"
                      step="0.01"
                      value={iniciarForm.data.temperaturas_folios[folio.id]?.temperatura_inicio || ''}
                      onChange={(e) => setTemperaturaInicioFolio(folio.id, e.target.value)}
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="secondary" onClick={() => setDialog(null)}>
              Cancelar
            </Button>
            <Button type="submit" disabled={iniciarForm.processing} onClick={submitIniciar}>
              Iniciar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Dialog: Inversión */}
      <Dialog open={dialog === 'inversion'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>Registrar inversión del flujo</DialogTitle>
            <DialogDescription>
              Registre la fecha y hora en que se invirtió el flujo de frío para todo el túnel, evitando la congelación de la fruta.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitInversion} className="space-y-4">
            <div>
              <Label>Fecha y hora de inversión</Label>
              <Input
                type="datetime-local"
                value={inversionForm.data.fecha_hora_inversion}
                onChange={(e) => inversionForm.setData('fecha_hora_inversion', e.target.value)}
                required
              />
              {inversionForm.errors.fecha_hora_inversion && (
                <p className="text-red-500 text-xs mt-1">{inversionForm.errors.fecha_hora_inversion}</p>
              )}
              {inversionForm.errors.estado && <p className="text-red-500 text-xs mt-1">{inversionForm.errors.estado}</p>}
            </div>
            <div>
              <Label>Temperatura ambiente en la inversión (°C)</Label>
              <Input
                type="number"
                step="0.01"
                value={inversionForm.data.temperatura_ambiente_inversion}
                onChange={(e) => inversionForm.setData('temperatura_ambiente_inversion', e.target.value)}
              />
            </div>
            <div className="max-h-64 space-y-2 overflow-y-auto">
              {folios.map((folio) => (
                <div key={folio.id} className="rounded-md border p-3">
                  <div className="mb-2 font-mono text-sm font-semibold">{folio.folio}</div>
                  <div className="grid grid-cols-2 gap-3">
                    <div>
                      <Label className="text-xs">T° interior (°C)</Label>
                      <Input
                        type="number"
                        step="0.01"
                        value={inversionForm.data.temperaturas_folios[folio.id]?.temperatura_inversion_interior || ''}
                        onChange={(e) => setTemperaturaInversionFolio(folio.id, 'temperatura_inversion_interior', e.target.value)}
                      />
                    </div>
                    <div>
                      <Label className="text-xs">T° exterior (°C)</Label>
                      <Input
                        type="number"
                        step="0.01"
                        value={inversionForm.data.temperaturas_folios[folio.id]?.temperatura_inversion_exterior || ''}
                        onChange={(e) => setTemperaturaInversionFolio(folio.id, 'temperatura_inversion_exterior', e.target.value)}
                      />
                    </div>
                  </div>
                </div>
              ))}
            </div>
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setDialog(null)}>
                Cancelar
              </Button>
              <Button type="submit" disabled={inversionForm.processing}>
                Registrar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {dialog === 'salida' && cargaActiva && (
        <SalidaDialog load={{ ...cargaActiva, folios }} camaras={camaras} onClose={() => setDialog(null)} />
      )}
    </AuthenticatedLayout>
  )
}
