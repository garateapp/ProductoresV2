import { useEffect, useState, Fragment } from 'react'
import axios from 'axios'
import { Link, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import SearchableSelect from '@/Components/SearchableSelect'
import {
  Dialog,
  DialogContent,
  DialogDescription,
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
import { getLocalDateInputValue } from '@/lib/datetime'

const buildInitialForm = () => ({
  process_id: '',
  fecha: getLocalDateInputValue(),
  turno: 'DÍA',
  linea: 'Línea 1',
  especie: '',
  variedad: '',
  packaging_id: '',
  cantidad_cajas: '',
  cantidad_pallets: '',
  manual_override: false,
  observacion: '',
})

const roundTo = (value, decimals = 4) => {
  const factor = 10 ** decimals
  return Math.round((Number(value) + Number.EPSILON) * factor) / factor
}

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

export default function InventoryProductions({ productions, packagings = [], processes = [], speciesOptions = [], varietiesBySpecies = {} }) {
  const { props } = usePage()
  const [preview, setPreview] = useState(null)
  const [loadingPreview, setLoadingPreview] = useState(false)
  const [selectedProduction, setSelectedProduction] = useState(null)
  const processOptions = processes.map((item) => ({ value: String(item.id), label: item.label }))
  const packagingOptions = packagings.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const { data, setData, post, processing, reset, errors } = useForm(buildInitialForm())
  const selectedProcess = processes.find((item) => String(item.id) === String(data.process_id)) || null
  const selectedPackaging = packagings.find((item) => String(item.id) === String(data.packaging_id)) || null
  const varietyOptions = varietiesBySpecies[data.especie] || []
  const standardWeight = Number(selectedPackaging?.peso_std || 0)
  const boxesPerPallet = Number(selectedPackaging?.cantidad_cajas || 0)
  const selectedNetWeight = (() => {
    if (!selectedProcess) {
      return 0
    }

    const packagingCode = selectedPackaging?.codigo || selectedProcess.default_packaging_code || ''
    if (packagingCode && selectedProcess.weights_by_packaging?.[packagingCode]) {
      return Number(selectedProcess.weights_by_packaging[packagingCode] || 0)
    }

    return Number(selectedProcess.net_weight || 0)
  })()
  const canAutoCalculate = Boolean(selectedProcess && selectedPackaging && selectedNetWeight > 0 && standardWeight > 0)
  const fieldsAreLocked = canAutoCalculate && !data.manual_override

  useEffect(() => {
    if (!data.variedad) {
      return
    }

    const hasCurrentVariety = varietyOptions.some((item) => item.value === data.variedad)
    if (!hasCurrentVariety) {
      setData('variedad', '')
    }
  }, [data.especie, data.variedad, setData, varietyOptions])

  useEffect(() => {
    if (data.manual_override || !canAutoCalculate) {
      return
    }

    const boxes = roundTo(selectedNetWeight / standardWeight)
    const pallets = boxesPerPallet > 0 ? roundTo(boxes / boxesPerPallet) : 0

    setData('cantidad_cajas', String(boxes))
    setData('cantidad_pallets', String(pallets))
  }, [data.manual_override, data.process_id, data.packaging_id, selectedNetWeight, standardWeight, boxesPerPallet])

  const handleProcessChange = (option) => {
    const value = option?.value || ''
    const process = processes.find((item) => String(item.id) === value)

    setData('process_id', value)

    if (!process) {
      setData('packaging_id', '')
      setData('especie', '')
      setData('variedad', '')
      if (!data.manual_override) {
        setData('cantidad_cajas', '')
        setData('cantidad_pallets', '')
      }
      return
    }

    if (process.fecha) {
      setData('fecha', process.fecha)
    }
    if (process.turno) {
      setData('turno', process.turno)
    }
    if (process.linea) {
      setData('linea', process.linea)
    }
    setData('especie', process.especie || '')
    setData('variedad', process.default_variedad || '')
    setData('packaging_id', process.default_packaging_id ? String(process.default_packaging_id) : '')
  }

  const handleManualOverrideChange = (event) => {
    setData('manual_override', event.target.checked)
  }

  const loadPreview = async () => {
    if (!data.process_id || !data.packaging_id || data.cantidad_cajas === '' || data.cantidad_pallets === '') return
    setLoadingPreview(true)
    try {
      const response = await axios.post(route('inventory.productions.preview'), data)
      setPreview(response.data)
    } finally {
      setLoadingPreview(false)
    }
  }

  const submit = (event) => {
    event.preventDefault()
    post(route('inventory.productions.store'), {
      preserveScroll: true,
      onSuccess: () => {
        reset(buildInitialForm())
        setPreview(null)
      },
    })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <CardTitle>Producción y consumo teórico</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}
          <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
            Registra producción operativa y contrástala con el consumo teórico del instructivo vigente. Si activas edición manual, las cantidades dejan de recalcularse automáticamente y pasan a ser una excepción operativa explícita para cajas y pallets.
          </div>

          <form onSubmit={submit} className="rounded border bg-gray-50 p-4 space-y-4">
            <div className="grid gap-3 md:grid-cols-4">
              <div className="md:col-span-2">
                <Label>Proceso</Label>
                <SearchableSelect
                  options={processOptions}
                  value={processOptions.find((item) => item.value === String(data.process_id)) || null}
                  onChange={handleProcessChange}
                  placeholder="Selecciona el proceso del instructivo"
                />
                {errors.process_id && <div className="mt-1 text-sm text-red-600">{errors.process_id}</div>}
                {selectedProcess ? (
                  <div className="mt-1 text-xs text-gray-500">
                    {selectedProcess.linea ? `Línea sugerida: ${selectedProcess.linea}` : 'Sin línea sugerida'}
                    {selectedProcess.default_packaging_code ? ` · Embalaje sugerido: ${selectedProcess.default_packaging_code}` : ''}
                    {selectedNetWeight ? ` · Peso neto aplicable: ${Number(selectedNetWeight).toLocaleString('es-CL')} kg` : ''}
                  </div>
                ) : null}
              </div>
              <div>
                <Label>Fecha</Label>
                <Input type="date" value={data.fecha} onChange={(e) => setData('fecha', e.target.value)} />
                {errors.fecha && <div className="mt-1 text-sm text-red-600">{errors.fecha}</div>}
              </div>
              <div>
                <Label>Turno</Label>
                <Input value={data.turno} onChange={(e) => setData('turno', e.target.value)} />
                {errors.turno && <div className="mt-1 text-sm text-red-600">{errors.turno}</div>}
              </div>
              <div>
                <Label>Línea</Label>
                <Input value={data.linea} onChange={(e) => setData('linea', e.target.value)} />
                {errors.linea && <div className="mt-1 text-sm text-red-600">{errors.linea}</div>}
              </div>
              <div>
                <Label>Embalaje</Label>
                <SearchableSelect
                  options={packagingOptions}
                  value={packagingOptions.find((item) => item.value === String(data.packaging_id)) || null}
                  onChange={(option) => setData('packaging_id', option?.value || '')}
                  placeholder="Selecciona embalaje"
                />
                {errors.packaging_id && <div className="mt-1 text-sm text-red-600">{errors.packaging_id}</div>}
              </div>
              <div>
                <Label>Especie</Label>
                <SearchableSelect
                  options={speciesOptions}
                  value={speciesOptions.find((item) => item.value === data.especie) || null}
                  onChange={(option) => setData('especie', option?.value || '')}
                  placeholder="Selecciona especie"
                />
                {errors.especie && <div className="mt-1 text-sm text-red-600">{errors.especie}</div>}
              </div>
              <div>
                <Label>Variedad</Label>
                <SearchableSelect
                  options={varietyOptions}
                  value={varietyOptions.find((item) => item.value === data.variedad) || null}
                  onChange={(option) => setData('variedad', option?.value || '')}
                  placeholder="Selecciona variedad"
                />
                {selectedProcess?.default_variedad && !data.variedad ? (
                  <div className="mt-1 text-xs text-gray-500">Sugerida por instructivo: {selectedProcess.default_variedad}</div>
                ) : null}
              </div>
              <div>
                <Label>Cantidad cajas</Label>
                <Input
                  type="number"
                  step="0.0001"
                  value={data.cantidad_cajas}
                  onChange={(e) => setData('cantidad_cajas', e.target.value)}
                  readOnly={fieldsAreLocked}
                  className={fieldsAreLocked ? 'bg-slate-100' : ''}
                />
                {fieldsAreLocked ? <div className="mt-1 text-xs text-gray-500">Calculado automáticamente desde el instructivo.</div> : null}
                {errors.cantidad_cajas && <div className="mt-1 text-sm text-red-600">{errors.cantidad_cajas}</div>}
              </div>
              <div>
                <Label>Cantidad pallets</Label>
                <Input
                  type="number"
                  step="0.0001"
                  value={data.cantidad_pallets}
                  onChange={(e) => setData('cantidad_pallets', e.target.value)}
                  readOnly={fieldsAreLocked}
                  className={fieldsAreLocked ? 'bg-slate-100' : ''}
                />
                {fieldsAreLocked ? <div className="mt-1 text-xs text-gray-500">Calculado automáticamente desde el instructivo.</div> : null}
                {errors.cantidad_pallets && <div className="mt-1 text-sm text-red-600">{errors.cantidad_pallets}</div>}
              </div>
              <div className="md:col-span-2 flex items-end">
                <label className="flex items-center gap-2 text-sm text-gray-700">
                  <input
                    type="checkbox"
                    checked={Boolean(data.manual_override)}
                    onChange={handleManualOverrideChange}
                    className="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                  />
                  Editar cantidades manualmente
                </label>
              </div>
              <div className="md:col-span-4">
                <Label>Observación</Label>
                <Textarea value={data.observacion} onChange={(e) => setData('observacion', e.target.value)} />
                {errors.observacion && <div className="mt-1 text-sm text-red-600">{errors.observacion}</div>}
              </div>
            </div>
            {selectedProcess && selectedPackaging ? (
              <div className="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-900">
                Cálculo automático: peso neto {Number(selectedNetWeight || 0).toLocaleString('es-CL')} kg / peso estándar {Number(selectedPackaging.peso_std || 0).toLocaleString('es-CL')} kg por caja.
                {Number(selectedPackaging.cantidad_cajas || 0) > 0 ? ` Pallet calculado con ${Number(selectedPackaging.cantidad_cajas).toLocaleString('es-CL')} cajas por pallet.` : ' El embalaje no tiene cajas por pallet configuradas.'}
                {data.manual_override ? ' Edición manual activa.' : ' Para corregir una excepción operativa, activa la edición manual.'}
              </div>
            ) : null}
            <div className="flex justify-between gap-2">
              <Button type="button" variant="outline" onClick={loadPreview} disabled={!data.process_id || !data.packaging_id || loadingPreview}>
                {loadingPreview ? 'Calculando...' : 'Vista previa teórica'}
              </Button>
              <Button type="submit" disabled={processing}>{processing ? 'Guardando...' : 'Registrar producción'}</Button>
            </div>
          </form>

          {preview && (
            <div className="rounded border bg-white p-4">
              <div className="font-semibold">Vista previa</div>
              <div className="mt-1 text-sm text-gray-600">
                {preview.sheet ? `Ficha v${preview.sheet.version} · ${preview.sheet.packaging}` : 'Sin ficha técnica vigente para el embalaje y fecha seleccionados.'}
              </div>
              {preview.rows?.length ? (
                <Table className="mt-4 border">
                  <TableHeader className="bg-slate-50">
                    <TableRow>
                      <TableHead>Material a utilizar</TableHead>
                      <TableHead>Servicio</TableHead>
                      <TableHead className="text-right">Unidad</TableHead>
                      <TableHead className="text-right">Pallet</TableHead>
                      <TableHead className="text-right">Total teórico</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {preview.rows.map((row) => (
                      <Fragment key={row.material_id}>
                        <TableRow className="border-t-2 border-slate-100 bg-white">
                          <TableCell className="font-medium">
                            <div>{row.material_codigo} · {row.material_nombre}</div>
                            <div className="text-xs font-normal text-slate-500">Unidad: {row.unidad_medida || '-'}</div>
                          </TableCell>
                          <TableCell>{row.service_name || '-'}</TableCell>
                          <TableCell className="text-right">{formatQuantity(row.theoretical_unit)}</TableCell>
                          <TableCell className="text-right">{formatQuantity(row.theoretical_pallet)}</TableCell>
                          <TableCell className="text-right font-bold text-blue-800">
                            {formatQuantity(row.theoretical_total)}
                          </TableCell>
                        </TableRow>
                        {row.stocks?.length ? (
                          <TableRow className="bg-slate-50/50">
                            <TableCell colSpan={5} className="py-2 pl-8 pr-4">
                              <div className="space-y-3 text-xs">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                  <span className="font-semibold uppercase tracking-tight text-slate-500">Stock disponible por ubicación y posición</span>
                                  <span className="font-medium text-slate-700">
                                    Requerido: {formatQuantity(row.theoretical_total)} · Disponible: {formatQuantity(row.stocks.reduce((acc, st) => acc + Number(st.stock || 0), 0))}
                                  </span>
                                </div>
                                {row.stocks.map((st, idx) => (
                                  <div key={`${row.material_id}-st-${idx}`} className="rounded border border-slate-200 bg-white p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                      <div className="font-medium text-slate-800">{st.location || 'Sin ubicación'}</div>
                                      <div className={`font-bold ${Number(st.stock || 0) < Number(row.theoretical_total || 0) ? 'text-amber-600' : 'text-emerald-700'}`}>
                                        Stock ubicación: {formatQuantity(st.stock)}
                                      </div>
                                    </div>
                                    {st.positions?.length ? (
                                      <div className="mt-2 overflow-x-auto">
                                        <table className="min-w-full text-left text-[11px]">
                                          <thead className="text-slate-500">
                                            <tr>
                                              <th className="py-1 pr-3 font-medium">Posición</th>
                                              <th className="py-1 pr-3 font-medium">Pallet/LPN</th>
                                              <th className="py-1 pr-3 font-medium">Lote</th>
                                              <th className="py-1 pr-3 font-medium">Estado</th>
                                              <th className="py-1 text-right font-medium">Cantidad</th>
                                            </tr>
                                          </thead>
                                          <tbody>
                                            {st.positions.map((position) => (
                                              <tr key={`${row.material_id}-pos-${position.id}`} className="border-t border-slate-100">
                                                <td className="py-1 pr-3 font-medium text-slate-700">#{position.id}</td>
                                                <td className="py-1 pr-3 text-slate-700">{position.logistic_unit?.license_plate_number || '-'}</td>
                                                <td className="py-1 pr-3 text-slate-600">{position.lot_code || '-'}</td>
                                                <td className="py-1 pr-3 text-slate-600">{position.status || '-'}</td>
                                                <td className="py-1 text-right font-semibold text-slate-900">{formatQuantity(position.quantity)}</td>
                                              </tr>
                                            ))}
                                          </tbody>
                                        </table>
                                      </div>
                                    ) : (
                                      <div className="mt-2 rounded border border-dashed border-slate-200 px-2 py-1 text-[11px] text-slate-500">
                                        Sin posiciones detalladas para esta ubicación.
                                      </div>
                                    )}
                                  </div>
                                ))}
                              </div>
                              {row.stocks.reduce((acc, s) => acc + Number(s.stock || 0), 0) < Number(row.theoretical_total || 0) && (
                                <div className="mt-2 text-[10px] font-bold uppercase text-red-600">
                                  Stock total insuficiente en bodega para cubrir el consumo teórico
                                </div>
                              )}
                            </TableCell>
                          </TableRow>
                        ) : (
                          <TableRow className="bg-red-50/30">
                            <TableCell colSpan={5} className="py-2 pl-8 text-xs font-bold text-red-600 uppercase italic">
                              Sin stock disponible en ninguna ubicación operativa
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    ))}
                  </TableBody>
                </Table>
              ) : null}
            </div>
          )}

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Turno / Línea</TableHead>
                <TableHead>Proceso</TableHead>
                <TableHead>Embalaje</TableHead>
                <TableHead className="text-right">Teórico</TableHead>
                <TableHead className="text-right">Real</TableHead>
                <TableHead className="text-right">Desviación</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(productions?.data || []).map((item) => (
                <TableRow key={item.id}>
                  <TableCell>
                    <div>{item.fecha}</div>
                    <div className="text-xs text-gray-500">{item.especie || '-'} · {item.variedad || '-'}</div>
                  </TableCell>
                  <TableCell>{item.turno} · {item.linea}</TableCell>
                  <TableCell>{item.process_label || '-'}</TableCell>
                  <TableCell>{item.packaging}</TableCell>
                  <TableCell className="text-right">{Number(item.calculation?.summary?.theoretical_total || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell className="text-right">{Number(item.calculation?.summary?.real_total || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell className={`text-right font-medium ${Number(item.calculation?.summary?.deviation_total || 0) > 0 ? 'text-red-600' : 'text-emerald-700'}`}>{Number(item.calculation?.summary?.deviation_total || 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell className="text-right">
                    <Button type="button" variant="outline" size="sm" onClick={() => setSelectedProduction(item)}>
                      Ver detalle
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {productions?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {productions.from ?? 0} a {productions.to ?? 0} de {productions.total ?? 0}</div>
              <div className="flex gap-1">
                {productions.links.map((link, index) => (
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

      <Dialog open={Boolean(selectedProduction)} onOpenChange={(open) => !open && setSelectedProduction(null)}>
        <DialogContent className="max-h-[85vh] max-w-5xl overflow-hidden">
          <DialogHeader>
            <DialogTitle>Detalle de producción registrada</DialogTitle>
            <DialogDescription>
              {selectedProduction ? `Registro #${selectedProduction.id} · ${selectedProduction.fecha} · ${selectedProduction.packaging}` : ''}
            </DialogDescription>
          </DialogHeader>

          {selectedProduction ? (
            <div className="space-y-4 overflow-y-auto pr-1">
              <div className="grid gap-3 md:grid-cols-4">
                <div className="rounded border bg-slate-50 p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Proceso</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedProduction.process_label || '-'}</div>
                </div>
                <div className="rounded border bg-slate-50 p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Turno / Línea</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedProduction.turno} · {selectedProduction.linea}</div>
                </div>
                <div className="rounded border bg-slate-50 p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Cajas</div>
                  <div className="mt-1 font-medium text-slate-900">{Number(selectedProduction.cantidad_cajas || 0).toLocaleString('es-CL')}</div>
                </div>
                <div className="rounded border bg-slate-50 p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Pallets</div>
                  <div className="mt-1 font-medium text-slate-900">{Number(selectedProduction.cantidad_pallets || 0).toLocaleString('es-CL')}</div>
                </div>
              </div>

              <div className="grid gap-3 md:grid-cols-4">
                <div className="rounded border bg-white p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Especie</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedProduction.especie || '-'}</div>
                </div>
                <div className="rounded border bg-white p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Variedad</div>
                  <div className="mt-1 font-medium text-slate-900">{selectedProduction.variedad || '-'}</div>
                </div>
                <div className="rounded border bg-white p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Teórico total</div>
                  <div className="mt-1 font-medium text-slate-900">{Number(selectedProduction.calculation?.summary?.theoretical_total || 0).toLocaleString('es-CL')}</div>
                </div>
                <div className="rounded border bg-white p-3">
                  <div className="text-xs uppercase tracking-wide text-slate-500">Desviación</div>
                  <div className={`mt-1 font-medium ${Number(selectedProduction.calculation?.summary?.deviation_total || 0) > 0 ? 'text-red-600' : 'text-emerald-700'}`}>
                    {Number(selectedProduction.calculation?.summary?.deviation_total || 0).toLocaleString('es-CL')}
                  </div>
                </div>
              </div>

              {selectedProduction.observacion ? (
                <div className="rounded border bg-amber-50 p-3 text-sm text-amber-900">
                  <div className="font-medium">Observación</div>
                  <div className="mt-1 whitespace-pre-wrap">{selectedProduction.observacion}</div>
                </div>
              ) : null}

              <div className="rounded border bg-white p-4">
                <div className="flex items-center justify-between gap-2">
                  <div>
                    <div className="font-semibold text-slate-900">Consumo teórico vs real</div>
                    <div className="text-sm text-slate-500">
                      {selectedProduction.calculation?.sheet
                        ? `Ficha v${selectedProduction.calculation.sheet.version} · Vigencia desde ${selectedProduction.calculation.sheet.vigencia?.desde || '-'}`
                        : 'Sin ficha técnica vigente para este registro.'}
                    </div>
                  </div>
                </div>

                {selectedProduction.calculation?.rows?.length ? (
                  <div className="mt-4 max-h-[45vh] overflow-auto rounded-lg border">
                    <Table>
                      <TableHeader className="sticky top-0 z-10 bg-slate-50">
                        <TableRow>
                          <TableHead>Material</TableHead>
                          <TableHead>Servicio</TableHead>
                          <TableHead>Unidad</TableHead>
                          <TableHead className="text-right">Teórico unidad</TableHead>
                          <TableHead className="text-right">Teórico pallet</TableHead>
                          <TableHead className="text-right">Teórico total</TableHead>
                          <TableHead className="text-right">Real</TableHead>
                          <TableHead className="text-right">Merma</TableHead>
                          <TableHead className="text-right">Desviación</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {selectedProduction.calculation.rows.map((row) => (
                          <TableRow key={`${selectedProduction.id}-${row.material_id}`}>
                            <TableCell>
                              <div className="font-medium">{row.material_codigo} · {row.material_nombre}</div>
                            </TableCell>
                            <TableCell>{row.service_name || '-'}</TableCell>
                            <TableCell>{row.unidad_medida || '-'}</TableCell>
                            <TableCell className="text-right">{Number(row.theoretical_unit || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className="text-right">{Number(row.theoretical_pallet || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className="text-right font-medium">{Number(row.theoretical_total || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className="text-right">{Number(row.real_total || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className="text-right">{Number(row.waste_total || 0).toLocaleString('es-CL')}</TableCell>
                            <TableCell className={`text-right font-medium ${Number(row.deviation_total || 0) > 0 ? 'text-red-600' : 'text-emerald-700'}`}>
                              {Number(row.deviation_total || 0).toLocaleString('es-CL')}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                ) : (
                  <div className="mt-4 rounded border border-dashed px-4 py-6 text-center text-sm text-slate-500">
                    No hay detalle calculado para esta producción.
                  </div>
                )}
              </div>
            </div>
          ) : null}
        </DialogContent>
      </Dialog>
    </div>
  )
}

InventoryProductions.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Producción</h2>}
  />
)
