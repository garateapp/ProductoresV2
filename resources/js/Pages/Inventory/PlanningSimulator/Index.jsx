import { router } from '@inertiajs/react'
import { useCallback, useEffect, useMemo, useRef, useState, Fragment } from 'react'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import SearchableSelect from '@/Components/SearchableSelect'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { cn } from '@/lib/utils'

function formatNumber(value, digits = 2) {
  return Number(value || 0).toLocaleString('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  })
}

function materialLabel(item) {
  return [item?.material_codigo, item?.material_nombre].filter(Boolean).join(' · ') || '-'
}

function stockLocationLabel(stock) {
  const positions = stock.positions || []
  const lpnList = positions
    .map((position) => position.logistic_unit?.license_plate_number)
    .filter(Boolean)
    .slice(0, 3)

  return `${stock.location || '-'}: ${formatNumber(stock.stock, 4)}${lpnList.length ? ` (${lpnList.join(', ')})` : ''}`
}

export default function PlanningSimulatorIndex({
  filters = {},
  lines = [],
  packagings = [],
  processLots = [],
  simulation = null,
}) {
  const [form, setForm] = useState({
    packing_line_id: filters.packing_line_id || '',
    packaging_id: filters.packaging_id || '',
    kilos: filters.kilos || '',
    mode: filters.mode || 'kilos',
    calibre_curve: '',
  })

  const [selectedLotIds, setSelectedLotIds] = useState(
    (simulation?.selected_lot_ids || filters.selected_lot_ids || []).map(Number)
  )

  const [calibreCurve, setCalibreCurve] = useState(
    simulation?.calibre_curve || [{ calibre: '', percentage: '' }]
  )

  const serializedCurve = useMemo(
    () => JSON.stringify(calibreCurve.filter((r) => r.calibre && r.percentage)),
    [calibreCurve]
  )

  useEffect(() => {
    setForm((current) => ({ ...current, calibre_curve: serializedCurve }))
  }, [serializedCurve])

  const addCalibreRow = () => {
    setCalibreCurve((prev) => [...prev, { calibre: '', percentage: '' }])
  }

  const removeCalibreRow = (idx) => {
    setCalibreCurve((prev) => prev.length > 1 ? prev.filter((_, i) => i !== idx) : [{ calibre: '', percentage: '' }])
  }

  const updateCalibreRow = (idx, field, value) => {
    setCalibreCurve((prev) => {
      const next = prev.map((row, i) => (i === idx ? { ...row, [field]: value } : row))
      const total = next.reduce((s, r) => s + (parseFloat(r.percentage) || 0), 0)
      if (total > 100) {
        const excess = total - 100
        const lastFilled = [...next].reverse().find((r) => parseFloat(r.percentage) > 0)
        if (lastFilled) {
          const lastIdx = next.lastIndexOf(lastFilled)
          next[lastIdx] = { ...next[lastIdx], percentage: String(Math.max(0, parseFloat(next[lastIdx].percentage) - excess)) }
        }
      }
      return next
    })
  }

  const [expandedLots, setExpandedLots] = useState({})

  const toggleExpandLot = (lotId) => {
    setExpandedLots((prev) => ({ ...prev, [lotId]: !prev[lotId] }))
  }

  const formRef = useRef(form)
  formRef.current = form

  const lineOptions = useMemo(() => lines.map((line) => ({
    value: String(line.id),
    label: `${line.nombre}${line.tipo ? ` · ${line.tipo}` : ''}`,
  })), [lines])

  const packagingOptions = useMemo(() => packagings.map((packaging) => ({
    value: String(packaging.id),
    label: `${packaging.codigo || 'S/C'} · ${packaging.nombre}`,
  })), [packagings])

  const lineNameMap = useMemo(() => {
    const map = {}
    for (const line of lines) {
      map[line.id] = line.nombre
    }
    return map
  }, [lines])

  const derivedKilos = useMemo(() => {
    if (form.mode !== 'lotes' || processLots.length === 0) return null
    const selected = processLots.filter((l) => selectedLotIds.includes(l.id))
    return selected.reduce((sum, l) => sum + (l.weight || 0), 0)
  }, [form.mode, processLots, selectedLotIds])

  const selectedPackaging = packagings.find((item) => String(item.id) === String(form.packaging_id))

  const debounceRef = useRef(null)

  const scheduleSubmit = useCallback((lotIds) => {
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      router.get(route('inventory.planning-simulator.index'), {
        ...formRef.current,
        selected_lot_ids: lotIds,
      }, {
        preserveScroll: true,
        preserveState: true,
      })
    }, 300)
  }, [])

  const toggleLot = (lotId) => {
    setSelectedLotIds((prev) => {
      const next = prev.includes(lotId)
        ? prev.filter((id) => id !== lotId)
        : [...prev, lotId]
      scheduleSubmit(next)
      return next
    })
  }

  const submit = (event) => {
    event.preventDefault()
    router.get(route('inventory.planning-simulator.index'), {
      ...form,
      selected_lot_ids: selectedLotIds,
    }, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const isInitialMount = useRef(true)

  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false
      return
    }
    if (form.mode === 'kilos' && !form.packing_line_id) return

    const timer = setTimeout(() => {
      router.get(route('inventory.planning-simulator.index'), {
        ...form,
        selected_lot_ids: selectedLotIds,
      }, {
        preserveScroll: true,
        preserveState: true,
      })
    }, 400)

    return () => clearTimeout(timer)
  }, [form.packing_line_id, form.mode])

  const reset = () => {
    const next = { packing_line_id: '', packaging_id: '', kilos: '', mode: 'kilos', calibre_curve: '' }
    setForm(next)
    setSelectedLotIds([])
    setCalibreCurve([{ calibre: '', percentage: '' }])
    router.get(route('inventory.planning-simulator.index'), next, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  return (
    <div className="mx-auto space-y-6 px-10 py-10">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight text-slate-950">Simulador de planificación</h1>
          <p className="mt-1 text-sm text-slate-500">
            Calcula consumo teórico por ficha técnica, stock disponible por ubicación y semielaborados activos para consumo.
          </p>
        </div>
        {simulation?.sheet ? (
          <Badge variant="outline" className="w-fit border-emerald-200 bg-emerald-50 text-emerald-700">
            Ficha técnica v{simulation.sheet.version}
          </Badge>
        ) : null}
      </div>

      <Card className="border-slate-200 shadow-sm">
        <CardHeader className="border-b border-slate-100 pb-4">
          <CardTitle className="text-lg text-slate-900">Parámetros</CardTitle>
        </CardHeader>
        <CardContent className="pt-6">
          <form onSubmit={submit} className="space-y-4">
            <div className="flex items-center gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1 text-sm w-fit">
              <button
                type="button"
                onClick={() => setForm((current) => ({ ...current, mode: 'kilos' }))}
                className={cn(
                  'rounded-md px-3 py-1.5 font-medium transition-colors',
                  form.mode === 'kilos' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800',
                )}
              >
                Por kilos
              </button>
              <button
                type="button"
                onClick={() => setForm((current) => ({ ...current, mode: 'lotes' }))}
                className={cn(
                  'rounded-md px-3 py-1.5 font-medium transition-colors',
                  form.mode === 'lotes' ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-800',
                )}
              >
                Por lotes
              </button>
            </div>

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1.4fr_1fr_auto] lg:items-end">
              <div>
                <Label>Línea</Label>
                <SearchableSelect
                  options={lineOptions}
                  value={lineOptions.find((item) => item.value === String(form.packing_line_id)) || null}
                  onChange={(option) => setForm((current) => ({ ...current, packing_line_id: option?.value || '' }))}
                  placeholder="Selecciona línea"
                  isClearable={false}
                />
              </div>
              <div>
                <Label>Embalaje</Label>
                <SearchableSelect
                  options={packagingOptions}
                  value={packagingOptions.find((item) => item.value === String(form.packaging_id)) || null}
                  onChange={(option) => setForm((current) => ({ ...current, packaging_id: option?.value || '' }))}
                  placeholder="Selecciona embalaje"
                  isClearable={false}
                />
              </div>
               {form.mode === 'kilos' ? (
                <div className="space-y-3">
                  <div>
                    <Label>Kilos</Label>
                    <Input
                      type="number"
                      min="0"
                      step="0.01"
                      value={form.kilos}
                      onChange={(event) => setForm((current) => ({ ...current, kilos: event.target.value }))}
                      placeholder="0"
                      className="mt-1 bg-white"
                    />
                    {selectedPackaging ? (
                      <div className="mt-1 text-xs text-slate-500">
                        {formatNumber(selectedPackaging.peso_std, 4)} kg/caja · {formatNumber(selectedPackaging.cantidad_cajas, 4)} cajas/pallet
                      </div>
                    ) : null}
                  </div>
                  <div>
                    <Label className="mb-1 block">Curva de calibres</Label>
                    <div className="space-y-1.5">
                      {calibreCurve.map((row, idx) => (
                        <div key={idx} className="flex items-center gap-1.5">
                          <Input
                            type="text"
                            value={row.calibre}
                            onChange={(e) => updateCalibreRow(idx, 'calibre', e.target.value)}
                            placeholder="Calibre"
                            className="h-8 bg-white w-28"
                          />
                          <div className="relative w-20">
                            <Input
                              type="number"
                              min="0"
                              max="100"
                              step="0.1"
                              value={row.percentage}
                              onChange={(e) => updateCalibreRow(idx, 'percentage', e.target.value)}
                              placeholder="%"
                              className="h-8 bg-white pr-6"
                            />
                            <span className="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-slate-400">%</span>
                          </div>
                          <button
                            type="button"
                            onClick={() => removeCalibreRow(idx)}
                            className="flex h-8 w-8 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-red-500 transition-colors text-lg leading-none"
                          >
                            ×
                          </button>
                        </div>
                      ))}
                    </div>
                    <Button type="button" variant="outline" size="sm" onClick={addCalibreRow} className="mt-1.5 h-7 text-xs">
                      + Agregar calibre
                    </Button>
                  </div>
                </div>
              ) : (
                <div>
                  <Label>Kilos derivados</Label>
                  <div className="mt-1 flex h-10 items-center rounded-lg border border-slate-200 bg-slate-50 px-3 text-sm font-semibold text-slate-900">
                    {simulation ? `${formatNumber(simulation.kilos, 2)} kg` : (derivedKilos > 0 ? `${formatNumber(derivedKilos, 2)} kg` : '—')}
                  </div>
                  {selectedPackaging ? (
                    <div className="mt-1 text-xs text-slate-500">
                      {formatNumber(selectedPackaging.peso_std, 4)} kg/caja · {formatNumber(selectedPackaging.cantidad_cajas, 4)} cajas/pallet
                    </div>
                  ) : null}
                </div>
              )}
              <div className="flex gap-2">
                <Button type="submit" disabled={
                  form.mode === 'lotes'
                    ? !form.packaging_id || selectedLotIds.length === 0
                    : !form.packing_line_id || !form.packaging_id || !form.kilos
                }>
                  Calcular
                </Button>
                <Button type="button" variant="outline" onClick={reset}>
                  Limpiar
                </Button>
              </div>
            </div>
          </form>
        </CardContent>
      </Card>

      {(() => {
        const lots = simulation?.process_lots || processLots
        if ((lots.length === 0 && !simulation?.line) && form.mode !== 'lotes') return null

        return lots.length > 0 ? (
          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
              <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <CardTitle className="text-lg text-slate-900">
                  {form.mode === 'lotes' ? 'Selección de lotes' : 'Lotes activos en la línea'}
                </CardTitle>
                {(simulation?.line_calibres?.length > 0 || (lots.length > 0 && !simulation)) && (
                  <div className="flex flex-wrap gap-1">
                    <span className="text-xs text-slate-500 mr-1 self-center">Calibres:</span>
                    {(simulation?.line_calibres || [...new Set(lots.filter((l) => l.calibre).map((l) => l.calibre))]).map((calibre) => (
                      <Badge key={calibre} variant="secondary" className="font-mono text-xs">{calibre}</Badge>
                    ))}
                  </div>
                )}
              </div>
            </CardHeader>
            <CardContent className="pt-4">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead className="w-10">
                      {form.mode === 'lotes' ? (
                        <input
                          type="checkbox"
                          className="h-4 w-4 rounded border-slate-300"
                          checked={selectedLotIds.length === lots.length}
                          onChange={() => {
                            const all = lots.map((l) => l.id)
                            if (selectedLotIds.length === all.length) {
                              setSelectedLotIds([])
                              scheduleSubmit([])
                            } else {
                              setSelectedLotIds(all)
                              scheduleSubmit(all)
                            }
                          }}
                        />
                      ) : null}
                    </TableHead>
                    <TableHead className="w-12">#</TableHead>
                    <TableHead>Calibre</TableHead>
                    <TableHead>Distribución</TableHead>
                    <TableHead>Variedad</TableHead>
                    <TableHead>Productor</TableHead>
                    {(form.mode === 'lotes' && lines.length > 1) ? <TableHead>Línea</TableHead> : null}
                    <TableHead>Inicio estimado</TableHead>
                    <TableHead className="text-right">Kilos neto</TableHead>
                    <TableHead>Estado</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {lots.map((lot) => (
                    <Fragment key={lot.id}>
                      <TableRow
                        className={cn(
                          selectedLotIds.includes(lot.id) && 'bg-sky-50/50',
                          form.mode === 'lotes' && 'cursor-pointer',
                        )}
                        onClick={form.mode === 'lotes' ? () => toggleLot(lot.id) : undefined}
                      >
                        <TableCell>
                          {form.mode === 'lotes' ? (
                            <div className="flex items-center gap-1">
                              {lot.calibre_distribution?.length > 0 && (
                                <button
                                  type="button"
                                  onClick={(e) => { e.stopPropagation(); toggleExpandLot(lot.id) }}
                                  className="text-slate-400 hover:text-slate-700 transition-colors"
                                >
                                  {expandedLots[lot.id] ? '▾' : '▸'}
                                </button>
                              )}
                              <input
                                type="checkbox"
                                className="h-4 w-4 rounded border-slate-300"
                                checked={selectedLotIds.includes(lot.id)}
                                onChange={() => toggleLot(lot.id)}
                              />
                            </div>
                          ) : null}
                        </TableCell>
                        <TableCell className="text-xs text-slate-500">{lot.order}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="border-slate-300 bg-slate-50 font-mono text-xs">
                            {lot.calibre || '—'}
                          </Badge>
                        </TableCell>
                        <TableCell>
                          {lot.calibre_distribution?.length > 0 ? (
                            <span className="inline-flex items-center gap-1 text-xs text-slate-500">
                              {lot.calibre_distribution.map((d, i) => (
                                <span key={d.calibre} className="whitespace-nowrap">
                                  {d.calibre} {d.percentage}%{i < lot.calibre_distribution.length - 1 ? ',' : ''}
                                </span>
                              ))}
                            </span>
                          ) : (
                            <span className="text-xs text-slate-400">—</span>
                          )}
                        </TableCell>
                        <TableCell>{lot.variety || '-'}</TableCell>
                        <TableCell className="text-xs">{lot.producer_name || lot.producer_code || '-'}</TableCell>
                        {(form.mode === 'lotes' && lines.length > 1) ? (
                          <TableCell className="text-xs">{lineNameMap[lot.line_id] || '-'}</TableCell>
                        ) : null}
                        <TableCell className="text-xs">{lot.estimated_start || '-'}</TableCell>
                        <TableCell className="text-right">{lot.weight > 0 ? Number(lot.weight).toLocaleString('es-CL') : '-'}</TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-xs capitalize">{lot.status}</Badge>
                        </TableCell>
                      </TableRow>
                      {expandedLots[lot.id] && lot.calibre_distribution?.length > 0 ? (
                        <TableRow className="bg-slate-50/50">
                          <TableCell colSpan={
                            4 + 4 + (form.mode === 'lotes' && lines.length > 1 ? 1 : 0)
                          } className="p-0">
                            <div className="px-10 py-3">
                              <div className="flex flex-wrap gap-3">
                                {lot.calibre_distribution.map((d) => (
                                  <div key={d.calibre} className="rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs">
                                    <div className="font-semibold text-slate-800">{d.calibre}</div>
                                    <div className="text-slate-500">{d.percentage}%</div>
                                    <div className="text-slate-600 font-medium">{formatNumber(d.kilos, 2)} kg</div>
                                  </div>
                                ))}
                              </div>
                            </div>
                          </TableCell>
                        </TableRow>
                      ) : null}
                    </Fragment>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        ) : <p className="text-sm text-slate-500 text-center py-8">No hay lotes disponibles.</p>
      })()}

      {simulation ? (
        <>
          <div className="grid gap-4 lg:grid-cols-4">
            <Card className="border-slate-200 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm text-slate-600">Kilos planificados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold text-slate-950">{formatNumber(simulation.kilos, 2)}</div>
              </CardContent>
            </Card>
            <Card className="border-slate-200 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm text-slate-600">Cajas estimadas</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold text-slate-950">{formatNumber(simulation.estimated_boxes, 0)}</div>
              </CardContent>
            </Card>
            <Card className="border-slate-200 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm text-slate-600">Pallets estimados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold text-slate-950">{formatNumber(simulation.estimated_pallets, 0)}</div>
              </CardContent>
            </Card>
            <Card className="border-slate-200 shadow-sm">
              <CardHeader className="pb-2">
                <CardTitle className="text-sm text-slate-600">Materiales con faltante</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-3xl font-semibold text-rose-700">{simulation.summary?.shortage_count || 0}</div>
              </CardContent>
            </Card>
          </div>

          {simulation.calibre_distribution?.length > 0 ? (
            <Card className="border-slate-200 shadow-sm">
              <CardHeader className="border-b border-slate-100 pb-3">
                <CardTitle className="text-sm text-slate-900">Distribución de calibres</CardTitle>
              </CardHeader>
              <CardContent className="pt-4">
                <div className="flex flex-wrap gap-3">
                  {simulation.calibre_distribution.map((d) => (
                    <div key={d.calibre} className="rounded-lg border border-slate-200 bg-white px-4 py-3 min-w-[120px]">
                      <div className="text-xs text-slate-500">Calibre {d.calibre}</div>
                      <div className="mt-1 text-lg font-semibold text-slate-900">{d.percentage}%</div>
                      <div className="text-xs text-slate-500">{formatNumber(d.kilos, 2)} kg</div>
                    </div>
                  ))}
                </div>
              </CardContent>
            </Card>
          ) : null}

          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
              <CardTitle className="text-lg text-slate-900">Materiales requeridos y ubicación</CardTitle>
            </CardHeader>
            <CardContent className="pt-4">
              {!simulation.sheet ? (
                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                  No existe ficha técnica activa para el embalaje seleccionado.
                </div>
              ) : (
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[22%]">Material</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Calibre</TableHead>
                        <TableHead className="text-right">Requerido</TableHead>
                        <TableHead className="text-right">Disponible</TableHead>
                        <TableHead className="text-right">Faltante</TableHead>
                        <TableHead className="w-[28%]">Ubicaciones / LPN</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {simulation.materials.length ? simulation.materials.map((row, idx) => (
                        <TableRow key={`${row.material_id}-${row.calibre || 'generic'}-${idx}`}
                          className={row.calibre ? 'border-t-0' : undefined}
                        >
                          <TableCell className="font-medium text-slate-900">
                            <div className="flex flex-col">
                              <span>{materialLabel(row)}</span>
                              {row.calibre && row.calibre_percentage ? (
                                <span className="text-[11px] text-slate-400 font-normal">
                                  {row.calibre_percentage}% del total
                                </span>
                              ) : null}
                              {row.es_despiece ? (
                                <span className="text-[11px] text-amber-600 font-normal mt-0.5">
                                  ← Despiece de {row.parent_material_id ? (simulation.materials.find(m => m.material_id === row.parent_material_id && !m.es_despiece)?.material_codigo || `#${row.parent_material_id}`) : 'semielaborado'}
                                </span>
                              ) : null}
                            </div>
                          </TableCell>
                          <TableCell>
                            <Badge variant="outline" className="border-slate-200 bg-slate-50 text-slate-700">
                              {row.tipo_material || 'material'}
                            </Badge>
                          </TableCell>
                          <TableCell>
                            {row.calibre ? (
                              <Badge variant="secondary" className="font-mono text-xs">
                                {row.calibre}
                              </Badge>
                            ) : (
                              <span className="text-xs text-slate-400">—</span>
                            )}
                          </TableCell>
                          <TableCell className="text-right">
                            {formatNumber(row.theoretical_total, 4)} {row.unidad_medida || ''}
                          </TableCell>
                          <TableCell className="text-right">
                            {formatNumber(row.available_total, 4)} {row.unidad_medida || ''}
                          </TableCell>
                          <TableCell className={`text-right font-medium ${row.shortage > 0 ? 'text-rose-700' : 'text-emerald-700'}`}>
                            {formatNumber(row.shortage, 4)} {row.unidad_medida || ''}
                          </TableCell>
                          <TableCell>
                            <div className="space-y-1 text-xs text-slate-600">
                              {(row.stocks || []).length ? row.stocks.map((stock) => (
                                <div key={`${row.material_id}-${stock.location_id}`}>{stockLocationLabel(stock)}</div>
                              )) : <span className="text-slate-400">Sin stock ubicado</span>}
                            </div>
                          </TableCell>
                        </TableRow>
                      )) : (
                      <TableRow>
                        <TableCell colSpan={7} className="py-8 text-center text-slate-500">
                          La ficha técnica no tiene materiales configurados.
                        </TableCell>
                      </TableRow>
                    )}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <Card className="border-slate-200 shadow-sm">
            <CardHeader className="border-b border-slate-100 pb-4">
              <div className="flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                <CardTitle className="text-lg text-slate-900">Semielaborados disponibles para consumir</CardTitle>
                <div className="text-xs text-slate-500">
                  Detalle limitado a {simulation.semi_finished?.lpn_limit || 200} LPN; el total por material considera todo el stock activo.
                </div>
              </div>
            </CardHeader>
            <CardContent className="space-y-5 pt-4">
              <div className="grid gap-3 lg:grid-cols-3">
                {(simulation.semi_finished?.materials || []).length ? simulation.semi_finished.materials.map((item) => (
                  <div key={item.material_id} className="rounded-lg border border-slate-200 bg-white p-4">
                    <div className="text-sm font-medium text-slate-900">{materialLabel(item)}</div>
                    <div className="mt-2 text-2xl font-semibold text-slate-950">
                      {formatNumber(item.available_total, 4)} {item.unidad_medida || ''}
                    </div>
                    <div className="mt-1 text-xs text-slate-500">{item.lpn_count} LPN activos</div>
                  </div>
                )) : (
                  <div className="rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-500 lg:col-span-3">
                    No hay semielaborados activos con cantidad disponible.
                  </div>
                )}
              </div>

              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>LPN</TableHead>
                    <TableHead>Material</TableHead>
                    <TableHead className="text-right">Disponible</TableHead>
                    <TableHead>Ubicación</TableHead>
                    <TableHead>Lote</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(simulation.semi_finished?.lpns || []).length ? simulation.semi_finished.lpns.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className="font-medium text-slate-900">{item.lpn}</TableCell>
                      <TableCell>{materialLabel(item)}</TableCell>
                      <TableCell className="text-right">{formatNumber(item.available_quantity, 4)} {item.unidad_medida || ''}</TableCell>
                      <TableCell>
                        <div>{item.location || '-'}</div>
                        {item.spatial_position ? <div className="text-xs text-slate-500">{item.spatial_position}</div> : null}
                      </TableCell>
                      <TableCell>{item.lot_code || '-'}</TableCell>
                    </TableRow>
                  )) : (
                    <TableRow>
                      <TableCell colSpan={5} className="py-8 text-center text-slate-500">
                        Sin LPN semielaborados disponibles.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </>
      ) : (
        <div className="rounded-lg border border-dashed border-slate-300 bg-slate-50 p-8 text-center text-sm text-slate-500">
          {form.mode === 'lotes'
            ? 'Selecciona línea, embalaje y los lotes para simular la planificación.'
            : 'Ingresa línea, kilos y embalaje para simular la planificación.'}
        </div>
      )}
    </div>
  )
}

PlanningSimulatorIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Simulador de planificación</h2>}
  />
)
