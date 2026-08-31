import { useEffect, useRef, useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { getLocalDateTimeInputValue } from '@/lib/datetime'
import { ArrowLeft, CheckCircle2, Circle, Eraser, Plus, Save, Trash2 } from 'lucide-react'

const conditionOptions = [
  { value: 'nuevo', label: 'Nuevo' },
  { value: 'usado', label: 'Usado' },
]

const formatFecha = (value) => {
  if (!value) return '-'
  const parsed = new Date(String(value).length === 10 ? `${value}T00:00:00` : value)
  return isNaN(parsed) ? value : parsed.toLocaleDateString('es-CL')
}

export default function CreateTechEquipmentDelivery({ equipment = [] }) {
  const [signatureError, setSignatureError] = useState('')
  const { data, setData, post, processing, errors, clearErrors } = useForm({
    person_name: '',
    person_rut: '',
    departamento: '',
    cargo: '',
    condicion: 'nuevo',
    delivered_at: getLocalDateTimeInputValue(),
    observations: '',
    signature_data_url: '',
    equipment_ids: [],
  })

  const options = equipment.map((item) => ({
    value: String(item.id),
    label: `${item.marca} · ${item.numero_serie}${item.descripcion ? ` · ${item.descripcion}` : ''}`,
  }))

  const toggleEquipment = (id) => {
    const key = String(id)
    const current = [...data.equipment_ids]
    const index = current.indexOf(key)
    if (index >= 0) {
      current.splice(index, 1)
    } else {
      current.push(key)
    }
    setData('equipment_ids', current)
    clearErrors('equipment_ids')
  }

  const selectedItems = equipment.filter((item) => data.equipment_ids.includes(String(item.id)))

  const submit = (event) => {
    event.preventDefault()

    if (!data.signature_data_url) {
      setSignatureError('La firma es obligatoria.')
      return
    }
    if (data.equipment_ids.length === 0) {
      setSignatureError('Debes asignar al menos un equipo.')
      return
    }

    setSignatureError('')
    clearErrors()
    post(route('inventory.tech-equipment-deliveries.store'))
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Nueva Acta de Entrega de Equipos</h2>
          <Link href={route('inventory.tech-equipment-deliveries.index')}>
            <Button variant="outline" type="button">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Volver
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Nueva Acta de Entrega de Equipos" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <form onSubmit={submit} className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Datos de la Persona</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label htmlFor="person_name">Nombre</Label>
                  <Input
                    id="person_name"
                    value={data.person_name}
                    onChange={(event) => setData('person_name', event.target.value)}
                    placeholder="Nombre completo"
                  />
                  {errors.person_name && <p className="text-sm text-red-600">{errors.person_name}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="person_rut">RUT</Label>
                  <Input
                    id="person_rut"
                    value={data.person_rut}
                    onChange={(event) => setData('person_rut', event.target.value)}
                    placeholder="12.345.678-9"
                  />
                  {errors.person_rut && <p className="text-sm text-red-600">{errors.person_rut}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="departamento">Departamento</Label>
                  <Input
                    id="departamento"
                    value={data.departamento}
                    onChange={(event) => setData('departamento', event.target.value)}
                    placeholder="Ej: Informática, Administración"
                  />
                  {errors.departamento && <p className="text-sm text-red-600">{errors.departamento}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="cargo">Cargo</Label>
                  <Input
                    id="cargo"
                    value={data.cargo}
                    onChange={(event) => setData('cargo', event.target.value)}
                    placeholder="Ej: Analista, Jefe de área"
                  />
                  {errors.cargo && <p className="text-sm text-red-600">{errors.cargo}</p>}
                </div>

                <div className="space-y-2">
                  <Label>Condición del equipo</Label>
                  <div className="grid grid-cols-2 gap-3">
                    {conditionOptions.map((option) => (
                      <button
                        key={option.value}
                        type="button"
                        onClick={() => setData('condicion', option.value)}
                        className={`flex items-center justify-center gap-2 rounded-md border px-4 py-2.5 text-sm font-medium transition-colors ${
                          data.condicion === option.value
                            ? 'border-greenex-vibrant-green bg-greenex-vibrant-green text-white'
                            : 'border-slate-300 text-slate-600 hover:bg-slate-50'
                        }`}
                      >
                        {data.condicion === option.value ? <CheckCircle2 className="w-4 h-4" /> : <Circle className="w-4 h-4" />}
                        {option.label}
                      </button>
                    ))}
                  </div>
                  {errors.condicion && <p className="text-sm text-red-600">{errors.condicion}</p>}
                </div>

                <div className="space-y-2">
                  <Label htmlFor="delivered_at">Fecha de entrega</Label>
                  <Input
                    id="delivered_at"
                    type="datetime-local"
                    value={data.delivered_at}
                    onChange={(event) => setData('delivered_at', event.target.value)}
                  />
                  {errors.delivered_at && <p className="text-sm text-red-600">{errors.delivered_at}</p>}
                </div>

                <div className="space-y-2 md:col-span-2">
                  <Label htmlFor="observations">Observaciones</Label>
                  <Textarea
                    id="observations"
                    value={data.observations}
                    onChange={(event) => setData('observations', event.target.value)}
                    placeholder="Observaciones de la entrega"
                  />
                  {errors.observations && <p className="text-sm text-red-600">{errors.observations}</p>}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Equipos Asignados</CardTitle>
              </CardHeader>
              <CardContent className="space-y-4">
                <SearchableSelect
                  options={options}
                  value={null}
                  onChange={(option) => {
                    if (option) {
                      toggleEquipment(option.value)
                    }
                  }}
                  placeholder="Buscar equipo para asignar..."
                />
                {errors.equipment_ids && <p className="text-sm text-red-600">{errors.equipment_ids}</p>}

                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Marca</TableHead>
                      <TableHead>N° de Serie</TableHead>
                      <TableHead>Fecha</TableHead>
                      <TableHead>Características</TableHead>
                      <TableHead className="w-12"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedItems.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell className="font-medium">{item.marca}</TableCell>
                        <TableCell className="font-mono">{item.numero_serie}</TableCell>
                        <TableCell>{formatFecha(item.fecha)}</TableCell>
                        <TableCell className="max-w-md">
                          <p className="truncate" title={item.descripcion}>{item.descripcion || '-'}</p>
                        </TableCell>
                        <TableCell>
                          <Button type="button" variant="ghost" size="sm" onClick={() => toggleEquipment(item.id)}>
                            <Trash2 className="w-4 h-4 text-red-500" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    ))}
                    {selectedItems.length === 0 && (
                      <TableRow>
                        <TableCell colSpan={5} className="text-center text-slate-400 py-8">
                          No hay equipos asignados. Busca y selecciona equipos arriba.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Firma del Receptor</CardTitle>
              </CardHeader>
              <CardContent>
                <SignaturePad
                  value={data.signature_data_url}
                  onChange={(value) => {
                    setData('signature_data_url', value)
                    setSignatureError('')
                  }}
                />
                {(signatureError || errors.signature_data_url) && (
                  <p className="text-sm text-red-600 mt-2">{signatureError || errors.signature_data_url}</p>
                )}
              </CardContent>
            </Card>

            <div className="flex justify-end gap-3">
              <Link href={route('inventory.tech-equipment-deliveries.index')}>
                <Button type="button" variant="ghost">Cancelar</Button>
              </Link>
              <Button type="submit" disabled={processing}>
                <Save className="w-4 h-4 mr-2" />
                Generar acta de entrega
              </Button>
            </div>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

function SignaturePad({ value, onChange }) {
  const canvasRef = useRef(null)
  const drawingRef = useRef(false)
  const hasInkRef = useRef(Boolean(value))

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return

    const rect = canvas.getBoundingClientRect()
    const ratio = window.devicePixelRatio || 1
    canvas.width = Math.max(rect.width * ratio, 1)
    canvas.height = 220 * ratio
    const context = canvas.getContext('2d')
    context.scale(ratio, ratio)
    context.lineCap = 'round'
    context.lineJoin = 'round'
    context.lineWidth = 2.5
    context.strokeStyle = '#111827'
  }, [])

  const point = (event) => {
    const rect = canvasRef.current.getBoundingClientRect()
    return { x: event.clientX - rect.left, y: event.clientY - rect.top }
  }

  const start = (event) => {
    event.preventDefault()
    const canvas = canvasRef.current
    const context = canvas.getContext('2d')
    const currentPoint = point(event)
    drawingRef.current = true
    hasInkRef.current = true
    canvas.setPointerCapture(event.pointerId)
    context.beginPath()
    context.moveTo(currentPoint.x, currentPoint.y)
  }

  const draw = (event) => {
    if (!drawingRef.current) return
    event.preventDefault()
    const context = canvasRef.current.getContext('2d')
    const currentPoint = point(event)
    context.lineTo(currentPoint.x, currentPoint.y)
    context.stroke()
  }

  const stop = () => {
    if (!drawingRef.current) return
    drawingRef.current = false
    onChange(canvasRef.current.toDataURL('image/png'))
  }

  const clear = () => {
    const canvas = canvasRef.current
    const context = canvas.getContext('2d')
    context.clearRect(0, 0, canvas.width, canvas.height)
    hasInkRef.current = false
    onChange('')
  }

  return (
    <div className="space-y-3">
      <div className="rounded-md border border-slate-300 bg-white">
        <canvas
          ref={canvasRef}
          className="block h-[220px] w-full touch-none"
          onPointerDown={start}
          onPointerMove={draw}
          onPointerUp={stop}
          onPointerCancel={stop}
          onPointerLeave={stop}
        />
      </div>
      <Button type="button" variant="outline" size="sm" onClick={clear} disabled={!hasInkRef.current && !value}>
        <Eraser className="w-4 h-4 mr-2" />
        Limpiar firma
      </Button>
    </div>
  )
}
