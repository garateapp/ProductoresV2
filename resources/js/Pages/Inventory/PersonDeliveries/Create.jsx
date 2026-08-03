import { useEffect, useMemo, useRef, useState } from 'react'
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
import { ArrowLeft, Eraser, Plus, Save, Trash2 } from 'lucide-react'

const emptyItem = { material_id: '', cantidad: '' }

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

export default function CreatePersonDelivery({ locations = [], materials = [] }) {
  const [stockReferenceByIndex, setStockReferenceByIndex] = useState({})
  const [signatureError, setSignatureError] = useState('')
  const { data, setData, post, processing, errors, clearErrors } = useForm({
    origin_location_id: '',
    person_name: '',
    person_position: '',
    delivered_at: getLocalDateTimeInputValue(),
    notes: '',
    signature_data_url: '',
    items: [{ ...emptyItem }],
  })

  const locationOptions = locations.map((location) => ({ value: String(location.id), label: location.nombre }))
  const materialOptions = materials.map((material) => ({
    value: String(material.id),
    label: `${material.codigo} · ${material.nombre}${material.unit ? ` (${material.unit})` : ''}`,
  }))
  const materialLookup = useMemo(() => new Map(materials.map((material) => [String(material.id), material])), [materials])
  const stockLookupKey = data.items.map((item) => item.material_id).join('|')

  useEffect(() => {
    let cancelled = false

    const loadStockReferences = async () => {
      if (!data.origin_location_id) {
        setStockReferenceByIndex({})
        return
      }

      const entries = await Promise.all(data.items.map(async (item, index) => {
        if (!item.material_id) {
          return [index, null]
        }

        try {
          const response = await window.axios.get(route('inventory.movements.stock-reference'), {
            params: {
              origin_location_id: data.origin_location_id,
              material_id: item.material_id,
            },
          })

          return [index, {
            stock_actual: Number(response.data.stock_actual || 0),
            positions_count: Array.isArray(response.data.positions) ? response.data.positions.length : 0,
            error: null,
          }]
        } catch {
          return [index, { stock_actual: null, positions_count: 0, error: 'No fue posible consultar stock.' }]
        }
      }))

      if (!cancelled) {
        setStockReferenceByIndex(Object.fromEntries(entries.filter((entry) => entry[1] !== null)))
      }
    }

    loadStockReferences()

    return () => {
      cancelled = true
    }
  }, [data.origin_location_id, stockLookupKey])

  const addItem = () => setData('items', [...data.items, { ...emptyItem }])
  const removeItem = (index) => setData('items', data.items.filter((_, currentIndex) => currentIndex !== index))
  const updateItem = (index, field, value) => {
    const nextItems = [...data.items]
    nextItems[index] = { ...nextItems[index], [field]: value }
    setData('items', nextItems)
  }

  const submit = (event) => {
    event.preventDefault()

    if (!data.signature_data_url) {
      setSignatureError('La firma es obligatoria.')
      return
    }

    setSignatureError('')
    clearErrors()
    post(route('inventory.person-deliveries.store'))
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Nueva Entrega de Materiales</h2>
          <Link href={route('inventory.person-deliveries.index')}>
            <Button variant="outline" type="button">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Volver
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Nueva Entrega de Materiales" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <form onSubmit={submit} className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Datos de Entrega</CardTitle>
              </CardHeader>
              <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div className="space-y-2">
                  <Label>Nombre de la persona</Label>
                  <Input value={data.person_name} onChange={(event) => setData('person_name', event.target.value)} />
                  {errors.person_name && <p className="text-sm text-red-600">{errors.person_name}</p>}
                </div>

                <div className="space-y-2">
                  <Label>Cargo</Label>
                  <Input value={data.person_position} onChange={(event) => setData('person_position', event.target.value)} />
                  {errors.person_position && <p className="text-sm text-red-600">{errors.person_position}</p>}
                </div>

                <div className="space-y-2">
                  <Label>Ubicación de origen</Label>
                  <SearchableSelect
                    options={locationOptions}
                    value={locationOptions.find((option) => option.value === data.origin_location_id)}
                    onChange={(option) => setData('origin_location_id', option?.value || '')}
                    placeholder="Selecciona bodega"
                  />
                  {errors.origin_location_id && <p className="text-sm text-red-600">{errors.origin_location_id}</p>}
                </div>

                <div className="space-y-2">
                  <Label>Fecha de entrega</Label>
                  <Input
                    type="datetime-local"
                    value={data.delivered_at}
                    onChange={(event) => setData('delivered_at', event.target.value)}
                  />
                  {errors.delivered_at && <p className="text-sm text-red-600">{errors.delivered_at}</p>}
                </div>

                <div className="space-y-2 md:col-span-2">
                  <Label>Observación</Label>
                  <Textarea value={data.notes} onChange={(event) => setData('notes', event.target.value)} />
                  {errors.notes && <p className="text-sm text-red-600">{errors.notes}</p>}
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Materiales Entregados</CardTitle>
                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                  <Plus className="w-4 h-4 mr-2" />
                  Agregar
                </Button>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Material</TableHead>
                      <TableHead className="w-44">Cantidad</TableHead>
                      <TableHead className="w-56">Stock</TableHead>
                      <TableHead className="w-12"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.items.map((item, index) => {
                      const reference = stockReferenceByIndex[index]
                      const requested = Number(item.cantidad || 0)
                      const isOverStock = reference?.stock_actual !== null && requested > Number(reference?.stock_actual || 0)
                      const material = materialLookup.get(String(item.material_id))

                      return (
                        <TableRow key={index}>
                          <TableCell>
                            <SearchableSelect
                              options={materialOptions}
                              value={materialOptions.find((option) => option.value === item.material_id)}
                              onChange={(option) => updateItem(index, 'material_id', option?.value || '')}
                              placeholder="Buscar material"
                            />
                            {errors[`items.${index}.material_id`] && <p className="text-sm text-red-600 mt-1">{errors[`items.${index}.material_id`]}</p>}
                          </TableCell>
                          <TableCell>
                            <Input
                              type="number"
                              step="0.0001"
                              min="0"
                              value={item.cantidad}
                              onChange={(event) => updateItem(index, 'cantidad', event.target.value)}
                            />
                            {errors[`items.${index}.cantidad`] && <p className="text-sm text-red-600 mt-1">{errors[`items.${index}.cantidad`]}</p>}
                          </TableCell>
                          <TableCell className={isOverStock ? 'text-red-600' : 'text-slate-600'}>
                            {reference
                              ? reference.error || `${formatQuantity(reference.stock_actual)} ${material?.unit || ''}`
                              : '-'}
                            {reference?.positions_count > 0 && <span className="block text-xs text-slate-500">FIFO posiciones</span>}
                          </TableCell>
                          <TableCell>
                            {data.items.length > 1 && (
                              <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(index)}>
                                <Trash2 className="w-4 h-4 text-red-500" />
                              </Button>
                            )}
                          </TableCell>
                        </TableRow>
                      )
                    })}
                  </TableBody>
                </Table>
                {errors.items && <p className="text-sm text-red-600 mt-2">{errors.items}</p>}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle>Firma</CardTitle>
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
              <Link href={route('inventory.person-deliveries.index')}>
                <Button type="button" variant="ghost">Cancelar</Button>
              </Link>
              <Button type="submit" disabled={processing}>
                <Save className="w-4 h-4 mr-2" />
                Generar acta y descontar stock
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
    if (!canvas) {
      return
    }

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
    if (!drawingRef.current) {
      return
    }

    event.preventDefault()
    const context = canvasRef.current.getContext('2d')
    const currentPoint = point(event)
    context.lineTo(currentPoint.x, currentPoint.y)
    context.stroke()
  }

  const stop = () => {
    if (!drawingRef.current) {
      return
    }

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
