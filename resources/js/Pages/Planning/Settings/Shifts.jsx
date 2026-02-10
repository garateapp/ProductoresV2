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

export default function Shifts({ shifts = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    horas: 8,
    hora_inicio: '',
    activo: true,
  })

  const startCreate = () => {
    setEditing(null)
    reset()
    setData({ codigo: '', nombre: '', horas: 8, hora_inicio: '', activo: true })
  }

  const startEdit = (shift) => {
    setEditing(shift)
    setData({
      codigo: shift.codigo ?? '',
      nombre: shift.nombre ?? '',
      horas: shift.horas ?? 8,
      hora_inicio: shift.hora_inicio ? String(shift.hora_inicio).slice(0, 5) : '',
      activo: Boolean(shift.activo),
    })
  }

  const submit = (e) => {
    e.preventDefault()
    if (editing?.id) {
      patch(route('planning.settings.shifts.update', editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) })
      return
    }
    post(route('planning.settings.shifts.store'), { preserveScroll: true, onSuccess: () => startCreate() })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Configuración · Turnos</CardTitle>
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
            <div className="grid grid-cols-1 md:grid-cols-5 gap-3">
              <div>
                <Label>Código</Label>
                <Input value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="A" />
                {errors.codigo && <div className="text-sm text-red-600 mt-1">{errors.codigo}</div>}
              </div>
              <div className="md:col-span-2">
                <Label>Nombre</Label>
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Turno día" />
                {errors.nombre && <div className="text-sm text-red-600 mt-1">{errors.nombre}</div>}
              </div>
              <div>
                <Label>Horas</Label>
                <Input type="number" step="0.25" value={data.horas} onChange={(e) => setData('horas', e.target.value)} min={0.25} max={24} />
                {errors.horas && <div className="text-sm text-red-600 mt-1">{errors.horas}</div>}
              </div>
              <div>
                <Label>Hora inicio</Label>
                <Input type="time" value={data.hora_inicio} onChange={(e) => setData('hora_inicio', e.target.value)} />
                {errors.hora_inicio && <div className="text-sm text-red-600 mt-1">{errors.hora_inicio}</div>}
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
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Horas</TableHead>
                <TableHead>Inicio</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(shifts || []).map((s) => (
                <TableRow key={s.id}>
                  <TableCell className="font-medium">{s.codigo}</TableCell>
                  <TableCell>{s.nombre}</TableCell>
                  <TableCell>{s.horas}</TableCell>
                  <TableCell>{s.hora_inicio ? String(s.hora_inicio).slice(0, 5) : '-'}</TableCell>
                  <TableCell>
                    {s.activo ? (
                      <Badge className="bg-green-50 text-green-800 border border-green-200">Sí</Badge>
                    ) : (
                      <Badge variant="outline" className="text-gray-600">No</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" onClick={() => startEdit(s)}>Editar</Button>
                  </TableCell>
                </TableRow>
              ))}
              {(!shifts || shifts.length === 0) && (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-sm text-gray-600">
                    No hay turnos creados.
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

Shifts.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Configuración</h2>}
  />
)
