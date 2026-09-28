import { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

const emptyForm = {
  codigo: '',
  nombre: '',
  service_id: '',
  activo: true,
}

export default function Labels({ labels = [], services = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const { data, setData, post, patch, delete: destroy, processing, errors, reset } = useForm(emptyForm)

  const serviceOptions = services.map((service) => ({
    value: String(service.id),
    label: service.name,
  }))

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (label) => {
    setEditing(label)
    setData({
      codigo: label.codigo,
      nombre: label.nombre,
      service_id: String(label.service_id || ''),
      activo: Boolean(label.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.labels.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }
    post(route('inventory.labels.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  const remove = (label) => {
    if (!window.confirm(`¿Eliminar la etiqueta "${label.codigo}"?`)) return
    destroy(route('inventory.labels.destroy', label.id), { preserveScroll: true })
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-6">
          <CardTitle className="text-2xl font-bold">Etiquetas</CardTitle>
          <Button onClick={startCreate}>Nueva Etiqueta</Button>
        </CardHeader>
        <CardContent className="space-y-8">
          <p className="text-sm text-slate-500">
            Catálogo de etiquetas disponibles para las fichas técnicas. Cada etiqueta pertenece a un servicio
            y se selecciona desde la ficha técnica del material o embalaje.
          </p>

          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded-lg border bg-slate-50/50 p-6 space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} placeholder="Ej: ETQ-001" className="mt-1 uppercase" />
                {errors.codigo && <div className="mt-1 text-sm text-red-600">{errors.codigo}</div>}
              </div>
              <div>
                <Label htmlFor="nombre">Nombre</Label>
                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Ej: Etiqueta cliente berry" className="mt-1" />
                {errors.nombre && <div className="mt-1 text-sm text-red-600">{errors.nombre}</div>}
              </div>
              <div>
                <Label htmlFor="service_id">Servicio</Label>
                <SearchableSelect
                  options={serviceOptions}
                  value={serviceOptions.find((item) => item.value === String(data.service_id)) || null}
                  onChange={(option) => setData('service_id', option?.value || '')}
                  placeholder="Selecciona servicio"
                />
                {errors.service_id && <div className="mt-1 text-sm text-red-600">{errors.service_id}</div>}
              </div>
            </div>

            <div className="flex items-center space-x-2">
              <Switch
                id="activo"
                checked={data.activo}
                onCheckedChange={(checked) => setData('activo', checked)}
              />
              <Label htmlFor="activo">Activo</Label>
            </div>

            <div className="flex justify-end gap-2 pt-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar' : 'Crear Etiqueta'}</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Servicio</TableHead>
                <TableHead>Fichas</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {labels.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-slate-400 py-8">Aún no hay etiquetas registradas.</TableCell>
                </TableRow>
              )}
              {labels.map((label) => (
                <TableRow key={label.id}>
                  <TableCell className="font-medium">{label.codigo}</TableCell>
                  <TableCell>{label.nombre}</TableCell>
                  <TableCell>{label.service?.name || <span className="text-slate-400">Sin servicio</span>}</TableCell>
                  <TableCell>{label.technical_sheets_count}</TableCell>
                  <TableCell>{label.activo ? <Badge>Activo</Badge> : <Badge variant="outline">Inactivo</Badge>}</TableCell>
                  <TableCell className="text-right flex justify-end gap-2">
                    <Button variant="ghost" size="sm" onClick={() => startEdit(label)}>Editar</Button>
                    <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={() => remove(label)}>Eliminar</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

Labels.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Etiquetas</h2>}
  />
)
