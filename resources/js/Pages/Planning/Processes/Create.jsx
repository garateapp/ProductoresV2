import React, { useEffect, useMemo } from 'react'
import { Link, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Textarea } from '@/Components/ui/textarea'

export default function Create({ especies = [], shifts = [], lines = [], defaults = {} }) {
  const { props } = usePage()
  const today = useMemo(() => new Date().toISOString().slice(0, 10), [])

  const initialEspecie = String(defaults?.especie || especies?.[0] || '')
  const initialFecha = String(defaults?.fecha || today)
  const initialShiftId = defaults?.shift_id ? String(defaults.shift_id) : (shifts?.[0]?.id ? String(shifts[0].id) : '')

  const { data, setData, post, processing, errors } = useForm({
    especie: initialEspecie,
    fecha: initialFecha,
    shift_id: initialShiftId,
    included_packing_line_ids: [],
    pedidos: '',
  })

  const linesForSpecies = useMemo(() => {
    const especie = String(data.especie || '')
    return (lines || []).filter((l) => {
      const legacy = String(l.especie || '')
      const list = Array.isArray(l.especies) ? l.especies.map(String) : []
      return legacy === especie || list.includes(especie)
    })
  }, [lines, data.especie])

  useEffect(() => {
    // Default: incluir todas las líneas de la especie
    const all = linesForSpecies.map((l) => l.id)
    setData('included_packing_line_ids', all)
  }, [data.especie])

  const submit = (e) => {
    e.preventDefault()
    post(route('planning.processes.store'))
  }

  const toggleLine = (lineId) => {
    const current = new Set(data.included_packing_line_ids || [])
    if (current.has(lineId)) current.delete(lineId)
    else current.add(lineId)
    setData('included_packing_line_ids', Array.from(current))
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Nuevo proceso</CardTitle>
          <Link href={route('planning.processes.index')}>
            <Button variant="outline">Volver</Button>
          </Link>
        </CardHeader>
        <CardContent>
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          )}

          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label>Especie</Label>
                <Select value={String(data.especie || '')} onValueChange={(v) => setData('especie', v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecciona especie" />
                  </SelectTrigger>
                  <SelectContent>
                    {(especies || []).map((e) => (
                      <SelectItem key={e} value={String(e)}>{String(e)}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.especie && <div className="text-sm text-red-600 mt-1">{errors.especie}</div>}
              </div>

              <div>
                <Label>Fecha</Label>
                <Input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} />
                {errors.fecha && <div className="text-sm text-red-600 mt-1">{errors.fecha}</div>}
              </div>

              <div>
                <Label>Turno</Label>
                <Select value={String(data.shift_id || '')} onValueChange={(v) => setData('shift_id', v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Selecciona turno" />
                  </SelectTrigger>
                  <SelectContent>
                    {(shifts || []).map((s) => (
                      <SelectItem key={s.id} value={String(s.id)}>
                        {s.codigo} · {s.nombre} ({s.horas}h)
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.shift_id && <div className="text-sm text-red-600 mt-1">{errors.shift_id}</div>}
              </div>
            </div>

            <div>
              <Label>Pedidos (opcional)</Label>
              <Textarea
                value={data.pedidos || ''}
                onChange={(e) => setData('pedidos', e.target.value)}
                placeholder="Ej: pedido particular, cliente, prioridad, observación operativa..."
              />
              {errors.pedidos && <div className="text-sm text-red-600 mt-1">{errors.pedidos}</div>}
            </div>

            <div className="rounded border bg-white">
              <div className="px-4 py-3 border-b bg-gray-50">
                <div className="font-semibold">Líneas/Cámaras incluidas</div>
                <div className="text-sm text-gray-600">Por defecto se incluyen todas. Desmarca si no aplica.</div>
              </div>
              <div className="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                {linesForSpecies.length === 0 ? (
                  <div className="text-sm text-gray-600">
                    No hay líneas configuradas para esta especie. Ve a Configuración → Líneas/Cámaras.
                  </div>
                ) : (
                  linesForSpecies.map((l) => {
                    const checked = (data.included_packing_line_ids || []).includes(l.id)
                    return (
                      <label key={l.id} className="flex items-center gap-3 rounded border px-3 py-2 cursor-pointer hover:bg-gray-50">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => toggleLine(l.id)}
                        />
                        <div className="flex-1">
                          <div className="font-medium">{l.nombre}</div>
                          <div className="text-xs text-gray-500">{l.tipo}</div>
                        </div>
                      </label>
                    )
                  })
                )}
              </div>
            </div>

            <div className="flex items-center justify-end gap-2">
              <Button type="submit" disabled={processing}>
                {processing ? 'Creando...' : 'Crear'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

Create.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
