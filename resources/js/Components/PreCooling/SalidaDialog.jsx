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
    temperatura_ambiente_final: load.temperatura_ambiente_final || '',
    temperaturas_folios: Object.fromEntries(load.folios.map((folio) => [folio.id, {
      temperatura_final_interna: folio.temperatura_final_interna || '',
      temperatura_final_externa: folio.temperatura_final_externa || '',
    }])),
    ubicaciones: {},
  })

  const camara = camaras.find((c) => String(c.id) === String(form.data.camara_id))
  const parametros = camara?.parametros || { banda: [], fila: [], columna: [], altura: [], nivel: [] }

  const handleCamaraChange = (value) => {
    form.setData({ camara_id: value, ubicaciones: {} })
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
    form.post(route('prefrio.loads.salir', load.id), {
      onSuccess: () => {
        toast.success('Carga marcada como salida del túnel')
        onClose()
      },
    })
  }

  return (
    <Dialog open onOpenChange={(open) => !open && onClose()}>
      <DialogContent className="max-w-3xl">
        <DialogHeader>
          <DialogTitle>Salida del túnel · {load.tipo_proceso}</DialogTitle>
          <DialogDescription>
            Asigne a cada folio su ubicación en la cámara de destino. La salida aplica a toda la carga del túnel. Esta acción es irreversible.
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

          <div>
            <Label className="mb-1 block">Temperatura ambiente al finalizar (°C)</Label>
            <Input
              type="number"
              step="0.01"
              value={form.data.temperatura_ambiente_final}
              onChange={(e) => form.setData('temperatura_ambiente_final', e.target.value)}
            />
            {form.errors.temperatura_ambiente_final && (
              <p className="mt-1 text-xs text-red-500">{form.errors.temperatura_ambiente_final}</p>
            )}
          </div>

          {form.errors.estado && <p className="text-red-500 text-xs">{form.errors.estado}</p>}
          {form.errors.folio && <p className="text-red-500 text-xs">{form.errors.folio}</p>}
          {form.errors.ubicaciones && <p className="text-red-500 text-xs">{form.errors.ubicaciones}</p>}

          {camara ? (
            <div className="space-y-2 max-h-72 overflow-y-auto pr-1">
              {load.folios.map((f) => {
                const ubic = form.data.ubicaciones[f.id] || {}
                return (
                  <div key={f.id} className="rounded-md border p-3">
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-mono text-sm font-semibold">{f.folio}</span>
                      <Badge variant="secondary">Nivel {f.nivel}</Badge>
                    </div>
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
                      </div>
                      <div>
                        <Label className="text-xs">T° final externa (°C)</Label>
                        <Input
                          type="number"
                          step="0.01"
                          value={form.data.temperaturas_folios[f.id]?.temperatura_final_externa || ''}
                          onChange={(e) => setTemperaturaFolio(f.id, 'temperatura_final_externa', e.target.value)}
                        />
                      </div>
                    </div>
                    {form.errors[`ubicaciones.${f.id}`] && (
                      <p className="text-red-500 text-xs mt-1">{form.errors[`ubicaciones.${f.id}`]}</p>
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
            <Button type="submit" disabled={form.processing || !camara}>
              Confirmar salida
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
