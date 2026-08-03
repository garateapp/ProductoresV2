import { Link, router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
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
  locationTypes = [],
}) {
  const [filterData, setFilterData] = useState({
    q: filters.q || '',
    location_id: filters.location_id || '',
    location_type: filters.location_type || '',
    material_id: filters.material_id || '',
    family_id: filters.family_id || '',
    stock_state: filters.stock_state || 'positive',
    per_page: filters.per_page || '20',
  })

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
    return ['q', 'location_id', 'location_type', 'material_id', 'family_id']
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
      stock_state: 'positive',
      per_page: '20',
    }

    setFilterData(resetData)
    router.get(route('inventory.stocks.index'), resetData, {
      preserveScroll: true,
      preserveState: true,
    })
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
          <div className="flex items-center gap-2 text-sm text-slate-500">
            <Badge variant="outline" className="border-slate-300 bg-slate-50 text-slate-700">
              {activeFilterCount} filtros activos
            </Badge>
            <Button type="button" variant="outline" onClick={resetFilters}>Limpiar filtros</Button>
          </div>
        </CardHeader>
        <CardContent className="space-y-6 pt-6">
          <form onSubmit={applyFilters} className="grid gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 lg:grid-cols-7">
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
              options={materialOptions}
              value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, material_id: option?.value || '' }))}
              placeholder="Todos los materiales"
            />
            <SearchableSelect
              options={familyOptions}
              value={familyOptions.find((item) => item.value === String(filterData.family_id)) || null}
              onChange={(option) => setFilterData((current) => ({ ...current, family_id: option?.value || '' }))}
              placeholder="Todas las familias"
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
                  <TableHead>Ubicación</TableHead>
                  <TableHead>Material</TableHead>
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
                {(stocks?.data || []).map((item) => (
                  <TableRow key={item.id} className="align-top">
                    <TableCell>
                      <div className="font-medium text-slate-900">{item.location?.nombre || '-'}</div>
                      <div className="text-xs text-slate-500">{item.location?.codigo || '-'} · {item.location?.tipo || '-'}</div>
                    </TableCell>
                    <TableCell>
                      <div className="font-medium text-slate-900">{item.material?.codigo || '-'}</div>
                      <div className="text-xs text-slate-500">{item.material?.nombre || '-'}</div>
                    </TableCell>
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
                ))}
                {(stocks?.data || []).length === 0 && (
                  <TableRow>
                    <TableCell colSpan={9} className="py-16 text-center">
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
