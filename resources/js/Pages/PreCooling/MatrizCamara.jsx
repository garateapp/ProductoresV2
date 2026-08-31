import { useState, lazy, Suspense } from 'react'
import { Head, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
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
import { Map, Box } from 'lucide-react'
import { getConsecutivePositionLabel } from '@/Components/PreCooling/cameraRackPositions'

const CameraRackScene3D = lazy(() => import('@/Components/PreCooling/CameraRackScene3D'))
const ROW_DIMENSION_BY_BAND = {
  Izquierda: 'fila_izquierda',
  'Central-Izq': 'fila_central_izq',
  'Central-Dcha': 'fila_central_dcha',
  Derecha: 'fila_derecha',
}

export default function MatrizCamara({ camaras, camara, parametros, saldos }) {
  const [banda, setBanda] = useState('')
  const [altura, setAltura] = useState('')
  const [viewMode, setViewMode] = useState('2d')

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

  const Celda = ({ fila, columna }) => {
    const items = saldosPorCelda(fila, columna)

    if (items.length === 0) {
      return (
        <div className="w-full min-h-[72px] flex flex-col items-center justify-center rounded-md border-2 border-dashed border-gray-200 text-gray-400">
          <span className="text-[10px]">Vacío</span>
        </div>
      )
    }

    const cajas = items.reduce((sum, s) => sum + (s.cajas || 0), 0)

    return (
      <div className="w-full min-h-[72px] flex flex-col items-start gap-1 rounded-md border-2 border-sky-300 bg-sky-50 p-1.5">
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
      </div>
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
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
