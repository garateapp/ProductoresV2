import { useState } from 'react'
import { router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import SearchableSelect from '@/Components/SearchableSelect'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Download, Filter, Search } from 'lucide-react'

const number = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

const tipoOptions = [
  { value: '', label: 'Todos los tipos' },
  { value: 'normal', label: 'Consumo normal' },
  { value: 'temporal', label: 'Consumo temporal' },
  { value: 'merma', label: 'Merma' },
]

const categoriaVariant = (categoria) =>
  ({
    normal: 'default',
    temporal: 'outline',
    merma: 'secondary',
  })[categoria] || 'default'

const categoriaClass = (categoria) =>
  ({
    normal: '',
    temporal: 'text-violet-600 border-violet-200',
    merma: 'text-orange-600 border-orange-200',
  })[categoria] || ''

export default function ConsumptionReportIndex({
  filters = {},
  totals = {},
  byService = [],
  byMaterial = [],
  byDate = [],
  movements = [],
  services = [],
  materials = [],
  locations = [],
}) {
  const [filterData, setFilterData] = useState({
    date_from: filters.date_from || '',
    date_to: filters.date_to || '',
    service_id: filters.service_id || '',
    material_id: filters.material_id || '',
    origin_location_id: filters.origin_location_id || '',
    tipo_folio: filters.tipo_folio || '',
    incluir_mermas: filters.incluir_mermas === '0' || filters.incluir_mermas === false ? false : true,
    q: filters.q || '',
  })

  const serviceOptions = services.map((item) => ({ value: String(item.id), label: item.name }))
  const materialOptions = materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const locationOptions = locations.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))

  const setField = (field, value) => setFilterData((current) => ({ ...current, [field]: value }))

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.consumption-reports.index'), filterData, { preserveScroll: true, preserveState: true })
  }

  const clearFilters = () => {
    const next = {
      date_from: '',
      date_to: '',
      service_id: '',
      material_id: '',
      origin_location_id: '',
      tipo_folio: '',
      incluir_mermas: true,
      q: '',
    }
    setFilterData(next)
    router.get(route('inventory.consumption-reports.index'), next, { preserveScroll: true, preserveState: true })
  }

  const exportUrl = (name) => {
    const params = new URLSearchParams()
    Object.entries(filterData).forEach(([key, value]) => {
      if (value !== '' && value !== null && value !== undefined) params.set(key, String(value))
    })
    return `${route(name)}?${params.toString()}`
  }

  return (
    <div className="mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div>
              <CardTitle>Consumo de materiales por servicio</CardTitle>
              <p className="mt-1 text-sm text-slate-600">
                Consumo normal y temporal de folios de producción, más mermas. Consumo normal + temporal = consumo real.
              </p>
            </div>
            <div className="flex gap-2">
              <Button type="button" variant="outline" asChild>
                <a href={exportUrl('inventory.consumption-reports.export-csv')}><Download className="mr-2 h-4 w-4" /> CSV</a>
              </Button>
              <Button type="button" variant="outline" asChild>
                <a href={exportUrl('inventory.consumption-reports.export-pdf')}><Download className="mr-2 h-4 w-4" /> PDF</a>
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent className="space-y-4">
          <form onSubmit={applyFilters} className="grid gap-3 rounded border bg-slate-50 p-4 md:grid-cols-8">
            <div>
              <Label>Desde</Label>
              <Input type="date" value={filterData.date_from} onChange={(e) => setField('date_from', e.target.value)} />
            </div>
            <div>
              <Label>Hasta</Label>
              <Input type="date" value={filterData.date_to} onChange={(e) => setField('date_to', e.target.value)} />
            </div>
            <div>
              <Label>Servicio</Label>
              <SearchableSelect
                options={serviceOptions}
                value={serviceOptions.find((item) => item.value === String(filterData.service_id)) || null}
                onChange={(option) => setField('service_id', option?.value || '')}
                placeholder="Todos"
              />
            </div>
            <div className="md:col-span-2">
              <Label>Material</Label>
              <SearchableSelect
                options={materialOptions}
                value={materialOptions.find((item) => item.value === String(filterData.material_id)) || null}
                onChange={(option) => setField('material_id', option?.value || '')}
                placeholder="Todos los materiales"
              />
            </div>
            <div>
              <Label>Origen</Label>
              <SearchableSelect
                options={locationOptions}
                value={locationOptions.find((item) => item.value === String(filterData.origin_location_id)) || null}
                onChange={(option) => setField('origin_location_id', option?.value || '')}
                placeholder="Todas"
              />
            </div>
            <div>
              <Label>Tipo</Label>
              <SearchableSelect
                options={tipoOptions}
                value={tipoOptions.find((item) => item.value === String(filterData.tipo_folio)) || null}
                onChange={(option) => setField('tipo_folio', option?.value || '')}
                placeholder="Todos"
              />
            </div>
            <label className="flex items-center gap-2 text-sm text-slate-700 md:col-span-2">
              <input
                type="checkbox"
                checked={filterData.incluir_mermas}
                onChange={(e) => setField('incluir_mermas', e.target.checked)}
                className="h-4 w-4 rounded border-slate-300"
              />
              Incluir mermas
            </label>
            <div className="relative md:col-span-4">
              <Search className="pointer-events-none absolute left-2 top-2 h-4 w-4 text-slate-400" />
              <Input className="pl-8" value={filterData.q} onChange={(e) => setField('q', e.target.value)} placeholder="Buscar folio o material" />
            </div>
            <div className="flex items-end gap-2 md:col-span-2">
              <Button type="submit"><Filter className="mr-2 h-4 w-4" /> Filtrar</Button>
              <Button type="button" variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
          </form>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <SummaryBox label="Consumo normal" value={number(totals.consumo_normal)} tone="default" />
            <SummaryBox label="Consumo temporal" value={number(totals.consumo_temporal)} tone="temporal" />
            <SummaryBox label="Total consumo" value={number(totals.consumo_total)} tone="default" />
            <SummaryBox label="Mermas" value={number(totals.merma)} tone="merma" />
            <SummaryBox label="Gran total" value={number(totals.gran_total)} tone="default" />
          </div>

          <Section title="Por servicio">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Servicio</TableHead>
                  <TableHead className="text-right">Materiales</TableHead>
                  <TableHead className="text-right">Consumo normal</TableHead>
                  <TableHead className="text-right">Consumo temporal</TableHead>
                  <TableHead className="text-right">Total consumo</TableHead>
                  <TableHead className="text-right">Mermas</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {byService.map((row) => (
                  <TableRow key={row.service_id ?? 'sin'}>
                    <TableCell className="font-medium">{row.service_name}</TableCell>
                    <TableCell className="text-right">{row.materiales}</TableCell>
                    <TableCell className="text-right">{number(row.normal)}</TableCell>
                    <TableCell className="text-right">{number(row.temporal)}</TableCell>
                    <TableCell className="text-right font-semibold">{number(row.consumo_total)}</TableCell>
                    <TableCell className="text-right">{number(row.merma)}</TableCell>
                    <TableCell className="text-right">{number(row.gran_total)}</TableCell>
                  </TableRow>
                ))}
                {!byService.length ? <EmptyRow colSpan={7} /> : null}
              </TableBody>
            </Table>
          </Section>

          <Section title="Por material" subtitle="Desglose por material para el rango y filtros seleccionados.">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Servicio</TableHead>
                  <TableHead>Material</TableHead>
                  <TableHead>Código</TableHead>
                  <TableHead className="text-right">Consumo normal</TableHead>
                  <TableHead className="text-right">Consumo temporal</TableHead>
                  <TableHead className="text-right">Total consumo</TableHead>
                  <TableHead className="text-right">Mermas</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {byMaterial.map((row) => (
                  <TableRow key={row.material_id}>
                    <TableCell>{row.service_name}</TableCell>
                    <TableCell className="font-medium">{row.material_nombre}</TableCell>
                    <TableCell className="font-mono text-xs text-slate-500">{row.material_codigo}</TableCell>
                    <TableCell className="text-right">{number(row.normal)}</TableCell>
                    <TableCell className="text-right">{number(row.temporal)}</TableCell>
                    <TableCell className="text-right font-semibold">{number(row.consumo_total)}</TableCell>
                    <TableCell className="text-right">{number(row.merma)}</TableCell>
                    <TableCell className="text-right">{number(row.gran_total)}</TableCell>
                  </TableRow>
                ))}
                {!byMaterial.length ? <EmptyRow colSpan={8} /> : null}
              </TableBody>
            </Table>
          </Section>

          <Section title="Por fecha">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead className="text-right">Consumo normal</TableHead>
                  <TableHead className="text-right">Consumo temporal</TableHead>
                  <TableHead className="text-right">Total consumo</TableHead>
                  <TableHead className="text-right">Mermas</TableHead>
                  <TableHead className="text-right">Total</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {byDate.map((row) => (
                  <TableRow key={row.fecha}>
                    <TableCell className="font-medium">{row.fecha}</TableCell>
                    <TableCell className="text-right">{number(row.normal)}</TableCell>
                    <TableCell className="text-right">{number(row.temporal)}</TableCell>
                    <TableCell className="text-right font-semibold">{number(row.consumo_total)}</TableCell>
                    <TableCell className="text-right">{number(row.merma)}</TableCell>
                    <TableCell className="text-right">{number(row.gran_total)}</TableCell>
                  </TableRow>
                ))}
                {!byDate.length ? <EmptyRow colSpan={6} /> : null}
              </TableBody>
            </Table>
          </Section>

          <Section title="Movimientos recientes" subtitle="Últimos 100 movimientos según filtros.">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Movimiento</TableHead>
                  <TableHead>Folio producción</TableHead>
                  <TableHead>Origen</TableHead>
                  <TableHead className="text-right">Cantidad</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Categoría</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {movements.map((row) => (
                  <TableRow key={row.movement_id}>
                    <TableCell>{row.fecha || '-'}</TableCell>
                    <TableCell>
                      <div className="font-mono text-xs">{row.movement_folio || '-'}</div>
                      <div className="text-xs text-slate-500">{row.tipo || '-'}</div>
                    </TableCell>
                    <TableCell className="font-mono text-xs">
                      {row.folio_produccion || '-'}
                      {row.folio_cantidad !== null && row.folio_cantidad !== undefined
                        ? <div className="text-slate-500">{number(row.folio_cantidad)} plt</div>
                        : null}
                    </TableCell>
                    <TableCell className="text-sm">{row.origen || '-'}</TableCell>
                    <TableCell className="text-right font-semibold">{number(row.cantidad)}</TableCell>
                    <TableCell><Badge variant="outline">{row.movement_estado || '-'}</Badge></TableCell>
                    <TableCell>
                      <Badge variant={categoriaVariant(row.categoria)} className={categoriaClass(row.categoria)}>
                        {row.categoria_label || '-'}
                      </Badge>
                    </TableCell>
                  </TableRow>
                ))}
                {!movements.length ? <EmptyRow colSpan={7} /> : null}
              </TableBody>
            </Table>
          </Section>
        </CardContent>
      </Card>
    </div>
  )
}

function SummaryBox({ label, value, tone }) {
  const classes = {
    default: 'text-slate-900',
    temporal: 'text-violet-700',
    merma: 'text-orange-700',
  }[tone] || 'text-slate-900'

  return (
    <div className="rounded-md border bg-white p-4 shadow-sm">
      <div className="text-xs font-medium uppercase tracking-wide text-slate-500">{label}</div>
      <div className={`mt-1 text-xl font-bold ${classes}`}>{value}</div>
    </div>
  )
}

function Section({ title, subtitle, children }) {
  return (
    <div className="rounded-md border bg-white">
      <div className="border-b px-4 py-3">
        <h3 className="text-sm font-semibold text-slate-900">{title}</h3>
        {subtitle ? <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p> : null}
      </div>
      <div className="p-4">{children}</div>
    </div>
  )
}

function EmptyRow({ colSpan }) {
  return (
    <TableRow>
      <TableCell colSpan={colSpan} className="py-8 text-center text-sm text-slate-500">
        Sin datos para los filtros seleccionados.
      </TableCell>
    </TableRow>
  )
}

ConsumptionReportIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Consumo por servicio</h2>}
  />
)