import { Fragment, useEffect, useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Textarea } from '@/Components/ui/textarea'
import SearchableSelect from '@/Components/SearchableSelect'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { getLocalDateTimeInputValue } from '@/lib/datetime'
import { applyDetailPatch } from './detailState'

const emptyDetail = { material_id: '', position_id: '', cantidad: '', sentido: 'salida', observacion: '' }

export default function InventoryMovements({ movements, movementTypes = [], movementStatuses = [], locations = [], materials = [], services = [], productions = [], filters = {} }) {
  const { props } = usePage()
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    movement_type_id: filters.movement_type_id || '',
    material_id: filters.material_id || '',
    service_id: filters.service_id || '',
    location_id: filters.location_id || '',
    estado: filters.estado || '',
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
  })
  console.log('movements', movements)
  const [stockReferenceByIndex, setStockReferenceByIndex] = useState({})
  const [rejectDialog, setRejectDialog] = useState({ open: false, movementId: null, transferUnitId: null, label: '' })
  const { data, setData, post, processing, errors, reset, setError, clearErrors } = useForm({
    movement_type_id: movementTypes[0]?.id || '',
    fecha_movimiento: getLocalDateTimeInputValue(),
    origin_location_id: '',
    destination_location_id: '',
    referencia_tipo: '',
    referencia_id: '',
    motivo: '',
    observacion: '',
    apply_now: true,
    details: [{ ...emptyDetail }],
  })
  const rejectForm = useForm({
    reason: '',
  })

  const selectedType = movementTypes.find((item) => Number(item.id) === Number(data.movement_type_id))
  const movementTypeOptions = movementTypes.map((item) => ({ value: String(item.id), label: item.nombre }))
  const locationOptions = locations.map((item) => ({ value: String(item.id), label: item.nombre }))
  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const serviceOptions = services.map((item) => ({ value: String(item.id), label: item.name }))
  const productionOptions = productions.map((item) => ({ value: String(item.id), label: item.label }))
  const movementStatusOptions = movementStatuses.map((item) => ({ value: item, label: item }))
  const directionOptions = [
    { value: 'salida', label: 'Salida' },
    { value: 'entrada', label: 'Entrada' },
  ]
  const grupoLabelMap = { solicitud: 'Solicitud', devolucion: 'Devolución' }
  const grupoBadgeVariant = { solicitud: 'default', devolucion: 'secondary' }

  const groupedMovements = (movements?.data || []).reduce((acc, movement) => {
    const key = movement.grupo || '__ungrouped__'
    if (!acc[key]) {
      acc[key] = {
        grupo: movement.grupo,
        grupo_tipo: movement.grupo_tipo,
        label: movement.grupo ? `${grupoLabelMap[movement.grupo_tipo] || ''} ${movement.grupo}` : 'Sin agrupación',
        movements: [],
      }
    }
    acc[key].movements.push(movement)
    return acc
  }, {})

  const groupEntries = Object.values(groupedMovements)

  const requiresExplicitPosition = ['CONSUMO', 'MERMA', 'AJUSTE_NEG'].includes(selectedType?.codigo)
  const typeHints = [
    selectedType?.requiere_origen ? 'Requiere origen.' : 'Origen opcional.',
    selectedType?.requiere_destino ? 'Requiere destino.' : 'Destino opcional.',
    selectedType?.requiere_motivo ? 'Motivo obligatorio.' : 'Motivo opcional.',
    selectedType?.permite_direcciones_mixtas ? 'Permite mezclar entradas y salidas por línea.' : 'El sentido queda fijado por el tipo.',
  ]

  const setType = (value) => setData('movement_type_id', value)

  const updateDetail = (index, fieldOrPatch, value) => {
    const patch = typeof fieldOrPatch === 'string' ? { [fieldOrPatch]: value } : fieldOrPatch

    setData((current) => ({
      ...current,
      details: applyDetailPatch(current.details, index, patch),
    }))

    if (Object.prototype.hasOwnProperty.call(patch, 'position_id')) {
      clearErrors(`details.${index}.position_id`)
    }
  }

  const addDetail = () => setData('details', [...data.details, { ...emptyDetail }])
  const removeDetail = (index) => setData('details', data.details.filter((_, currentIndex) => currentIndex !== index))

  const submit = (event) => {
    event.preventDefault()

    const missingPositionIndex = data.details.findIndex((detail, index) => {
      const availablePositions = stockReferenceByIndex[index]?.positions || []

      return requiresExplicitPosition
        && detail.material_id
        && availablePositions.length > 0
        && !detail.position_id
    })

    if (missingPositionIndex !== -1) {
      const key = `details.${missingPositionIndex}.position_id`
      const message = 'Debes seleccionar una posición de stock para esta línea.'
      setError(key, message)
      return
    }

    clearErrors()

    post(route('inventory.movements.store'), {
      preserveScroll: true,
      onSuccess: () => reset({
        movement_type_id: movementTypes[0]?.id || '',
        fecha_movimiento: getLocalDateTimeInputValue(),
        origin_location_id: '',
        destination_location_id: '',
        referencia_tipo: '',
        referencia_id: '',
        motivo: '',
        observacion: '',
        apply_now: true,
        details: [{ ...emptyDetail }],
      }),
    })
  }

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.movements.index'), filterData, { preserveScroll: true, preserveState: true })
  }

  const applyMovement = (id) => router.post(route('inventory.movements.apply', id), {}, { preserveScroll: true })
  const confirmMovement = (id) => router.post(route('inventory.movements.confirm', id), {}, { preserveScroll: true })
  const confirmTransferUnit = (movementId, transferUnitId) => router.post(route('inventory.movements.confirm', movementId), {
    transfer_unit_ids: [transferUnitId],
  }, { preserveScroll: true })
  const openRejectDialog = (movementId, transferUnitId, label) => {
    rejectForm.setData('reason', '')
    rejectForm.clearErrors()
    setRejectDialog({ open: true, movementId, transferUnitId, label })
  }

  const closeRejectDialog = () => {
    setRejectDialog({ open: false, movementId: null, transferUnitId: null, label: '' })
    rejectForm.reset()
    rejectForm.clearErrors()
  }

  const rejectTransferUnit = (event) => {
    event.preventDefault()
    if (!rejectDialog.movementId || !rejectDialog.transferUnitId) {
      return
    }

    rejectForm
      .transform((current) => ({
        transfer_unit_ids: [rejectDialog.transferUnitId],
        reason: current.reason,
      }))
      .post(route('inventory.movements.reject', rejectDialog.movementId), {
      preserveScroll: true,
      onSuccess: () => closeRejectDialog(),
    })
  }

  useEffect(() => {
    let cancelled = false

    const loadStockReferences = async () => {
      if (!data.origin_location_id) {
        setStockReferenceByIndex({})
        return
      }

      const entries = await Promise.all(data.details.map(async (detail, index) => {
        if (!detail.material_id) {
          return [index, null]
        }

        try {
          const response = await window.axios.get(route('inventory.movements.stock-reference'), {
            params: {
              origin_location_id: data.origin_location_id,
              material_id: detail.material_id,
            },
          })

          return [index, {
            stock_actual: Number(response.data.stock_actual || 0),
            positions: Array.isArray(response.data.positions) ? response.data.positions : [],
            error: null,
          }]
        } catch (error) {
          return [index, {
            stock_actual: null,
            positions: [],
            error: 'No fue posible consultar el stock del origen.',
          }]
        }
      }))

      if (!cancelled) {
        setStockReferenceByIndex(Object.fromEntries(entries.filter((entry) => entry[1] !== null)))
      }
    }

    loadStockReferences()

    return () => {
      cancelled = true
    }
  }, [data.details, data.origin_location_id])

  const detailPositionError = (index) => {
    const key = `details.${index}.position_id`

    if (errors[key]) {
      return errors[key]
    }

    const availablePositions = stockReferenceByIndex[index]?.positions || []
    const detail = data.details[index]

    if (
      requiresExplicitPosition
      && detail?.material_id
      && availablePositions.length > 0
      && !detail?.position_id
    ) {
      return 'Selecciona una posición para continuar.'
    }

    return null
  }

  const hasBlockingPositionErrors = data.details.some((detail, index) => {
    const availablePositions = stockReferenceByIndex[index]?.positions || []

    return requiresExplicitPosition
      && detail.material_id
      && availablePositions.length > 0
      && !detail.position_id
  })

  return (
    <div className="mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Movimientos internos</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}
          <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            Registra movimientos manuales y revisa su trazabilidad. Para `CONSUMO`, `MERMA` y `AJUSTE_NEG`, la posición es obligatoria cuando el stock de la ubicación origen ya está modelado por posiciones.
          </div>

          <form onSubmit={submit} className="rounded border bg-gray-50 p-4 space-y-4">
            <div className="rounded border bg-white p-4 space-y-4">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <div className="text-sm font-medium text-slate-900">Cabecera del movimiento</div>
                  <div className="text-xs text-slate-500">Define tipo, ruta operativa y referencia antes de cargar líneas.</div>
                </div>
                {selectedType ? (
                  <div className="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-xs text-blue-900 max-w-md">
                    <div className="font-medium">{selectedType.nombre}</div>
                    <div className="mt-1 flex flex-wrap gap-x-3 gap-y-1">
                      {typeHints.map((hint) => (
                        <span key={hint}>{hint}</span>
                      ))}
                    </div>
                  </div>
                ) : null}
              </div>

              <div className="grid gap-3 md:grid-cols-4">
                <div>
                  <Label>Tipo de movimiento</Label>
                  <SearchableSelect
                    options={movementTypeOptions}
                    value={movementTypeOptions.find((item) => item.value === String(data.movement_type_id)) || null}
                    onChange={(option) => setType(option?.value || '')}
                    placeholder="Selecciona tipo"
                    isClearable={false}
                  />
                  {errors.movement_type_id && <div className="mt-1 text-sm text-red-600">{errors.movement_type_id}</div>}
                </div>
                <div>
                  <Label>Fecha y hora</Label>
                  <Input type="datetime-local" value={data.fecha_movimiento} onChange={(e) => setData('fecha_movimiento', e.target.value)} />
                  {errors.fecha_movimiento && <div className="mt-1 text-sm text-red-600">{errors.fecha_movimiento}</div>}
                </div>
                <div>
                  <Label>Ubicación origen</Label>
                  <SearchableSelect
                    options={locationOptions}
                    value={locationOptions.find((item) => item.value === String(data.origin_location_id)) || null}
                    onChange={(option) => setData('origin_location_id', option?.value || '')}
                    placeholder="Sin origen"
                  />
                  {errors.origin_location_id && <div className="mt-1 text-sm text-red-600">{errors.origin_location_id}</div>}
                </div>
                <div>
                  <Label>Ubicación destino</Label>
                  <SearchableSelect
                    options={locationOptions}
                    value={locationOptions.find((item) => item.value === String(data.destination_location_id)) || null}
                    onChange={(option) => setData('destination_location_id', option?.value || '')}
                    placeholder="Sin destino"
                  />
                  {errors.destination_location_id && <div className="mt-1 text-sm text-red-600">{errors.destination_location_id}</div>}
                </div>
                <div>
                  <Label>Referencia de producción</Label>
                  <SearchableSelect
                    options={productionOptions}
                    value={productionOptions.find((item) => item.value === String(data.referencia_id)) || null}
                    onChange={(option) => {
                      setData('referencia_tipo', option?.value ? 'production' : '')
                      setData('referencia_id', option?.value || '')
                    }}
                    placeholder="Sin referencia operativa"
                  />
                  {errors.referencia_id && <div className="mt-1 text-sm text-red-600">{errors.referencia_id}</div>}
                </div>
                <div>
                  <Label>Motivo</Label>
                  <Input value={data.motivo} onChange={(e) => setData('motivo', e.target.value)} />
                  {errors.motivo && <div className="mt-1 text-sm text-red-600">{errors.motivo}</div>}
                </div>
                <div className="md:col-span-2">
                  <Label>Observación</Label>
                  <Textarea value={data.observacion} onChange={(e) => setData('observacion', e.target.value)} />
                  {errors.observacion && <div className="mt-1 text-sm text-red-600">{errors.observacion}</div>}
                </div>
              </div>
            </div>

            <div className="space-y-3 rounded border bg-white p-4">
              <div className="flex items-center justify-between gap-4">
                <div>
                  <div className="font-medium text-slate-900">Líneas de detalle</div>
                  <div className="text-xs text-slate-500">Agrega una línea por material. Si la ubicación origen tiene stock posicionado, podrás seleccionar una posición específica.</div>
                </div>
                <Button type="button" variant="outline" onClick={addDetail}>Agregar línea</Button>
              </div>
              {data.details.map((detail, index) => (
                <div key={`detail-${index}`} className="grid gap-3 rounded border bg-white p-3 md:grid-cols-12">
                  <div className="md:col-span-5">
                    <Label>Material</Label>
                    <SearchableSelect
                      options={materialOptions}
                      value={materialOptions.find((item) => item.value === String(detail.material_id)) || null}
                      onChange={(option) => {
                        updateDetail(index, {
                          material_id: option?.value || '',
                          position_id: '',
                        })
                      }}
                      placeholder="Selecciona material"
                    />
                    {data.origin_location_id && stockReferenceByIndex[index]?.stock_actual !== null && stockReferenceByIndex[index]?.stock_actual !== undefined ? (
                      <div className="mt-1 text-xs text-gray-500">
                        Stock actual en ubicación origen: {stockReferenceByIndex[index].stock_actual.toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                      </div>
                    ) : null}
                    {stockReferenceByIndex[index]?.error ? <div className="mt-1 text-xs text-red-600">{stockReferenceByIndex[index].error}</div> : null}
                  </div>
                  <div className="md:col-span-3">
                    <Label>Posición origen</Label>
                    <SearchableSelect
                      options={(stockReferenceByIndex[index]?.positions || []).map((position) => ({
                        value: String(position.id),
                        label: `${position.logistic_unit?.license_plate_number || 'Sin LPN'} · ${position.location?.codigo || 'Sin ubicación'} · ${Number(position.quantity).toLocaleString('es-CL', { maximumFractionDigits: 4 })}${position.lot_code ? ` · ${position.lot_code}` : ''}`,
                      }))}
                      value={(stockReferenceByIndex[index]?.positions || []).map((position) => ({
                        value: String(position.id),
                        label: `${position.logistic_unit?.license_plate_number || 'Sin LPN'} · ${position.location?.codigo || 'Sin ubicación'} · ${Number(position.quantity).toLocaleString('es-CL', { maximumFractionDigits: 4 })}${position.lot_code ? ` · ${position.lot_code}` : ''}`,
                      })).find((item) => item.value === String(detail.position_id)) || null}
                      onChange={(option) => updateDetail(index, 'position_id', option?.value || '')}
                      placeholder={(stockReferenceByIndex[index]?.positions || []).length ? 'Selecciona posición' : 'Sin posiciones específicas'}
                      isDisabled={!(stockReferenceByIndex[index]?.positions || []).length}
                    />
                    {requiresExplicitPosition && (stockReferenceByIndex[index]?.positions || []).length ? (
                      <div className="mt-1 text-xs font-medium text-amber-700">
                        Obligatoria para {selectedType?.nombre?.toLowerCase() || 'este movimiento'}.
                      </div>
                    ) : null}
                    {!requiresExplicitPosition && (stockReferenceByIndex[index]?.positions || []).length ? (
                      <div className="mt-1 text-xs text-gray-500">
                        Opcional para este tipo; recomendada si necesitas trazabilidad exacta.
                      </div>
                    ) : null}
                    {detailPositionError(index) ? <div className="mt-1 text-xs text-red-600">{detailPositionError(index)}</div> : null}
                  </div>
                  <div className="md:col-span-1">
                    <Label>Cantidad</Label>
                    <Input type="number" step="0.0001" value={detail.cantidad} onChange={(e) => updateDetail(index, 'cantidad', e.target.value)} />
                  </div>
                  <div className="md:col-span-1">
                    <Label>Sentido</Label>
                    <SearchableSelect
                      options={directionOptions}
                      value={directionOptions.find((item) => item.value === detail.sentido) || null}
                      onChange={(option) => updateDetail(index, 'sentido', option?.value || 'salida')}
                      placeholder="Selecciona sentido"
                      isClearable={false}
                      isDisabled={!selectedType?.permite_direcciones_mixtas}
                    />
                    {!selectedType?.permite_direcciones_mixtas ? <div className="mt-1 text-xs text-gray-500">Fijado por tipo de movimiento.</div> : null}
                  </div>
                  <div className="md:col-span-1">
                    <Label>Obs.</Label>
                    <Input value={detail.observacion} onChange={(e) => updateDetail(index, 'observacion', e.target.value)} />
                  </div>
                  <div className="md:col-span-1 flex items-end">
                    <Button type="button" variant="destructive" size="sm" onClick={() => removeDetail(index)} disabled={data.details.length === 1}>Quitar</Button>
                  </div>
                </div>
              ))}
              {errors.details && <div className="text-sm text-red-600">{errors.details}</div>}
            </div>

            <div className="flex items-center justify-between">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={Boolean(data.apply_now)} onChange={(e) => setData('apply_now', e.target.checked)} />
                Aplicar inmediatamente
              </label>
              <Button type="submit" disabled={processing || hasBlockingPositionErrors}>
                {processing ? 'Guardando...' : 'Registrar movimiento'}
              </Button>
            </div>
          </form>

          <form onSubmit={applyFilters} className="grid gap-3 rounded border p-4 md:grid-cols-9">
            <Input value={filterData.q} onChange={(e) => setFilterData((current) => ({ ...current, q: e.target.value }))} placeholder="Folio, motivo u observación" />
            <SearchableSelect
              options={movementTypeOptions}
              value={movementTypeOptions.find((item) => item.value === String(filterData.movement_type_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, movement_type_id: option?.value || '' }))}
              placeholder="Todos los tipos"
            />
            <SearchableSelect
              options={materialOptions}
              value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, material_id: option?.value || '' }))}
              placeholder="Todos los materiales"
            />
            <SearchableSelect
              options={serviceOptions}
              value={serviceOptions.find((item) => item.value === String(filterData.service_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, service_id: option?.value || '' }))}
              placeholder="Todos los servicios"
            />
            <SearchableSelect
              options={locationOptions}
              value={locationOptions.find((item) => item.value === String(filterData.location_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, location_id: option?.value || '' }))}
              placeholder="Todas las ubicaciones"
            />
            <SearchableSelect
              options={movementStatusOptions}
              value={movementStatusOptions.find((item) => item.value === String(filterData.estado)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, estado: option?.value || '' }))}
              placeholder="Todos los estados"
            />
            <Input value={filterData.date_from} type="date" onChange={(e) => setFilterData((current) => ({ ...current, date_from: e.target.value }))} />
            <Input value={filterData.date_to} type="date" onChange={(e) => setFilterData((current) => ({ ...current, date_to: e.target.value }))} />
            <div className="flex gap-2">
              <Button type="submit">Filtrar</Button>
              <Button
                type="button"
                variant="outline"
                onClick={() => {
                  const next = {
                    q: '',
                    movement_type_id: '',
                    material_id: '',
                    service_id: '',
                    location_id: '',
                    estado: '',
                    date_from: '',
                    date_to: '',
                  }
                  setFilterData(next)
                  router.get(route('inventory.movements.index'), next, { preserveScroll: true, preserveState: true })
                }}
              >
                Limpiar
              </Button>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Folio</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Ruta</TableHead>
                <TableHead>Detalle</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {groupEntries.map((group) => (
                <Fragment key={group.grupo || '__ungrouped__'}>
                  <TableRow className="bg-slate-100 hover:bg-slate-100">
                    <TableCell colSpan={6} className="py-2">
                      <div className="flex items-center gap-2">
                        {group.grupo ? (
                          <Badge variant={grupoBadgeVariant[group.grupo_tipo] || 'outline'}>
                            {grupoLabelMap[group.grupo_tipo] || group.grupo_tipo}
                          </Badge>
                        ) : null}
                        <span className={`text-sm font-semibold ${group.grupo ? 'text-slate-800' : 'text-slate-400 italic'}`}>
                          {group.label}
                        </span>
                        <span className="text-xs text-slate-400">· {group.movements.length} movimiento(s)</span>
                      </div>
                    </TableCell>
                  </TableRow>
                  {group.movements.map((movement) => (
                    <TableRow key={movement.id}>
                      <TableCell>
                        <div className="font-medium">{movement.folio}</div>
                        <div className="text-xs text-gray-500">{movement.fecha_movimiento}</div>
                      </TableCell>
                      <TableCell>{movement.tipo}</TableCell>
                      <TableCell>{movement.origen || '-'} → {movement.destino || '-'}</TableCell>
                      <TableCell>
                        <div className="space-y-1">
                          {movement.details.map((detail) => (
                            <div key={detail.id} className="text-xs">
                              {detail.sentido}: {detail.material} · {Number(detail.cantidad).toLocaleString('es-CL')}
                              {detail.service ? ` · ${detail.service}` : ''}
                              {detail.position_label ? ` · ${detail.position_label}` : ''}
                            </div>
                          ))}
                          {movement.transfer_units?.length ? movement.transfer_units.map((unit) => (
                            <div key={unit.id} className="rounded border border-slate-200 bg-slate-50 px-2 py-2 text-xs text-slate-700">
                              <div>
                                Pallet {unit.license_plate_number || `#${unit.id}`} · {Number(unit.quantity).toLocaleString('es-CL')} · {unit.status}
                                {unit.origin_snapshot || unit.destination_snapshot ? ` · ${unit.origin_snapshot || '-'} → ${unit.destination_snapshot || '-'}` : ''}
                                {unit.rejection_reason ? ` · ${unit.rejection_reason}` : ''}
                              </div>
                              {unit.position_count ? (
                                <div className="mt-1 space-y-1 text-[11px] text-slate-600">
                                  <div>{unit.position_count} posicion(es) vinculadas al despacho:</div>
                                  {unit.position_labels?.map((label, idx) => (
                                    <div key={`${unit.id}-${idx}`}>- {label}</div>
                                  ))}
                                </div>
                              ) : (
                                <div className="mt-1 text-[11px] text-slate-500">Sin snapshot de posiciones.</div>
                              )}
                            </div>
                          )) : null}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline">{movement.estado}</Badge>
                        {movement.receipt_hash ? <div className="mt-1 text-[10px] text-gray-500">{movement.receipt_hash.slice(0, 16)}...</div> : null}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-2">
                          {movement.estado === 'borrador' && <Button type="button" size="sm" onClick={() => applyMovement(movement.id)}>Aplicar</Button>}
                          {movement.estado === 'aplicado' && movement.destino && !movement.transfer_units?.length && <Button type="button" variant="outline" size="sm" onClick={() => confirmMovement(movement.id)}>Confirmar</Button>}
                          {movement.estado === 'aplicado' && (movement.transfer_units || []).filter((unit) => unit.status === 'in_transit').map((unit) => (
                            <div key={unit.id} className="flex gap-2">
                              <Button type="button" variant="outline" size="sm" onClick={() => confirmTransferUnit(movement.id, unit.id)}>
                                Recibir {unit.license_plate_number || `#${unit.id}`}
                              </Button>
                              <Button type="button" variant="destructive" size="sm" onClick={() => openRejectDialog(movement.id, unit.id, unit.license_plate_number || `#${unit.id}`)}>
                                Rechazar
                              </Button>
                            </div>
                          ))}
                          {movement.estado === 'aplicado' && (movement.transfer_units || []).filter((unit) => unit.status === 'return_pending').map((unit) => (
                            <Button key={unit.id} type="button" variant="outline" size="sm" onClick={() => confirmTransferUnit(movement.id, unit.id)}>
                              Confirmar retorno {unit.license_plate_number || `#${unit.id}`}
                            </Button>
                          ))}
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </Fragment>
              ))}
            </TableBody>
          </Table>

          {movements?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {movements.from ?? 0} a {movements.to ?? 0} de {movements.total ?? 0}</div>
              <div className="flex gap-1">
                {movements.links.map((link, index) => (
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

      <Dialog open={rejectDialog.open} onOpenChange={(open) => !open && closeRejectDialog()}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Rechazar pallet en tránsito</DialogTitle>
            <DialogDescription>
              {rejectDialog.label ? `Indica el motivo para ${rejectDialog.label}. El retorno quedará pendiente para origen.` : ''}
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={rejectTransferUnit} className="space-y-4">
            <div>
              <Label>Motivo del rechazo</Label>
              <Textarea value={rejectForm.data.reason} onChange={(e) => rejectForm.setData('reason', e.target.value)} />
              {rejectForm.errors.reason ? <div className="mt-1 text-sm text-red-600">{rejectForm.errors.reason}</div> : null}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={closeRejectDialog}>Cancelar</Button>
              <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                {rejectForm.processing ? 'Guardando...' : 'Confirmar rechazo'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  )
}

InventoryMovements.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Movimientos</h2>}
  />
)
