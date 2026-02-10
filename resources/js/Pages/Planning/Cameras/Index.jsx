import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'

function fmtTime(dtStr) {
  if (!dtStr) return '-'
  try {
    const d = new Date(dtStr)
    return d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit' })
  } catch {
    return '-'
  }
}

function fmtDate(dtStr) {
  if (!dtStr) return ''
  try {
    const d = new Date(dtStr)
    return d.toLocaleDateString('es-CL', { day: '2-digit', month: '2-digit' })
  } catch {
    return ''
  }
}

function ProcessMini({ title, block, highlight }) {
  return (
    <div className={`rounded-md border p-3 ${highlight ? 'border-emerald-300 bg-emerald-50' : 'border-slate-200 bg-white'}`}>
      <div className="flex items-center justify-between gap-2">
        <div className="text-xs font-semibold text-slate-600">{title}</div>
        {block?.estado ? (
          <Badge variant="secondary" className="uppercase">
            {String(block.estado)}
          </Badge>
        ) : null}
      </div>
      {block ? (
        <div className="mt-1 space-y-1">
          <div className="text-sm font-semibold text-slate-900">
            {block.especie || '-'}
            {block.exportadora ? <span className="text-slate-500 font-normal"> · {block.exportadora}</span> : null}
          </div>
          <div className="text-xs text-slate-600">
            {fmtTime(block.inicio)} – {fmtTime(block.fin)}
            <span className="text-slate-400"> ({fmtDate(block.inicio)})</span>
          </div>
          <div className="text-xs text-slate-700">
            <span className="font-semibold">{block.bins}</span> bins · <span className="font-semibold">{Math.round(Number(block.kilos || 0))}</span> kg
          </div>
          {block.pedidos ? (
            <div className="text-xs text-slate-700">
              <span className="font-semibold">Pedidos:</span> {String(block.pedidos)}
            </div>
          ) : null}
          <div className="pt-1">
            <Button size="sm" variant="outline" onClick={() => router.visit(route('planning.processes.show', block.process_id))}>
              Abrir proceso
            </Button>
          </div>
        </div>
      ) : (
        <div className="mt-2 text-xs text-slate-500">Sin proceso</div>
      )}
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

  const lineIds = useMemo(() => cards.map((c) => c?.line?.id).filter(Boolean), [cards])

  const liveFetch = async () => {
    try {
      const res = await fetch(route('planning.cameras.live') + `?date=${encodeURIComponent(filters.date)}&shift_id=${encodeURIComponent(filters.shift_id)}&` + new URLSearchParams(lineIds.map((id) => ['line_ids[]', String(id)])).toString(), {
        headers: { Accept: 'application/json' },
      })
      const json = await res.json()
      if (json?.ok) {
        setLive(json.data || {})
      }
    } catch {
      // noop: modo planta, no molestamos con alertas por cada fallo de red
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

  const { data, setData, post, processing } = useForm({
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

  return (
    <AuthenticatedLayout>
      <Head title="Cámaras" />

      <div className="mx-auto w-full max-w-none px-4 py-6">
        <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
          <div>
            <div className="text-2xl font-bold text-slate-900">Cámaras</div>
            <div className="text-sm text-slate-600">Anterior · Actual · Siguiente + bins descontados (scan)</div>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div className="w-full sm:w-[170px]">
              <Label>Fecha</Label>
              <Input
                type="date"
                value={filters.date}
                onChange={(e) => onFilterChange({ ...filters, date: e.target.value })}
              />
            </div>
            <div className="w-full sm:w-[220px]">
              <Label>Turno</Label>
              <Select value={String(filters.shift_id || '')} onValueChange={(v) => onFilterChange({ ...filters, shift_id: Number(v) })}>
                <SelectTrigger>
                  <SelectValue placeholder="Selecciona turno" />
                </SelectTrigger>
                <SelectContent>
                  {shifts.map((s) => (
                    <SelectItem key={s.id} value={String(s.id)}>
                      {s.codigo} · {s.nombre}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <Button variant="outline" onClick={liveFetch}>
              Actualizar
            </Button>
          </div>
        </div>

        <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
          {cards.map((c) => {
            const line = c.line
            const blocks = c.blocks || {}
            const monitor = c.monitor || {}
            const liveRow = live?.[line.id] || null
            const deducted = liveRow ? Number(liveRow.deducted_bins || 0) : Number(monitor.deducted_bins || 0)
            const lastScan = liveRow ? liveRow.last_scanned_at : monitor.last_scanned_at
            const currentBins = Number(blocks?.current?.bins || 0)
            const pct = currentBins > 0 ? Math.min(100, Math.round((deducted / currentBins) * 100)) : 0

            return (
              <Card key={line.id} className="border-slate-200">
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <CardTitle className="text-lg">{line.nombre}</CardTitle>
                      <div className="mt-1 text-xs text-slate-600">
                        <Badge variant="secondary" className="uppercase">
                          {line.tipo}
                        </Badge>
                      </div>
                    </div>
                    <div className="text-right">
                      <div className="text-xs text-slate-500">Último scan</div>
                      <div className="text-sm font-semibold text-slate-900">{lastScan ? fmtTime(lastScan) : '-'}</div>
                    </div>
                  </div>
                </CardHeader>
                <CardContent className="space-y-3">
                  <div className="grid grid-cols-1 gap-2">
                    <ProcessMini title="Anterior" block={blocks.prev} />
                    <ProcessMini title="Actual" block={blocks.current} highlight />
                    <ProcessMini title="Siguiente" block={blocks.next} />
                  </div>

                  <div className="rounded-md border border-slate-200 bg-slate-50 p-3">
                    <div className="flex items-center justify-between gap-2">
                      <div>
                        <div className="text-xs font-semibold text-slate-600">Bins descontados</div>
                        <div className="text-2xl font-extrabold text-slate-900">{deducted}</div>
                      </div>
                      <div className="text-right">
                        <div className="text-xs text-slate-600">Plan</div>
                        <div className="text-sm font-semibold text-slate-900">{currentBins || '-'}</div>
                        {currentBins > 0 ? (
                          <div className="text-xs text-slate-600">{pct}%</div>
                        ) : null}
                      </div>
                    </div>
                    {currentBins > 0 ? (
                      <div className="mt-2 h-2 w-full overflow-hidden rounded bg-slate-200">
                        <div className="h-2 bg-emerald-500" style={{ width: `${pct}%` }} />
                      </div>
                    ) : null}
                  </div>

                  <div className="rounded-md border border-slate-200 p-3">
                    <div className="flex items-end justify-between gap-3">
                      <div className="flex-1">
                        <Label className="text-xs">Vincular N° Proceso (SQL)</Label>
                        <Input
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
                      <Button disabled={processing} onClick={() => {
                        bind(line.id, bindNumbers?.[line.id] ?? '')
                      }}>
                        Vincular
                      </Button>
                    </div>
                    <div className="mt-2 text-xs text-slate-600">
                      {liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number ? (
                        <span className="font-semibold">Vinculado:</span>
                      ) : (
                        <span className="font-semibold">Tip:</span>
                      )}{' '}
                      {liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number
                        ? `Proceso ${liveRow?.sqlsrv_production_number || monitor.sqlsrv_production_number}`
                        : 'ingresa el número de proceso real para ver descuentos en vivo.'}
                    </div>
                  </div>

                  {Array.isArray(liveRow?.recent) && liveRow.recent.length > 0 ? (
                    <div className="rounded-md border border-slate-200 p-3">
                      <div className="text-xs font-semibold text-slate-600">Últimos scans</div>
                      <div className="mt-2 space-y-1">
                        {liveRow.recent.slice(0, 6).map((r, idx) => (
                          <div key={idx} className="flex items-center justify-between text-xs">
                            <div className="font-mono text-slate-900">{r.folio}</div>
                            <div className="text-slate-600">
                              {r.scanned_at ? fmtTime(r.scanned_at) : '-'} {r.user ? <span className="text-slate-400">·</span> : null} {r.user || ''}
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
