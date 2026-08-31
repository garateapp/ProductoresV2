import { useEffect, useRef, useState } from 'react'
import { Head, Link, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { getLocalDateTimeInputValue } from '@/lib/datetime'
import { ArrowLeft, Download, Eraser, RotateCcw } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
  : '-'

const condicionLabel = (value) => (value === 'nuevo' ? 'Nuevo' : 'Usado')

export default function TechEquipmentDeliveryShow({ act }) {
  const [returnOpen, setReturnOpen] = useState(false)
  const [signatureError, setSignatureError] = useState('')
  const { data, setData, post, processing, errors, clearErrors } = useForm({
    returned_at: getLocalDateTimeInputValue(),
    return_observations: '',
    return_signature_data_url: '',
  })

  const isReturned = Boolean(act.returned_at)

  const submitReturn = (event) => {
    event.preventDefault()
    if (!data.return_signature_data_url) {
      setSignatureError('La firma de devolución es obligatoria.')
      return
    }
    setSignatureError('')
    clearErrors()
    post(route('inventory.tech-equipment-deliveries.return', act.id), {
      preserveScroll: true,
      onSuccess: () => setReturnOpen(false),
    })
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4 print:hidden">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Acta {act.codigo}</h2>
          <div className="flex gap-2">
            <Link href={route('inventory.tech-equipment-deliveries.index')}>
              <Button variant="outline" type="button">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Volver
              </Button>
            </Link>
            <a href={route('inventory.tech-equipment-deliveries.pdf', act.id)} target="_blank" rel="noopener noreferrer">
              <Button type="button">
                <Download className="w-4 h-4 mr-2" />
                Ver PDF
              </Button>
            </a>
            {!isReturned && (
              <Button type="button" onClick={() => setReturnOpen(true)}>
                <RotateCcw className="w-4 h-4 mr-2" />
                Registrar devolución
              </Button>
            )}
          </div>
        </div>
      }
    >
      <Head title={`Acta ${act.codigo}`} />

      <div className="py-10 print:py-0">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8 print:max-w-none print:px-0">
          <Card className="print:border-0 print:shadow-none">
            <CardContent className="p-8 print:p-0">
              <div className="space-y-8 text-slate-900">
                <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="text-sm uppercase tracking-wide text-slate-500">Acta de Entrega de Equipos Tecnológicos</p>
                    <h1 className="text-3xl font-semibold">{act.codigo}</h1>
                  </div>
                  <div className="text-sm text-slate-600 sm:text-right">
                    <div>{formatDate(act.delivered_at)}</div>
                    <div className="mt-2">
                      Condición: <Badge variant="outline">{condicionLabel(act.condicion)}</Badge>
                    </div>
                    {isReturned && (
                      <div className="mt-2">
                        Devolución: <Badge>{formatDate(act.returned_at)}</Badge>
                      </div>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                  <Info label="Persona que recibe" value={act.person_name} />
                  <Info label="RUT" value={act.person_rut || '-'} />
                  <Info label="Departamento" value={act.departamento || '-'} />
                  <Info label="Cargo" value={act.cargo || '-'} />
                  <Info label="Entregado por" value={act.creator?.name || '-'} />
                </div>

                <div className="overflow-hidden rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Marca</TableHead>
                        <TableHead>N° de Serie</TableHead>
                        <TableHead>Fecha</TableHead>
                        <TableHead>Características</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {act.items.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell className="font-medium">{item.equipment?.marca || '-'}</TableCell>
                          <TableCell className="font-mono">{item.equipment?.numero_serie || '-'}</TableCell>
                          <TableCell>{item.equipment?.fecha || '-'}</TableCell>
                          <TableCell>{item.equipment?.descripcion || '-'}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                {act.observations && (
                  <div className="rounded-md border bg-slate-50 p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Observaciones</p>
                    <p className="mt-1 whitespace-pre-wrap">{act.observations}</p>
                  </div>
                )}

                {isReturned && act.return_observations && (
                  <div className="rounded-md border border-greenex-vibrant-green bg-green-50 p-4">
                    <p className="text-xs uppercase tracking-wide text-green-700">Observaciones de devolución</p>
                    <p className="mt-1 whitespace-pre-wrap">{act.return_observations}</p>
                  </div>
                )}

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  <div className="rounded-md border p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Firma receptor</p>
                    <div className="mt-3 flex h-40 items-center justify-center border-b">
                      {act.signature_data_url ? (
                        <img src={act.signature_data_url} alt="Firma receptor" className="max-h-36 max-w-full object-contain" />
                      ) : (
                        <span className="text-sm text-slate-400">Sin firma</span>
                      )}
                    </div>
                    <p className="mt-3 text-center text-sm font-medium">{act.person_name}</p>
                  </div>

                  <div className="rounded-md border p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Firma devolución</p>
                    <div className="mt-3 flex h-40 items-center justify-center border-b">
                      {act.return_signature_data_url ? (
                        <img src={act.return_signature_data_url} alt="Firma devolución" className="max-h-36 max-w-full object-contain" />
                      ) : (
                        <span className="text-sm text-slate-400">{isReturned ? 'Sin imagen' : 'Pendiente'}</span>
                      )}
                    </div>
                    <p className="mt-3 text-center text-sm font-medium">
                      {isReturned ? act.person_name : 'Pendiente de devolución'}
                    </p>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={returnOpen} onOpenChange={setReturnOpen}>
        <DialogContent className="sm:max-w-lg">
          <form onSubmit={submitReturn} className="space-y-5">
            <DialogHeader>
              <DialogTitle>Registrar devolución de equipos</DialogTitle>
              <DialogDescription>
                Se registrará la devolución y se actualizará el historial del equipo.
              </DialogDescription>
            </DialogHeader>

            <div className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="returned_at">Fecha de devolución</Label>
                <Input
                  id="returned_at"
                  type="datetime-local"
                  value={data.returned_at}
                  onChange={(event) => setData('returned_at', event.target.value)}
                />
                {errors.returned_at && <p className="text-sm text-red-600">{errors.returned_at}</p>}
              </div>

              <div className="space-y-2">
                <Label htmlFor="return_observations">Observaciones de la devolución</Label>
                <Textarea
                  id="return_observations"
                  value={data.return_observations}
                  onChange={(event) => setData('return_observations', event.target.value)}
                  placeholder="Estado en que se recibe, novedades, etc."
                />
                {errors.return_observations && <p className="text-sm text-red-600">{errors.return_observations}</p>}
              </div>

              <div className="space-y-2">
                <Label>Firma de devolución</Label>
                <SignaturePad
                  value={data.return_signature_data_url}
                  onChange={(value) => {
                    setData('return_signature_data_url', value)
                    setSignatureError('')
                  }}
                />
                {(signatureError || errors.return_signature_data_url) && (
                  <p className="text-sm text-red-600">{signatureError || errors.return_signature_data_url}</p>
                )}
              </div>
            </div>

            <DialogFooter className="gap-2">
              <Button type="button" variant="outline" onClick={() => setReturnOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>
                <RotateCcw className="w-4 h-4 mr-2" />
                Confirmar devolución
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}

function Info({ label, value }) {
  return (
    <div className="rounded-md border p-4">
      <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 text-lg font-medium">{value}</p>
    </div>
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
    canvas.height = 160 * ratio
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
          className="block h-[160px] w-full touch-none"
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
