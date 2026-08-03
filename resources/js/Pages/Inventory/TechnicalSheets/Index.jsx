import { useState, useRef } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import { Textarea } from '@/Components/ui/textarea'

const emptyItem = { material_id: '', replacement_material_id: '', cantidad_estandar: '', calibre: '' }
const emptyForm = {
  packaging_id: '',
  material_id: '',
  es_semielaborado: false,
  fecha_vigencia_desde: '',
  fecha_vigencia_hasta: '',
  activo: true,
  observacion: '',
  unit_items: [{ ...emptyItem }],
  pallet_items: [{ ...emptyItem }],
}

export default function InventoryTechnicalSheets({ sheets = [], packagings = [], materials = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const fileInputRef = useRef(null)
  const { data, setData, post, patch, processing, errors, reset } = useForm(emptyForm)
  const importForm = useForm({ file: null })
  const packagingOptions = packagings.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))

  const syncPackagings = () => router.post(route('inventory.technical-sheets.sync-packagings'), {}, { preserveScroll: true })

  const handleFileChange = (event) => {
    const file = event.target.files?.[0]
    if (!file) return
    importForm.setData('file', file)
    importForm.post(route('inventory.technical-sheets.import'), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        importForm.reset('file')
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
      onFinish: () => {
        importForm.reset('file')
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
    })
  }

  const setCollectionItem = (collection, index, field, value) => {
    const next = [...data[collection]]
    next[index] = { ...next[index], [field]: value }
    setData(collection, next)
  }

  const addItem = (collection) => setData(collection, [...data[collection], { ...emptyItem }])
  const removeItem = (collection, index) => setData(collection, data[collection].filter((_, currentIndex) => currentIndex !== index))

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
  }

  const startEdit = (sheet) => {
    setEditing(sheet)
    setData({
      packaging_id: sheet.packaging_id,
      material_id: sheet.material_id,
      es_semielaborado: Boolean(sheet.es_semielaborado),
      fecha_vigencia_desde: sheet.fecha_vigencia_desde || '',
      fecha_vigencia_hasta: sheet.fecha_vigencia_hasta || '',
      activo: Boolean(sheet.activo),
      observacion: sheet.observacion || '',
      unit_items: sheet.unit_items.length ? sheet.unit_items.map((item) => ({ material_id: item.material_id, replacement_material_id: item.replacement_material_id || '', cantidad_estandar: item.cantidad_estandar, calibre: item.calibre || '' })) : [{ ...emptyItem }],
      pallet_items: sheet.pallet_items.length ? sheet.pallet_items.map((item) => ({ material_id: item.material_id, replacement_material_id: item.replacement_material_id || '', cantidad_estandar: item.cantidad_estandar, calibre: item.calibre || '' })) : [{ ...emptyItem }],
    })
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.technical-sheets.update', editing.id), { preserveScroll: true, onSuccess: startCreate })
      return
    }
    post(route('inventory.technical-sheets.store'), { preserveScroll: true, onSuccess: startCreate })
  }

  const renderItems = (title, collection) => (
    <div className="space-y-3">
      <div className="flex items-center justify-between">
        <div className="font-medium">{title}</div>
        <Button type="button" variant="outline" onClick={() => addItem(collection)}>Agregar</Button>
      </div>
      {data[collection].map((item, index) => (
        <div key={`${collection}-${index}`} className="grid gap-3 rounded border bg-white p-3 md:grid-cols-12">
          <div className="md:col-span-4">
            <Label>Material Principal</Label>
            <SearchableSelect
              options={materialOptions}
              value={materialOptions.find((material) => material.value === String(item.material_id)) || null}
              onChange={(option) => setCollectionItem(collection, index, 'material_id', option?.value || '')}
              placeholder="Material principal"
            />
          </div>
          <div className="md:col-span-3">
            <Label>Material Reemplazo (Opcional)</Label>
            <SearchableSelect
              options={materialOptions}
              value={materialOptions.find((material) => material.value === String(item.replacement_material_id)) || null}
              onChange={(option) => setCollectionItem(collection, index, 'replacement_material_id', option?.value || '')}
              placeholder="Material reemplazo"
            />
          </div>
          <div className="md:col-span-2">
            <Label>Cant. Estándar</Label>
            <Input type="number" step="0.000001" value={item.cantidad_estandar} onChange={(e) => setCollectionItem(collection, index, 'cantidad_estandar', e.target.value)} />
          </div>
          <div className="md:col-span-2">
            <Label>Calibre</Label>
            <Input value={item.calibre} onChange={(e) => setCollectionItem(collection, index, 'calibre', e.target.value)} placeholder="Ej: L, XL, 28, 30..." />
          </div>
          <div className="md:col-span-1 flex items-end">
            <Button type="button" variant="destructive" size="sm" onClick={() => removeItem(collection, index)} disabled={data[collection].length === 1}>Quitar</Button>
          </div>
        </div>
      ))}
    </div>
  )

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Fichas técnicas</CardTitle>
          <div className="flex gap-2">
            <Button variant="outline" onClick={syncPackagings}>Sincronizar embalajes</Button>
            <Button variant="outline" onClick={() => window.location.href = route('inventory.technical-sheets.template')}>Descargar plantilla</Button>
            <Button variant="outline" onClick={() => fileInputRef.current?.click()} disabled={importForm.processing}>
              {importForm.processing ? 'Subiendo...' : 'Subir masivamente'}
            </Button>
            <input ref={fileInputRef} type="file" accept=".xlsx,.xls" className="hidden" onChange={handleFileChange} />
            <Button variant="outline" onClick={startCreate}>Nueva ficha</Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.warning && <div className="rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800 whitespace-pre-wrap">{props.flash.warning}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded border bg-gray-50 p-4 space-y-4">
            <div className="grid gap-3 md:grid-cols-4">
              <div className="md:col-span-4 flex items-center gap-2">
                <input 
                    type="checkbox" 
                    id="es_semielaborado"
                    checked={data.es_semielaborado} 
                    onChange={(e) => setData({ ...data, es_semielaborado: e.target.checked, packaging_id: '', material_id: '' })} 
                />
                <Label htmlFor="es_semielaborado" className="font-bold cursor-pointer">Es Producto Semielaborado</Label>
              </div>

              {data.es_semielaborado ? (
                <div>
                  <Label>Material Semielaborado</Label>
                  <SearchableSelect
                    options={materialOptions}
                    value={materialOptions.find((item) => item.value === String(data.material_id)) || null}
                    onChange={(option) => setData('material_id', option?.value || '')}
                    placeholder="Selecciona material"
                  />
                </div>
              ) : (
                <div>
                  <Label>Embalaje</Label>
                  <SearchableSelect
                    options={packagingOptions}
                    value={packagingOptions.find((item) => item.value === String(data.packaging_id)) || null}
                    onChange={(option) => setData('packaging_id', option?.value || '')}
                    placeholder="Selecciona embalaje"
                  />
                </div>
              )}
              <div>
                <Label>Vigencia desde</Label>
                <Input type="date" value={data.fecha_vigencia_desde} onChange={(e) => setData('fecha_vigencia_desde', e.target.value)} />
              </div>
              <div>
                <Label>Vigencia hasta</Label>
                <Input type="date" value={data.fecha_vigencia_hasta} onChange={(e) => setData('fecha_vigencia_hasta', e.target.value)} />
              </div>
              <label className="flex items-end gap-2 text-sm"><input type="checkbox" checked={Boolean(data.activo)} onChange={(e) => setData('activo', e.target.checked)} />Activa</label>
            </div>
            <div>
              <Label>Observación</Label>
              <Textarea value={data.observacion} onChange={(e) => setData('observacion', e.target.value)} />
            </div>
            {renderItems('Materiales por unidad', 'unit_items')}
            {renderItems('Materiales por pallet', 'pallet_items')}
            {errors.items && <div className="text-sm text-red-600">{errors.items}</div>}
            <div className="flex justify-end gap-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar ficha' : 'Crear ficha'}</Button>
            </div>
          </form>

          <div className="space-y-3">
            {sheets.map((sheet) => (
              <div key={sheet.id} className="rounded border bg-white p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div className="font-semibold">{sheet.es_semielaborado ? (sheet.material?.nombre || '-') : (sheet.packaging || '-')}</div>
                    <div className="text-sm text-gray-600">Versión {sheet.version} · {sheet.fecha_vigencia_desde} → {sheet.fecha_vigencia_hasta || 'vigente'} · por {sheet.creator || '-'}</div>
                    <div className="mt-2 flex gap-2">
                      {sheet.activo ? <Badge>Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}
                      {sheet.es_semielaborado ? <Badge variant="secondary">Semielaborado</Badge> : <Badge variant="secondary">Embalaje</Badge>}
                    </div>
                  </div>
                  <Button variant="outline" size="sm" onClick={() => startEdit(sheet)}>Editar</Button>
                </div>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  <div>
                    <div className="mb-2 text-sm font-medium">Unidad</div>
                    <div className="space-y-1 text-sm">
                      {sheet.unit_items.map((item, index) => (
                        <div key={`unit-${sheet.id}-${index}`}>
                          {item.label} {item.replacement_label ? `(o ${item.replacement_label})` : ''}: {Number(item.cantidad_estandar).toLocaleString('es-CL')}{item.calibre ? ` [${item.calibre}]` : ''}
                        </div>
                      ))}
                      {sheet.unit_items.length === 0 && <div className="text-gray-500">Sin materiales por unidad.</div>}
                    </div>
                  </div>
                  <div>
                    <div className="mb-2 text-sm font-medium">Pallet</div>
                    <div className="space-y-1 text-sm">
                      {sheet.pallet_items.map((item, index) => (
                        <div key={`pallet-${sheet.id}-${index}`}>
                          {item.label} {item.replacement_label ? `(o ${item.replacement_label})` : ''}: {Number(item.cantidad_estandar).toLocaleString('es-CL')}{item.calibre ? ` [${item.calibre}]` : ''}
                        </div>
                      ))}
                      {sheet.pallet_items.length === 0 && <div className="text-gray-500">Sin materiales por pallet.</div>}
                    </div>
                  </div>
                </div>
              </div>
            ))}
            {sheets.length === 0 && <div className="rounded border py-10 text-center text-sm text-gray-500">No hay fichas técnicas creadas.</div>}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

InventoryTechnicalSheets.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Fichas técnicas</h2>}
  />
)
