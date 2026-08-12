import { useRef, useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import { ChevronDown, ChevronUp, ImagePlus, Trash2 } from 'lucide-react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import { Textarea } from '@/Components/ui/textarea'

const emptyItem = { material_id: '', replacement_material_id: '', cantidad_estandar: '', calibre: '' }

const emptyPackagingSpec = {
  identificacion: {
    etiqueta: '', cliente: '', mercado: '', categoria: '', certificacion_planta: '', certificacion_producto: '', especie: '',
  },
  formato: {
    peso_bruto_inferior: '', peso_bruto_superior: '', peso_bruto_medio: '', peso_neto_inferior: '', peso_neto_superior: '',
    peso_individual_inferior: '', peso_individual_superior: '', ancho_caja: '', largo_caja: '', alto_caja: '', unidad_altura: 'mm',
  },
  calidad: {
    calibre_inferior: '', calibre_superior: '', color: '', solidos_solubles: '', materia_seca: '', variedades_aceptadas: '',
    variedades_no_aceptadas: '', firmeza: '', color_cubrimiento: '', sellado_termico: '', sellado_mecanico: '',
  },
  tolerancias: { calidad: '', condicion: '' },
  paletizaje: {
    tiempo_viaje: '', tipo_transporte: '', posiciones_zunchos_horizontales: '', zunchos_verticales_lado_1m: '',
    zunchos_verticales_lado_1_2m: '', cajas_por_pallet: '', base_pallet: '', altura_pallet: '', filas_cajas: '',
    numero_zunchos_horizontales: '', metros_zuncho: '', sellos_zuncho: '', protocolo_sag: '', instrucciones: '',
  },
  responsables: { ruta_spec: '', revisado_por: '', autorizado_por: '' },
}

const createEmptyForm = () => ({
  nombre: '',
  packaging_id: '',
  material_id: '',
  es_semielaborado: false,
  fecha_vigencia_desde: '',
  fecha_vigencia_hasta: '',
  activo: true,
  observacion: '',
  packaging_spec: structuredClone(emptyPackagingSpec),
  unit_items: [{ ...emptyItem }],
  pallet_items: [{ ...emptyItem }],
  existing_images: [],
  new_images: [],
  removed_image_ids: [],
})

const specSections = [
  {
    key: 'identificacion', title: 'Identificación comercial', columns: 3,
    fields: [
      ['etiqueta', 'Etiqueta'], ['cliente', 'Cliente'], ['mercado', 'Mercado'], ['categoria', 'Categoría'], ['certificacion_planta', 'Certificación planta'],
      ['certificacion_producto', 'Certificación producto'], ['especie', 'Especie'],
    ],
  },
  {
    key: 'formato', title: 'Formato y dimensiones', columns: 4,
    fields: [
      ['peso_bruto_inferior', 'Peso bruto inferior'], ['peso_bruto_superior', 'Peso bruto superior'], ['peso_bruto_medio', 'Peso bruto medio'],
      ['peso_neto_inferior', 'Peso neto fruta inferior'], ['peso_neto_superior', 'Peso neto fruta superior'],
      ['peso_individual_inferior', 'Peso individual inferior'], ['peso_individual_superior', 'Peso individual superior'],
      ['ancho_caja', 'Ancho caja (cm)'], ['largo_caja', 'Largo caja (cm)'], ['alto_caja', 'Alto caja'], ['unidad_altura', 'Unidad altura'],
    ],
  },
  {
    key: 'calidad', title: 'Norma de calidad', columns: 3,
    fields: [
      ['calibre_inferior', 'Calibre inferior'], ['calibre_superior', 'Calibre superior'], ['color', 'Color'],
      ['solidos_solubles', 'Sólidos solubles'], ['materia_seca', 'Materia seca'], ['variedades_aceptadas', 'Variedades aceptadas'],
      ['variedades_no_aceptadas', 'Variedades no aceptadas'], ['firmeza', 'Firmeza'], ['color_cubrimiento', 'Color cubrimiento'],
      ['sellado_termico', 'Sellado térmico'], ['sellado_mecanico', 'Sellado mecánico'],
    ],
  },
  {
    key: 'paletizaje', title: 'Paletizaje y despacho', columns: 3,
    fields: [
      ['tiempo_viaje', 'Tiempo de viaje (días)'], ['tipo_transporte', 'Tipo transporte'], ['cajas_por_pallet', 'Cajas por pallet'],
      ['base_pallet', 'Base pallet (cajas)'], ['altura_pallet', 'Altura pallet (m)'], ['filas_cajas', 'Filas de cajas'],
      ['posiciones_zunchos_horizontales', 'Posiciones zunchos horizontales'], ['numero_zunchos_horizontales', 'N° zunchos horizontales'],
      ['zunchos_verticales_lado_1m', 'Zunchos verticales lado 1,0 m'], ['zunchos_verticales_lado_1_2m', 'Zunchos verticales lado 1,2 m'],
      ['metros_zuncho', 'Metros de zuncho'], ['sellos_zuncho', 'Sellos de zuncho'], ['protocolo_sag', 'Protocolo SAG'],
    ],
  },
]

function FieldError({ message }) {
  return message ? <div className="mt-1 text-xs text-red-600">{message}</div> : null
}

function Section({ title, children, open = false }) {
  return (
    <details open={open} className="rounded-lg border bg-white">
      <summary className="cursor-pointer select-none px-4 py-3 font-semibold text-gray-800">{title}</summary>
      <div className="border-t p-4">{children}</div>
    </details>
  )
}

export default function InventoryTechnicalSheets({ sheets = [], packagings = [], materials = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const fileInputRef = useRef(null)
  const imageInputRef = useRef(null)
  const form = useForm(createEmptyForm())
  const { data, setData, post, processing, errors, reset, clearErrors, transform } = form
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
      onFinish: () => {
        importForm.reset('file')
        if (fileInputRef.current) fileInputRef.current.value = ''
      },
    })
  }

  const releasePreviews = () => data.new_images.forEach((image) => image.preview && URL.revokeObjectURL(image.preview))

  const startCreate = () => {
    releasePreviews()
    setEditing(null)
    clearErrors()
    reset()
    setData(createEmptyForm())
  }

  const mergeSpec = (stored = {}) => Object.fromEntries(
    Object.entries(emptyPackagingSpec).map(([section, values]) => [section, { ...values, ...(stored[section] || {}) }]),
  )

  const startEdit = (sheet) => {
    releasePreviews()
    setEditing(sheet)
    clearErrors()
    setData({
      nombre: sheet.nombre || '',
      packaging_id: sheet.packaging_id || '',
      material_id: sheet.material_id || '',
      es_semielaborado: Boolean(sheet.es_semielaborado),
      fecha_vigencia_desde: sheet.fecha_vigencia_desde || '',
      fecha_vigencia_hasta: sheet.fecha_vigencia_hasta || '',
      activo: Boolean(sheet.activo),
      observacion: sheet.observacion || '',
      packaging_spec: mergeSpec(sheet.packaging_spec),
      unit_items: sheet.unit_items.length ? sheet.unit_items.map((item) => ({ material_id: item.material_id, replacement_material_id: item.replacement_material_id || '', cantidad_estandar: item.cantidad_estandar, calibre: item.calibre || '' })) : [{ ...emptyItem }],
      pallet_items: sheet.pallet_items.length ? sheet.pallet_items.map((item) => ({ material_id: item.material_id, replacement_material_id: item.replacement_material_id || '', cantidad_estandar: item.cantidad_estandar, calibre: item.calibre || '' })) : [{ ...emptyItem }],
      existing_images: (sheet.images || []).map((image, index) => ({ ...image, orden: image.orden ?? index })),
      new_images: [],
      removed_image_ids: [],
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const submit = (event) => {
    event.preventDefault()
    const target = editing?.id
      ? route('inventory.technical-sheets.update', editing.id)
      : route('inventory.technical-sheets.store')

    transform((payload) => editing?.id ? { ...payload, _method: 'patch' } : payload)
    post(target, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: startCreate,
    })
  }

  const setCollectionItem = (collection, index, field, value) => {
    const next = [...data[collection]]
    next[index] = { ...next[index], [field]: value }
    setData(collection, next)
  }

  const addItem = (collection) => setData(collection, [...data[collection], { ...emptyItem }])
  const removeItem = (collection, index) => setData(collection, data[collection].filter((_, currentIndex) => currentIndex !== index))

  const setSpec = (section, field, value) => setData('packaging_spec', {
    ...data.packaging_spec,
    [section]: { ...data.packaging_spec[section], [field]: value },
  })

  const addImages = (event) => {
    const files = Array.from(event.target.files || [])
    const available = Math.max(0, 20 - data.existing_images.length - data.new_images.length)
    const additions = files.slice(0, available).map((file, index) => ({
      file,
      descripcion: '',
      orden: data.existing_images.length + data.new_images.length + index,
      preview: URL.createObjectURL(file),
    }))
    setData('new_images', [...data.new_images, ...additions])
    event.target.value = ''
  }

  const updateImage = (collection, index, field, value) => {
    const next = [...data[collection]]
    next[index] = { ...next[index], [field]: value }
    setData(collection, next)
  }

  const removeExistingImage = (index) => {
    const image = data.existing_images[index]
    setData({
      ...data,
      existing_images: data.existing_images.filter((_, itemIndex) => itemIndex !== index),
      removed_image_ids: [...data.removed_image_ids, image.id],
    })
  }

  const removeNewImage = (index) => {
    const image = data.new_images[index]
    if (image.preview) URL.revokeObjectURL(image.preview)
    setData('new_images', data.new_images.filter((_, itemIndex) => itemIndex !== index))
  }

  const moveImage = (collection, index, direction) => {
    const destination = index + direction
    if (destination < 0 || destination >= data[collection].length) return
    const next = [...data[collection]]
    ;[next[index], next[destination]] = [next[destination], next[index]]
    const offset = collection === 'new_images' ? data.existing_images.length : 0
    setData(collection, next.map((image, imageIndex) => ({ ...image, orden: offset + imageIndex })))
  }

  const renderItems = (title, collection) => (
    <Section title={title} open>
      <div className="mb-3 flex justify-end">
        <Button type="button" variant="outline" onClick={() => addItem(collection)}>Agregar material</Button>
      </div>
      <div className="space-y-3">
        {data[collection].map((item, index) => (
          <div key={`${collection}-${index}`} className="grid gap-3 rounded border bg-gray-50 p-3 md:grid-cols-12">
            <div className="md:col-span-4">
              <Label>Material principal</Label>
              <SearchableSelect options={materialOptions} value={materialOptions.find((material) => material.value === String(item.material_id)) || null} onChange={(option) => setCollectionItem(collection, index, 'material_id', option?.value || '')} placeholder="Material principal" />
              <FieldError message={errors[`${collection}.${index}.material_id`]} />
            </div>
            <div className="md:col-span-3">
              <Label>Material reemplazo (opcional)</Label>
              <SearchableSelect options={materialOptions} value={materialOptions.find((material) => material.value === String(item.replacement_material_id)) || null} onChange={(option) => setCollectionItem(collection, index, 'replacement_material_id', option?.value || '')} placeholder="Material reemplazo" />
              <FieldError message={errors[`${collection}.${index}.replacement_material_id`]} />
            </div>
            <div className="md:col-span-2">
              <Label>Cant. estándar</Label>
              <Input type="number" min="0" step="0.000001" value={item.cantidad_estandar} onChange={(event) => setCollectionItem(collection, index, 'cantidad_estandar', event.target.value)} />
              <FieldError message={errors[`${collection}.${index}.cantidad_estandar`]} />
            </div>
            <div className="md:col-span-2">
              <Label>Calibre</Label>
              <Input value={item.calibre} onChange={(event) => setCollectionItem(collection, index, 'calibre', event.target.value)} placeholder="Ej: L, XL, 28..." />
            </div>
            <div className="flex items-end md:col-span-1">
              <Button type="button" variant="destructive" size="sm" onClick={() => removeItem(collection, index)} disabled={data[collection].length === 1}>Quitar</Button>
            </div>
          </div>
        ))}
      </div>
    </Section>
  )

  const renderSpecSection = ({ key, title, fields, columns }) => (
    <Section key={key} title={title} open={key === 'identificacion'}>
      <div className={`grid gap-3 ${columns === 4 ? 'md:grid-cols-4' : 'md:grid-cols-3'}`}>
        {fields.map(([field, label]) => (
          <div key={field}>
            <Label>{label}</Label>
            <Input value={data.packaging_spec[key][field]} onChange={(event) => setSpec(key, field, event.target.value)} />
            <FieldError message={errors[`packaging_spec.${key}.${field}`]} />
          </div>
        ))}
      </div>
      {key === 'paletizaje' && (
        <div className="mt-3">
          <Label>Instrucciones de paletizaje</Label>
          <Textarea rows={4} value={data.packaging_spec.paletizaje.instrucciones} onChange={(event) => setSpec('paletizaje', 'instrucciones', event.target.value)} />
        </div>
      )}
    </Section>
  )

  const renderImageCard = (image, index, collection, isNew) => (
    <div key={`${collection}-${image.id || image.file?.name}-${index}`} className="overflow-hidden rounded-lg border bg-white">
      <img src={isNew ? image.preview : image.url} alt={image.descripcion || image.original_name || 'Imagen de ficha técnica'} className="h-44 w-full bg-gray-100 object-contain" />
      <div className="space-y-2 p-3">
        <div className="truncate text-xs text-gray-500">{isNew ? image.file?.name : image.original_name}</div>
        <Label>Descripción</Label>
        <Textarea rows={3} value={image.descripcion} onChange={(event) => updateImage(collection, index, 'descripcion', event.target.value)} placeholder="Indique qué muestra la imagen y la instrucción asociada" />
        <FieldError message={errors[`${collection}.${index}.descripcion`]} />
        <FieldError message={errors[`${collection}.${index}.file`]} />
        <div className="flex justify-between gap-2">
          <div className="flex gap-1">
            <Button type="button" variant="outline" size="sm" aria-label="Mover imagen hacia arriba" onClick={() => moveImage(collection, index, -1)} disabled={index === 0}><ChevronUp className="h-4 w-4" /></Button>
            <Button type="button" variant="outline" size="sm" aria-label="Mover imagen hacia abajo" onClick={() => moveImage(collection, index, 1)} disabled={index === data[collection].length - 1}><ChevronDown className="h-4 w-4" /></Button>
          </div>
          <Button type="button" variant="destructive" size="sm" onClick={() => isNew ? removeNewImage(index) : removeExistingImage(index)}><Trash2 className="mr-1 h-4 w-4" />Quitar</Button>
        </div>
      </div>
    </div>
  )

  return (
    <div className="container mx-auto space-y-4 py-10">
      <Card>
        <CardHeader className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
          <CardTitle>Fichas técnicas</CardTitle>
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={syncPackagings}>Sincronizar embalajes</Button>
            <Button variant="outline" onClick={() => window.location.href = route('inventory.technical-sheets.template')}>Descargar plantilla</Button>
            <Button variant="outline" onClick={() => fileInputRef.current?.click()} disabled={importForm.processing}>{importForm.processing ? 'Subiendo...' : 'Subir masivamente'}</Button>
            <input ref={fileInputRef} type="file" accept=".xlsx,.xls" className="hidden" onChange={handleFileChange} />
            <Button variant="outline" onClick={startCreate}>Nueva ficha</Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.warning && <div className="whitespace-pre-wrap rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">{props.flash.warning}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="space-y-4 rounded-lg border bg-gray-50 p-4">
            <div className="flex items-center gap-2 rounded border bg-white px-3 py-2">
              <input type="checkbox" id="es_semielaborado" checked={data.es_semielaborado} onChange={(event) => setData({ ...data, es_semielaborado: event.target.checked, packaging_id: '', material_id: '' })} />
              <Label htmlFor="es_semielaborado" className="cursor-pointer font-bold">Es producto semielaborado</Label>
            </div>

            <Section title="Datos generales" open>
              <div className="grid gap-3 md:grid-cols-4">
                <div className="md:col-span-2">
                  <Label>Nombre de la ficha</Label>
                  <Input value={data.nombre} onChange={(event) => setData('nombre', event.target.value)} placeholder={data.es_semielaborado ? 'Ej: Pulpa de cereza seleccionada' : 'Ej: CEREZAS-SAN FRANCISCO-C0535096-CEAMGR'} />
                  <FieldError message={errors.nombre} />
                </div>
                <div className="md:col-span-2">
                  {data.es_semielaborado ? (
                    <>
                      <Label>Material semielaborado</Label>
                      <SearchableSelect options={materialOptions} value={materialOptions.find((item) => item.value === String(data.material_id)) || null} onChange={(option) => setData('material_id', option?.value || '')} placeholder="Selecciona material" />
                      <FieldError message={errors.material_id} />
                    </>
                  ) : (
                    <>
                      <Label>Embalaje</Label>
                      <SearchableSelect options={packagingOptions} value={packagingOptions.find((item) => item.value === String(data.packaging_id)) || null} onChange={(option) => setData('packaging_id', option?.value || '')} placeholder="Selecciona embalaje" />
                      <FieldError message={errors.packaging_id} />
                    </>
                  )}
                </div>
                <div>
                  <Label>Vigencia desde</Label>
                  <Input type="date" value={data.fecha_vigencia_desde} onChange={(event) => setData('fecha_vigencia_desde', event.target.value)} />
                  <FieldError message={errors.fecha_vigencia_desde} />
                </div>
                <div>
                  <Label>Vigencia hasta</Label>
                  <Input type="date" value={data.fecha_vigencia_hasta} onChange={(event) => setData('fecha_vigencia_hasta', event.target.value)} />
                  <FieldError message={errors.fecha_vigencia_hasta} />
                </div>
                <label className="flex items-center gap-2 pt-6 text-sm"><input type="checkbox" checked={Boolean(data.activo)} onChange={(event) => setData('activo', event.target.checked)} />Activa</label>
                <div className="md:col-span-4">
                  <Label>Observación general</Label>
                  <Textarea value={data.observacion} onChange={(event) => setData('observacion', event.target.value)} />
                </div>
              </div>
            </Section>

            {!data.es_semielaborado && (
              <>
                {specSections.map(renderSpecSection)}
                <Section title="Tolerancia de defectos">
                  <div className="grid gap-3 md:grid-cols-2">
                    <div><Label>Calidad</Label><Textarea rows={4} value={data.packaging_spec.tolerancias.calidad} onChange={(event) => setSpec('tolerancias', 'calidad', event.target.value)} /></div>
                    <div><Label>Condición</Label><Textarea rows={4} value={data.packaging_spec.tolerancias.condicion} onChange={(event) => setSpec('tolerancias', 'condicion', event.target.value)} /></div>
                  </div>
                </Section>
              </>
            )}

            {renderItems(data.es_semielaborado ? 'Materiales por unidad' : 'Materiales producto primario', 'unit_items')}
            {renderItems(data.es_semielaborado ? 'Materiales por pallet' : 'Materiales embalaje secundario', 'pallet_items')}
            <FieldError message={errors.items} />

            {!data.es_semielaborado && (
              <>
                <Section title="Imágenes de embalado, etiquetado y paletizaje" open>
                  <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm text-gray-600">Hasta 20 imágenes JPG, PNG o WebP de 8 MB. Cada una requiere descripción.</p>
                    <Button type="button" variant="outline" onClick={() => imageInputRef.current?.click()} disabled={data.existing_images.length + data.new_images.length >= 20}><ImagePlus className="mr-2 h-4 w-4" />Agregar imágenes</Button>
                    <input ref={imageInputRef} type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={addImages} />
                  </div>
                  <FieldError message={errors.new_images} />
                  <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {data.existing_images.map((image, index) => renderImageCard(image, index, 'existing_images', false))}
                    {data.new_images.map((image, index) => renderImageCard(image, index, 'new_images', true))}
                  </div>
                  {data.existing_images.length + data.new_images.length === 0 && <div className="rounded border border-dashed py-8 text-center text-sm text-gray-500">Aún no hay imágenes en esta ficha.</div>}
                </Section>
                <Section title="Responsables y referencia">
                  <div className="grid gap-3 md:grid-cols-3">
                    <div><Label>Ruta SPEC</Label><Input value={data.packaging_spec.responsables.ruta_spec} onChange={(event) => setSpec('responsables', 'ruta_spec', event.target.value)} /></div>
                    <div><Label>Revisado por</Label><Input value={data.packaging_spec.responsables.revisado_por} onChange={(event) => setSpec('responsables', 'revisado_por', event.target.value)} /></div>
                    <div><Label>Autorizado por</Label><Input value={data.packaging_spec.responsables.autorizado_por} onChange={(event) => setSpec('responsables', 'autorizado_por', event.target.value)} /></div>
                  </div>
                </Section>
              </>
            )}

            <div className="flex justify-end gap-2">
              {editing && <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>}
              <Button type="submit" disabled={processing}>{processing ? 'Guardando...' : editing ? 'Actualizar ficha' : 'Crear ficha'}</Button>
            </div>
          </form>

          <div className="space-y-3">
            {sheets.map((sheet) => (
              <div key={sheet.id} className="rounded-lg border bg-white p-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                  <div>
                    <div className="font-semibold">{sheet.nombre || (sheet.es_semielaborado ? sheet.material?.nombre : sheet.packaging) || '-'}</div>
                    <div className="text-sm text-gray-600">{sheet.es_semielaborado ? (sheet.material?.nombre || '-') : (sheet.packaging || '-')}</div>
                    <div className="text-sm text-gray-600">Versión {sheet.version} · {sheet.fecha_vigencia_desde} → {sheet.fecha_vigencia_hasta || 'vigente'} · por {sheet.creator || '-'}</div>
                    <div className="mt-2 flex gap-2">
                      {sheet.activo ? <Badge>Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}
                      <Badge variant="secondary">{sheet.es_semielaborado ? 'Semielaborado' : 'Embalaje'}</Badge>
                      {!sheet.es_semielaborado && sheet.images?.length > 0 && <Badge variant="outline">{sheet.images.length} imágenes</Badge>}
                    </div>
                  </div>
                  <Button variant="outline" size="sm" onClick={() => startEdit(sheet)}>Editar</Button>
                </div>
                <div className="mt-4 grid gap-4 md:grid-cols-2">
                  {[
                    ['Unidad', sheet.unit_items, 'unit'],
                    ['Pallet', sheet.pallet_items, 'pallet'],
                  ].map(([title, items, prefix]) => (
                    <div key={prefix}>
                      <div className="mb-2 text-sm font-medium">{title}</div>
                      <div className="space-y-1 text-sm">
                        {items.map((item, index) => <div key={`${prefix}-${sheet.id}-${index}`}>{item.label} {item.replacement_label ? `(o ${item.replacement_label})` : ''}: {Number(item.cantidad_estandar).toLocaleString('es-CL')}{item.calibre ? ` [${item.calibre}]` : ''}</div>)}
                        {items.length === 0 && <div className="text-gray-500">Sin materiales por {title.toLowerCase()}.</div>}
                      </div>
                    </div>
                  ))}
                </div>
                {!sheet.es_semielaborado && sheet.images?.length > 0 && (
                  <div className="mt-4 flex gap-2 overflow-x-auto pb-1">
                    {sheet.images.slice(0, 6).map((image) => <img key={image.id} src={image.url} title={image.descripcion} alt={image.descripcion} className="h-20 w-28 flex-none rounded border bg-gray-50 object-cover" />)}
                  </div>
                )}
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
  <AuthenticatedLayout children={page} header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Inventario · Fichas técnicas</h2>} />
)
