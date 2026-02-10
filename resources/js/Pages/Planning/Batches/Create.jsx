import React, { useEffect, useMemo, useState } from 'react'
import { Link, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import axios from 'axios'

function nextMondayFrom(dateStr) {
  const base = dateStr ? new Date(`${dateStr}T00:00:00`) : new Date()
  const d = new Date(base.getFullYear(), base.getMonth(), base.getDate())
  const day = d.getDay() // 0..6 (0 = domingo)
  const isoDay = ((day + 6) % 7) + 1 // 1..7 (1 = lunes)
  const add = 8 - isoDay // lunes siguiente (si hoy es lunes => +7)
  d.setDate(d.getDate() + add)
  return d.toISOString().slice(0, 10)
}

export default function Create({ especies = [], shifts = [], lines = [], defaults = {}, estimationSpecies: initialEstimationSpecies = [] }) {
  const { props } = usePage()
  const today = useMemo(() => new Date().toISOString().slice(0, 10), [])
  const [estimationSpecies, setEstimationSpecies] = useState(Array.isArray(initialEstimationSpecies) ? initialEstimationSpecies : [])

  const initialEspecie = String(defaults?.especie || '__ALL__')
  const initialWeekStart = String(defaults?.week_start || nextMondayFrom(today))
  const initialShiftId = defaults?.shift_id ? String(defaults.shift_id) : (shifts?.[0]?.id ? String(shifts[0].id) : '')

  const { data, setData, post, processing, errors } = useForm({
    especie: initialEspecie,
    week_start: initialWeekStart,
    days: 7,
    shift_id: initialShiftId,
    included_packing_line_ids: [],
    auto_generate: true,
  })

  // Refresca qué especies tienen estimación para el rango elegido (para autoseleccionar líneas).
  useEffect(() => {
    const weekStart = String(data.week_start || '').trim()
    const days = Number(data.days || 7)
    if (!weekStart) return

    const t = setTimeout(async () => {
      try {
        const res = await axios.get(route('planning.batches.estimation-species'), {
          params: { week_start: weekStart, days: Number.isFinite(days) ? days : 7 },
        })
        const list = res?.data?.data
        setEstimationSpecies(Array.isArray(list) ? list.map(String) : [])
      } catch (e) {
        setEstimationSpecies([])
      }
    }, 250)
    return () => clearTimeout(t)
  }, [data.week_start, data.days])

  const linesForSpecies = useMemo(() => {
    const especie = String(data.especie || '')
    if (!especie || especie === '__ALL__') return (lines || [])
      .filter((l) => {
        if (!Array.isArray(estimationSpecies) || estimationSpecies.length === 0) return true
        const legacy = String(l.especie || '')
        const list = Array.isArray(l.especies) ? l.especies.map(String) : []
        return estimationSpecies.includes(legacy) || list.some((s) => estimationSpecies.includes(String(s)))
      })
    return (lines || []).filter((l) => {
      const legacy = String(l.especie || '')
      const list = Array.isArray(l.especies) ? l.especies.map(String) : []
      return legacy === especie || list.includes(especie)
    })
  }, [lines, data.especie, estimationSpecies])

  useEffect(() => {
    const all = linesForSpecies.map((l) => l.id)
    setData('included_packing_line_ids', all)
  }, [data.especie, estimationSpecies])

  const toggleLine = (lineId) => {
    const current = new Set(data.included_packing_line_ids || [])
    if (current.has(lineId)) current.delete(lineId)
    else current.add(lineId)
    setData('included_packing_line_ids', Array.from(current))
  }

  const submit = (e) => {
    e.preventDefault()
    post(route('planning.batches.store'))
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Nueva planificación semanal</CardTitle>
          <Link href={route('planning.batches.index')}>
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
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <Label>Especie</Label>
                <Select value={String(data.especie || '')} onValueChange={(v) => setData('especie', v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="(Todas)" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="__ALL__">(Todas)</SelectItem>
                    {(especies || []).map((e) => (
                      <SelectItem
                        key={e}
                        value={String(e)}
                        disabled={Array.isArray(estimationSpecies) && estimationSpecies.length > 0 ? !estimationSpecies.includes(String(e)) : false}
                      >
                        {String(e)}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {errors.especie && <div className="text-sm text-red-600 mt-1">{errors.especie}</div>}
                {Array.isArray(estimationSpecies) && estimationSpecies.length > 0 && data.especie !== '__ALL__' && !estimationSpecies.includes(String(data.especie)) ? (
                  <div className="text-xs text-amber-800 mt-1">
                    Esta especie no tiene estimación en el rango seleccionado, por eso no se considera en el batch.
                  </div>
                ) : null}
              </div>

              <div>
                <Label>Inicio semana</Label>
                <Input type="date" value={data.week_start} onChange={(e) => setData('week_start', e.target.value)} />
                <div className="text-xs text-gray-500 mt-1">Tip: por defecto el lunes de la semana siguiente.</div>
                {errors.week_start && <div className="text-sm text-red-600 mt-1">{errors.week_start}</div>}
              </div>

              <div>
                <Label>Días</Label>
                <Input type="number" min="1" max="14" value={data.days} onChange={(e) => setData('days', e.target.value)} />
                {errors.days && <div className="text-sm text-red-600 mt-1">{errors.days}</div>}
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

            <div className="rounded border bg-white">
              <div className="px-4 py-3 border-b bg-gray-50">
                <div className="font-semibold">Líneas/Cámaras incluidas</div>
                <div className="text-sm text-gray-600">
                  Por defecto se incluyen solo líneas/cámaras de especies con estimación en el rango seleccionado.
                </div>
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
                        <input type="checkbox" checked={checked} onChange={() => toggleLine(l.id)} />
                        <div className="flex-1">
                          <div className="font-medium">{l.nombre}</div>
                          <div className="text-xs text-gray-500">
                            {l.tipo}
                            {l.especie ? ` · ${l.especie}` : ''}
                          </div>
                        </div>
                      </label>
                    )
                  })
                )}
              </div>
            </div>

            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={Boolean(data.auto_generate)}
                onChange={(e) => setData('auto_generate', e.target.checked)}
              />
              Generar propuesta automáticamente (recomendado)
            </label>

            <div className="flex items-center justify-end gap-2">
              <Button type="submit" disabled={processing}>
                {processing ? 'Creando...' : 'Crear semana'}
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
