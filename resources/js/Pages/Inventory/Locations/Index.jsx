import { useState } from 'react'
import { useForm, usePage, Link } from '@inertiajs/react'
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
  tipo: 'bodega',
  permite_stock_negativo: false,
  es_bodega_central: false,
  activo: true,
}

export default function InventoryLocations({ locations = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const { data, setData, post, patch, processing, errors, reset } = useForm(emptyForm)
  const locationTypeOptions = ['bodega', 'armado', 'altillo', 'produccion', 'merma', 'ajuste', 'otras'].map((type) => ({
    value: type,
    label: type,
  }))

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (location) => {
    setEditing(location)
    setData({
      codigo: location.codigo,
      nombre: location.nombre,
      tipo: location.tipo,
      permite_stock_negativo: Boolean(location.permite_stock_negativo),
      es_bodega_central: Boolean(location.es_bodega_central),
      activo: Boolean(location.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.locations.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }
    post(route('inventory.locations.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-6">
          <CardTitle className="text-2xl font-bold">Gestión de Ubicaciones</CardTitle>
          <Button onClick={startCreate}>Nueva Ubicación</Button>
        </CardHeader>
        <CardContent className="space-y-8">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded-lg border bg-slate-50/50 p-6 space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input id="codigo" value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} disabled={Boolean(editing)} className="mt-1" />
                {errors.codigo && <div className="mt-1 text-sm text-red-600">{errors.codigo}</div>}
              </div>
              <div>
                <Label htmlFor="nombre">Nombre</Label>
                <Input id="nombre" value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} className="mt-1" />
              </div>
              <div>
                <Label htmlFor="tipo">Tipo</Label>
                <SearchableSelect
                  options={locationTypeOptions}
                  value={locationTypeOptions.find((item) => item.value === data.tipo) || null}
                  onChange={(option) => setData('tipo', option?.value || 'bodega')}
                  placeholder="Selecciona tipo"
                  isClearable={false}
                />
              </div>
            </div>

            <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
              <div className="flex items-center space-x-2">
                <Switch
                  id="permite_stock_negativo"
                  checked={data.permite_stock_negativo}
                  onCheckedChange={(checked) => setData('permite_stock_negativo', checked)}
                />
                <Label htmlFor="permite_stock_negativo">Permite stock negativo</Label>
              </div>
              <div className="flex items-center space-x-2">
                <Switch
                  id="es_bodega_central"
                  checked={data.es_bodega_central}
                  onCheckedChange={(checked) => setData('es_bodega_central', checked)}
                />
                <Label htmlFor="es_bodega_central">Es Bodega Central</Label>
              </div>
              <div className="flex items-center space-x-2">
                <Switch
                  id="activo"
                  checked={data.activo}
                  onCheckedChange={(checked) => setData('activo', checked)}
                />
                <Label htmlFor="activo">Activo</Label>
              </div>
            </div>
            
            <div className="flex justify-end gap-2 pt-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar' : 'Crear Ubicación'}</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Configuración</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {locations.map((location) => (
                <TableRow key={location.id}>
                  <TableCell className="font-medium">{location.codigo}</TableCell>
                  <TableCell>{location.nombre}</TableCell>
                  <TableCell className="capitalize">{location.tipo}</TableCell>
                  <TableCell className="space-x-2">
                    {location.es_bodega_central && <Badge variant="secondary">Bodega Central</Badge>}
                    {location.permite_stock_negativo && <Badge variant="outline" className="text-amber-600 border-amber-200">Stock Neg.</Badge>}
                  </TableCell>
                  <TableCell>{location.activo ? <Badge>Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}</TableCell>
                  <TableCell className="text-right flex justify-end gap-2">
                    <Link href={route('inventory.locations.users', location.id)}>
                      <Button variant="ghost" size="sm">Encargados</Button>
                    </Link>
                    <Button variant="ghost" size="sm" onClick={() => startEdit(location)}>Editar</Button>
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

InventoryLocations.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Ubicaciones</h2>}
  />
)
