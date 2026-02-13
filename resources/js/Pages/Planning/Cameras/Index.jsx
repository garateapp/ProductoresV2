import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import {
  Activity,
  Cpu,
  Gauge,
  Link2,
  Radar,
  RefreshCw,
  ScanLine,
  Sparkles,
  Timer,
  Waves,
} from 'lucide-react'
import {
  Area,
  AreaChart,
  Bar,
  BarChart,
  CartesianGrid,
  PolarAngleAxis,
  RadialBar,
  RadialBarChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts'

const numberFmt = new Intl.NumberFormat('es-CL')
const BRAND = {
  darkGreen: '#3f8b42',
  vibrantGreen: '#80b61f',
  corpGreen: '#038c34',
  orange: '#f78e2c',
  corpOrange: '#fe790f',
  grid: '#cbd5e1',
  axis: '#475569',
  radialBg: '#e2e8f0',
}

function parseDateSafe(value) {
  if (!value) return null
  const raw = String(value)
  const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw
  const date = new Date(normalized)
  if (Number.isNaN(date.getTime())) return null
  return date
}

function fmtTime(dtStr) {
  const d = parseDateSafe(dtStr)
  if (!d) return '-'
  return d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
}

function fmtDate(dtStr) {
  const d = parseDateSafe(dtStr)
  if (!d) return ''
  return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit' })
}

function statusBadgeClass(status) {
  const value = String(status || '').toUpperCase()
  const map = {
    BORRADOR: 'bg-slate-100 text-slate-700 border-slate-300',
    CONFLICTO: 'bg-red-50 text-red-700 border-red-300',
    CONFIRMADO: 'bg-greenex-vibrant-green/15 text-greenex-dark-green border-greenex-vibrant-green/50',
    EN_PROCESO: 'bg-greenex-orange/15 text-greenex-orange border-greenex-orange/50',
    CERRADO: 'bg-slate-200 text-slate-700 border-slate-300',
  }
  return map[value] || 'bg-slate-100 text-slate-700 border-slate-300'
}

function TelemetryTooltip({ active, payload, label }) {
  if (!active || !payload || payload.length === 0) return null
  return (
    <div className="rounded-lg border border-greenex-vibrant-green/35 bg-white/95 px-3 py-2 text-xs text-slate-700 shadow-xl">
      <div className="font-semibold text-greenex-orange">{label}</div>
      {payload.map((p) => (
        <div key={`${p.name}-${p.dataKey}`} className="mt-0.5 flex items-center justify-between gap-3">
          <span className="text-slate-500">{p.name}:</span>
          <span className="font-semibold text-slate-800">{numberFmt.format(Number(p.value || 0))}</span>
        </div>
      ))}
    </div>
  )
}

function ProcessNode({ title, block, highlight }) {
  const statusClass = statusBadgeClass(block?.estado)

  return (
    <div
      className={`relative overflow-hidden rounded-xl border p-3 ${
        highlight
          ? 'border-greenex-vibrant-green/50 bg-gradient-to-br from-greenex-vibrant-green/12 via-white to-greenex-orange/10 shadow-[0_0_18px_rgba(128,182,31,0.18)]'
          : 'border-slate-200 bg-white'
      }`}
    >
      <div className="absolute -right-8 -top-8 h-24 w-24 rounded-full bg-greenex-orange/15 blur-2xl" />
      <div className="relative z-10">
        <div className="flex items-center justify-between gap-2">
          <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</div>
          {block?.estado ? (
            <Badge variant="outline" className={statusClass}>
              {String(block.estado)}
            </Badge>
          ) : null}
        </div>

        {block ? (
          <div className="mt-2 space-y-1.5">
            <div className="text-sm font-semibold text-slate-900">
              {block.especie || '-'}
              {block.exportadora ? <span className="font-normal text-slate-500"> · {block.exportadora}</span> : null}
            </div>
            <div className="text-xs text-slate-600">
              <span className="font-semibold text-slate-800">Variedad:</span> {block.variedad || '-'}
            </div>
            <div className="text-xs text-slate-600">
              <span className="font-semibold text-slate-800">Destino:</span> {block.destino || '-'}
            </div>
            <div className="text-xs text-slate-600">
              <span className="font-semibold text-slate-800">N° Proceso:</span> {block.process_number || block.process_id || '-'}
            </div>
            <div className="text-xs text-slate-600">
              <span className="font-semibold text-slate-800">N° Lote:</span> {block.lote || '-'}
            </div>
            <div className="text-xs text-slate-500">
              {fmtTime(block.inicio)} - {fmtTime(block.fin)}
              <span className="text-slate-400"> ({fmtDate(block.inicio)})</span>
            </div>
            <div className="text-xs text-slate-600">
              <span className="font-semibold text-greenex-vibrant-green">{numberFmt.format(Number(block.bins || 0))}</span> bins ·{' '}
              <span className="font-semibold text-greenex-orange">{numberFmt.format(Math.round(Number(block.kilos || 0)))}</span> kg
            </div>
            {block.pedidos ? (
              <div className="text-xs text-slate-600">
                <span className="font-semibold text-slate-800">Pedidos:</span> {String(block.pedidos)}
              </div>
            ) : null}
            <div className="pt-1">
              <Button
                size="sm"
                variant="outline"
                className="border-greenex-vibrant-green/50 bg-white text-greenex-dark-green hover:bg-greenex-vibrant-green/10"
                onClick={() => router.visit(route('planning.processes.show', block.process_id))}
              >
                Abrir proceso
              </Button>
            </div>
          </div>
        ) : (
          <div className="mt-2 text-xs text-slate-500">Sin proceso</div>
        )}
      </div>
    </div>
  )
}

function HeroMetric({ icon: Icon, label, value, hint, tone = 'cyan' }) {
  const toneMap = {
    cyan: 'from-greenex-vibrant-green/18 via-white to-greenex-dark-green/8 border-greenex-vibrant-green/35 text-slate-800',
    emerald: 'from-greenex-dark-green/14 via-white to-greenex-vibrant-green/10 border-greenex-dark-green/35 text-slate-800',
    amber: 'from-greenex-orange/20 via-white to-greenex-orange/8 border-greenex-orange/35 text-slate-800',
    sky: 'from-greenex-vibrant-green/12 via-white to-greenex-orange/10 border-greenex-vibrant-green/30 text-slate-800',
    slate: 'from-slate-100 to-white border-slate-200 text-slate-800',
  }

  return (
    <div className={`rounded-xl border bg-gradient-to-br p-3 ${toneMap[tone] || toneMap.slate}`}>
      <div className="flex items-center justify-between gap-2">
        <div className="text-xs font-semibold uppercase tracking-wide text-slate-600">{label}</div>
        <Icon className="h-4 w-4 opacity-90" />
      </div>
      <div className="mt-1 text-2xl font-black leading-tight">{value}</div>
      <div className="mt-1 text-xs text-slate-600">{hint}</div>
    </div>
  )
}

export default function Index(props) {
  const { filters, shifts, cards } = props

  const [live, setLive] = useState({})
  const [bindNumbers, setBindNumbers] = useState(() => {
    const out = {}
    for (const c of cards || []) {
      const id = c?.line?.id
      if (!id) continue
      out[id] = String(c?.monitor?.sqlsrv_production_number || '')
    }
    return out
  })
  const timerRef = useRef(null)

  const lineIds = useMemo(() => (cards || []).map((c) => c?.line?.id).filter(Boolean), [cards])

  const liveFetch = async () => {
    try {
      const query = new URLSearchParams()
      query.set('date', String(filters.date || ''))
      query.set('shift_id', String(filters.shift_id || ''))
      for (const id of lineIds) {
        query.append('line_ids[]', String(id))
      }

      const res = await fetch(`${route('planning.cameras.live')}?${query.toString()}`, {
        headers: { Accept: 'application/json' },
      })
      const json = await res.json()
      if (json?.ok) {
        setLive(json.data || {})
      }
    } catch {
      // No interrumpimos operación con alertas por errores transitorios.
    }
  }

  useEffect(() => {
    liveFetch()
    if (timerRef.current) clearInterval(timerRef.current)
    timerRef.current = setInterval(liveFetch, 5000)
    return () => {
      if (timerRef.current) clearInterval(timerRef.current)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [filters.date, filters.shift_id, lineIds.join(',')])

  useEffect(() => {
    const out = {}
    for (const c of cards || []) {
      const id = c?.line?.id
      if (!id) continue
      out[id] = String(c?.monitor?.sqlsrv_production_number || '')
    }
    setBindNumbers(out)
  }, [cards])

  const { setData, post, processing } = useForm({
    packing_line_id: '',
    date: filters.date,
    shift_id: filters.shift_id,
    process_number: '',
  })

  const bind = (lineId, processNumber) => {
    const val = String(processNumber || '').trim()
    if (!val) return
    setData({
      packing_line_id: String(lineId),
      date: filters.date,
      shift_id: String(filters.shift_id),
      process_number: val,
    })
    post(route('planning.cameras.bind-sqlsrv'), { preserveScroll: true })
  }

  const onFilterChange = (next) => {
    router.get(route('planning.cameras.index'), next, { preserveScroll: true, preserveState: true })
  }

  const cameraCards = useMemo(() => {
    return (cards || []).map((c) => {
      const line = c?.line || {}
      const blocks = c?.blocks || {}
      const monitor = c?.monitor || {}
      const liveRow = live?.[line.id] || null

      const deducted = liveRow ? Number(liveRow.deducted_bins || 0) : Number(monitor.deducted_bins || 0)
      const currentBins = Number(blocks?.current?.bins || 0)
      const pct = currentBins > 0 ? Math.min(100, Math.round((deducted / currentBins) * 100)) : 0
      const lastScan = liveRow ? liveRow.last_scanned_at : monitor.last_scanned_at

      const recentRaw = Array.isArray(liveRow?.recent) ? liveRow.recent : []
      const recent = recentRaw.slice(0, 8)
      const trendData = recent.length
        ? recent
            .slice()
            .reverse()
            .map((r, idx) => ({
              label: fmtTime(r?.scanned_at),
              scans: idx + 1,
              user: r?.user || '',
              folio: r?.folio || '',
            }))
        : Array.from({ length: 8 }, (_, idx) => ({ label: `T${idx + 1}`, scans: 0 }))

      return {
        line,
        blocks,
        monitor,
        liveRow,
        deducted,
        currentBins,
        pct,
        lastScan,
        trendData,
      }
    })
  }, [cards, live])

  const overview = useMemo(() => {
    const totalCameras = cameraCards.length
    const activeProcesses = cameraCards.filter((c) => c?.blocks?.current).length
    const totalPlanBins = cameraCards.reduce((acc, c) => acc + Number(c.currentBins || 0), 0)
    const totalDeducted = cameraCards.reduce((acc, c) => acc + Number(c.deducted || 0), 0)
    const avgPct = totalPlanBins > 0 ? Math.round((totalDeducted / totalPlanBins) * 100) : 0

    return {
      totalCameras,
      activeProcesses,
      totalPlanBins,
      totalDeducted,
      avgPct,
    }
  }, [cameraCards])

  const telemetryBars = useMemo(() => {
    return cameraCards.map((c) => ({
      line: String(c?.line?.nombre || '-').replace(/^L[íi]nea\s*/i, 'L'),
      plan: Number(c.currentBins || 0),
      descontado: Number(c.deducted || 0),
    }))
  }, [cameraCards])

  return (
    <AuthenticatedLayout>
      <Head title="Camaras" />

      <div className="mx-auto w-full max-w-none space-y-5 bg-gradient-to-b from-greenex-light-green/20 via-white to-white px-4 py-6">
        <div className="relative overflow-hidden rounded-2xl border border-greenex-vibrant-green/30 bg-gradient-to-br from-white via-greenex-light-green/25 to-white p-5 text-slate-800 shadow-[0_10px_30px_rgba(63,139,66,0.12)]">
          <div className="pointer-events-none absolute -left-20 top-0 h-56 w-56 rounded-full bg-greenex-vibrant-green/18 blur-3xl" />
          <div className="pointer-events-none absolute right-0 top-0 h-44 w-44 rounded-full bg-greenex-orange/18 blur-3xl" />
          <div className="relative z-10 flex flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
              <div>
                <div className="inline-flex items-center gap-2 rounded-full border border-greenex-vibrant-green/45 bg-white/70 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-greenex-dark-green">
                  <Radar className="h-3.5 w-3.5" />
                  Vision de linea en tiempo real
                </div>
                <h1 className="mt-3 text-2xl font-black tracking-tight md:text-3xl">Camaras Command Center</h1>
                <p className="mt-1 text-sm text-slate-600">
                  Monitorea proceso anterior, actual y siguiente con telemetria de descuentos en vivo.
                </p>
              </div>

              <div className="grid w-full grid-cols-1 gap-3 sm:grid-cols-3 lg:w-auto">
                <div className="sm:w-[170px]">
                  <Label className="text-xs font-semibold uppercase tracking-wider text-slate-600">Fecha</Label>
                  <Input
                    type="date"
                    className="mt-1 border-greenex-vibrant-green/35 bg-white text-slate-800"
                    value={filters.date}
                    onChange={(e) => onFilterChange({ ...filters, date: e.target.value })}
                  />
                </div>
                <div className="sm:w-[240px]">
                  <Label className="text-xs font-semibold uppercase tracking-wider text-slate-600">Turno</Label>
                  <Select value={String(filters.shift_id || '')} onValueChange={(v) => onFilterChange({ ...filters, shift_id: Number(v) })}>
                    <SelectTrigger className="mt-1 border-greenex-vibrant-green/35 bg-white text-slate-800">
                      <SelectValue placeholder="Selecciona turno" />
                    </SelectTrigger>
                    <SelectContent>
                      {(shifts || []).map((s) => (
                        <SelectItem key={s.id} value={String(s.id)}>
                          {s.codigo} · {s.nombre}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="flex items-end">
                  <Button
                    type="button"
                    className="w-full border border-greenex-orange/55 bg-gradient-to-r from-greenex-dark-green/85 via-greenex-vibrant-green/70 to-greenex-orange/65 text-white hover:from-greenex-dark-green hover:to-greenex-orange/80"
                    onClick={liveFetch}
                  >
                    <RefreshCw className="mr-2 h-4 w-4" />
                    Actualizar
                  </Button>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
              <HeroMetric
                icon={Cpu}
                label="Lineas activas"
                value={numberFmt.format(overview.totalCameras)}
                hint="Camaras monitoreadas"
                tone="cyan"
              />
              <HeroMetric
                icon={Activity}
                label="Procesos actuales"
                value={numberFmt.format(overview.activeProcesses)}
                hint="Bloques en ejecucion"
                tone="emerald"
              />
              <HeroMetric
                icon={Gauge}
                label="Plan bins"
                value={numberFmt.format(overview.totalPlanBins)}
                hint="Capacidad comprometida"
                tone="sky"
              />
              <HeroMetric
                icon={ScanLine}
                label="Descontados"
                value={numberFmt.format(overview.totalDeducted)}
                hint="Lecturas de scan"
                tone="amber"
              />
              <HeroMetric
                icon={Waves}
                label="Rendimiento"
                value={`${overview.avgPct}%`}
                hint="Avance promedio"
                tone="slate"
              />
            </div>
          </div>
        </div>

        <Card className="border-greenex-vibrant-green/30 bg-white text-slate-800 shadow-[0_8px_24px_rgba(63,139,66,0.1)]">
          <CardHeader className="pb-2">
            <CardTitle className="flex items-center gap-2 text-base font-bold">
              <Sparkles className="h-4 w-4 text-greenex-orange" />
              Telemetria comparativa por linea
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="h-56 w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={telemetryBars} margin={{ top: 8, right: 12, bottom: 0, left: 0 }}>
                  <CartesianGrid stroke={BRAND.grid} strokeDasharray="4 4" vertical={false} />
                  <XAxis dataKey="line" stroke={BRAND.axis} fontSize={11} tickMargin={8} />
                  <YAxis stroke={BRAND.axis} fontSize={11} allowDecimals={false} width={36} />
                  <Tooltip content={<TelemetryTooltip />} />
                  <Bar dataKey="plan" name="Plan bins" fill={BRAND.vibrantGreen} radius={[6, 6, 0, 0]} />
                  <Bar dataKey="descontado" name="Bins descontados" fill={BRAND.orange} radius={[6, 6, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
          {cameraCards.map((c) => {
            const line = c.line || {}
            const blocks = c.blocks || {}
            const monitor = c.monitor || {}
            const liveRow = c.liveRow
            const radialData = [{ name: 'avance', value: Math.max(0, Math.min(100, Number(c.pct || 0))), fill: BRAND.vibrantGreen }]
            const gradientId = `scan-gradient-${line.id || Math.random().toString(36).slice(2)}`

            return (
              <Card
                key={line.id}
                className="relative overflow-hidden border-greenex-vibrant-green/25 bg-white text-slate-800 shadow-[0_8px_20px_rgba(63,139,66,0.08)]"
              >
                <div className="pointer-events-none absolute -right-10 top-1 h-24 w-24 rounded-full bg-greenex-vibrant-green/20 blur-2xl" />
                <div className="pointer-events-none absolute -left-10 bottom-1 h-24 w-24 rounded-full bg-greenex-orange/15 blur-2xl" />

                <CardHeader className="relative z-10 pb-2">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <CardTitle className="text-lg font-black tracking-tight">{line.nombre}</CardTitle>
                      <div className="mt-2 flex items-center gap-2">
                        <Badge variant="outline" className="border-greenex-vibrant-green/50 bg-greenex-vibrant-green/10 text-greenex-dark-green">
                          {String(line.tipo || '').toUpperCase()}
                        </Badge>
                        <Badge variant="outline" className="border-greenex-orange/40 bg-greenex-orange/10 text-greenex-orange">
                          ID {line.id}
                        </Badge>
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-[11px] uppercase tracking-wider text-slate-500">Ultimo scan</div>
                      <div className="text-sm font-semibold text-greenex-orange">{c.lastScan ? fmtTime(c.lastScan) : '-'}</div>
                    </div>
                  </div>
                </CardHeader>

                <CardContent className="relative z-10 space-y-3">
                  <div className="grid grid-cols-1 gap-2">
                    <ProcessNode title="Anterior" block={blocks.prev} />
                    <ProcessNode title="Actual" block={blocks.current} highlight />
                    <ProcessNode title="Siguiente" block={blocks.next} />
                  </div>

                  <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
                    <div className="rounded-xl border border-greenex-vibrant-green/35 bg-gradient-to-br from-white to-greenex-light-green/20 p-3">
                      <div className="flex items-center justify-between gap-2">
                        <div className="text-xs font-semibold uppercase tracking-wide text-greenex-vibrant-green">Avance de descuento</div>
                        <Timer className="h-4 w-4 text-greenex-vibrant-green" />
                      </div>
                      <div className="relative mt-2 h-40">
                        <ResponsiveContainer width="100%" height="100%">
                          <RadialBarChart
                            innerRadius="62%"
                            outerRadius="100%"
                            barSize={16}
                            data={radialData}
                            startAngle={90}
                            endAngle={-270}
                          >
                            <PolarAngleAxis type="number" domain={[0, 100]} tick={false} />
                            <RadialBar cornerRadius={12} dataKey="value" background={{ fill: BRAND.radialBg }} />
                          </RadialBarChart>
                        </ResponsiveContainer>
                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                          <div className="text-3xl font-black text-greenex-vibrant-green">{c.pct}%</div>
                          <div className="text-[11px] uppercase tracking-wider text-slate-500">progreso</div>
                        </div>
                      </div>
                      <div className="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div className="rounded-lg border border-greenex-vibrant-green/25 bg-white p-2">
                          <div className="text-slate-500">Plan</div>
                          <div className="mt-0.5 font-semibold text-slate-800">{numberFmt.format(c.currentBins || 0)} bins</div>
                        </div>
                        <div className="rounded-lg border border-greenex-orange/25 bg-white p-2">
                          <div className="text-slate-500">Descontado</div>
                          <div className="mt-0.5 font-semibold text-greenex-orange">{numberFmt.format(c.deducted || 0)} bins</div>
                        </div>
                      </div>
                    </div>

                    <div className="rounded-xl border border-greenex-orange/40 bg-gradient-to-br from-white to-orange-50 p-3">
                      <div className="flex items-center justify-between gap-2">
                        <div className="text-xs font-semibold uppercase tracking-wide text-greenex-orange">Cadencia de scans</div>
                        <Radar className="h-4 w-4 text-greenex-orange" />
                      </div>
                      <div className="mt-2 h-40">
                        <ResponsiveContainer width="100%" height="100%">
                          <AreaChart data={c.trendData} margin={{ top: 8, right: 8, bottom: 0, left: 0 }}>
                            <defs>
                              <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                                <stop offset="5%" stopColor={BRAND.orange} stopOpacity={0.55} />
                                <stop offset="95%" stopColor={BRAND.orange} stopOpacity={0.02} />
                              </linearGradient>
                            </defs>
                            <CartesianGrid stroke={BRAND.grid} strokeDasharray="4 4" vertical={false} />
                            <XAxis dataKey="label" stroke={BRAND.axis} fontSize={10} tickLine={false} axisLine={false} />
                            <YAxis stroke={BRAND.axis} fontSize={10} allowDecimals={false} width={28} tickLine={false} axisLine={false} />
                            <Tooltip content={<TelemetryTooltip />} />
                            <Area
                              type="monotone"
                              dataKey="scans"
                              name="Scans"
                              stroke={BRAND.orange}
                              strokeWidth={2}
                              fill={`url(#${gradientId})`}
                              isAnimationActive={false}
                            />
                          </AreaChart>
                        </ResponsiveContainer>
                      </div>
                      <div className="mt-2 text-xs text-slate-500">
                        Señal en vivo cada 5s · histórico corto de lecturas recientes.
                      </div>
                    </div>
                  </div>

                  <div className="rounded-xl border border-greenex-vibrant-green/25 bg-white p-3">
                    <div className="flex items-end justify-between gap-3">
                      <div className="flex-1">
                        <Label className="text-xs font-semibold uppercase tracking-wide text-slate-600">
                          Vincular N° Proceso SQL
                        </Label>
                        <Input
                          className="mt-1 border-greenex-vibrant-green/35 bg-white text-slate-800"
                          placeholder="Ej: 12345"
                          value={bindNumbers?.[line.id] ?? ''}
                          onChange={(e) => setBindNumbers((prev) => ({ ...prev, [line.id]: e.target.value }))}
                          onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                              bind(line.id, e.currentTarget.value)
                            }
                          }}
                        />
                      </div>
                      <Button
                        disabled={processing}
                        className="border border-greenex-orange/55 bg-greenex-orange/10 text-greenex-orange hover:bg-greenex-orange/20"
                        onClick={() => bind(line.id, bindNumbers?.[line.id] ?? '')}
                      >
                        <Link2 className="mr-2 h-4 w-4" />
                        Vincular
                      </Button>
                    </div>
                    <div className="mt-2 text-xs text-slate-600">
                      {liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number ? (
                        <span className="font-semibold text-greenex-vibrant-green">Vinculado:</span>
                      ) : (
                        <span className="font-semibold text-greenex-orange">Tip:</span>
                      )}{' '}
                      {liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number
                        ? `Proceso ${liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number}`
                        : 'ingresa el numero de proceso real para activar telemetria de descuentos.'}
                    </div>
                  </div>

                  {Array.isArray(liveRow?.recent) && liveRow.recent.length > 0 ? (
                    <div className="rounded-xl border border-greenex-vibrant-green/20 bg-white p-3">
                      <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        <ScanLine className="h-3.5 w-3.5 text-greenex-vibrant-green" />
                        Ultimos scans
                      </div>
                      <div className="mt-2 space-y-1.5">
                        {liveRow.recent.slice(0, 6).map((r, idx) => (
                          <div key={`${line.id}-recent-${idx}`} className="flex items-center justify-between gap-2 text-xs">
                            <div className="font-mono text-greenex-vibrant-green">{r.folio}</div>
                            <div className="text-slate-600">
                              {r.scanned_at ? fmtTime(r.scanned_at) : '-'} {r.user ? <span className="text-slate-400">·</span> : null}{' '}
                              {r.user || ''}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  ) : null}
                </CardContent>
              </Card>
            )
          })}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
