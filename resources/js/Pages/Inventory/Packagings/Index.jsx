import { useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import { Textarea } from '@/Components/ui/textarea'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

const emptyForm = {
  nombre: '',
  tipo: '',
  peso_std: '',
  tramo_sag_embalajes: '',
  descripcion: '',
  altura: '',
  cantidad_cajas: '',
  multiplicador: '',
  activo: true,
}

export default function InventoryPackagings({ packagings, filters = {}, types = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    tipo: filters.tipo || '',
    active: filters.active ?? '',
  })
  const { data, setData, patch, processing, errors, reset } = useForm(emptyForm)
  const typeOptions = types.map((type) => ({ value: type, label: type }))
  const activeOptions = [
    { value: '1', label: 'Activos' },
    { value: '0', label: 'Inactivos' },
  ]

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (packaging) => {
    setEditing(packaging)
    setData({
      nombre: packaging.nombre || '',
      tipo: packaging.tipo || '',
      peso_std: packaging.peso_std ?? '',
      tramo_sag_embalajes: packaging.tramo_sag_embalajes || '',
      descripcion: packaging.descripcion || '',
      altura: packaging.altura || '',
      cantidad_cajas: packaging.cantidad_cajas ?? '',
      multiplicador: packaging.multiplicador ?? '',
      activo: Boolean(packaging.activo),
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (!editing?.id) {
      return
    }

    patch(route('inventory.packagings.update', editing.id), {
      preserveScroll: true,
      onSuccess: startCreate,
    })
  }

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.packagings.index'), filterData, {
      preserveState: true,
      preserveScroll: true,
    })
  }

  const syncSqlsrv = () => {
    router.post(route('inventory.packagings.sync-sqlsrv'), {}, { preserveScroll: true })
  }

  return (
    <div className="container mx-auto space-y-4 py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle>Embalajes</CardTitle>
            <p className="mt-1 text-sm text-gray-600">
              Catálogo local de embalajes sincronizado desde SQL Server.
            </p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" onClick={syncSqlsrv}>Importar SQL Server</Button>
            <Button variant="outline" onClick={startCreate} disabled={!editing}>Limpiar edición</Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="space-y-4 rounded border bg-gray-50 p-4">
            <div className="grid gap-3 md:grid-cols-6">
              <div>
                <Label>Código</Label>
                <Input value={editing?.codigo || ''} disabled placeholder="Selecciona un embalaje" />
              </div>
              <div className="md:col-span-2">
                <Label>Nombre</Label>
                <Input value={data.nombre} onChange={(event) => setData('nombre', event.target.value)} disabled={!editing} />
                {errors.nombre && <div className="mt-1 text-sm text-red-600">{errors.nombre}</div>}
              </div>
              <div>
                <Label>Tipo</Label>
                <Input value={data.tipo} onChange={(event) => setData('tipo', event.target.value)} disabled={!editing} />
                {errors.tipo && <div className="mt-1 text-sm text-red-600">{errors.tipo}</div>}
              </div>
              <div>
                <Label>Altura</Label>
                <Input value={data.altura} onChange={(event) => setData('altura', event.target.value)} disabled={!editing} />
              </div>
              <div>
                <Label>Cantidad cajas</Label>
                <Input type="number" step="0.0001" value={data.cantidad_cajas} onChange={(event) => setData('cantidad_cajas', event.target.value)} disabled={!editing} />
              </div>
              <div>
                <Label>Peso estándar</Label>
                <Input type="number" step="0.0001" value={data.peso_std} onChange={(event) => setData('peso_std', event.target.value)} disabled={!editing} />
              </div>
              <div>
                <Label>Multiplicador</Label>
                <Input type="number" step="0.0001" value={data.multiplicador} onChange={(event) => setData('multiplicador', event.target.value)} disabled={!editing} />
              </div>
              <div className="md:col-span-2">
                <Label>Tramo SAG</Label>
                <Input value={data.tramo_sag_embalajes} onChange={(event) => setData('tramo_sag_embalajes', event.target.value)} disabled={!editing} />
              </div>
              <label className="flex items-end gap-2 text-sm">
                <input type="checkbox" checked={Boolean(data.activo)} onChange={(event) => setData('activo', event.target.checked)} disabled={!editing} />
                Activo
              </label>
              <div className="md:col-span-6">
                <Label>Descripción</Label>
                <Textarea value={data.descripcion} onChange={(event) => setData('descripcion', event.target.value)} disabled={!editing} />
              </div>
            </div>
            <div className="flex justify-end gap-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing || !editing}>
                {processing ? 'Guardando...' : 'Actualizar embalaje'}
              </Button>
            </div>
          </form>

          <form onSubmit={applyFilters} className="grid gap-3 rounded border p-4 md:grid-cols-4">
            <Input value={filterData.q} onChange={(event) => setFilterData((current) => ({ ...current, q: event.target.value }))} placeholder="Buscar código, nombre o descripción" />
            <SearchableSelect
              options={typeOptions}
              value={typeOptions.find((item) => item.value === filterData.tipo) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, tipo: option?.value || '' }))}
              placeholder="Todos los tipos"
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
                <TableHead>Tipo</TableHead>
                <TableHead className="text-right">Cajas</TableHead>
                <TableHead>Altura</TableHead>
                <TableHead className="text-right">Multiplicador</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(packagings?.data || []).map((packaging) => (
                <TableRow key={packaging.id}>
                  <TableCell className="font-medium">{packaging.codigo}</TableCell>
                  <TableCell>
                    <div>{packaging.nombre}</div>
                    <div className="text-xs text-gray-500">{packaging.tramo_sag_embalajes || packaging.descripcion || '-'}</div>
                  </TableCell>
                  <TableCell>{packaging.tipo || '-'}</TableCell>
                  <TableCell className="text-right">{Number(packaging.cantidad_cajas || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell>{packaging.altura || '-'}</TableCell>
                  <TableCell className="text-right">{Number(packaging.multiplicador || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell>{packaging.activo ? <Badge>Activo</Badge> : <Badge variant="outline">Inactivo</Badge>}</TableCell>
                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" onClick={() => startEdit(packaging)}>Editar</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {packagings?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {packagings.from ?? 0} a {packagings.to ?? 0} de {packagings.total ?? 0}</div>
              <div className="flex gap-1">
                {packagings.links.map((link, index) => (
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

InventoryPackagings.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventario · Embalajes</h2>}
  />
)
