import React, { useMemo, useState } from 'react'
import { router } from '@inertiajs/react'
import Select from 'react-select'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
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

function formatNumber(value, digits = 0) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) return '0'
  return number.toLocaleString('es-CL', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })
}

function formatDate(value) {
  if (!value) return '—'
  const text = String(value)
  if (/^\d{4}-\d{2}-\d{2}/.test(text)) return text.slice(0, 10)
  if (/^\d{2}\/\d{2}\/\d{4}$/.test(text)) return text
  const date = new Date(text)
  return Number.isNaN(date.getTime()) ? text : date.toISOString().slice(0, 10)
}

const COLUMN_KEYS = [
  'exportadora',
  'especie',
  'numproceso',
  'fecha_proceso',
  'n_g_recepcion',
  'csg_productor',
  'referencias_recepcion',
  'numero_guia',
  'fecha_recepcion',
  'fecha_cosecha',
  'n_categoria',
  'n_productor',
  'n_variedad',
  'nota_calidad_sqlsrv',
  'c_centrocosto',
  'n_bodega',
  'cantidad_bins',
  'peso_neto',
]

function normalizeText(value) {
  return String(value ?? '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
}

function isPlanned(row) {
  return Boolean(row.numproceso) && row.numproceso !== '—'
}

function toOptions(values) {
  return [...new Set((values || []).map((value) => String(value)).filter((value) => value !== ''))]
    .map((value) => ({ value, label: value }))
}

function toMultiValues(value) {
  if (Array.isArray(value)) {
    return value.map((item) => String(item)).filter((item) => item !== '')
  }

  if (value === null || value === undefined || value === '') return []

  return [String(value)]
}

function MultiSelectFilter({ label, value, options, onChange, placeholder = 'Seleccionar', hideLabel = false }) {
  const selected = toMultiValues(value)
  const selectedOptions = (options || []).filter((option) => selected.includes(String(option.value)))

  return (
    <div>
      {!hideLabel ? <Label>{label}</Label> : null}
      <Select
        isMulti
        closeMenuOnSelect={false}
        options={options || []}
        value={selectedOptions}
        onChange={(items) => onChange((items || []).map((item) => String(item.value)))}
        placeholder={placeholder}
        className={hideLabel ? 'text-xs' : 'mt-1 text-sm'}
        classNamePrefix="react-select"
        menuPortalTarget={typeof document !== 'undefined' ? document.body : null}
        styles={{ menuPortal: (base) => ({ ...base, zIndex: 9999 }) }}
        noOptionsMessage={() => 'Sin resultados'}
      />
    </div>
  )
}

export default function FruitInventoryIndex({ filters = {}, options = {}, detalle = [] }) {
  const [columnFilters, setColumnFilters] = useState({})

  const apply = (patch) => {
    router.get(
      route('planning.fruit-inventory.index'),
      { ...(filters || {}), ...patch },
      { preserveState: true, replace: true },
    )
  }

  const limpiar = () => {
    setColumnFilters({})
    router.get(route('planning.fruit-inventory.index'), {}, { preserveState: true, replace: true })
  }

  const columnOptions = useMemo(() => {
    const numericKeys = ['cantidad_bins', 'peso_neto']
    const dateKeys = ['fecha_proceso', 'fecha_recepcion', 'fecha_cosecha']

    return COLUMN_KEYS.reduce((acc, key) => {
      const seen = new Set()
      acc[key] = []

      detalle.forEach((row) => {
        const raw = row[key] === null || row[key] === undefined || row[key] === '' ? '—' : String(row[key])
        if (seen.has(raw)) return
        seen.add(raw)

        let label = raw
        if (dateKeys.includes(key)) {
          label = formatDate(raw)
        } else if (numericKeys.includes(key) && Number.isFinite(Number(raw))) {
          label = formatNumber(Number(raw), key === 'cantidad_bins' ? 0 : 3)
        }

        acc[key].push({ value: raw, label })
      })

      acc[key].sort((a, b) => String(a.label).localeCompare(String(b.label), 'es', { numeric: true }))
      return acc
    }, {})
  }, [detalle])

  const sortedDetalle = useMemo(
    () => [...detalle].sort((a, b) => Number(isPlanned(a)) - Number(isPlanned(b))),
    [detalle],
  )

  const filteredDetalle = useMemo(() => {
    const active = Object.entries(columnFilters)
      .map(([key, values]) => [key, toMultiValues(values).map(normalizeText)])
      .filter(([, values]) => values.length > 0)

    if (active.length === 0) return sortedDetalle

    return sortedDetalle.filter((row) =>
      active.every(([key, values]) => {
        const raw = row[key] === null || row[key] === undefined || row[key] === '' ? '—' : String(row[key])
        return values.includes(normalizeText(raw))
      }),
    )
  }, [sortedDetalle, columnFilters])

  const resumenFiltrado = useMemo(() => {
    const sumRows = (rows, key) => rows.reduce((total, row) => total + Number(row[key] || 0), 0)
    const grupos = filteredDetalle.reduce((acc, row) => {
      const key = String(row.exportadora || '(sin exportadora)')
      if (!acc[key]) acc[key] = []
      acc[key].push(row)
      return acc
    }, {})

    return {
      general: {
        lotes: filteredDetalle.length,
        cantidad_bins: sumRows(filteredDetalle, 'cantidad_bins'),
        peso_neto: sumRows(filteredDetalle, 'peso_neto'),
        asignado_peso_neto: sumRows(filteredDetalle, 'asignado_peso_neto'),
        pendiente_peso_neto: sumRows(filteredDetalle, 'pendiente_peso_neto'),
      },
      por_exportadora: Object.entries(grupos)
        .map(([exportadora, rows]) => ({
          exportadora,
          lotes: rows.length,
          cantidad_bins: sumRows(rows, 'cantidad_bins'),
          peso_neto: sumRows(rows, 'peso_neto'),
          asignado_peso_neto: sumRows(rows, 'asignado_peso_neto'),
          pendiente_peso_neto: sumRows(rows, 'pendiente_peso_neto'),
        }))
        .sort((a, b) => String(a.exportadora).localeCompare(String(b.exportadora))),
    }
  }, [filteredDetalle])

  const general = resumenFiltrado.general
  const porExportadora = resumenFiltrado.por_exportadora

  return (
    <AuthenticatedLayout>
      <div className="w-full space-y-4 px-4 py-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <div className="text-xl font-bold">Existencias de fruta</div>
            <div className="text-sm text-gray-600">
              Stock SQLSRV vs. peso planificado en processes + process_lots
            </div>
          </div>
          <Button variant="outline" onClick={limpiar}>
            Limpiar filtros
          </Button>
        </div>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-lg font-bold">Filtros</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 gap-3 md:grid-cols-5">
              <MultiSelectFilter
                label="Especie"
                value={filters.especie}
                options={toOptions(options.especies)}
                onChange={(values) => apply({ especie: values })}
                placeholder="Todas las especies"
              />
              <MultiSelectFilter
                label="Variedad"
                value={filters.variedad}
                options={toOptions(options.variedades)}
                onChange={(values) => apply({ variedad: values })}
                placeholder="Todas las variedades"
              />
              <MultiSelectFilter
                label="Exportadora"
                value={filters.exportadora}
                options={toOptions(options.exportadoras)}
                onChange={(values) => apply({ exportadora: values })}
                placeholder="Todas las exportadoras"
              />
              <div>
                <Label>Fecha planificación desde</Label>
                <Input
                  className="mt-1"
                  type="date"
                  value={String(filters.fecha_from || '')}
                  onChange={(event) => apply({ fecha_from: event.target.value })}
                />
              </div>
              <div>
                <Label>Fecha planificación hasta</Label>
                <Input
                  className="mt-1"
                  type="date"
                  value={String(filters.fecha_to || '')}
                  onChange={(event) => apply({ fecha_to: event.target.value })}
                />
              </div>
            </div>
          </CardContent>
        </Card>

        <div className="grid grid-cols-2 gap-3 md:grid-cols-4">
          <Card>
            <CardContent className="py-4">
              <div className="text-xs text-gray-600">Peso neto general</div>
              <div className="text-2xl font-bold">{formatNumber(general.peso_neto, 3)} kg</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="py-4">
              <div className="text-xs text-gray-600">Asignado en planificación</div>
              <div className="text-2xl font-bold text-red-600">
                {formatNumber(general.asignado_peso_neto, 3)} kg
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="py-4">
              <div className="text-xs text-gray-600">Falta por asignar</div>
              <div className="text-2xl font-bold">{formatNumber(general.pendiente_peso_neto, 3)} kg</div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="py-4">
              <div className="text-xs text-gray-600">Bins / lotes</div>
              <div className="text-2xl font-bold">
                {formatNumber(general.cantidad_bins)} / {formatNumber(general.lotes)}
              </div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-lg font-bold">Existencias por exportadora</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Exportadora</TableHead>
                    <TableHead className="text-right">Lotes</TableHead>
                    <TableHead className="text-right">Bins</TableHead>
                    <TableHead className="text-right">Peso neto</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {porExportadora.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={4} className="py-8 text-center text-sm text-gray-600">
                        Sin datos para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : (
                    porExportadora.map((row) => (
                      <TableRow key={String(row.exportadora)}>
                        <TableCell className="font-medium">{row.exportadora}</TableCell>
                        <TableCell className="text-right">{formatNumber(row.lotes)}</TableCell>
                        <TableCell className="text-right">{formatNumber(row.cantidad_bins)}</TableCell>
                        <TableCell className="text-right">{formatNumber(row.peso_neto, 3)}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
                {porExportadora.length > 0 ? (
                  <tfoot className="border-t font-semibold">
                    <TableRow>
                      <TableCell>Total</TableCell>
                      <TableCell className="text-right">{formatNumber(general.lotes)}</TableCell>
                      <TableCell className="text-right">{formatNumber(general.cantidad_bins)}</TableCell>
                      <TableCell className="text-right">{formatNumber(general.peso_neto, 3)}</TableCell>
                    </TableRow>
                  </tfoot>
                ) : null}
              </Table>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-lg font-bold">
              Detalle de existencias ({filteredDetalle.length}/{detalle.length})
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Exportadora</TableHead>
                    <TableHead>Especie</TableHead>
                    <TableHead>Proceso</TableHead>
                    <TableHead>F. proceso</TableHead>
                    <TableHead>N° G. Recepción</TableHead>
                    <TableHead>CSG Productor</TableHead>
                    <TableHead>Referencias recepción</TableHead>
                    <TableHead>N° guía</TableHead>
                    <TableHead>Fecha recepción</TableHead>
                    <TableHead>Fecha cosecha</TableHead>
                    <TableHead>Categoría</TableHead>
                    <TableHead>Productor</TableHead>
                    <TableHead>Variedad</TableHead>
                    <TableHead>Nota calidad</TableHead>
                    <TableHead>C. centro costo</TableHead>
                    <TableHead>Bodega</TableHead>
                    <TableHead className="text-right">Bins</TableHead>
                    <TableHead className="text-right">Peso neto</TableHead>
                  </TableRow>
                  <TableRow>
                    {COLUMN_KEYS.map((key) => (
                      <TableHead key={key} className="p-1">
                        <div className="min-w-[110px]">
                          <MultiSelectFilter
                            value={columnFilters[key] || []}
                            options={columnOptions[key] || []}
                            placeholder="Filtrar"
                            hideLabel
                            onChange={(values) =>
                              setColumnFilters((prev) => ({ ...prev, [key]: values }))
                            }
                          />
                        </div>
                      </TableHead>
                    ))}
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredDetalle.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={18} className="py-8 text-center text-sm text-gray-600">
                        Sin datos para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : (
                    filteredDetalle.map((row) => (
                      <TableRow
                        key={`${String(row.exportadora)}-${String(row.n_g_recepcion)}`}
                        className={isPlanned(row) ? 'bg-red-50 text-red-700' : undefined}
                      >
                        <TableCell className="whitespace">{row.exportadora}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.especie ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.numproceso ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{formatDate(row.fecha_proceso)}</TableCell>
                        <TableCell className="whitespace-nowrap font-medium">{row.n_g_recepcion}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.csg_productor ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.referencias_recepcion ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.numero_guia ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{formatDate(row.fecha_recepcion)}</TableCell>
                        <TableCell className="whitespace-nowrap">{formatDate(row.fecha_cosecha)}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.n_categoria ?? '—'}</TableCell>
                        <TableCell className="whitespace">{row.n_productor ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.n_variedad ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.nota_calidad_sqlsrv ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.c_centrocosto ?? '—'}</TableCell>
                        <TableCell className="whitespace-nowrap">{row.n_bodega ?? '—'}</TableCell>
                        <TableCell className="text-right">{formatNumber(row.cantidad_bins)}</TableCell>
                        <TableCell className="text-right">{formatNumber(row.peso_neto, 3)}</TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
                {filteredDetalle.length > 0 ? (
                  <tfoot className="border-t font-semibold">
                    <TableRow>
                      <TableCell colSpan={16}>Total</TableCell>
                      <TableCell className="text-right">{formatNumber(general.cantidad_bins)}</TableCell>
                      <TableCell className="text-right">{formatNumber(general.peso_neto, 3)}</TableCell>
                    </TableRow>
                  </tfoot>
                ) : null}
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  )
}
