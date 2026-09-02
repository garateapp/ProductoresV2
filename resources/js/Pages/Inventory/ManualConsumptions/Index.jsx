import { useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import Select from 'react-select'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
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
  tipo_accion: 'reembalaje',
  semielaborado_material_id: '',
  materials: [{ material_id: '', cantidad: '' }],
  fecha: new Date().toISOString().slice(0, 10),
  location_id: '',
  linea: '',
  turno: '',
  id_g_produccion: '',
  folios: [],
  folio_nuevo: '',
  observacion: '',
}

const accionHint = {
  reembalaje: 'Selecciona el folio de origen (de la vista de producción), uno o más materiales y sus cantidades consumidas, y opcionalmente el nuevo folio del pallet.',
  reproceso: 'Selecciona el folio de origen (de la vista de producción), uno o más materiales y sus cantidades consumidas, y opcionalmente el nuevo folio del pallet.',
  completar_saldos: 'Selecciona los folios de origen que completarán los saldos y uno o más materiales con sus cantidades a consolidar.',
}

const estadoVariant = (estado) =>
  ({
    aplicado: 'default',
    borrador: 'secondary',
  })[estado] || 'outline'

const folioLabel = (f) => {
  const embalaje = f.n_embalaje || f.c_embalaje || ''
  const qty = typeof f.cantidad === 'number' && f.cantidad > 0 ? ` · ${f.cantidad}` : ''
  const fecha = f.fecha_produccion ? ` · ${f.fecha_produccion}` : ''
  return `${f.folio}${embalaje ? ` · ${embalaje}` : ''}${qty}${fecha}`
}

export default function ManualConsumptions({ history = {}, materials = [], locations = [], filters = {}, tipoOptions = [], origenFolios = [], semielaboradoCompositions = {} }) {
  const { props } = usePage()
  const { data, setData, post, processing, errors, reset } = useForm(emptyForm)

  const materialOptions = materials.map((m) => ({ value: String(m.id), label: `${m.codigo} · ${m.nombre}` }))
  const locationOptions = locations.map((l) => ({ value: String(l.id), label: `${l.codigo} · ${l.nombre}` }))
  const accionOptions = tipoOptions.map((t) => ({ value: t.value, label: t.label }))
  const origenOptions = origenFolios.map((f) => ({ value: String(f.id_g_produccion), label: folioLabel(f) }))
  const semielaboradoOptions = materials
    .filter((m) => m.tipo_material === 'semielaborado')
    .map((m) => ({ value: String(m.id), label: `${m.codigo} · ${m.nombre}` }))

  const materialMap = materials.reduce((acc, m) => {
    acc[String(m.id)] = m
    return acc
  }, {})

  const materialRow = (materialId) => ({
    material_id: materialId ?? '',
    cantidad: '',
  })

  const addMaterial = () => setData((prev) => ({ ...prev, materials: [...(prev.materials || []), materialRow()] }))

  const removeMaterial = (index) =>
    setData((prev) => ({
      ...prev,
      materials: (prev.materials || []).filter((_, i) => i !== index),
    }))

  const setMaterialField = (index, field, value) =>
    setData((prev) => ({
      ...prev,
      materials: (prev.materials || []).map((row, i) => (i === index ? { ...row, [field]: value } : row)),
    }))

  const onSemielaboradoChange = (value) => {
    const id = value || ''
    setData((prev) => ({
      ...prev,
      semielaborado_material_id: id,
      materials: id ? (semielaboradoCompositions[id] || []).map((c) => ({ material_id: c.material_id, cantidad: c.cantidad_estandar })) : [],
    }))
  }

  const setField = (field, value) => setData(field, value)

  const onTipoChange = (value) => {
    setData((prev) => ({
      ...prev,
      tipo_accion: value,
      id_g_produccion: '',
      folios: [],
      folio_nuevo: '',
      semielaborado_material_id: '',
      materials: data.materials && data.materials.length ? data.materials : [materialRow()],
    }))
  }

  const submit = (event) => {
    event.preventDefault()
    post(route('inventory.manual-consumptions.store'), {
      preserveScroll: true,
      onSuccess: () => {
        reset()
      },
    })
  }

  const retry = (row) => {
    if (!window.confirm(`¿Reintentar el consumo #${row.id} (${row.tipo_label})?`)) return
    post(route('inventory.manual-consumptions.retry', row.id), { preserveScroll: true })
  }

  const isCompletar = data.tipo_accion === 'completar_saldos'
  const isReembalaje = data.tipo_accion === 'reembalaje' || data.tipo_accion === 'reproceso'

  const folioValue = (options, id) => options.find((o) => o.value === String(id)) || null
  const selectedOrigen = folioValue(origenOptions, data.id_g_produccion)
  const selectedFoliosMany = origenOptions.filter((o) => (data.folios || []).includes(Number(o.value)))

  return (
    <div className="container mx-auto py-10 space-y-6">
      <Card>
        <CardHeader>
          <CardTitle className="text-2xl font-bold">Consumo manual de materiales</CardTitle>
        </CardHeader>
        <CardContent className="space-y-8">
          <p className="text-sm text-slate-500">
            Registra consumos de inventario que no provienen del consumo automático: reembalajes, reprocesos y
            completar saldos. Cada acción genera un movimiento tipo CONSUMO que queda separado en el reporte de consumo.
          </p>

          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="rounded-lg border bg-slate-50/50 p-6 space-y-6">
            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="tipo_accion">Tipo de acción</Label>
                <SearchableSelect
                  options={accionOptions}
                  value={accionOptions.find((o) => o.value === data.tipo_accion) || accionOptions[0]}
                  onChange={(option) => onTipoChange(option?.value || 'reembalaje')}
                  placeholder="Selecciona tipo"
                  isClearable={false}
                />
              </div>
              <div>
                <Label htmlFor="semielaborado_material_id">Semielaborado <span className="text-slate-400">(opcional)</span></Label>
                <SearchableSelect
                  options={semielaboradoOptions}
                  value={semielaboradoOptions.find((o) => o.value === String(data.semielaborado_material_id)) || null}
                  onChange={(option) => onSemielaboradoChange(option?.value || '')}
                  placeholder="Si consumes un semielaborado, precarga aquí sus componentes"
                  isClearable
                />
                <p className="mt-1 text-xs text-slate-500">Solo se descuenta stock de los componentes que dejes listados abajo.</p>
              </div>
            </div>

            <div>
              <Label>Materiales a consumir <span className="text-red-500">*</span></Label>
              <div className="mt-2 space-y-3">
                {(data.materials || []).map((row, index) => (
                  <div key={index} className="grid gap-4 md:grid-cols-[1fr_180px_auto] items-end">
                    <div>
                      <SearchableSelect
                        options={materialOptions}
                        value={materialOptions.find((o) => o.value === String(row.material_id)) || null}
                        onChange={(option) => setMaterialField(index, 'material_id', option?.value || '')}
                        placeholder="Selecciona material"
                        isClearable={false}
                      />
                    </div>
                    <div>
                      <Input type="number" step="any" min="0" value={row.cantidad} onChange={(e) => setMaterialField(index, 'cantidad', e.target.value)} placeholder="Cantidad" />
                    </div>
                    <div>
                      <Button type="button" variant="ghost" size="sm" onClick={() => removeMaterial(index)} disabled={(data.materials || []).length <= 1}>
                        Quitar
                      </Button>
                    </div>
                  </div>
                ))}
                <Button type="button" variant="outline" size="sm" onClick={addMaterial}>
                  + Agregar material
                </Button>
                {errors.materials && <div className="mt-1 text-sm text-red-600">{errors.materials}</div>}
              </div>
            </div>

            <div className="grid gap-6 md:grid-cols-3">
              <div>
                <Label htmlFor="fecha">Fecha</Label>
                <Input id="fecha" type="date" value={data.fecha} onChange={(e) => setField('fecha', e.target.value)} className="mt-1" />
                {errors.fecha && <div className="mt-1 text-sm text-red-600">{errors.fecha}</div>}
              </div>
              <div>
                <Label htmlFor="location_id">Ubicación de origen</Label>
                <SearchableSelect
                  options={locationOptions}
                  value={locationOptions.find((o) => o.value === String(data.location_id)) || null}
                  onChange={(option) => setField('location_id', option?.value || '')}
                  placeholder="Autodetecta por línea/turno"
                  isClearable
                />
                {errors.location_id && <div className="mt-1 text-sm text-red-600">{errors.location_id}</div>}
              </div>
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <Label htmlFor="linea">Línea</Label>
                  <Input id="linea" value={data.linea} onChange={(e) => setField('linea', e.target.value)} placeholder="Ej: R Packing" className="mt-1" />
                </div>
                <div>
                  <Label htmlFor="turno">Turno</Label>
                  <Input id="turno" value={data.turno} onChange={(e) => setField('turno', e.target.value)} placeholder="Ej: Turno 1" className="mt-1" />
                </div>
              </div>
            </div>

            {isReembalaje && (
              <div className="grid gap-6 md:grid-cols-2">
                <div>
                  <Label htmlFor="id_g_produccion">Folio de origen <span className="text-red-500">*</span></Label>
                  <SearchableSelect
                    options={origenOptions}
                    value={selectedOrigen}
                    onChange={(option) => setField('id_g_produccion', option?.value || '')}
                    placeholder="Selecciona el folio de origen (vista de producción)"
                    isClearable
                  />
                  {errors.id_g_produccion && <div className="mt-1 text-sm text-red-600">{errors.id_g_produccion}</div>}
                </div>
                <div>
                  <Label htmlFor="folio_nuevo">Nuevo folio del pallet <span className="text-slate-400">(opcional)</span></Label>
                  <Input id="folio_nuevo" value={data.folio_nuevo} onChange={(e) => setField('folio_nuevo', e.target.value)} placeholder="Folio de producción otorgado al pallet" className="mt-1" />
                </div>
              </div>
            )}

            {isCompletar && (
              <div>
                <Label htmlFor="folios">Folios de origen que completarán los saldos <span className="text-red-500">*</span></Label>
                <Select
                  id="folios"
                  isMulti
                  options={origenOptions}
                  value={selectedFoliosMany}
                  onChange={(selected) => setField('folios', (selected || []).map((o) => Number(o.value)))}
                  placeholder="Selecciona uno o más folios de origen (vista de producción)"
                  className="mt-1 text-sm"
                  classNamePrefix="react-select"
                  menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
                  noOptionsMessage={() => 'Sin resultados'}
                />
                {errors.folios && <div className="mt-1 text-sm text-red-600">{errors.folios}</div>}
                <p className="mt-1 text-xs text-slate-500">Se consolidan en un solo pallet completando el saldo.</p>
              </div>
            )}

            <div>
              <Label htmlFor="observacion">Observación</Label>
              <Textarea id="observacion" value={data.observacion} onChange={(e) => setField('observacion', e.target.value)} rows={2} className="mt-1" />
            </div>

            <div className="rounded bg-white border px-4 py-3 text-sm text-slate-600">
              {accionHint[data.tipo_accion]}
            </div>

            <div className="flex justify-end pt-2">
              <Button type="submit" disabled={processing}>Registrar consumo</Button>
            </div>
          </form>

          <div>
            <h3 className="mb-3 text-sm font-semibold text-slate-900">Historial de consumos manuales</h3>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Acción</TableHead>
                  <TableHead>Material</TableHead>
                  <TableHead className="text-right">Cantidad</TableHead>
                  <TableHead>Origen</TableHead>
                  <TableHead>Folio(s)</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(history?.data || []).map((row) => (
                  <TableRow key={row.id}>
                    <TableCell>{row.fecha}</TableCell>
                    <TableCell><Badge>{row.tipo_label}</Badge></TableCell>
                    <TableCell>
                      {(row.details && row.details.length ? row.details : [{ material_codigo: row.material_codigo, material_nombre: row.material_nombre, cantidad: row.cantidad }]).map((d, i) => (
                        <div key={i}>
                          <div className="font-medium">{d.material_nombre}</div>
                          <div className="text-xs text-slate-500">{d.material_codigo} · {d.cantidad}</div>
                        </div>
                      ))}
                    </TableCell>
                    <TableCell className="text-right font-semibold">
                      {(row.details && row.details.length ? row.details : [{ cantidad: row.cantidad }]).reduce((sum, d) => sum + Number(d.cantidad || 0), 0)}
                    </TableCell>
                    <TableCell>{row.location_name || <span className="text-slate-400">—</span>}</TableCell>
                    <TableCell className="font-mono text-xs">
                      {row.tipo_accion === 'completar_saldos'
                        ? (row.folios || []).map((f) => (typeof f === 'object' ? f.folio : f)).join(', ')
                        : (row.folio_nuevo || row.folios?.map((f) => (typeof f === 'object' ? f.folio : f)).join(', ') || '—')}
                    </TableCell>
                    <TableCell>
                      <Badge variant={estadoVariant(row.estado)}>{row.estado}</Badge>
                    </TableCell>
                    <TableCell className="text-right">
                      {row.estado !== 'aplicado' && (
                        <Button variant="ghost" size="sm" onClick={() => retry(row)}>Reintentar</Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
                {!(history?.data || []).length && (
                  <TableRow>
                    <TableCell colSpan={8} className="py-8 text-center text-sm text-slate-500">
                      Aún no hay consumos manuales registrados.
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

ManualConsumptions.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Consumo manual</h2>}
  />
)
