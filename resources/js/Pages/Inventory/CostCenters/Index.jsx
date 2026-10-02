import { useRef, useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Badge } from '@/Components/ui/badge'
import { Textarea } from '@/Components/ui/textarea'
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
  descripcion: '',
  service_id: '',
  activo: true,
}

export default function CostCenters({ costCenters = [], services = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const { data, setData, post, patch, delete: destroy, processing, errors, reset } = useForm(emptyForm)
  const fileInputRef = useRef(null)
  const importForm = useForm({ file: null })

  const handleFileChange = (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    importForm.setData('file', file)
    importForm.post(route('inventory.cost-centers.import'), {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => {
        importForm.reset('file')
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
      onError: () => {
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
    })
  }

  const serviceOptions = services.map((service) => ({
    value: String(service.id),
    label: service.name,
  }))

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (costCenter) => {
    setEditing(costCenter)
    setData({
      codigo: costCenter.codigo,
      nombre: costCenter.nombre,
      descripcion: costCenter.descripcion || '',
      service_id: costCenter.service_id ? String(costCenter.service_id) : '',
      activo: Boolean(costCenter.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()

    if (editing?.id) {
      patch(route('inventory.cost-centers.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }

    post(route('inventory.cost-centers.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  const remove = (costCenter) => {
    const warning = costCenter.deliveries_count > 0
      ? ` tiene ${costCenter.deliveries_count} entrega(s) asociada(s) y se desactivará en lugar de eliminarse.`
      : '.'

    if (!window.confirm(`¿Eliminar el centro de costo "${costCenter.codigo}"${warning}`)) return

    destroy(route('inventory.cost-centers.destroy', costCenter.id), { preserveScroll: true })
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <CardTitle className="text-2xl font-bold">Centros de Costo</CardTitle>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={startCreate}>Nuevo</Button>
            <Button
              variant="outline"
              onClick={() => window.location.href = route('inventory.cost-centers.template')}
            >
              Descargar plantilla
            </Button>
            <Button
              variant="outline"
              onClick={() => fileInputRef.current?.click()}
              disabled={importForm.processing}
            >
              {importForm.processing ? 'Subiendo...' : 'Subir masivamente'}
            </Button>
            <input
              ref={fileInputRef}
              type="file"
              accept=".xlsx,.xls"
              className="hidden"
              onChange={handleFileChange}
            />
          </div>
        </CardHeader>
        <CardContent className="space-y-8">
          <p className="text-sm text-slate-500">
            Catálogo de centros de costo usado para imputar las entregas de materiales a personas.
            Se puede asociar cada centro a un servicio, aunque es opcional. Para cargar varios a la vez,
            descarga la plantilla y súbela con "Subir masivamente": las filas se identifican por código,
            así que si el código ya existe se actualiza en lugar de duplicarse.
          </p>

          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.warning && <div className="rounded border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{props.flash.warning}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{props.flash.error}</div>}
          {errors.file && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{errors.file}</div>}

          <form onSubmit={submit} className="rounded-lg border bg-slate-50/50 p-6 space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input
                  id="codigo"
                  value={data.codigo}
                  onChange={(e) => setData('codigo', e.target.value)}
                  placeholder="Ej: CC-1001"
                  className="mt-1"
                />
                {errors.codigo && <div className="mt-1 text-sm text-red-600">{errors.codigo}</div>}
              </div>

              <div>
                <Label htmlFor="nombre">Nombre</Label>
                <Input
                  id="nombre"
                  value={data.nombre}
                  onChange={(e) => setData('nombre', e.target.value)}
                  placeholder="Ej: Bodega Central"
                  className="mt-1"
                />
                {errors.nombre && <div className="mt-1 text-sm text-red-600">{errors.nombre}</div>}
              </div>

              <div>
                <Label htmlFor="service_id">Servicio</Label>
                <SearchableSelect
                  options={serviceOptions}
                  value={serviceOptions.find((option) => option.value === String(data.service_id)) || null}
                  onChange={(option) => setData('service_id', option?.value || '')}
                  placeholder="Sin servicio"
                />
                {errors.service_id && <div className="mt-1 text-sm text-red-600">{errors.service_id}</div>}
              </div>
            </div>

            <div>
              <Label htmlFor="descripcion">Descripción</Label>
              <Textarea
                id="descripcion"
                value={data.descripcion}
                onChange={(e) => setData('descripcion', e.target.value)}
                placeholder="Detalle opcional del centro de costo"
                className="mt-1"
              />
              {errors.descripcion && <div className="mt-1 text-sm text-red-600">{errors.descripcion}</div>}
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
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar' : 'Crear Centro de Costo'}</Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Servicio</TableHead>
                <TableHead className="text-right">Entregas</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {costCenters.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="py-8 text-center text-slate-400">
                    Aún no hay centros de costo registrados.
                  </TableCell>
                </TableRow>
              )}
              {costCenters.map((costCenter) => (
                <TableRow key={costCenter.id}>
                  <TableCell className="font-mono font-medium">{costCenter.codigo}</TableCell>
                  <TableCell>
                    {costCenter.nombre}
                    {costCenter.descripcion && (
                      <span className="block text-xs text-slate-500">{costCenter.descripcion}</span>
                    )}
                  </TableCell>
                  <TableCell>
                    {costCenter.service?.name ?? <span className="text-slate-400">—</span>}
                  </TableCell>
                  <TableCell className="text-right tabular-nums">{costCenter.deliveries_count}</TableCell>
                  <TableCell>
                    {costCenter.activo ? <Badge>Activo</Badge> : <Badge variant="outline">Inactivo</Badge>}
                  </TableCell>
                  <TableCell className="flex justify-end gap-2 text-right">
                    <Button variant="ghost" size="sm" onClick={() => startEdit(costCenter)}>Editar</Button>
                    <Button
                      variant="ghost"
                      size="sm"
                      className="text-red-600 hover:text-red-700"
                      onClick={() => remove(costCenter)}
                    >
                      Eliminar
                    </Button>
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

CostCenters.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Centros de Costo</h2>}
  />
)
