import { Link, router } from '@inertiajs/react'
import { Fragment, useMemo, useState } from 'react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Badge } from '@/Components/ui/badge'
import SearchableSelect from '@/Components/SearchableSelect'
import { ChevronDown, ChevronRight, Download } from 'lucide-react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

function formatNumber(value, digits = 2) {
  return Number(value || 0).toLocaleString('es-CL', {
    minimumFractionDigits: 0,
    maximumFractionDigits: digits,
  })
}

export default function InventoryStocksIndex({
  filters = {},
  summary = {},
  locationSummaries = [],
  stocks,
  locations = [],
  materials = [],
  families = [],
  services = [],
  locationTypes = [],
}) {
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    location_id: filters.location_id || '',
    location_type: filters.location_type || '',
    material_id: filters.material_id || '',
    family_id: filters.family_id || '',
    service_id: filters.service_id || '',
    stock_state: filters.stock_state || 'positive',
    per_page: filters.per_page || '20',
  })

  const [expandedRows, setExpandedRows] = useState(new Set())

  const toggleRow = (id) => {
    setExpandedRows((prev) => {
      const next = new Set(prev)
      if (next.has(id)) {
        next.delete(id)
      } else {
        next.add(id)
      }
      return next
    })
  }

  const allRowIds = useMemo(() => (stocks?.data || []).map((item) => item.id), [stocks])
  const allExpanded = allRowIds.length > 0 && allRowIds.every((id) => expandedRows.has(id))

  const toggleAll = () => {
    if (allExpanded) {
      setExpandedRows(new Set())
    } else {
      setExpandedRows(new Set(allRowIds))
    }
  }

  const locationOptions = locations.map((item) => ({
    value: String(item.id),
    label: `${item.nombre} · ${item.tipo}`,
  }))

  const materialOptions = materials.map((item) => ({
    value: String(item.id),
    label: `${item.codigo} · ${item.nombre}`,
  }))

  const familyOptions = families.map((item) => ({
    value: String(item.id),
    label: item.nombre,
  }))

  const serviceOptions = services.map((item) => ({
    value: String(item.id),
    label: item.name,
  }))

  const locationTypeOptions = locationTypes.map((item) => ({
    value: item,
    label: item,
  }))

  const stockStateOptions = [
    { value: 'positive', label: 'Con stock' },
    { value: 'all', label: 'Todos' },
    { value: 'zero', label: 'En cero' },
    { value: 'negative', label: 'Negativos' },
  ]

  const perPageOptions = [
    { value: '20', label: '20 filas' },
    { value: '50', label: '50 filas' },
    { value: '100', label: '100 filas' },
  ]

  const activeFilterCount = useMemo(() => {
    return ['q', 'location_id', 'location_type', 'material_id', 'family_id', 'service_id']
      .filter((key) => String(filterData[key] || '').trim() !== '').length
      + (filterData.stock_state !== 'positive' ? 1 : 0)
      + (filterData.per_page !== '20' ? 1 : 0)
  }, [filterData])

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.stocks.index'), filterData, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const resetFilters = () => {
    const resetData = {
      q: '',
      location_id: '',
      location_type: '',
      material_id: '',
      family_id: '',
      service_id: '',
      stock_state: 'positive',
      per_page: '20',
    }

    setFilterData(resetData)
    setExpandedRows(new Set())
    router.get(route('inventory.stocks.index'), resetData, {
      preserveScroll: true,
      preserveState: true,
    })
  }

  const exportUrl = () => {
    const params = new URLSearchParams()
    Object.entries(filterData).forEach(([key, value]) => {
      if (key !== 'per_page' && value !== '' && value !== null && value !== undefined) {
        params.set(key, String(value))
      }
    })
    const queryString = params.toString()
    return queryString ? `${route('inventory.stocks.export')}?${queryString}` : route('inventory.stocks.export')
  }

  return (
    <div className="mx-auto py-10 space-y-6 px-10">
      <div className="grid gap-4 lg:grid-cols-4">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-slate-600">Posiciones visibles</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-semibold tracking-tight text-slate-900">{summary.positions ?? 0}</div>
            <div className="mt-1 text-sm text-slate-500">Filas `ubicación + material` según filtros activos.</div>
          </CardContent>
        </Card>
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-slate-600">Con stock</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-semibold tracking-tight text-emerald-700">{summary.positive_positions ?? 0}</div>
            <div className="mt-1 text-sm text-slate-500">Posiciones con disponibilidad positiva.</div>
          </CardContent>
        </Card>
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-slate-600">Negativos</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-semibold tracking-tight text-rose-700">{summary.negative_positions ?? 0}</div>
            <div className="mt-1 text-sm text-slate-500">Ubicaciones con stock bajo cero a revisar.</div>
          </CardContent>
        </Card>
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm text-slate-600">Stock total visible</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-semibold tracking-tight text-slate-900">{formatNumber(summary.stock_total, 4)}</div>
            <div className="mt-1 text-sm text-slate-500">Suma de stock en las posiciones filtradas.</div>
          </CardContent>
        </Card>
      </div>

      <Card className="border-slate-200 shadow-sm">
        <CardHeader className="flex flex-col gap-3 border-b border-slate-100 pb-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <CardTitle className="text-xl text-slate-900">Stock por ubicación</CardTitle>
            <p className="mt-1 text-sm text-slate-500">
              Revisa rápido dónde está cada material, cuánto hay en esa ubicación y cómo se reparte respecto del stock interno total.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-sm text-slate-500">
            <Badge variant="outline" className="border-slate-300 bg-slate-50 text-slate-700">
              {activeFilterCount} filtros activos
            </Badge>
            <Button
              type="button"
              variant="outline"
              onClick={toggleAll}
              className="text-slate-700"
            >
              {allExpanded ? (
                <>
                  <ChevronDown className="mr-1.5 h-4 w-4" /> Colapsar todo
                </>
              ) : (
                <>
                  <ChevronRight className="mr-1.5 h-4 w-4" /> Desglosar todo
                </>
              )}
            </Button>
            <Button type="button" variant="outline" onClick={resetFilters}>Limpiar filtros</Button>
            <Button asChild variant="outline" className="border-emerald-600 text-emerald-700 hover:bg-emerald-50">
              <a href={exportUrl()}>
                <Download className="mr-2 h-4 w-4" /> Exportar a Excel
              </a>
            </Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-6 pt-6">
          <form onSubmit={applyFilters} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4">
            <Input
              value={filterData.q}
              onChange={(event) => setFilterData((current) => ({ ...current, q: event.target.value }))}
              placeholder="Buscar material o ubicación"
              className="bg-white"
            />
            <SearchableSelect
              options={locationOptions}
              value={locationOptions.find((item) => item.value === String(filterData.location_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, location_id: option?.value || '' }))}
              placeholder="Todas las ubicaciones"
            />
            <SearchableSelect
              options={locationTypeOptions}
              value={locationTypeOptions.find((item) => item.value === String(filterData.location_type)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, location_type: option?.value || '' }))}
              placeholder="Todos los tipos"
            />
            <SearchableSelect
              options={serviceOptions}
              value={serviceOptions.find((item) => item.value === String(filterData.service_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, service_id: option?.value || '' }))}
              placeholder="Todos los servicios"
            />
            <SearchableSelect
              options={familyOptions}
              value={familyOptions.find((item) => item.value === String(filterData.family_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, family_id: option?.value || '' }))}
              placeholder="Todas las familias"
            />
            <SearchableSelect
              options={materialOptions}
              value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, material_id: option?.value || '' }))}
              placeholder="Todos los materiales"
            />
            <SearchableSelect
              options={stockStateOptions}
              value={stockStateOptions.find((item) => item.value === String(filterData.stock_state)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, stock_state: option?.value || 'positive' }))}
              placeholder="Estado del stock"
              isClearable={false}
            />
            <div className="flex gap-2">
              <div className="min-w-[140px] flex-1">
                <SearchableSelect
                  options={perPageOptions}
                  value={perPageOptions.find((item) => item.value === String(filterData.per_page)) || null}
                  onChange={(option) => setFilterData((current) => ({ ...current, per_page: option?.value || '20' }))}
                  placeholder="Filas"
                  isClearable={false}
                />
              </div>
              <Button type="submit" className="shrink-0">Aplicar</Button>
            </div>
          </form>

          <div className="grid gap-3 xl:grid-cols-4">
            {locationSummaries.map((item) => (
              <button
                key={item.id}
                type="button"
                className="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50"
                onClick={() => {
                  const nextFilters = { ...filterData, location_id: String(item.id) }
                  setFilterData(nextFilters)
                  router.get(route('inventory.stocks.index'), nextFilters, {
                    preserveScroll: true,
                    preserveState: true,
                  })
                }}
              >
                <div className="flex items-center justify-between gap-3">
                  <div className="min-w-0">
                    <div className="truncate font-medium text-slate-900">{item.nombre}</div>
                    <div className="text-xs uppercase tracking-wide text-slate-500">{item.tipo}</div>
                  </div>
                  {item.negative_positions > 0 ? (
                    <Badge className="bg-rose-100 text-rose-700 hover:bg-rose-100">{item.negative_positions} neg.</Badge>
                  ) : (
                    <Badge variant="outline" className="border-slate-300 text-slate-600">{item.positions_count} pos.</Badge>
                  )}
                </div>
                <div className="mt-4 text-2xl font-semibold tracking-tight text-slate-900">{formatNumber(item.stock_total, 4)}</div>
                <div className="mt-1 text-sm text-slate-500">Stock total en esta ubicación</div>
              </button>
            ))}
          </div>

          <div className="overflow-hidden rounded-xl border border-slate-200">
            <Table>
              <TableHeader className="bg-slate-50">
                <TableRow>
                  <TableHead className="w-10 text-center"></TableHead>
                  <TableHead>Ubicación</TableHead>
                  <TableHead>Material</TableHead>
                  <TableHead>Servicio</TableHead>
                  <TableHead>Familia</TableHead>
                  <TableHead>Unidad</TableHead>
                  <TableHead className="text-right">Stock ubicación</TableHead>
                  <TableHead className="text-right">Total interno</TableHead>
                  <TableHead className="text-right">SAP global</TableHead>
                  <TableHead className="text-right">% distribución</TableHead>
                  <TableHead>Estado</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(stocks?.data || []).map((item) => {
                  const isExpanded = expandedRows.has(item.id)
                  const hasDistributions = item.distributions && item.distributions.length > 0

                  return (
                    <Fragment key={item.id}>
                      <TableRow
                        className={`align-top cursor-pointer transition-colors ${isExpanded ? 'bg-emerald-50/40 hover:bg-emerald-50/60' : 'hover:bg-slate-50/70'}`}
                        onClick={() => toggleRow(item.id)}
                      >
                        <TableCell className="text-center pt-4">
                          <button
                            type="button"
                            className="inline-flex items-center justify-center p-1 rounded hover:bg-slate-200/60 text-slate-500 transition"
                            onClick={(e) => {
                              e.stopPropagation()
                              toggleRow(item.id)
                            }}
                            title={isExpanded ? 'Colapsar desglose' : 'Desglosar posiciones'}
                          >
                            {isExpanded ? (
                              <ChevronDown className="h-4 w-4 text-emerald-700" />
                            ) : (
                              <ChevronRight className="h-4 w-4 text-slate-400" />
                            )}
                          </button>
                        </TableCell>
                        <TableCell>
                          <div className="font-medium text-slate-900">{item.location?.nombre || '-'}</div>
                          <div className="text-xs text-slate-500">{item.location?.codigo || '-'} · {item.location?.tipo || '-'}</div>
                        </TableCell>
                        <TableCell>
                          <div className="font-medium text-slate-900 flex items-center gap-1.5">
                            <span>{item.material?.codigo || '-'}</span>
                            {hasDistributions && (
                              <Badge variant="outline" className="text-[10px] px-1.5 py-0 border-emerald-300 bg-emerald-50 text-emerald-800">
                                {item.distributions.length} {item.distributions.length === 1 ? 'pos.' : 'pos.'}
                              </Badge>
                            )}
                          </div>
                          <div className="text-xs text-slate-500">{item.material?.nombre || '-'}</div>
                        </TableCell>
                        <TableCell>{item.material?.servicio || '-'}</TableCell>
                        <TableCell>{item.material?.familia || '-'}</TableCell>
                        <TableCell>{item.material?.unidad || '-'}</TableCell>
                        <TableCell className={`text-right font-semibold ${item.status === 'negative' ? 'text-rose-700' : 'text-slate-900'}`}>
                          {formatNumber(item.stock_actual, 4)}
                        </TableCell>
                        <TableCell className="text-right">{formatNumber(item.material_internal_total, 4)}</TableCell>
                        <TableCell className="text-right">{formatNumber(item.sap_on_hand, 4)}</TableCell>
                        <TableCell className="text-right">{formatNumber(item.distribution_ratio, 2)}%</TableCell>
                        <TableCell>
                          {item.status === 'negative' ? (
                            <Badge className="bg-rose-100 text-rose-700 hover:bg-rose-100">Negativo</Badge>
                          ) : item.status === 'zero' ? (
                            <Badge variant="outline" className="border-amber-300 text-amber-700">En cero</Badge>
                          ) : (
                            <Badge className="bg-emerald-100 text-emerald-700 hover:bg-emerald-100">Disponible</Badge>
                          )}
                        </TableCell>
                      </TableRow>

                      {isExpanded && (
                        <TableRow className="bg-slate-50/70 hover:bg-slate-50/70 border-b">
                          <TableCell colSpan={11} className="py-3 px-6">
                            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-xs space-y-3">
                              <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-2.5">
                                <div className="text-xs font-semibold uppercase tracking-wider text-slate-700 flex items-center gap-2">
                                  <span>Distribución espacial de material</span>
                                  <Badge variant="outline" className="text-xs font-medium border-slate-300 text-slate-700">
                                    {item.distributions?.length || 0} {item.distributions?.length === 1 ? 'posición' : 'posiciones'}
                                  </Badge>
                                </div>
                                <div className="text-xs text-slate-500">
                                  Ubicación: <span className="font-semibold text-slate-700">{item.location?.nombre}</span> · Material: <span className="font-semibold text-slate-700">{item.material?.codigo}</span> ({item.material?.nombre})
                                </div>
                              </div>

                              {hasDistributions ? (
                                <div className="overflow-x-auto rounded border border-slate-200">
                                  <table className="w-full text-xs text-left">
                                    <thead className="bg-slate-100/70 text-slate-600 font-semibold border-b border-slate-200">
                                      <tr>
                                        <th className="py-2.5 px-3">Prefijo</th>
                                        <th className="py-2.5 px-3">Columna</th>
                                        <th className="py-2.5 px-3">Fila</th>
                                        <th className="py-2.5 px-3">Coordenada completa</th>
                                        <th className="py-2.5 px-3 text-right">Stock en posición</th>
                                        <th className="py-2.5 px-3 text-center">Bultos / LPN</th>
                                        <th className="py-2.5 px-3">Lotes</th>
                                      </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                      {item.distributions.map((dist, idx) => (
                                        <tr key={idx} className="hover:bg-slate-50/60">
                                          <td className="py-2 px-3 font-medium text-slate-800">
                                            {dist.spatial_prefix ? (
                                              <Badge variant="outline" className="font-mono bg-slate-50 text-slate-700 border-slate-300">
                                                {dist.spatial_prefix}
                                              </Badge>
                                            ) : (
                                              <span className="text-slate-400 font-mono">-</span>
                                            )}
                                          </td>
                                          <td className="py-2 px-3 font-mono font-medium text-slate-800">
                                            {dist.spatial_column || '-'}
                                          </td>
                                          <td className="py-2 px-3 font-mono font-medium text-slate-800">
                                            {dist.spatial_row || '-'}
                                          </td>
                                          <td className="py-2 px-3 text-slate-600">
                                            {[
                                              dist.spatial_prefix ? `Prefijo ${dist.spatial_prefix}` : null,
                                              dist.spatial_column ? `Columna ${dist.spatial_column}` : null,
                                              dist.spatial_row ? `Fila ${dist.spatial_row}` : null,
                                            ].filter(Boolean).join(' · ') || 'Sin coordenadas específicas'}
                                          </td>
                                          <td className="py-2 px-3 text-right font-semibold text-emerald-800">
                                            {formatNumber(dist.quantity, 4)} {item.material?.unidad || ''}
                                          </td>
                                          <td className="py-2 px-3 text-center text-slate-600">
                                            {dist.lpn_count > 0 ? (
                                              <span className="inline-flex items-center px-2 py-0.5 rounded text-xs bg-slate-100 text-slate-700 font-medium">
                                                {dist.lpn_count}
                                              </span>
                                            ) : (
                                              <span className="text-slate-400">-</span>
                                            )}
                                          </td>
                                          <td className="py-2 px-3 text-slate-600">
                                            {dist.lot_codes && dist.lot_codes.length > 0 ? (
                                              <div className="flex flex-wrap gap-1">
                                                {dist.lot_codes.map((lot, lIdx) => (
                                                  <Badge key={lIdx} variant="secondary" className="text-[10px] px-1.5 py-0 bg-slate-100 text-slate-700">
                                                    {lot}
                                                  </Badge>
                                                ))}
                                              </div>
                                            ) : (
                                              <span className="text-slate-400">-</span>
                                            )}
                                          </td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                </div>
                              ) : (
                                <div className="py-3 px-4 text-center text-xs text-slate-500 italic bg-slate-50 rounded border border-dashed border-slate-200">
                                  No hay desglose específico de prefijo, columna y fila registrado para este saldo.
                                </div>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      )}
                    </Fragment>
                  )
                })}
                {(stocks?.data || []).length === 0 && (
                  <TableRow>
                    <TableCell colSpan={11} className="py-16 text-center">
                      <div className="space-y-2">
                        <div className="text-base font-medium text-slate-700">No hay posiciones de stock para estos filtros.</div>
                        <div className="text-sm text-slate-500">Prueba limpiando filtros o cambiando el estado del stock visible.</div>
                      </div>
                    </TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </div>

          {stocks?.links?.length ? (
            <div className="flex flex-col gap-3 text-sm text-slate-600 lg:flex-row lg:items-center lg:justify-between">
              <div>
                Mostrando {stocks.from ?? 0} a {stocks.to ?? 0} de {stocks.total ?? 0} posiciones
              </div>
              <div className="flex flex-wrap gap-1">
                {stocks.links.map((link, index) => (
                  <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    preserveScroll
                    preserveState
                    className={`rounded-md border px-3 py-1.5 ${link.active ? 'border-emerald-300 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-white'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
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

InventoryStocksIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Stock por ubicación</h2>}
  />
)
