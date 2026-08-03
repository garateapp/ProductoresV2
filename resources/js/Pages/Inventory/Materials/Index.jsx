import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
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
import { Textarea } from '@/Components/ui/textarea'

const emptyForm = {
  codigo: '',
  nombre: '',
  descripcion: '',
  family_id: '',
  unit_id: '',
  service_id: '',
  tipo_material: 'consumo',
  stock_minimo: '',
  activo: true,
}

export default function InventoryMaterials({ materials, families = [], units = [], services = [], filters = {} }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    family_id: filters.family_id || '',
    service_id: filters.service_id || '',
    active: filters.active ?? '',
  })
  const { data, setData, post, patch, processing, errors, reset } = useForm(emptyForm)
  const familyOptions = families.map((item) => ({ value: String(item.id), label: item.nombre }))
  const unitOptions = units.map((item) => ({ value: String(item.id), label: item.codigo }))
  const serviceOptions = services.map((item) => ({ value: String(item.id), label: item.name }))
  const materialTypeOptions = [
    { value: 'consumo', label: 'Consumo' },
    { value: 'semielaborado', label: 'Semielaborado' },
    { value: 'retornable', label: 'Retornable' },
  ]
  const activeOptions = [
    { value: '1', label: 'Activos' },
    { value: '0', label: 'Inactivos' },
  ]

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (material) => {
    setEditing(material)
    setData({
      codigo: material.codigo,
      nombre: material.nombre,
      descripcion: material.descripcion || '',
      family_id: material.family_id ? String(material.family_id) : '',
      unit_id: material.unit_id ? String(material.unit_id) : '',
      service_id: material.service_id ? String(material.service_id) : '',
      tipo_material: material.tipo_material,
      stock_minimo: material.stock_minimo || '',
      activo: Boolean(material.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.materials.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }
    post(route('inventory.materials.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.materials.index'), filterData, { preserveState: true, preserveScroll: true })
  }

  const syncSap = () => {
    router.post(route('inventory.materials.sync-sap'), {}, { preserveScroll: true })
  }

  const syncCentralStock = () => {
    router.post(route('inventory.materials.sync-central-stock'), {}, { preserveScroll: true })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Materiales</CardTitle>
          <div className="flex gap-2">
            <Button type="button" variant="outline" onClick={syncSap}>Sincronizar SAP</Button>
            <Button type="button" variant="outline" onClick={syncCentralStock}>Cargar stock a Bodega Central</Button>
            <Button type="button" variant="outline" onClick={startCreate}>Nuevo</Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded border bg-gray-50 p-4 space-y-4">
            <div className="grid gap-3 md:grid-cols-6">
              <div className="md:col-span-2">
                <Label>Código</Label>
                <Input value={data.codigo} onChange={(e) => setData('codigo', e.target.value)} disabled={Boolean(editing)} />
                {errors.codigo && <div className="mt-1 text-sm text-red-600">{errors.codigo}</div>}
              </div>
              <div className="md:col-span-2">
                <Label>Nombre</Label>
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
                {errors.nombre && <div className="mt-1 text-sm text-red-600">{errors.nombre}</div>}
              </div>
              <div>
                <Label>Familia</Label>
                <SearchableSelect
                  options={familyOptions}
                  value={familyOptions.find((item) => item.value === String(data.family_id)) || null}
                  onChange={(option) => setData('family_id', option?.value || '')}
                  placeholder="Sin familia"
                />
              </div>
              <div>
                <Label>Unidad</Label>
                <SearchableSelect
                  options={unitOptions}
                  value={unitOptions.find((item) => item.value === String(data.unit_id)) || null}
                  onChange={(option) => setData('unit_id', option?.value || '')}
                  placeholder="Sin unidad"
                />
              </div>
              <div className="md:col-span-2">
                <Label>Servicio</Label>
                <SearchableSelect
                  options={serviceOptions}
                  value={serviceOptions.find((item) => item.value === String(data.service_id)) || null}
                  onChange={(option) => setData('service_id', option?.value || '')}
                  placeholder="Sin servicio"
                />
                {errors.service_id && <div className="mt-1 text-sm text-red-600">{errors.service_id}</div>}
              </div>
              <div>
                <Label>Tipo</Label>
                <SearchableSelect
                  options={materialTypeOptions}
                  value={materialTypeOptions.find((item) => item.value === data.tipo_material) || null}
                  onChange={(option) => setData('tipo_material', option?.value || 'consumo')}
                  placeholder="Selecciona tipo"
                  isClearable={false}
                />
              </div>
              <div>
                <Label>Stock Mínimo</Label>
                <Input type="number" step="0.0001" value={data.stock_minimo} onChange={(e) => setData('stock_minimo', e.target.value)} placeholder="0.0000" />
              </div>
              <div className="md:col-span-6">
                <Label>Descripción</Label>
                <Textarea value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} />
              </div>
              <label className="flex items-center gap-2 text-sm md:col-span-1 md:mt-8">
                <input type="checkbox" checked={Boolean(data.activo)} onChange={(e) => setData('activo', e.target.checked)} />
                Activo
              </label>
            </div>
            <div className="flex justify-end gap-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing}>{processing ? 'Guardando...' : editing ? 'Actualizar' : 'Crear'}</Button>
            </div>
          </form>

          <form onSubmit={applyFilters} className="grid gap-3 rounded border p-4 md:grid-cols-5">
            <Input value={filterData.q} onChange={(e) => setFilterData((current) => ({ ...current, q: e.target.value }))} placeholder="Buscar código o nombre" />
            <SearchableSelect
              options={familyOptions}
              value={familyOptions.find((item) => item.value === String(filterData.family_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, family_id: option?.value || '' }))}
              placeholder="Todas las familias"
            />
            <SearchableSelect
              options={serviceOptions}
              value={serviceOptions.find((item) => item.value === String(filterData.service_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, service_id: option?.value || '' }))}
              placeholder="Todos los servicios"
            />
            <SearchableSelect
              options={activeOptions}
              value={activeOptions.find((item) => item.value === String(filterData.active)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, active: option?.value || '' }))}
              placeholder="Todos"
            />
            <Button type="submit">Filtrar</Button>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Servicio</TableHead>
                <TableHead>Familia</TableHead>
                <TableHead>Unidad</TableHead>
                <TableHead className="text-right">SAP</TableHead>
                <TableHead className="text-right">Interno</TableHead>
                <TableHead className="text-right">Mínimo</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(materials?.data || []).map((material) => (
                <TableRow key={material.id}>
                  <TableCell className="font-medium">{material.codigo}</TableCell>
                  <TableCell>{material.nombre}</TableCell>
                  <TableCell>{material.servicio || '-'}</TableCell>
                  <TableCell>{material.familia || '-'}</TableCell>
                  <TableCell>{material.unidad || '-'}</TableCell>
                  <TableCell className="text-right">{Number(material.sap_on_hand || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell className={`text-right font-bold ${Number(material.internal_stock || 0) < Number(material.stock_minimo || 0) ? 'text-red-600' : ''}`}>
                    {Number(material.internal_stock || 0).toLocaleString('es-CL')}
                  </TableCell>
                  <TableCell className="text-right text-slate-500 italic">
                    {Number(material.stock_minimo || 0).toLocaleString('es-CL')}
                  </TableCell>
                  <TableCell>{material.activo ? <Badge>Activo</Badge> : <Badge variant="outline">Inactivo</Badge>}</TableCell>
                  <TableCell className="text-right"><Button variant="outline" size="sm" onClick={() => startEdit(material)}>Editar</Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {materials?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {materials.from ?? 0} a {materials.to ?? 0} de {materials.total ?? 0}</div>
              <div className="flex gap-1">
                {materials.links.map((link, index) => (
                  <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    preserveScroll
                    preserveState
                    className={`rounded border px-3 py-1 ${link.active ? 'bg-indigo-50 text-indigo-700' : 'bg-white'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}

InventoryMaterials.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Materiales</h2>}
  />
)
