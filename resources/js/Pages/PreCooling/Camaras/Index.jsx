import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Badge } from '@/Components/ui/badge'
import ParametrizacionEditor from '@/Components/PreCooling/ParametrizacionEditor'
import { Plus, Pencil } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { toast, Toaster } from 'sonner'
import { buildConsecutivePositionsByBand } from '@/Components/PreCooling/cameraRackPositions'

const DIMENSIONES = [
  { key: 'columna', label: 'Columnas' },
  { key: 'altura', label: 'Alturas' },
  { key: 'nivel', label: 'Niveles' },
]

const BANDAS_CAMARA = ['Izquierda', 'Central-Izq', 'Central-Dcha', 'Derecha']
const BAND_ROW_DIMENSIONS = {
  Izquierda: 'fila_izquierda',
  'Central-Izq': 'fila_central_izq',
  'Central-Dcha': 'fila_central_dcha',
  Derecha: 'fila_derecha',
}
const VACIOS = {
  banda: [...BANDAS_CAMARA],
  fila_izquierda: ['1'],
  fila_central_izq: ['2'],
  fila_central_dcha: ['3'],
  fila_derecha: ['4'],
  columna: ['1', '2', '3'],
  altura: [],
  nivel: ['1'],
}

const TIPOS_CAMARA = [
  { value: 'rackeable', label: 'Rackeable' },
  { value: 'planta_libre', label: 'Planta Libre' },
]

export default function CamarasIndex({ camaras }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    tipo: 'rackeable',
    activo: true,
    parametros: VACIOS,
  })

  const openDialog = (camara = null) => {
    if (camara) {
      setEditing(camara)
      setData({
        codigo: camara.codigo,
        nombre: camara.nombre,
        tipo: camara.tipo || 'rackeable',
        activo: !!camara.activo,
        parametros: (() => {
          const legacyRows = camara.parametros?.fila || ['1']
          return {
          banda: [...(camara.parametros?.banda || BANDAS_CAMARA)],
          fila_izquierda: [...(camara.parametros?.fila_izquierda || legacyRows)],
          fila_central_izq: [...(camara.parametros?.fila_central_izq || legacyRows)],
          fila_central_dcha: [...(camara.parametros?.fila_central_dcha || legacyRows)],
          fila_derecha: [...(camara.parametros?.fila_derecha || legacyRows)],
          columna: [...(camara.parametros?.columna || [])],
          altura: [...(camara.parametros?.altura || [])],
          nivel: [...(camara.parametros?.nivel?.length ? camara.parametros.nivel : ['1'])],
          }
        })(),
      })
    } else {
      setEditing(null)
      reset()
    }
    setIsOpen(true)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    const options = {
      onSuccess: () => {
        setIsOpen(false)
        toast.success(editing ? 'Cámara actualizada correctamente' : 'Cámara creada correctamente')
      },
    }
    if (editing) {
      patch(route('prefrio.camaras.update', editing.id), options)
    } else {
      post(route('prefrio.camaras.store'), options)
    }
  }

  const setBandRowCount = (band, rawCount) => {
    const count = Math.min(Math.max(Number.parseInt(rawCount, 10) || 1, 1), 20)
    const countsByBand = Object.fromEntries(BANDAS_CAMARA.map((currentBand) => [
      currentBand,
      currentBand === band
        ? count
        : (data.parametros?.[BAND_ROW_DIMENSIONS[currentBand]]?.length || 1),
    ]))
    const positionsByBand = buildConsecutivePositionsByBand(BANDAS_CAMARA, countsByBand)
    setData('parametros', {
      ...data.parametros,
      ...Object.fromEntries(BANDAS_CAMARA.map((currentBand) => [
        BAND_ROW_DIMENSIONS[currentBand],
        positionsByBand[currentBand],
      ])),
    })
  }

  const parametrosBloqueados = !!editing?.tiene_saldos

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Cámaras</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nueva Cámara
          </Button>
        </div>
      }
    >
      <Head title="Prefrío · Cámaras" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Código</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Parametrización</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {camaras.map((camara) => (
                    <TableRow key={camara.id}>
                      <TableCell className="font-mono">{camara.codigo}</TableCell>
                      <TableCell>{camara.nombre}</TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${camara.tipo === 'rackeable' ? 'bg-purple-50 text-purple-800' : 'bg-amber-50 text-amber-800'}`}>
                          {TIPOS_CAMARA.find((t) => t.value === camara.tipo)?.label || camara.tipo}
                        </span>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {Object.entries(camara.parametros || {}).map(([dim, valores]) =>
                            valores.length > 0 ? (
                              <span key={dim} className="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                                {dim}: {valores.join(', ')}
                              </span>
                            ) : null
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col gap-1">
                          <span className={`px-2 py-1 rounded text-xs w-fit ${camara.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                            {camara.activo ? 'Activa' : 'Inactiva'}
                          </span>
                          {camara.tiene_saldos && <Badge variant="destructive">Con saldos</Badge>}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(camara)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {camaras.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-gray-500 py-6">
                        Sin cámaras registradas.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editing ? `Editar Cámara · ${editing.codigo}` : 'Nueva Cámara'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input
                  id="codigo"
                  value={data.codigo}
                  onChange={(e) => setData('codigo', e.target.value)}
                  disabled={!!editing}
                  required
                />
                {errors.codigo && <p className="text-red-500 text-xs mt-1">{errors.codigo}</p>}
              </div>
              <div>
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                  id="nombre"
                  value={data.nombre}
                  onChange={(e) => setData('nombre', e.target.value)}
                  required
                />
                {errors.nombre && <p className="text-red-500 text-xs mt-1">{errors.nombre}</p>}
              </div>
            </div>

            <div>
              <Label htmlFor="tipo">Tipo de Cámara</Label>
              <Select value={data.tipo} onValueChange={(value) => setData('tipo', value)}>
                <SelectTrigger>
                  <SelectValue placeholder="Seleccionar tipo" />
                </SelectTrigger>
                <SelectContent>
                  {TIPOS_CAMARA.map((t) => (
                    <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.tipo && <p className="text-red-500 text-xs mt-1">{errors.tipo}</p>}
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <Label>
                  {data.tipo === 'planta_libre'
                    ? 'Matriz de la cámara (filas demarcadas en suelo)'
                    : 'Matriz de la cámara (racks)'}
                </Label>
                {parametrosBloqueados && (
                  <Badge variant="destructive">Parametrización bloqueada por saldos registrados</Badge>
                )}
              </div>
              {data.tipo === 'planta_libre' && (
                <p className="mb-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                  Distribución física: fila lateral · pasillo · dos filas centrales · pasillo · fila lateral. Las columnas definen las posiciones a lo largo de cada fila.
                </p>
              )}
              {data.tipo === 'rackeable' && (
                <p className="mb-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900">
                  Estructura rackeable: cada fila configurada corresponde a una posición longitudinal de la banda. Cada posición contiene tres columnas fijas de profundidad (1 frente, 2 centro y 3 fondo) y se distribuye verticalmente por altura.
                </p>
              )}
              <div className="mb-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                {BANDAS_CAMARA.map((band) => {
                  const dimension = BAND_ROW_DIMENSIONS[band]
                  const count = data.parametros?.[dimension]?.length || 1
                  return (
                    <div key={band} className="rounded-md border border-blue-200 bg-blue-50/60 p-3">
                      <Label className="text-xs font-semibold text-blue-900">Banda {band}</Label>
                      <div className="mt-2 flex items-center gap-2">
                        <Input
                          type="number"
                          min="1"
                          max="20"
                          value={count}
                          disabled={parametrosBloqueados}
                          onChange={(event) => setBandRowCount(band, event.target.value)}
                        />
                        <span className="whitespace-nowrap text-xs text-gray-500">
                          {data.tipo === 'rackeable' ? 'posición(es)' : 'fila(s)'}
                        </span>
                      </div>
                    </div>
                  )
                })}
              </div>
              <ParametrizacionEditor
                dimensions={data.tipo === 'rackeable'
                  ? DIMENSIONES.filter(({ key }) => key === 'altura')
                  : DIMENSIONES}
                values={data.parametros}
                onChange={(next) => setData('parametros', next)}
                disabled={parametrosBloqueados}
              />
              {errors.parametros && <p className="text-red-500 text-xs mt-1">{errors.parametros}</p>}
            </div>

            {editing && (
              <div className="flex items-center space-x-2">
                <Switch id="activo" checked={data.activo} onCheckedChange={(checked) => setData('activo', checked)} />
                <Label htmlFor="activo">Activa</Label>
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>Guardar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
