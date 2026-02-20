import React, { useMemo, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Bug } from 'lucide-react'

function formatNumber(n, digits = 0) {
  const val = Number(n || 0)
  if (!Number.isFinite(val)) return '0'
  return val.toLocaleString('es-CL', { minimumFractionDigits: digits, maximumFractionDigits: digits })
}

function speciesBadgeClasses(species) {
  const s = String(species || '').toLowerCase()
  if (s.includes('cherr') || s.includes('cerez')) return 'bg-red-50 text-red-800 border-red-200'
  if (s.includes('nectar')) return 'bg-amber-50 text-amber-800 border-amber-200'
  if (s.includes('duraz') || s.includes('peach')) return 'bg-orange-50 text-orange-800 border-orange-200'
  if (s.includes('ciruel') || s.includes('plum')) return 'bg-violet-50 text-violet-800 border-violet-200'
  if (s.includes('uva') || s.includes('grape')) return 'bg-emerald-50 text-emerald-800 border-emerald-200'
  return 'bg-slate-50 text-slate-700 border-slate-200'
}

function buildVisibleColumns(weeks = [], collapsedWeeks = {}) {
  const cols = []
  ;(weeks || []).forEach((w) => {
    const isCollapsed = Boolean(collapsedWeeks[w.key])
    if (isCollapsed) {
      cols.push({ type: 'week', key: w.key, week: w })
      return
    }
    ;(w.days || []).forEach((d) => cols.push({ type: 'day', key: d, day: d, weekKey: w.key, week: w }))
  })
  return cols
}

function isoWeekLabel(weekKey) {
  return String(weekKey || '').replace('-', ' ')
}

function nextMondayFrom(dateStr) {
  const base = dateStr ? new Date(`${dateStr}T00:00:00`) : new Date()
  const d = new Date(base.getFullYear(), base.getMonth(), base.getDate())
  const day = d.getDay() // 0..6 (0=domingo)
  const isoDay = ((day + 6) % 7) + 1 // 1..7 (1=lunes)
  const add = 8 - isoDay
  d.setDate(d.getDate() + add)
  return d.toISOString().slice(0, 10)
}

function WeekHeader({ week, collapsed, onToggle }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <div>
        <div className="font-semibold">{isoWeekLabel(week.key)}</div>
        <div className="text-[11px] text-gray-500">{week.range_label}</div>
      </div>
      <Button size="sm" variant="outline" onClick={onToggle}>
        {collapsed ? 'Expandir' : 'Colapsar'}
      </Button>
    </div>
  )
}

function MexicoFlagIcon({ className = '' }) {
  return (
    <svg
      className={className}
      viewBox="0 0 24 16"
      role="img"
      aria-label="México"
    >
      <rect x="0.5" y="0.5" width="23" height="15" rx="2" fill="#ffffff" stroke="#e5e7eb" />
      <rect x="0.5" y="0.5" width="7.66" height="15" rx="2" fill="#0f9d58" opacity="0.95" />
      <rect x="15.84" y="0.5" width="7.66" height="15" rx="2" fill="#db4437" opacity="0.95" />
      <circle cx="12" cy="8" r="1.3" fill="#111827" opacity="0.35" />
    </svg>
  )
}

function EstBadge({ children }) {
  return (
    <Badge
      variant="outline"
      className="bg-sky-50 text-sky-800 border-sky-200 text-[10px] font-bold px-1.5 py-0 leading-4"
      title="Estimación"
    >
      {children}
    </Badge>
  )
}

function MatrixTable({
  days = [],
  weeks = [],
  rows = [],
  tab = {},
  collapsedWeeks = {},
  setCollapsedWeeks,
  showFutureOverlay = false,
  overlayTab = null,
  rowBadges = {},
}) {
  // Estas dos primeras columnas son sticky. El ancho debe ser estable para que la columna "Total"
  // no se monte sobre otras celdas al hacer scroll horizontal.
  const stickyCol1WidthPx = 560

  const speciesGroupKey = (value) => {
    const raw = String(value || '').trim()
    if (!raw) return ''
    try {
      return raw
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/\s+/g, ' ')
        .trim()
    } catch {
      return raw.toLowerCase().replace(/\s+/g, ' ').trim()
    }
  }

  const dayMeta = useMemo(() => {
    const map = new Map()
    ;(days || []).forEach((d) => map.set(d.date, d))
    return map
  }, [days])

  const todayDate = useMemo(() => {
    const found = (days || []).find((d) => Boolean(d?.is_today))
    return String(found?.date || new Date().toISOString().slice(0, 10))
  }, [days])

  const isFutureDay = (date) => String(date || '') > todayDate

  const cols = useMemo(() => buildVisibleColumns(weeks, collapsedWeeks), [weeks, collapsedWeeks])

  const totalsByDay = tab?.totals_by_day || {}
  const rowTotals = tab?.row_totals || {}
  const cells = tab?.cells || {}

  const overlayCells = overlayTab?.cells || {}
  const overlayTotalsByDay = overlayTab?.totals_by_day || {}

  const overlayFutureRowTotals = useMemo(() => {
    if (!showFutureOverlay) return {}
    const futureDays = (days || []).map((d) => d.date).filter((d) => isFutureDay(d))
    const next = {}
    ;(rows || []).forEach((r) => {
      const row = overlayCells?.[r.key] || {}
      next[r.key] = futureDays.reduce((acc, d) => acc + Number(row?.[d] || 0), 0)
    })
    return next
  }, [days, overlayCells, rows, showFutureOverlay, todayDate])

  const visibleRows = useMemo(() => {
    if (!showFutureOverlay) {
      return (rows || []).filter((r) => Number(rowTotals?.[r.key] || 0) > 0)
    }
    return (rows || []).filter((r) => {
      const base = Number(rowTotals?.[r.key] || 0)
      const overlay = Number(overlayFutureRowTotals?.[r.key] || 0)
      return base > 0 || overlay > 0
    })
  }, [rows, rowTotals, showFutureOverlay, overlayFutureRowTotals])

  const groupedVisibleRows = useMemo(() => {
    const groupsMap = new Map()
    ;(visibleRows || []).forEach((r) => {
      const label = String(r.especie || 'Sin especie').trim() || 'Sin especie'
      const key = speciesGroupKey(label) || 'sin-especie'
      if (!groupsMap.has(key)) groupsMap.set(key, { key, especie: label, items: [] })
      const group = groupsMap.get(key)
      group.items.push(r)
    })

    const groups = [...groupsMap.values()]
    groups.sort((a, b) => String(a.especie).localeCompare(String(b.especie), 'es'))
    groups.forEach((g) => {
      g.items.sort((a, b) => {
        const byVariedad = String(a.variedad || '').localeCompare(String(b.variedad || ''), 'es')
        if (byVariedad !== 0) return byVariedad
        // México primero dentro de la misma variedad.
        return Number(Boolean(b.mexico)) - Number(Boolean(a.mexico))
      })
    })
    return groups
  }, [visibleRows])

  const speciesTotals = useMemo(() => {
    const out = {}

    ;(groupedVisibleRows || []).forEach((g) => {
      const dayTotals = {}
      const overlayDayTotals = {}
      ;(days || []).forEach((d) => {
        dayTotals[d.date] = 0
        overlayDayTotals[d.date] = 0
      })

      let total = 0
      let overlayTotalFuture = 0

      ;(g.items || []).forEach((r) => {
        const row = cells?.[r.key] || {}
        const rowOverlay = overlayCells?.[r.key] || {}

        ;(days || []).forEach((d) => {
          const val = Number(row?.[d.date] || 0)
          if (val) dayTotals[d.date] += val

          const ov = Number(rowOverlay?.[d.date] || 0)
          if (ov) overlayDayTotals[d.date] += ov
          if (showFutureOverlay && isFutureDay(d.date) && ov) overlayTotalFuture += ov
        })

        total += Number(rowTotals?.[r.key] || 0)
      })

      out[g.key] = { dayTotals, overlayDayTotals, total, overlayTotalFuture }
    })

    return out
  }, [cells, days, groupedVisibleRows, isFutureDay, overlayCells, rowTotals, showFutureOverlay])

  const weekTotalForRow = (rowKey, week) => {
    const row = cells?.[rowKey] || {}
    return (week.days || []).reduce((acc, d) => acc + Number(row?.[d] || 0), 0)
  }

  const weekOverlayTotalForRowFuture = (rowKey, week) => {
    if (!showFutureOverlay) return 0
    const row = overlayCells?.[rowKey] || {}
    return (week.days || []).reduce((acc, d) => acc + (isFutureDay(d) ? Number(row?.[d] || 0) : 0), 0)
  }

  const weekTotalGlobal = (week) => {
    return (week.days || []).reduce((acc, d) => acc + Number(totalsByDay?.[d] || 0), 0)
  }

  const weekOverlayTotalGlobalFuture = (week) => {
    if (!showFutureOverlay) return 0
    return (week.days || []).reduce((acc, d) => acc + (isFutureDay(d) ? Number(overlayTotalsByDay?.[d] || 0) : 0), 0)
  }

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-3">
        {(weeks || []).map((w) => (
          <Card key={w.key}>
            <CardContent className="p-3">
              <WeekHeader
                week={w}
                collapsed={Boolean(collapsedWeeks[w.key])}
                onToggle={() => setCollapsedWeeks((prev) => ({ ...prev, [w.key]: !prev[w.key] }))}
              />
            </CardContent>
          </Card>
        ))}
      </div>

      <div
        className="overflow-auto border rounded"
        style={{ '--ff-sticky-col1': `${stickyCol1WidthPx}px` }}
      >
        <Table className="min-w-[1200px]">
          <TableHeader>
            <TableRow>
              <TableHead className="sticky left-0 bg-white z-30 w-[560px] min-w-[560px] max-w-[560px]">Especie / Variedad</TableHead>
              <TableHead className="sticky bg-white z-30 text-right min-w-[120px]" style={{ left: 'var(--ff-sticky-col1)' }}>Total</TableHead>

              {cols.map((c) => {
                if (c.type === 'week') {
                  return (
                    <TableHead key={c.key} className="text-right min-w-[120px] bg-gray-50">
                      {isoWeekLabel(c.key)}
                    </TableHead>
                  )
                }
                const meta = dayMeta.get(c.day)
                const isToday = Boolean(meta?.is_today)
                return (
                  <TableHead key={c.key} className={`text-right min-w-[90px] ${isToday ? 'bg-amber-50' : ''}`}>
                    <div className="text-xs">{meta?.label || c.day}</div>
                    <div className="text-[11px] text-gray-500">D{meta?.dow}</div>
                  </TableHead>
                )
              })}
            </TableRow>
          </TableHeader>

          <TableBody>
            {(groupedVisibleRows || []).map((g) => {
              const st = speciesTotals?.[g.key] || { dayTotals: {}, overlayDayTotals: {}, total: 0, overlayTotalFuture: 0 }
              return (
                <React.Fragment key={`g:${g.key}`}>
                  <TableRow className="bg-slate-50">
                    <TableCell className="sticky left-0 bg-slate-50 z-20 w-[560px] min-w-[560px] max-w-[560px]">
                      <div className="flex items-center gap-2">
                        <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-bold ${speciesBadgeClasses(g.especie)}`}>
                          {g.especie}
                        </span>
                        <span className="text-xs text-gray-500">Totales</span>
                      </div>
                    </TableCell>
                    <TableCell className="sticky bg-slate-50 z-20 text-right font-bold" style={{ left: 'var(--ff-sticky-col1)' }}>
                      <div>{formatNumber(st.total, 0)}</div>
                      {showFutureOverlay && st.overlayTotalFuture > 0 ? (
                        <div className="mt-1">
                          <EstBadge>{formatNumber(st.overlayTotalFuture, 0)}</EstBadge>
                        </div>
                      ) : null}
                    </TableCell>

                    {cols.map((c) => {
                        if (c.type === 'week') {
                          const val = (c.week.days || []).reduce((acc, d) => acc + Number(st.dayTotals?.[d] || 0), 0)
                          const overlayVal = showFutureOverlay
                            ? (c.week.days || []).reduce((acc, d) => acc + (isFutureDay(d) ? Number(st.overlayDayTotals?.[d] || 0) : 0), 0)
                            : 0
                          return (
                          <TableCell key={`g:${g.key}:${c.key}`} className="text-right font-bold bg-slate-50">
                              {val ? <div>{formatNumber(val, 0)}</div> : null}
                              {showFutureOverlay && overlayVal > 0 ? (
                                <div className="mt-1">
                                  <EstBadge>{formatNumber(overlayVal, 0)}</EstBadge>
                                </div>
                              ) : null}
                            </TableCell>
                          )
                        }
                        const meta = dayMeta.get(c.day)
                        const isToday = Boolean(meta?.is_today)
                        const val = Number(st.dayTotals?.[c.day] || 0)
                        const overlayVal = showFutureOverlay && isFutureDay(c.day) ? Number(st.overlayDayTotals?.[c.day] || 0) : 0
                        return (
                        <TableCell key={`g:${g.key}:${c.key}`} className={`text-right font-bold bg-slate-50 ${isToday ? 'bg-amber-50' : ''}`}>
                            {val ? <div>{formatNumber(val, 0)}</div> : null}
                            {showFutureOverlay && overlayVal > 0 ? (
                              <div className="mt-1">
                                <EstBadge>{formatNumber(overlayVal, 0)}</EstBadge>
                              </div>
                            ) : null}
                          </TableCell>
                        )
                      })}
                  </TableRow>

                  {(g.items || []).map((r) => {
                    const rowKey = r.key
                    const row = cells?.[rowKey] || {}
                    const total = Number(rowTotals?.[rowKey] || 0)
                    const overlayTotalFuture = Number(overlayFutureRowTotals?.[rowKey] || 0)
                    const badges = rowBadges?.[rowKey] || {}
                    return (
                      <TableRow key={rowKey} className="odd:bg-gray-50/40">
                        <TableCell className="sticky left-0 bg-white z-20 w-[560px] min-w-[560px] max-w-[560px]">
                          <div className="flex flex-wrap items-center gap-2">
                            <span className="text-xs text-gray-700 font-semibold whitespace-normal break-words">
                              {r.variedad || 'Sin variedad'}
                            </span>
                            {Boolean(r.mexico) ? (
                              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800" title="México">
                                <MexicoFlagIcon className="h-4 w-6" />
                                México
                              </span>
                            ) : null}
                            {badges?.mosca ? (
                              <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800" title="Radio Mosca">
                                <Bug className="h-3.5 w-3.5" />
                                Mosca
                              </span>
                            ) : null}
                          </div>
                        </TableCell>
                        <TableCell className="sticky bg-white z-20 text-right font-semibold" style={{ left: 'var(--ff-sticky-col1)' }}>
                          <div>{formatNumber(total, 0)}</div>
                          {showFutureOverlay && overlayTotalFuture > 0 ? (
                            <div className="mt-1">
                              <EstBadge>{formatNumber(overlayTotalFuture, 0)}</EstBadge>
                            </div>
                          ) : null}
                        </TableCell>

                        {cols.map((c) => {
                          if (c.type === 'week') {
                            const val = weekTotalForRow(rowKey, c.week)
                            const overlayVal = weekOverlayTotalForRowFuture(rowKey, c.week)
                            return (
                              <TableCell key={`${rowKey}:${c.key}`} className="text-right font-medium bg-gray-50">
                                {val ? <div>{formatNumber(val, 0)}</div> : null}
                                {showFutureOverlay && overlayVal > 0 ? (
                                  <div className="mt-1">
                                    <EstBadge>{formatNumber(overlayVal, 0)}</EstBadge>
                                  </div>
                                ) : null}
                              </TableCell>
                            )
                          }
                          const meta = dayMeta.get(c.day)
                          const isToday = Boolean(meta?.is_today)
                          const val = Number(row?.[c.day] || 0)
                          const overlayVal = showFutureOverlay && isFutureDay(c.day) ? Number(overlayCells?.[rowKey]?.[c.day] || 0) : 0
                          return (
                            <TableCell key={`${rowKey}:${c.key}`} className={`text-right ${isToday ? 'bg-amber-50' : ''}`}>
                              {val ? <div>{formatNumber(val, 0)}</div> : null}
                              {showFutureOverlay && overlayVal > 0 ? (
                                <div className="mt-1">
                                  <EstBadge>{formatNumber(overlayVal, 0)}</EstBadge>
                                </div>
                              ) : null}
                            </TableCell>
                          )
                        })}
                      </TableRow>
                    )
                  })}
                </React.Fragment>
              )
            })}

            {/* Totales */}
            <TableRow className="bg-slate-100">
              <TableCell className="sticky left-0 bg-slate-100 z-20 font-bold w-[560px] min-w-[560px] max-w-[560px]">TOTAL</TableCell>
              <TableCell className="sticky bg-slate-100 z-20 text-right font-bold" style={{ left: 'var(--ff-sticky-col1)' }}>
                <div>{formatNumber(Number(tab?.grand_total || 0), 0)}</div>
                {showFutureOverlay ? (
                  <div className="mt-1">
                    <EstBadge>
                      {formatNumber(Object.keys(overlayTotalsByDay).reduce((acc, d) => acc + (isFutureDay(d) ? Number(overlayTotalsByDay?.[d] || 0) : 0), 0), 0)}
                    </EstBadge>
                  </div>
                ) : null}
              </TableCell>
              {cols.map((c) => {
                if (c.type === 'week') {
                  const overlayWeek = weekOverlayTotalGlobalFuture(c.week)
                  return (
                    <TableCell key={`total:${c.key}`} className="text-right font-bold">
                      <div>{formatNumber(weekTotalGlobal(c.week), 0)}</div>
                      {showFutureOverlay && overlayWeek > 0 ? (
                        <div className="mt-1">
                          <EstBadge>{formatNumber(overlayWeek, 0)}</EstBadge>
                        </div>
                      ) : null}
                    </TableCell>
                  )
                }
                const meta = dayMeta.get(c.day)
                const isToday = Boolean(meta?.is_today)
                const val = Number(totalsByDay?.[c.day] || 0)
                const overlayVal = showFutureOverlay && isFutureDay(c.day) ? Number(overlayTotalsByDay?.[c.day] || 0) : 0
                return (
                  <TableCell key={`total:${c.key}`} className={`text-right font-bold ${isToday ? 'bg-amber-100' : ''}`}>
                    {val ? <div>{formatNumber(val, 0)}</div> : null}
                    {showFutureOverlay && overlayVal > 0 ? (
                      <div className="mt-1">
                        <EstBadge>{formatNumber(overlayVal, 0)}</EstBadge>
                      </div>
                    ) : null}
                  </TableCell>
                )
              })}
            </TableRow>

            {(!visibleRows || visibleRows.length === 0) ? (
              <TableRow>
                <TableCell colSpan={2 + cols.length} className="py-10 text-center text-sm text-gray-600">
                  Sin datos para el rango/filtros (todas las filas quedan en 0).
                </TableCell>
              </TableRow>
            ) : null}
          </TableBody>
        </Table>
      </div>
    </div>
  )
}

export default function FruitFlowIndex({ seasons = [], especies = [], filters = {}, matrix = {} }) {
  const { props } = usePage()
  const [tab, setTab] = useState('estimation')
  const [collapsedWeeks, setCollapsedWeeks] = useState({})

  const seasonOptions = useMemo(() => {
    return (seasons || []).map((s) => ({
      id: s.id,
      label: s.code ? `${s.code}${s.is_active ? ' (activa)' : ''}` : `#${s.id}`,
    }))
  }, [seasons])

  const apply = (patch) => {
    const next = { ...(filters || {}), ...patch }
    router.get(route('planning.fruit-flow.index'), next, {
      preserveState: true,
      replace: true,
    })
  }

  // Proceso diario sigue siendo por especie (para evitar mezclar capacidades),
  // pero la semana puede crearse para "todas" si no se filtra especie.
  const canCreateProcess = String(filters?.especie || '') !== '' && String(filters?.anchor || '') !== ''
  const canCreateWeek = String(filters?.anchor || '') !== ''
  const days = matrix?.days || []
  const weeks = matrix?.weeks || []
  const rows = matrix?.rows || []
  const tabs = matrix?.tabs || {}
  const activeTab = tabs?.[tab] || {}
  const estimationVersionIdsByOrigin = tabs?.estimation?.version_ids_by_origin || {}
  const agronomoVersionIds = Array.isArray(estimationVersionIdsByOrigin?.agronomo)
    ? estimationVersionIdsByOrigin.agronomo
    : []
  const plannerVersionIds = Array.isArray(estimationVersionIdsByOrigin?.servicio_planificador)
    ? estimationVersionIdsByOrigin.servicio_planificador
    : []

  return (
    <div className="w-full px-4 py-8 space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="text-xl font-bold">Flujo de fruta (previo a planificación)</div>
          <div className="text-sm text-gray-600">
            Existencias + Estimación bisemanal  − Procesado
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link href={route('planning.service-estimations.index')}>
            <Button variant="outline">Estimación servicios</Button>
          </Link>
          <Link href={route('planning.processes.index')}>
            <Button variant="outline">Ir a procesos</Button>
          </Link>
          <Button
            variant="outline"
            disabled={!canCreateWeek}
            title={!canCreateWeek ? 'Selecciona una especie para crear la semana.' : 'Crear planificación semanal'}
            onClick={() => {
              router.get(route('planning.batches.create'), {
                especie: filters.especie,
                week_start: nextMondayFrom(filters.anchor),
              })
            }}
          >
            Crear semana siguiente
          </Button>
          <div className="flex flex-col items-end">
            <Button
              disabled={!canCreateProcess}
              title={!canCreateProcess ? 'Selecciona una especie para crear la planificación.' : 'Crear planificación'}
              onClick={() => {
                router.get(route('planning.processes.create'), {
                  especie: filters.especie,
                  fecha: filters.anchor,
                })
              }}
            >
              Crear planificación
            </Button>
            {!canCreateProcess ? (
              <div className="mt-1 text-[11px] text-gray-500">Selecciona una especie.</div>
            ) : null}
          </div>
        </div>
      </div>

      {props?.flash?.error ? (
        <div className="rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
          {props.flash.error}
        </div>
      ) : null}

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg font-bold">Filtros</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div>
              <Label>Temporada</Label>
              <select
                className="mt-1 w-full rounded border px-2 py-2 text-sm"
                value={String(filters.season_id || '')}
                onChange={(e) => apply({ season_id: e.target.value })}
              >
                {seasonOptions.map((s) => (
                  <option key={s.id} value={String(s.id)}>{s.label}</option>
                ))}
              </select>
            </div>

            <div>
              <Label>Especie</Label>
              <select
                className="mt-1 w-full rounded border px-2 py-2 text-sm"
                value={String(filters.especie || '')}
                onChange={(e) => apply({ especie: e.target.value })}
              >
                <option value="">(Todas)</option>
                {(especies || []).map((e) => (
                  <option key={String(e)} value={String(e)}>{String(e)}</option>
                ))}
              </select>
            </div>

            <div>
              <Label>Variedad (opcional)</Label>
              <Input
                className="mt-1"
                placeholder="Ej: Nectarine XYZ"
                value={String(filters.variedad || '')}
                onChange={(e) => apply({ variedad: e.target.value })}
              />
            </div>

            <div>
              <Label>Semana (ancla)</Label>
              <Input
                className="mt-1"
                type="date"
                value={String(filters.anchor || '')}
                onChange={(e) => apply({ anchor: e.target.value })}
              />
            </div>

            <div>
              <Label>Productor</Label>
              <Input
                className="mt-1"
                placeholder="Buscar productor..."
                value={String(filters.producer_q || '')}
                onChange={(e) => apply({ producer_q: e.target.value })}
              />
            </div>

            <div className="flex items-end">
              <label className="flex items-center gap-2 text-sm">
                <input
                  type="checkbox"
                  checked={Boolean(filters.only_active_producers)}
                  onChange={(e) => apply({ only_active_producers: e.target.checked ? 1 : 0 })}
                />
                Solo productores activos
              </label>
            </div>
          </div>

          <div className="mt-3 text-xs text-gray-600">
            {Array.isArray(tabs?.estimation?.version_ids) && tabs.estimation.version_ids.length > 0 ? (
              <span>
                Estimación bisemanal: versiones{' '}
                {tabs.estimation.version_ids.map((id) => (
                  <Badge key={String(id)} variant="outline" className="ml-1">#{id}</Badge>
                ))}
                {agronomoVersionIds.length > 0 ? (
                  <span className="ml-2 text-emerald-700">
                    · Agrónomos: {agronomoVersionIds.map((id) => `#${id}`).join(', ')}
                  </span>
                ) : null}
                {plannerVersionIds.length > 0 ? (
                  <span className="ml-2 text-indigo-700">
                    · Servicios(planificador): {plannerVersionIds.map((id) => `#${id}`).join(', ')}
                  </span>
                ) : null}
              </span>
            ) : (
              <span className="text-amber-800">
                No hay versión bisemanal ACTIVE para la temporada seleccionada.
              </span>
            )}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg font-bold">Matriz (3 semanas)</CardTitle>
          <div className="text-sm text-gray-600">
            Columnas por día, agrupadas por semana. El día actual se marca en amarillo.
          </div>
        </CardHeader>
        <CardContent>
            <Tabs value={tab} onValueChange={setTab}>
              <TabsList className="grid grid-cols-3 w-full max-w-xl">
                <TabsTrigger value="estimation">Estimación</TabsTrigger>
              <TabsTrigger value="reception">Existencias</TabsTrigger>
              <TabsTrigger value="processed">Procesado</TabsTrigger>
            </TabsList>

            <TabsContent value={tab} className="mt-4">
              <div className="flex items-center justify-between gap-2 mb-3">
                <div className="text-sm text-gray-600">
                  <span className="font-medium">{activeTab?.title || ''}</span>
                  {tab === 'estimation' && Array.isArray(activeTab?.version_ids) && activeTab.version_ids.length > 0 ? (
                    <span className="ml-2">
                      · versiones {activeTab.version_ids.map((id) => `#${id}`).join(', ')}
                      {plannerVersionIds.length > 0 ? (
                        <span className="ml-1 text-indigo-700">
                          (incluye servicios: {plannerVersionIds.map((id) => `#${id}`).join(', ')})
                        </span>
                      ) : null}
                    </span>
                  ) : null}
                  {tab === 'reception' ? (
                    <span className="ml-2 inline-flex items-center gap-2 text-sky-800 font-semibold">
                      · Las estimaciones futuras se muestran en el badge celeste
                      <EstBadge>123</EstBadge>
                    </span>
                  ) : null}
                </div>
                <div className="flex items-center gap-2">
                  <Button variant="outline" size="sm" onClick={() => setCollapsedWeeks({})}>Expandir todo</Button>
                  <Button
                    variant="outline"
                    size="sm"
                    onClick={() => {
                      const next = {}
                      ;(weeks || []).forEach((w) => { next[w.key] = true })
                      setCollapsedWeeks(next)
                    }}
                  >
                    Colapsar todo
                  </Button>
                </div>
              </div>

              <MatrixTable
                days={days}
                weeks={weeks}
                rows={rows}
                tab={activeTab}
                collapsedWeeks={collapsedWeeks}
                setCollapsedWeeks={setCollapsedWeeks}
                showFutureOverlay={tab === 'reception'}
                overlayTab={tab === 'reception' ? tabs?.estimation : null}
                rowBadges={tab === 'reception' ? (activeTab?.row_badges || {}) : {}}
              />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  )
}

FruitFlowIndex.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
