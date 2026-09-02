import { useForm } from '@inertiajs/react'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
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
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/Components/ui/dialog'
import { toast } from 'sonner'

const DIMENSIONES = ['banda', 'fila', 'columna', 'altura', 'nivel']
const ROW_DIMENSION_BY_BAND = {
  Izquierda: 'fila_izquierda',
  'Central-Izq': 'fila_central_izq',
  'Central-Dcha': 'fila_central_dcha',
  Derecha: 'fila_derecha',
}

const ahoraLocal = () => {
  const d = new Date()
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

export default function SalidaDialog({ load, camaras, onClose }) {
  const form = useForm({
    camara_id: '',
    fecha_hora_fin: ahoraLocal(),
    temperatura_ambiente_tunel_salida: '',
    temperatura_ambiente_camara_salida: '',
    folios_seleccionados: Object.fromEntries(load.folios.map((folio) => [folio.id, true])),
    temperaturas_folios: Object.fromEntries(load.folios.map((folio) => [folio.id, {
      temperatura_final_interna: folio.temperatura_final_interna || '',
      temperatura_final_externa: folio.temperatura_final_externa || '',
    }])),
    ubicaciones: {},
  })

  const camara = camaras.find((c) => String(c.id) === String(form.data.camara_id))
  const parametros = camara?.parametros || { banda: [], fila: [], columna: [], altura: [], nivel: [] }

  const handleCamaraChange = (value) => {
    form.setData({ ...form.data, camara_id: value, ubicaciones: {} })
  }

  const toggleFolio = (folioId, checked) => {
    form.setData('folios_seleccionados', {
      ...form.data.folios_seleccionados,
      [folioId]: checked,
    })
  }

  const setUbicacion = (folioId, dimension, value) => {
    form.setData(`ubicaciones.${folioId}`, {
      ...(form.data.ubicaciones[folioId] || {}),
      [dimension]: value,
      ...(dimension === 'banda' ? { fila: '' } : {}),
    })
  }

  const setTemperaturaFolio = (folioId, campo, value) => {
    form.setData('temperaturas_folios', {
      ...form.data.temperaturas_folios,
      [folioId]: {
        ...(form.data.temperaturas_folios[folioId] || {}),
        [campo]: value,
      },
    })
  }

  const optionsForDimension = (dimension, ubicacion) => {
    if (dimension !== 'fila') return parametros[dimension] || []
    return parametros[ROW_DIMENSION_BY_BAND[ubicacion.banda]] || []
  }

  const submit = (e) => {
    e.preventDefault()
    const seleccionados = load.folios.filter((folio) => form.data.folios_seleccionados[folio.id])

    form.transform((data) => ({
      ...data,
      ubicaciones: Object.fromEntries(seleccionados.map((folio) => [folio.id, data.ubicaciones[folio.id] || {}])),
      temperaturas_folios: Object.fromEntries(seleccionados.map((folio) => [folio.id, data.temperaturas_folios[folio.id] || {}])),
    }))
    form.post(route('prefrio.loads.salir', load.id), {
      onSuccess: () => {
        toast.success('Salida parcial registrada')
        onClose()
      },
    })
  }

  const cantidadSeleccionada = load.folios.filter((folio) => form.data.folios_seleccionados[folio.id]).length

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Salida del túnel · {load.tipo_proceso}</DialogTitle>
          <DialogDescription>
            Seleccione los folios que saldrán ahora y asigne su ubicación en la cámara. El proceso seguirá abierto mientras queden folios en el túnel.
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={submit} className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <Label className="mb-1 block">Cámara de destino</Label>
              <Select value={form.data.camara_id} onValueChange={handleCamaraChange}>
                <SelectTrigger>
                  <SelectValue placeholder="Seleccione cámara" />
                </SelectTrigger>
                <SelectContent>
                  {camaras.map((c) => (
                    <SelectItem key={c.id} value={String(c.id)}>
                      {c.codigo} · {c.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {form.errors.camara_id && <p className="text-red-500 text-xs mt-1">{form.errors.camara_id}</p>}
            </div>
            <div>
              <Label className="mb-1 block">Fecha y hora de término</Label>
              <Input
                type="datetime-local"
                value={form.data.fecha_hora_fin}
                onChange={(e) => form.setData('fecha_hora_fin', e.target.value)}
                required
              />
              {form.errors.fecha_hora_fin && (
                <p className="text-red-500 text-xs mt-1">{form.errors.fecha_hora_fin}</p>
              )}
            </div>
          </div>

          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div>
              <Label className="mb-1 block">Temperatura ambiente del túnel (°C)</Label>
              <Input
                type="number"
                step="0.01"
                value={form.data.temperatura_ambiente_tunel_salida}
                onChange={(e) => form.setData('temperatura_ambiente_tunel_salida', e.target.value)}
              />
              {form.errors.temperatura_ambiente_tunel_salida && (
                <p className="mt-1 text-xs text-red-500">{form.errors.temperatura_ambiente_tunel_salida}</p>
              )}
            </div>
            <div>
              <Label className="mb-1 block">Temperatura ambiente de la cámara (°C)</Label>
              <Input
                type="number"
                step="0.01"
                value={form.data.temperatura_ambiente_camara_salida}
                onChange={(e) => form.setData('temperatura_ambiente_camara_salida', e.target.value)}
              />
              {form.errors.temperatura_ambiente_camara_salida && (
                <p className="mt-1 text-xs text-red-500">{form.errors.temperatura_ambiente_camara_salida}</p>
              )}
            </div>
          </div>

          {form.errors.estado && <p className="text-red-500 text-xs">{form.errors.estado}</p>}
          {form.errors.folio && <p className="text-red-500 text-xs">{form.errors.folio}</p>}
          {form.errors.ubicaciones && <p className="text-red-500 text-xs">{form.errors.ubicaciones}</p>}
          {form.errors.folios && <p className="text-red-500 text-xs">{form.errors.folios}</p>}

          {camara ? (
            <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
              {load.folios.map((f) => {
                const ubic = form.data.ubicaciones[f.id] || {}
                const seleccionado = Boolean(form.data.folios_seleccionados[f.id])
                return (
                  <div key={f.id} className="rounded-md border p-3">
                    <div className="flex items-center justify-between mb-2">
                      <label className="flex cursor-pointer items-center gap-2">
                        <input
                          type="checkbox"
                          checked={seleccionado}
                          onChange={(e) => toggleFolio(f.id, e.target.checked)}
                          className="h-4 w-4 rounded border-gray-300"
                        />
                        <span className="font-mono text-sm font-semibold">{f.folio}</span>
                      </label>
                      <Badge variant={seleccionado ? 'default' : 'secondary'}>
                        {seleccionado ? 'Sale ahora' : 'Permanece'}
                      </Badge>
                    </div>
                    {seleccionado && (
                      <>
                    <div className="grid grid-cols-2 sm:grid-cols-5 gap-2">
                      {DIMENSIONES.map((dim) => (
                        <div key={dim}>
                          <Label className="text-xs capitalize">{dim}</Label>
                          <Select value={ubic[dim] || ''} onValueChange={(v) => setUbicacion(f.id, dim, v)}>
                            <SelectTrigger>
                              <SelectValue placeholder="—" />
                            </SelectTrigger>
                            <SelectContent>
                              {optionsForDimension(dim, ubic).map((val) => (
                                <SelectItem key={val} value={val}>
                                  {val}
                                </SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        </div>
                      ))}
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 border-t pt-3">
                      <div>
                        <Label className="text-xs">T° final interna (°C)</Label>
                        <Input
                          type="number"
                          step="0.01"
                          value={form.data.temperaturas_folios[f.id]?.temperatura_final_interna || ''}
                          onChange={(e) => setTemperaturaFolio(f.id, 'temperatura_final_interna', e.target.value)}
                        />
                        {form.errors[`temperaturas_folios.${f.id}.temperatura_final_interna`] && (
                          <p className="mt-1 text-xs text-red-500">
                            {form.errors[`temperaturas_folios.${f.id}.temperatura_final_interna`]}
                          </p>
                        )}
                      </div>
                      <div>
                        <Label className="text-xs">T° final externa (°C)</Label>
                        <Input
                          type="number"
                          step="0.01"
                          value={form.data.temperaturas_folios[f.id]?.temperatura_final_externa || ''}
                          onChange={(e) => setTemperaturaFolio(f.id, 'temperatura_final_externa', e.target.value)}
                        />
                        {form.errors[`temperaturas_folios.${f.id}.temperatura_final_externa`] && (
                          <p className="mt-1 text-xs text-red-500">
                            {form.errors[`temperaturas_folios.${f.id}.temperatura_final_externa`]}
                          </p>
                        )}
                      </div>
                    </div>
                    {form.errors[`ubicaciones.${f.id}`] && (
                      <p className="text-red-500 text-xs mt-1">{form.errors[`ubicaciones.${f.id}`]}</p>
                    )}
                      </>
                    )}
                  </div>
                )
              })}
            </div>
          ) : (
            <p className="text-sm text-gray-500">Seleccione una cámara para asignar las ubicaciones.</p>
          )}

          <DialogFooter>
            <Button type="button" variant="secondary" onClick={onClose}>
              Cancelar
            </Button>
            <Button type="submit" disabled={form.processing || !camara || cantidadSeleccionada === 0}>
              Registrar salida ({cantidadSeleccionada})
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
