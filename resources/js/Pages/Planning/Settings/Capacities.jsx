import React, { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

export default function Capacities({ capacities = [], lines = [], shifts = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    packing_line_id: lines?.[0]?.id ? String(lines[0].id) : '',
    especie: lines?.[0]?.especie ?? '',
    bins_por_hora: '',
    shift_id: '',
    vigencia_desde: new Date().toISOString().slice(0, 10),
    vigencia_hasta: '',
    activo: true,
  })

  const startCreate = () => {
    setEditing(null)
    reset()
    setData({
      packing_line_id: lines?.[0]?.id ? String(lines[0].id) : '',
      especie: lines?.[0]?.especie ?? '',
      bins_por_hora: '',
      shift_id: '',
      vigencia_desde: new Date().toISOString().slice(0, 10),
      vigencia_hasta: '',
      activo: true,
    })
  }

  const startEdit = (cap) => {
    setEditing(cap)
    setData({
      packing_line_id: String(cap.packing_line_id ?? cap.packingLine?.id ?? ''),
      especie: cap.especie ?? '',
      bins_por_hora: cap.bins_por_hora ?? '',
      shift_id: cap.shift_id ? String(cap.shift_id) : '',
      vigencia_desde: cap.vigencia_desde ?? '',
      vigencia_hasta: cap.vigencia_hasta ?? '',
      activo: Boolean(cap.activo),
    })
  }

  const onLineChange = (lineId) => {
    const line = (lines || []).find((l) => String(l.id) === String(lineId))
    setData('packing_line_id', lineId)
    if (line?.especie) setData('especie', line.especie)
  }

  const submit = (e) => {
    e.preventDefault()
    if (editing?.id) {
      patch(route('planning.settings.capacities.update', editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) })
      return
    }
    post(route('planning.settings.capacities.store'), { preserveScroll: true, onSuccess: () => startCreate() })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Configuración · Capacidades</CardTitle>
          <Button variant="outline" onClick={startCreate}>Nuevo</Button>
        </CardHeader>
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
            </div>
          )}
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          )}

          <form onSubmit={submit} className="rounded border p-4 bg-gray-50 mb-4">
            <div className="grid grid-cols-1 md:grid-cols-6 gap-3">
              <div className="md:col-span-2">
                <Label>Línea/Cámara</Label>
                <select className="mt-1 w-full rounded border px-2 py-2 text-sm" value={data.packing_line_id} onChange={(e) => onLineChange(e.target.value)}>
                  {(lines || []).map((l) => (
                    <option key={l.id} value={String(l.id)}>{l.nombre}</option>
                  ))}
                </select>
                {errors.packing_line_id && <div className="text-sm text-red-600 mt-1">{errors.packing_line_id}</div>}
              </div>
              <div className="md:col-span-1">
                <Label>Especie</Label>
                <Input value={data.especie} onChange={(e) => setData('especie', e.target.value)} />
                {errors.especie && <div className="text-sm text-red-600 mt-1">{errors.especie}</div>}
              </div>
              <div className="md:col-span-1">
                <Label>Bins/hora</Label>
                <Input type="number" step="0.01" value={data.bins_por_hora} onChange={(e) => setData('bins_por_hora', e.target.value)} />
                {errors.bins_por_hora && <div className="text-sm text-red-600 mt-1">{errors.bins_por_hora}</div>}
              </div>
              <div className="md:col-span-1">
                <Label>Turno (opcional)</Label>
                <select className="mt-1 w-full rounded border px-2 py-2 text-sm" value={data.shift_id} onChange={(e) => setData('shift_id', e.target.value)}>
                  <option value="">(Todos)</option>
                  {(shifts || []).map((s) => (
                    <option key={s.id} value={String(s.id)}>{s.codigo} · {s.nombre}</option>
                  ))}
                </select>
                {errors.shift_id && <div className="text-sm text-red-600 mt-1">{errors.shift_id}</div>}
              </div>
              <div className="md:col-span-1">
                <Label>Desde</Label>
                <Input type="date" value={data.vigencia_desde} onChange={(e) => setData('vigencia_desde', e.target.value)} />
                {errors.vigencia_desde && <div className="text-sm text-red-600 mt-1">{errors.vigencia_desde}</div>}
              </div>
              <div className="md:col-span-1">
                <Label>Hasta</Label>
                <Input type="date" value={data.vigencia_hasta} onChange={(e) => setData('vigencia_hasta', e.target.value)} />
                {errors.vigencia_hasta && <div className="text-sm text-red-600 mt-1">{errors.vigencia_hasta}</div>}
              </div>
            </div>

            <div className="flex items-center justify-between mt-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={Boolean(data.activo)} onChange={(e) => setData('activo', e.target.checked)} />
                Activo
              </label>
              <div className="flex items-center gap-2">
                {editing ? (
                  <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>
                ) : null}
                <Button type="submit" disabled={processing}>
                  {processing ? 'Guardando...' : editing ? 'Actualizar' : 'Crear'}
                </Button>
              </div>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Línea/Cámara</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Bins/h</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Vigencia</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(capacities || []).map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-medium">
                    {c.packingLine?.nombre ?? c.packing_line?.nombre ?? `#${c.packing_line_id}`}
                  </TableCell>
                  <TableCell>{c.especie}</TableCell>
                  <TableCell>{c.bins_por_hora}</TableCell>
                  <TableCell>{c.shift ? `${c.shift.codigo} · ${c.shift.nombre}` : '(Todos)'}</TableCell>
                  <TableCell>
                    <div className="text-xs text-gray-700">{c.vigencia_desde} → {c.vigencia_hasta ?? '∞'}</div>
                  </TableCell>
                  <TableCell>
                    {c.activo ? (
                      <Badge className="bg-green-50 text-green-800 border border-green-200">Sí</Badge>
                    ) : (
                      <Badge variant="outline" className="text-gray-600">No</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" onClick={() => startEdit(c)}>Editar</Button>
                  </TableCell>
                </TableRow>
              ))}
              {(!capacities || capacities.length === 0) && (
                <TableRow>
                  <TableCell colSpan={7} className="py-10 text-center text-sm text-gray-600">
                    No hay capacidades configuradas.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

Capacities.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Configuración</h2>}
  />
)
