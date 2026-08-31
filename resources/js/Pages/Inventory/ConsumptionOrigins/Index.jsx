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
  linea: '',
  turno: '',
  location_id: '',
  activo: true,
}

export default function ConsumptionOrigins({ origins = [], locations = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const { data, setData, post, patch, delete: destroy, processing, errors, reset } = useForm(emptyForm)

  const locationOptions = locations.map((location) => ({
    value: String(location.id),
    label: `${location.codigo} · ${location.nombre}`,
  }))

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (origin) => {
    setEditing(origin)
    setData({
      linea: origin.linea,
      turno: origin.turno || '',
      location_id: String(origin.location_id || ''),
      activo: Boolean(origin.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.consumption-origins.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }
    post(route('inventory.consumption-origins.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  const remove = (origin) => {
    if (!window.confirm(`¿Eliminar el origen "${origin.linea}" (${origin.turno || 'sin turno'})?`)) return
    destroy(route('inventory.consumption-origins.destroy', origin.id), { preserveScroll: true })
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-6">
          <CardTitle className="text-2xl font-bold">Orígenes de Consumo Automático</CardTitle>
          <Button onClick={startCreate}>Nuevo Origen</Button>
        </CardHeader>
        <CardContent className="space-y-8">
          <p className="text-sm text-slate-500">
            Define qué ubicación de origen se usa para el consumo según la línea y el turno del folio de producción.
            Si no existe un mapeo, se usará la primera ubicación de tipo «producción».
          </p>

          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded-lg border bg-slate-50/50 p-6 space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="linea">Línea</Label>
                <Input id="linea" value={data.linea} onChange={(e) => setData('linea', e.target.value)} placeholder="Ej: R Packing" className="mt-1" />
                {errors.linea && <div className="mt-1 text-sm text-red-600">{errors.linea}</div>}
              </div>
              <div>
                <Label htmlFor="turno">Turno</Label>
                <Input id="turno" value={data.turno} onChange={(e) => setData('turno', e.target.value)} placeholder="Ej: Turno 1 (vacío = todos)" className="mt-1" />
                {errors.turno && <div className="mt-1 text-sm text-red-600">{errors.turno}</div>}
              </div>
              <div>
                <Label htmlFor="location_id">Ubicación de origen</Label>
                <SearchableSelect
                  options={locationOptions}
                  value={locationOptions.find((item) => item.value === String(data.location_id)) || null}
                  onChange={(option) => setData('location_id', option?.value || '')}
                  placeholder="Selecciona ubicación"
                  isClearable={false}
                />
                {errors.location_id && <div className="mt-1 text-sm text-red-600">{errors.location_id}</div>}
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
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar' : 'Crear Origen'}</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Línea</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Ubicación</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {origins.length === 0 && (
                <TableRow>
                  <TableCell colSpan={5} className="text-center text-slate-400 py-8">Aún no hay orígenes configurados.</TableCell>
                </TableRow>
              )}
              {origins.map((origin) => (
                <TableRow key={origin.id}>
                  <TableCell className="font-medium">{origin.linea}</TableCell>
                  <TableCell>{origin.turno || <span className="text-slate-400">—</span>}</TableCell>
                  <TableCell>{origin.location ? `${origin.location.codigo} · ${origin.location.nombre}` : <span className="text-red-600">Sin ubicación</span>}</TableCell>
                  <TableCell>{origin.activo ? <Badge>Activo</Badge> : <Badge variant="outline">Inactivo</Badge>}</TableCell>
                  <TableCell className="text-right flex justify-end gap-2">
                    <Button variant="ghost" size="sm" onClick={() => startEdit(origin)}>Editar</Button>
                    <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={() => remove(origin)}>Eliminar</Button>
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

ConsumptionOrigins.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Orígenes de Consumo</h2>}
  />
)