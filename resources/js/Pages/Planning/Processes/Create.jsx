import React, { useEffect, useMemo, useRef } from 'react'
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

  const initialEspecies = useMemo(() => {
    const fromDefaults = Array.isArray(defaults?.especies)
      ? defaults.especies.map(String).filter(Boolean)
      : []

    if (fromDefaults.length > 0) {
      return Array.from(new Set(fromDefaults))
    }

    if (defaults?.especie) {
      return [String(defaults.especie)]
    }

    return especies?.[0] ? [String(especies[0])] : []
  }, [defaults, especies])

  const initialEspecie = String(initialEspecies?.[0] || defaults?.especie || especies?.[0] || '')
  const initialPlanningMode = String(defaults?.planning_mode || 'normal')
  const initialFecha = String(defaults?.fecha || today)
  const initialShiftId = defaults?.shift_id ? String(defaults.shift_id) : (shifts?.[0]?.id ? String(shifts[0].id) : '')

  const { data, setData, post, processing, errors } = useForm({
    especies: initialEspecies,
    especie: initialEspecie,
    planning_mode: initialPlanningMode,
    fecha: initialFecha,
    shift_id: initialShiftId,
    included_packing_line_ids: [],
    pedidos: '',
  })

  const lastAutoSpeciesKeyRef = useRef(null)

  const speciesItemError = useMemo(() => {
    const entries = Object.entries(errors || {})
    const row = entries.find(([key]) => key.startsWith('especies.'))
    return row ? row[1] : null
  }, [errors])

  const linesForSpecies = useMemo(() => {
    const selected = Array.isArray(data.especies) ? data.especies.map(String).filter(Boolean) : []
    if (selected.length === 0) return []

    return (lines || []).filter((l) => {
      const legacy = String(l.especie || '')
      const list = Array.isArray(l.especies) ? l.especies.map(String) : []
      const allSpecies = [legacy, ...list].filter(Boolean)
      return selected.some((especie) => allSpecies.includes(especie))
    })
  }, [lines, data.especies])

  const selectedSpeciesKey = useMemo(() => {
    return (Array.isArray(data.especies) ? data.especies : [])
      .map(String)
      .filter(Boolean)
      .sort()
      .join('|')
  }, [data.especies])

  useEffect(() => {
    // Solo auto-selecciona líneas cuando cambia la selección de especies.
    if (lastAutoSpeciesKeyRef.current === selectedSpeciesKey) return
    const all = linesForSpecies.map((l) => l.id)
    setData('included_packing_line_ids', all)
    lastAutoSpeciesKeyRef.current = selectedSpeciesKey
  }, [selectedSpeciesKey, linesForSpecies, setData])

  const submit = (e) => {
    e.preventDefault()
    post(route('planning.processes.store'))
  }

  const toggleSpecies = (especie) => {
    const current = new Set((data.especies || []).map(String))
    if (current.has(especie)) current.delete(especie)
    else current.add(especie)

    const next = Array.from(current)
    setData('especies', next)
    setData('especie', next[0] || '')
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
            <div className="rounded border bg-white">
              <div className="px-4 py-3 border-b bg-gray-50">
                <div className="font-semibold">Especies</div>
                <div className="text-sm text-gray-600">Selecciona una o más especies para crear procesos separados.</div>
              </div>
              <div className="p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                {(especies || []).map((especie) => {
                  const checked = (data.especies || []).includes(especie)
                  return (
                    <label key={especie} className="flex items-center gap-3 rounded border px-3 py-2 cursor-pointer hover:bg-gray-50">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={() => toggleSpecies(especie)}
                      />
                      <span className="font-medium">{especie}</span>
                    </label>
                  )
                })}
              </div>
              {errors.especies && <div className="px-4 pb-3 text-sm text-red-600">{errors.especies}</div>}
              {!errors.especies && speciesItemError && <div className="px-4 pb-3 text-sm text-red-600">{speciesItemError}</div>}
            </div>

            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div>
                <Label>Tipo</Label>
                <Select value={String(data.planning_mode || 'normal')} onValueChange={(v) => setData('planning_mode', String(v || 'normal'))}>
                  <SelectTrigger>
                    <SelectValue placeholder="Tipo de planificación" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="normal">Normal</SelectItem>
                    <SelectItem value="reembalaje">Reembalaje</SelectItem>
                  </SelectContent>
                </Select>
                {errors.planning_mode && <div className="text-sm text-red-600 mt-1">{errors.planning_mode}</div>}
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
                {(data.especies || []).length === 0 ? (
                  <div className="text-sm text-gray-600">
                    Selecciona al menos una especie para habilitar líneas/cámaras.
                  </div>
                ) : linesForSpecies.length === 0 ? (
                  <div className="text-sm text-gray-600">
                    No hay líneas configuradas para las especies seleccionadas. Ve a Configuración → Líneas/Cámaras.
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
              {errors.included_packing_line_ids && <div className="px-4 pb-3 text-sm text-red-600">{errors.included_packing_line_ids}</div>}
            </div>

            <div className="flex items-center justify-end gap-2">
              <Button type="submit" disabled={processing}>
                {processing ? 'Creando...' : ((data.especies || []).length > 1 ? 'Crear procesos' : 'Crear')}
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
