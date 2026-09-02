import { useState, lazy, Suspense } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
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
import { Map, Box, LogOut, Move, PackageSearch } from 'lucide-react'
import { getConsecutivePositionLabel } from '@/Components/PreCooling/cameraRackPositions'

const CameraRackScene3D = lazy(() => import('@/Components/PreCooling/CameraRackScene3D'))
const ROW_DIMENSION_BY_BAND = {
  Izquierda: 'fila_izquierda',
  'Central-Izq': 'fila_central_izq',
  'Central-Dcha': 'fila_central_dcha',
  Derecha: 'fila_derecha',
}

export default function MatrizCamara({ camaras, camara, parametros, saldos, tiposProcesos = [] }) {
  const [banda, setBanda] = useState('')
  const [altura, setAltura] = useState('')
  const [viewMode, setViewMode] = useState('2d')
  const [detailItems, setDetailItems] = useState([])
  const [assignmentTarget, setAssignmentTarget] = useState(null)
  const [assignmentMode, setAssignmentMode] = useState('manual')
  const [retiringSaldo, setRetiringSaldo] = useState(null)
  const assignmentForm = useForm({
    camara_id: '',
    banda: '',
    fila: '',
    columna: '',
    altura: '',
    nivel: '',
  })
  const manualForm = useForm({
    camara_id: '',
    banda: '',
    fila: '',
    columna: '',
    altura: '',
    nivel: '',
    folio: '',
    tipo_proceso_id: '',
    cajas: '',
    pallets: '',
    especie: '',
    variedad: '',
    productor: '',
  })

  const bandas = parametros?.banda || []
  const bandaActual = bandas.includes(banda) ? banda : bandas[0] || ''
  const alturas = parametros?.altura || []
  const alturaActual = alturas.includes(altura) ? altura : alturas[0] || ''
  const isRackable = camara?.tipo !== 'planta_libre'

  const filas = parametros?.[ROW_DIMENSION_BY_BAND[bandaActual]] || parametros?.fila || []
  const rowsByBand = Object.fromEntries(bandas.map((currentBand) => [
    currentBand,
    parametros?.[ROW_DIMENSION_BY_BAND[currentBand]] || parametros?.fila || [],
  ]))
  const positionLabel = (band, row) => (
    getConsecutivePositionLabel(bandas, rowsByBand, band, row)
  )
  const configuredColumns = parametros?.columna || []
  const columnas = isRackable
    ? Array.from({ length: 3 }, (_, index) => configuredColumns[index] || String(index + 1))
    : configuredColumns

  const handleCamaraChange = (value) => {
    if (!value) return
    router.get(route('prefrio.matriz.camara'), { camara_id: value }, { preserveState: true, preserveScroll: true })
  }

  const saldosPorCelda = (fila, columna) =>
    saldos.filter((s) => (
      s.banda === bandaActual
      && s.fila === fila
      && s.columna === columna
      && s.altura === alturaActual
    ))

  const saldosEnAltura = saldos.filter((s) => s.altura === alturaActual).map((s) => ({
    ...s,
    camara: camara?.codigo,
  }))

  const niveles = parametros?.nivel || []

  const openAssignment = (target, saldo = null) => {
    const nivelInicial = saldo?.nivel && niveles.includes(saldo.nivel)
      ? saldo.nivel
      : niveles[0] || ''

    setDetailItems([])
    setAssignmentMode(saldo ? 'existing' : 'manual')
    setAssignmentTarget({ ...target, saldoId: saldo ? String(saldo.id) : '' })
    assignmentForm.clearErrors()
    manualForm.clearErrors()
    assignmentForm.setData({
      camara_id: camara ? String(camara.id) : '',
      banda: target.banda,
      fila: target.fila,
      columna: target.columna,
      altura: target.altura,
      nivel: nivelInicial,
    })
    manualForm.setData({
      camara_id: camara ? String(camara.id) : '',
      banda: target.banda,
      fila: target.fila,
      columna: target.columna,
      altura: target.altura,
      nivel: nivelInicial,
      folio: '',
      tipo_proceso_id: '',
      cajas: '',
      pallets: '',
      especie: '',
      variedad: '',
      productor: '',
    })
  }

  const submitManual = (event) => {
    event.preventDefault()

    manualForm.post(route('prefrio.saldos.store'), {
      preserveScroll: true,
      onSuccess: () => setAssignmentTarget(null),
    })
  }

  const submitAssignment = (event) => {
    event.preventDefault()
    const saldoId = assignmentTarget?.saldoId
    if (!saldoId) return

    assignmentForm.patch(route('prefrio.saldos.update', saldoId), {
      preserveScroll: true,
      onSuccess: () => setAssignmentTarget(null),
    })
  }

  const confirmRetiro = () => {
    if (!retiringSaldo) return

    router.delete(route('prefrio.saldos.destroy', retiringSaldo.id), {
      preserveScroll: true,
      onSuccess: () => {
        setRetiringSaldo(null)
        setDetailItems([])
      },
    })
  }

  const targetLabel = assignmentTarget
    ? `${assignmentTarget.banda}/${positionLabel(assignmentTarget.banda, assignmentTarget.fila)}/${assignmentTarget.columna}/${assignmentTarget.altura}`
    : ''
  const activeAssignmentForm = assignmentMode === 'manual' ? manualForm : assignmentForm

  const Celda = ({ fila, columna }) => {
    const items = saldosPorCelda(fila, columna)

    if (items.length === 0) {
      return (
        <button
          type="button"
          className="w-full min-h-[72px] flex flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-200 text-gray-400 transition hover:border-sky-400 hover:bg-sky-50 hover:text-sky-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
          onClick={() => openAssignment({ banda: bandaActual, fila, columna, altura: alturaActual })}
        >
          <Move className="mb-1 h-3.5 w-3.5" />
          <span className="text-[10px]">Asignar folio</span>
        </button>
      )
    }

    const cajas = items.reduce((sum, s) => sum + (s.cajas || 0), 0)

    return (
      <button
        type="button"
        className="w-full min-h-[72px] flex flex-col items-start gap-1 rounded-md border-2 border-sky-300 bg-sky-50 p-1.5 text-left transition hover:border-sky-500 hover:bg-sky-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-sky-500"
        onClick={() => setDetailItems(items)}
      >
        <div className="flex items-center justify-between w-full gap-1">
          <span className="px-1.5 py-0.5 rounded bg-sky-100 text-[10px] font-semibold text-sky-800">
            {items.length} folio(s)
          </span>
          {cajas > 0 && <span className="text-[10px] text-gray-500">{cajas} cajas</span>}
        </div>
        {items.map((s) => (
          <div key={s.id} className="w-full text-[10px] leading-tight">
            <span className="font-mono font-bold text-gray-800">
              {s.folio}
            </span>
            {s.especie && <span className="text-gray-600"> · {s.especie}</span>}
          </div>
        ))}
        <span className="mt-auto text-[9px] font-medium text-sky-700">Ver detalle</span>
      </button>
    )
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Matriz de cámaras</h2>
        </div>
      }
    >
      <Head title="Prefrío · Matriz de cámaras" />

      <div className="py-12">
        <div className="mx-auto sm:px-6 lg:px-8 space-y-4">
          <Card>
            <CardContent className="pt-6 flex flex-col sm:flex-row items-start sm:items-end gap-4">
              <div className="w-full sm:w-80">
                <Label className="mb-1 block">Cámara</Label>
                <Select value={camara ? String(camara.id) : ''} onValueChange={handleCamaraChange}>
                  <SelectTrigger>
                    <SelectValue placeholder="Seleccione una cámara" />
                  </SelectTrigger>
                  <SelectContent>
                    {camaras.map((c) => (
                      <SelectItem key={c.id} value={String(c.id)} disabled={!c.activo}>
                        {c.codigo} · {c.nombre}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              {bandas.length > 0 && (
                <div className="w-full sm:w-48">
                  <Label className="mb-1 block">Banda</Label>
                  <Select value={bandaActual} onValueChange={setBanda}>
                    <SelectTrigger>
                      <SelectValue placeholder="Banda" />
                    </SelectTrigger>
                    <SelectContent>
                      {bandas.map((b) => (
                        <SelectItem key={b} value={b}>{b}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              )}
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

          {!camara ? (
            <Card>
              <CardContent className="pt-6 text-center text-gray-500 py-10">
                Seleccione una cámara para visualizar su matriz.
              </CardContent>
            </Card>
          ) : filas.length === 0 ? (
            <Card>
              <CardContent className="pt-6 text-center text-gray-500 py-10">
                La cámara no tiene parametrización. Configúrela desde el módulo de Cámaras.
              </CardContent>
            </Card>
          ) : (
            <Card>
              <CardContent className="pt-6 overflow-x-auto">
                {viewMode === '3d' ? (
                  <Suspense fallback={<div className="text-center text-gray-400 py-10">Cargando vista 3D...</div>}>
                    <CameraRackScene3D
                      parametros={parametros}
                      folios={isRackable ? saldos : saldosEnAltura}
                      codigo={camara?.codigo}
                      titulo={camara?.nombre}
                      tipo={camara?.tipo}
                    />
                  </Suspense>
                ) : (
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-24">{isRackable ? 'Posición' : 'Fila'}</TableHead>
                      {columnas.map((c) => (
                        <TableHead key={c} className="text-center">
                          Col. {c}
                        </TableHead>
                      ))}
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {filas.map((f) => (
                      <TableRow key={f}>
                        <TableCell className="font-semibold">
                          {isRackable ? `Posición ${positionLabel(bandaActual, f)}` : `Fila ${f}`}
                        </TableCell>
                        {columnas.map((c) => (
                          <TableCell key={c} className="p-1 min-w-[160px]">
                            <Celda fila={f} columna={c} />
                          </TableCell>
                        ))}
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
                )}
              </CardContent>
            </Card>
          )}

          <div className="flex flex-wrap gap-3 text-xs text-gray-600">
            <span className="inline-flex items-center gap-1.5">
              <span className="w-3 h-3 rounded bg-sky-50 border border-sky-300" /> Ocupado
            </span>
            <span className="inline-flex items-center gap-1.5">
              <span className="w-3 h-3 rounded border-2 border-dashed border-gray-200" /> Vacío
            </span>
          </div>

          {saldos.length > 0 && (
            <Card>
              <CardContent className="pt-6">
                <h3 className="font-semibold text-sm mb-3">Saldos de la cámara</h3>
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>{isRackable ? 'Banda/Posición/Columna/Altura' : 'Ubicación'}</TableHead>
                        {!isRackable && <TableHead>Nivel</TableHead>}
                        <TableHead>Folio</TableHead>
                        <TableHead>Proceso</TableHead>
                        <TableHead>Cajas</TableHead>
                        <TableHead>Pallets</TableHead>
                        <TableHead>Especie</TableHead>
                        <TableHead>Variedad</TableHead>
                        <TableHead>Productor</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {saldos.map((s) => (
                        <TableRow key={s.id}>
                          <TableCell className="font-mono text-xs">
                            {s.banda}/{isRackable ? positionLabel(s.banda, s.fila) : s.fila}/{s.columna}/{s.altura}
                          </TableCell>
                          {!isRackable && (
                            <TableCell>
                              <Badge variant="secondary">{s.nivel}</Badge>
                            </TableCell>
                          )}
                          <TableCell className="font-mono">{s.folio}</TableCell>
                          <TableCell>{s.tipo_proceso || '—'}</TableCell>
                          <TableCell>{s.cajas ?? '—'}</TableCell>
                          <TableCell>{s.pallets ?? '—'}</TableCell>
                          <TableCell>{s.especie || '—'}</TableCell>
                          <TableCell>{s.variedad || '—'}</TableCell>
                          <TableCell>{s.productor || '—'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </CardContent>
            </Card>
          )}

          <Dialog open={detailItems.length > 0} onOpenChange={(open) => !open && setDetailItems([])}>
            <DialogContent className="max-h-[85vh] max-w-2xl overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Detalle de la posición</DialogTitle>
                <DialogDescription>
                  Revise los folios almacenados, reubíquelos o registre su salida de la cámara.
                </DialogDescription>
              </DialogHeader>

              <div className="space-y-3">
                {detailItems.map((saldo) => (
                  <div key={saldo.id} className="rounded-lg border border-sky-200 bg-sky-50/60 p-4">
                    <div className="mb-3 flex flex-wrap items-start justify-between gap-2">
                      <div>
                        <p className="font-mono text-base font-bold text-gray-900">{saldo.folio}</p>
                        <p className="text-xs text-gray-500">
                          {saldo.banda}/{positionLabel(saldo.banda, saldo.fila)}/{saldo.columna}/{saldo.altura}/{saldo.nivel}
                        </p>
                      </div>
                      <Badge>{saldo.tipo_proceso || 'Sin proceso'}</Badge>
                    </div>

                    <dl className="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
                      <div><dt className="text-xs text-gray-500">Especie</dt><dd>{saldo.especie || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Variedad</dt><dd>{saldo.variedad || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Productor</dt><dd>{saldo.productor || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Exportadora</dt><dd>{saldo.exportadora || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Embalaje</dt><dd>{saldo.embalaje || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Categoría / calibre</dt><dd>{[saldo.categoria, saldo.calibre].filter(Boolean).join(' · ') || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Cajas</dt><dd>{saldo.cajas ?? '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Pallets</dt><dd>{saldo.pallets ?? '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Proceso / túnel</dt><dd>{[saldo.proceso_numero, saldo.tunel].filter(Boolean).join(' · ') || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">Salida del túnel</dt><dd>{saldo.fecha_hora_salida || '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">T° ambiente túnel</dt><dd>{saldo.temperatura_ambiente_tunel_salida != null ? `${saldo.temperatura_ambiente_tunel_salida} °C` : '—'}</dd></div>
                      <div><dt className="text-xs text-gray-500">T° ambiente cámara</dt><dd>{saldo.temperatura_ambiente_camara_salida != null ? `${saldo.temperatura_ambiente_camara_salida} °C` : '—'}</dd></div>
                    </dl>

                    {saldo.temperaturas && (
                      <div className="mt-3 border-t border-sky-200 pt-3">
                        <p className="mb-1 text-xs font-semibold text-gray-600">Temperaturas del folio</p>
                        <div className="flex flex-wrap gap-1.5">
                          {Object.entries(saldo.temperaturas).map(([tipo, valor]) => (
                            <Badge key={tipo} variant="secondary">{tipo}: {valor} °C</Badge>
                          ))}
                        </div>
                      </div>
                    )}

                    <div className="mt-4 flex flex-wrap justify-end gap-2">
                      <Button
                        type="button"
                        size="sm"
                        variant="secondary"
                        onClick={() => openAssignment({
                          banda: saldo.banda,
                          fila: saldo.fila,
                          columna: saldo.columna,
                          altura: saldo.altura,
                        }, saldo)}
                      >
                        <Move className="mr-1.5 h-3.5 w-3.5" /> Cambiar nivel
                      </Button>
                      <Button
                        type="button"
                        size="sm"
                        variant="destructive"
                        onClick={() => {
                          setDetailItems([])
                          setRetiringSaldo(saldo)
                        }}
                      >
                        <LogOut className="mr-1.5 h-3.5 w-3.5" /> Sacar de cámara
                      </Button>
                    </div>
                  </div>
                ))}
              </div>

              <DialogFooter className="gap-2">
                {detailItems[0] && (
                  <Button
                    type="button"
                    variant="secondary"
                    onClick={() => openAssignment({
                      banda: detailItems[0].banda,
                      fila: detailItems[0].fila,
                      columna: detailItems[0].columna,
                      altura: detailItems[0].altura,
                    })}
                  >
                    <PackageSearch className="mr-1.5 h-4 w-4" /> Asignar otro folio aquí
                  </Button>
                )}
                <Button type="button" variant="ghost" onClick={() => setDetailItems([])}>Cerrar</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>

          <Dialog open={Boolean(assignmentTarget)} onOpenChange={(open) => !open && setAssignmentTarget(null)}>
            <DialogContent className="max-h-[90vh] max-w-lg overflow-y-auto">
              <form onSubmit={assignmentMode === 'manual' ? submitManual : submitAssignment}>
                <DialogHeader>
                  <DialogTitle>Asignar folio a posición</DialogTitle>
                  <DialogDescription>
                    Destino {targetLabel}. Puede ingresar un folio nuevo aunque no exista previamente en el sistema.
                  </DialogDescription>
                </DialogHeader>

                <div className="space-y-4 py-5">
                  <div className="grid grid-cols-2 gap-1 rounded-md bg-gray-100 p-1">
                    <Button
                      type="button"
                      size="sm"
                      variant={assignmentMode === 'manual' ? 'default' : 'ghost'}
                      onClick={() => setAssignmentMode('manual')}
                    >
                      Ingresar folio nuevo
                    </Button>
                    <Button
                      type="button"
                      size="sm"
                      variant={assignmentMode === 'existing' ? 'default' : 'ghost'}
                      disabled={saldos.length === 0}
                      onClick={() => setAssignmentMode('existing')}
                    >
                      Mover folio existente
                    </Button>
                  </div>

                  {assignmentMode === 'manual' ? (
                    <>
                      <div>
                        <Label htmlFor="manual-folio" className="mb-1 block">Folio *</Label>
                        <Input
                          id="manual-folio"
                          value={manualForm.data.folio}
                          onChange={(event) => manualForm.setData('folio', event.target.value)}
                          maxLength={50}
                          autoFocus
                          placeholder="Ingrese el número de folio"
                        />
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <Label className="mb-1 block">Proceso (opcional)</Label>
                          <Select value={manualForm.data.tipo_proceso_id} onValueChange={(value) => manualForm.setData('tipo_proceso_id', value)}>
                            <SelectTrigger><SelectValue placeholder="Sin proceso" /></SelectTrigger>
                            <SelectContent>
                              {tiposProcesos.map((tipo) => (
                                <SelectItem key={tipo.id} value={String(tipo.id)}>{tipo.codigo} · {tipo.nombre}</SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                        <div>
                          <Label className="mb-1 block">Nivel *</Label>
                          <Select value={manualForm.data.nivel} onValueChange={(value) => manualForm.setData('nivel', value)}>
                            <SelectTrigger><SelectValue placeholder="Seleccione nivel" /></SelectTrigger>
                            <SelectContent>
                              {niveles.map((nivel) => <SelectItem key={nivel} value={nivel}>{nivel}</SelectItem>)}
                            </SelectContent>
                          </Select>
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <Label htmlFor="manual-cajas" className="mb-1 block">Cajas</Label>
                          <Input id="manual-cajas" type="number" min="0" value={manualForm.data.cajas} onChange={(event) => manualForm.setData('cajas', event.target.value)} />
                        </div>
                        <div>
                          <Label htmlFor="manual-pallets" className="mb-1 block">Pallets</Label>
                          <Input id="manual-pallets" type="number" min="0" value={manualForm.data.pallets} onChange={(event) => manualForm.setData('pallets', event.target.value)} />
                        </div>
                      </div>

                      <div className="grid grid-cols-2 gap-3">
                        <div>
                          <Label htmlFor="manual-especie" className="mb-1 block">Especie</Label>
                          <Input id="manual-especie" value={manualForm.data.especie} onChange={(event) => manualForm.setData('especie', event.target.value)} maxLength={255} />
                        </div>
                        <div>
                          <Label htmlFor="manual-variedad" className="mb-1 block">Variedad</Label>
                          <Input id="manual-variedad" value={manualForm.data.variedad} onChange={(event) => manualForm.setData('variedad', event.target.value)} maxLength={255} />
                        </div>
                      </div>

                      <div>
                        <Label htmlFor="manual-productor" className="mb-1 block">Productor</Label>
                        <Input id="manual-productor" value={manualForm.data.productor} onChange={(event) => manualForm.setData('productor', event.target.value)} maxLength={255} />
                      </div>
                    </>
                  ) : (
                    <>
                      <div>
                        <Label className="mb-1 block">Folio existente</Label>
                        <Select
                          value={assignmentTarget?.saldoId || ''}
                          onValueChange={(value) => setAssignmentTarget((current) => ({ ...current, saldoId: value }))}
                        >
                          <SelectTrigger><SelectValue placeholder="Seleccione un folio" /></SelectTrigger>
                          <SelectContent>
                            {saldos.map((saldo) => (
                              <SelectItem key={saldo.id} value={String(saldo.id)}>
                                {saldo.folio} · {saldo.banda}/{positionLabel(saldo.banda, saldo.fila)}/{saldo.columna}/{saldo.altura}/{saldo.nivel}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label className="mb-1 block">Nivel *</Label>
                        <Select value={assignmentForm.data.nivel} onValueChange={(value) => assignmentForm.setData('nivel', value)}>
                          <SelectTrigger><SelectValue placeholder="Seleccione nivel" /></SelectTrigger>
                          <SelectContent>
                            {niveles.map((nivel) => <SelectItem key={nivel} value={nivel}>{nivel}</SelectItem>)}
                          </SelectContent>
                        </Select>
                      </div>
                    </>
                  )}

                  {Object.keys(activeAssignmentForm.errors).length > 0 && (
                    <p className="rounded-md bg-red-50 px-3 py-2 text-xs text-red-700">
                      {Object.values(activeAssignmentForm.errors)[0]}
                    </p>
                  )}
                </div>

                <DialogFooter>
                  <Button type="button" variant="secondary" onClick={() => setAssignmentTarget(null)}>Cancelar</Button>
                  <Button
                    type="submit"
                    disabled={assignmentMode === 'manual'
                      ? !manualForm.data.folio.trim() || !manualForm.data.nivel || manualForm.processing
                      : !assignmentTarget?.saldoId || !assignmentForm.data.nivel || assignmentForm.processing}
                  >
                    {activeAssignmentForm.processing
                      ? 'Guardando…'
                      : assignmentMode === 'manual' ? 'Ingresar folio' : 'Asignar posición'}
                  </Button>
                </DialogFooter>
              </form>
            </DialogContent>
          </Dialog>

          <Dialog open={Boolean(retiringSaldo)} onOpenChange={(open) => !open && setRetiringSaldo(null)}>
            <DialogContent className="max-w-md">
              <DialogHeader>
                <DialogTitle>Sacar folio de la cámara</DialogTitle>
                <DialogDescription>
                  El folio {retiringSaldo?.folio} dejará de aparecer como saldo activo en {camara?.codigo}. La operación quedará registrada en auditoría.
                </DialogDescription>
              </DialogHeader>
              <DialogFooter>
                <Button type="button" variant="secondary" onClick={() => setRetiringSaldo(null)}>Cancelar</Button>
                <Button type="button" variant="destructive" onClick={confirmRetiro}>Confirmar salida</Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
