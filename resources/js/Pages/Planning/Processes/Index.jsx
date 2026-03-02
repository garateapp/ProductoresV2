import React, { useEffect, useMemo, useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Calendar, ChevronDown, ChevronUp, GanttChartSquare, Layers, Printer, RotateCcw, Save } from 'lucide-react'

function StatusBadge({ status }) {
  const value = String(status || '')
  const map = {
    BORRADOR: 'bg-slate-100 text-slate-800 border-slate-200',
    CONFLICTO: 'bg-red-50 text-red-800 border-red-200',
    CONFIRMADO: 'bg-green-50 text-green-800 border-green-200',
    EN_PROCESO: 'bg-blue-50 text-blue-800 border-blue-200',
    CERRADO: 'bg-slate-200 text-slate-900 border-slate-300',
  }
  return (
    <span
      className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold ${map[value] || 'bg-slate-50 text-slate-700 border-slate-200'}`}
    >
      {value || '-'}
    </span>
  )
}

function formatDate(value) {
  if (!value) return '-'
  const raw = String(value)
  const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(normalized)
  const d = new Date(isDateOnly ? `${normalized}T12:00:00Z` : normalized)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago' }).format(d)
}

function timeToMinutes(value) {
  if (!value) return null
  const raw = String(value)
  // acepta "YYYY-MM-DD HH:mm:ss" o "YYYY-MM-DDTHH:mm:ss"
  const m = raw.match(/(\d{2}):(\d{2})(?::\d{2})?/)
  if (!m) return null
  const hh = Number(m[1])
  const mm = Number(m[2])
  if (!Number.isFinite(hh) || !Number.isFinite(mm)) return null
  return hh * 60 + mm
}

function fmtHM(value) {
  if (!value) return '-'
  const m = String(value).match(/(\d{2}:\d{2})/)
  return m ? m[1] : '-'
}

function minutesToHM(totalMinutes) {
  const m = Math.max(0, Math.round(Number(totalMinutes) || 0))
  const hh = Math.floor(m / 60)
  const mm = m % 60
  return `${String(hh).padStart(2, '0')}:${String(mm).padStart(2, '0')}`
}

function getProcessLineLots(process, lineId) {
  const map = process?.line_lots || {}
  const a = map?.[String(lineId)]
  const b = map?.[Number(lineId)]
  if (Array.isArray(a)) return a
  if (Array.isArray(b)) return b
  return []
}

function lotDisplayLabel(lot) {
  return lot?.n_g_recepcion ? String(lot.n_g_recepcion) : '-'
}

function getManualLineOrder(process, lineId) {
  const map = process?.manual_line_order || {}
  const raw = map?.[String(lineId)] ?? map?.[Number(lineId)]
  const num = Number(raw)
  return Number.isFinite(num) && num > 0 ? num : null
}

function normalizeIdList(values) {
  if (!Array.isArray(values)) return []
  return values
    .map((id) => Number(id))
    .filter((id) => Number.isFinite(id) && id > 0)
}

function areIdListsEqual(a, b) {
  const left = normalizeIdList(a)
  const right = normalizeIdList(b)
  if (left.length !== right.length) return false
  for (let i = 0; i < left.length; i += 1) {
    if (left[i] !== right[i]) return false
  }
  return true
}

function applyProcessOrder(processes, orderIds) {
  const desired = normalizeIdList(orderIds)
  if (!desired.length) return processes
  const byId = new Map((processes || []).map((p) => [Number(p?.id || 0), p]))
  const used = new Set()
  const ordered = []

  for (const id of desired) {
    const p = byId.get(id)
    if (p && !used.has(id)) {
      ordered.push(p)
      used.add(id)
    }
  }

  for (const p of (processes || [])) {
    const id = Number(p?.id || 0)
    if (id > 0 && !used.has(id)) {
      ordered.push(p)
      used.add(id)
    }
  }

  return ordered
}

function moveProcessInOrder(orderIds, processId, direction) {
  const ids = normalizeIdList(orderIds)
  const pid = Number(processId)
  const idx = ids.findIndex((id) => id === pid)
  if (idx < 0) return ids
  const target = direction === 'up' ? idx - 1 : idx + 1
  if (target < 0 || target >= ids.length) return ids
  const next = [...ids]
  const tmp = next[idx]
  next[idx] = next[target]
  next[target] = tmp
  return next
}

function LineGroupGantt({ group, onDelete, onMoveOrder, onSaveOrder, onResetOrder, savingOrder }) {
  const { dateKey, shiftLabel, shiftId, lineId, lineName, processes: list, printableProcessId } = group
  const shift = list?.[0]?.shift || null
  const shiftStartStr = shift?.hora_inicio ? String(shift.hora_inicio) : '08:00:00'
  const shiftStartMin = timeToMinutes(shiftStartStr) ?? 8 * 60
  const shiftHours = Number(shift?.horas ?? 0) || 0
  const totalMinutes = Math.max(60, Math.round(shiftHours * 60))
  const shiftEndMin = shiftStartMin + totalMinutes
  const shiftEndStr = `${String(Math.floor(shiftEndMin / 60)).padStart(2, '0')}:${String(shiftEndMin % 60).padStart(2, '0')}`

  const rows = (list || [])
    .map((p) => {
    const lt = p?.line_times?.[String(lineId)] || p?.line_times?.[Number(lineId)] || null
      const startMin = timeToMinutes(lt?.inicio_estimado || null)
      const endMin = timeToMinutes(lt?.fin_estimado || null)
      const duration = Number(lt?.duration_minutes ?? null)
      const durationMinutes =
        Number.isFinite(duration) && duration > 0
          ? duration
          : (startMin !== null && endMin !== null && endMin > startMin ? (endMin - startMin) : 30)
      return {
      p,
      lt,
      lineLots: getProcessLineLots(p, lineId),
      start: lt?.inicio_estimado || null,
      end: lt?.fin_estimado || null,
        startMin,
        endMin,
        durationMinutes,
    }
  })

  return (
    <div className="rounded border overflow-hidden bg-white">
      <div className="flex items-center justify-between gap-3 border-b bg-gray-50 px-3 py-2">
        <div className="min-w-0">
          <div className="font-semibold truncate">{lineName}</div>
          <div className="text-xs text-gray-600 truncate">
            Turno: <span className="font-medium">{shiftLabel}</span> · {shiftStartStr.slice(0, 5)}–{shiftEndStr} · {rows.length} proceso(s)
          </div>
        </div>

        <div className="flex items-center gap-2">
          {Number(lineId) > 0 ? (
            <Button
              size="sm"
              onClick={() => onSaveOrder?.(group)}
              disabled={!group?.hasDraft || !!savingOrder}
            >
              <Save className="h-4 w-4 mr-2" />
              {savingOrder ? 'Guardando...' : 'Guardar orden'}
            </Button>
          ) : null}

          {Number(lineId) > 0 && group?.hasDraft ? (
            <Button size="sm" variant="outline" onClick={() => onResetOrder?.(group?.key)} disabled={!!savingOrder}>
              <RotateCcw className="h-4 w-4 mr-2" />
              Restablecer
            </Button>
          ) : null}

          {Number(lineId) > 0 ? (
            <a
              href={route('planning.lines.day', { packingLine: lineId, date: dateKey, shift_id: shiftId })}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Button size="sm" variant="outline">Ver línea</Button>
            </a>
          ) : null}

          {Number(lineId) > 0 && printableProcessId ? (
            <a
              href={`${route('planning.processes.instruction', printableProcessId)}?line_id=${lineId}`}
              target="_blank"
              rel="noopener noreferrer"
            >
              <Button size="sm" variant="secondary">
                <Printer className="h-4 w-4 mr-2" />
                Imprimir línea
              </Button>
            </a>
          ) : (
            <Badge variant="outline" className="text-gray-500">Sin imprimir</Badge>
          )}
        </div>
      </div>

      <div className="p-3">
        <div className="rounded border bg-white">
          <div className="flex items-center justify-between px-3 py-2 text-[11px] text-gray-600 border-b bg-slate-50">
            <div className="font-semibold">Gantt</div>
            <div className="text-gray-500">{shiftStartStr.slice(0, 5)}–{shiftEndStr}</div>
          </div>

          <div className="divide-y">
            {(() => {
              let cursor = shiftStartMin
              return rows.map(({ p, lt, lineLots, startMin, endMin, start, end, durationMinutes }, rowIndex) => {
              const status = p?.estado?.value ?? p?.estado
                const lotsPreview = (lineLots || []).map(lotDisplayLabel).slice(0, 3).join(', ')
                const lotsMore = Math.max(0, (lineLots || []).length - 3)
                const lotsLabel = (lineLots || []).length > 0
                  ? `${lotsPreview}${lotsMore > 0 ? ` +${lotsMore}` : ''}`
                  : '-'
                const producerLabel = (lineLots?.[0]?.producer || p?.first_lot?.producer || '-')
                const canMove = Number(lineId) > 0
                const canMoveUp = canMove && rowIndex > 0
                const canMoveDown = canMove && rowIndex < (rows.length - 1)
                // Respetar secuencialidad: los procesos se ejecutan uno tras otro por línea.
                // Si el estimado tiene un "hueco" (startMin > cursor), lo respetamos.
                if (startMin !== null && startMin > cursor) {
                  cursor = startMin
                }
                const displayStart = cursor
                const displayEnd = cursor + Math.max(1, durationMinutes || 30)
                cursor = displayEnd

                const leftPct = Math.max(0, Math.min(100, ((displayStart - shiftStartMin) / totalMinutes) * 100))
                const rightPct = Math.max(0, Math.min(100, ((displayEnd - shiftStartMin) / totalMinutes) * 100))
              const widthPct = Math.max(2, rightPct - leftPct)

              const tone = status === 'CONFIRMADO'
                ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-900'
                : status === 'CONFLICTO'
                  ? 'bg-red-500/15 border-red-500/40 text-red-900'
                  : 'bg-indigo-500/15 border-indigo-500/40 text-indigo-900'

                const label = (startMin !== null && endMin !== null)
                  ? `${fmtHM(start)}–${fmtHM(end)}`
                  : `${minutesToHM(displayStart)}–${minutesToHM(displayEnd)}`

              return (
                <div key={String(p?.id)} className="grid grid-cols-12 gap-3 px-3 py-2 items-center">
                  <div className="col-span-3 min-w-0">
                    <div className="flex items-center gap-2">
                      {canMove ? (
                        <span className="inline-flex items-center gap-1 rounded border bg-white px-1 py-0.5">
                          <Button
                            size="icon"
                            variant="ghost"
                            className="h-5 w-5"
                            disabled={!canMoveUp || !!savingOrder}
                            onClick={() => onMoveOrder?.(group, p?.id, 'up')}
                          >
                            <ChevronUp className="h-3.5 w-3.5" />
                          </Button>
                          <Button
                            size="icon"
                            variant="ghost"
                            className="h-5 w-5"
                            disabled={!canMoveDown || !!savingOrder}
                            onClick={() => onMoveOrder?.(group, p?.id, 'down')}
                          >
                            <ChevronDown className="h-3.5 w-3.5" />
                          </Button>
                        </span>
                      ) : null}
                      <span className="font-semibold">#{p.id}</span>
                      <StatusBadge status={status} />
                    </div>
                    <div className="text-[11px] text-gray-600 truncate">{p.especie}</div>
                  </div>

                  <div className="col-span-9">
                    <div className="relative h-9 rounded bg-white border">
                      <div
                        className={`absolute top-1.5 h-6 rounded border px-2 flex items-center gap-2 ${tone}`}
                        style={{ left: `${leftPct}%`, width: `${widthPct}%` }}
                        title={start && end ? `${fmtHM(start)}–${fmtHM(end)}` : 'Sin horarios estimados'}
                      >
                        <span className="text-[11px] font-semibold whitespace-nowrap">
                            {label}
                        </span>
                        <span className="text-[11px] font-semibold whitespace-nowrap">
                          #{p?.id || ''}
                        </span>
                        <span className="text-[11px] text-gray-700 whitespace-nowrap">
                          Lotes {lotsLabel}
                        </span>
                        <span className="text-[11px] text-gray-600 truncate">
                          {producerLabel}
                        </span>
                        <span className="text-[11px] text-gray-600 whitespace-nowrap">
                          {Number(lt?.bins || 0) ? `${Number(lt.bins).toLocaleString('es-CL')} bins` : ''}
                          {Number(lt?.kilos || 0) ? ` · ${Math.round(Number(lt.kilos)).toLocaleString('es-CL')} kg` : ''}
                        </span>
                      </div>

                      <div className="absolute inset-y-0 right-2 flex items-center gap-2">
                        <Link href={route('planning.processes.show', p.id)}>
                          <Button variant="outline" size="sm">Editar</Button>
                        </Link>
                        <Button
                          variant="destructive"
                          size="sm"
                          onClick={() => onDelete?.(p?.id)}
                          disabled={!p?.id}
                        >
                          Eliminar
                        </Button>
                        {status === 'CONFIRMADO' ? (
                          <a
                            href={`${route('planning.processes.instruction', p.id)}${Number(lineId) > 0 ? `?line_id=${Number(lineId)}` : ''}`}
                            target="_blank"
                            rel="noopener noreferrer"
                          >
                            <Button variant="secondary" size="sm">Imprimir</Button>
                          </a>
                        ) : null}
                      </div>
                    </div>
                  </div>
                </div>
              )
              })
            })()}
          </div>
        </div>
      </div>
    </div>
  )
}

export default function Index({ processes, filters }) {
  const { props } = usePage()
  const { data, setData, get } = useForm({
    especie: filters?.especie ?? '',
  })
  const handleDeleteProcess = (processId) => {
    const id = Number(processId)
    if (!Number.isFinite(id) || id <= 0) return
    if (!confirm(`¿Eliminar el proceso #${id}? Esta acción no se puede deshacer.`)) return
    router.delete(route('planning.processes.destroy', id), {
      data: { especie: String(data.especie || '').trim() || null },
      preserveScroll: true,
      preserveState: true,
    })
  }
  const viewKey = useMemo(() => 'planning.processes.index.view', [])
  const [view, setView] = useState(() => {
    try {
      return window.localStorage.getItem(viewKey) || 'table'
    } catch (e) {
      return 'table'
    }
  })
  const [draftOrdersByGroup, setDraftOrdersByGroup] = useState({})
  const [savingGroupKey, setSavingGroupKey] = useState('')

  useEffect(() => {
    try {
      window.localStorage.setItem(viewKey, view)
    } catch (e) {
      // ignore
    }
  }, [viewKey, view])

  useEffect(() => {
    setDraftOrdersByGroup({})
    setSavingGroupKey('')
  }, [processes?.data])

  const resetGroupOrderDraft = (groupKey) => {
    setDraftOrdersByGroup((prev) => {
      if (!prev?.[groupKey]) return prev
      const next = { ...prev }
      delete next[groupKey]
      return next
    })
  }

  const moveProcessOrder = (group, processId, direction) => {
    const groupKey = String(group?.key || '')
    const current = normalizeIdList(group?.currentOrderIds)
    if (!groupKey || current.length < 2) return

    const next = moveProcessInOrder(current, processId, direction)
    if (areIdListsEqual(next, current)) return

    const base = normalizeIdList(group?.baseOrderIds)
    setDraftOrdersByGroup((prev) => {
      if (areIdListsEqual(next, base)) {
        if (!prev?.[groupKey]) return prev
        const cleaned = { ...prev }
        delete cleaned[groupKey]
        return cleaned
      }
      return { ...prev, [groupKey]: next }
    })
  }

  const saveGroupOrder = (group) => {
    const lineId = Number(group?.lineId || 0)
    const shiftId = Number(group?.shiftId || 0)
    const date = String(group?.dateKey || '')
    const processIds = normalizeIdList(group?.currentOrderIds)
    if (lineId <= 0 || shiftId <= 0 || !date || !processIds.length) return

    setSavingGroupKey(String(group.key || ''))
    router.patch(route('planning.processes.line-order.update'), {
      date,
      shift_id: shiftId,
      line_id: lineId,
      process_ids: processIds,
    }, {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => resetGroupOrderDraft(String(group.key || '')),
      onFinish: () => setSavingGroupKey(''),
    })
  }

  const dateSections = useMemo(() => {
    const list = Array.isArray(processes?.data) ? processes.data : []

    // 1) Agrupamos por (fecha, turno, línea) para poder imprimir instructivo por línea.
    const groupMap = new Map()

    for (const p of list) {
      const dateKey = p?.fecha ? String(p.fecha).slice(0, 10) : ''
      const shiftId = Number(p?.shift?.id || 0)
      const shiftLabel = p?.shift?.codigo ? `${p.shift.codigo}${p.shift?.nombre ? ` · ${p.shift.nombre}` : ''}` : '-'
      const lines = Array.isArray(p?.lines) && p.lines.length
        ? p.lines
        : [{ id: 0, nombre: '(sin línea)' }]

      for (const line of lines) {
        const lineId = Number(line?.id || 0)
        const lineName = line?.nombre ? String(line.nombre) : (lineId ? `Línea ${lineId}` : '(sin línea)')
        const groupKey = `${dateKey}|${shiftId}|${lineId}`
        if (!groupMap.has(groupKey)) {
          groupMap.set(groupKey, {
            key: groupKey,
            dateKey,
            shiftId,
            shiftLabel,
            lineId,
            lineName,
            processes: [],
          })
        }
        groupMap.get(groupKey).processes.push(p)
      }
    }

    const groups = Array.from(groupMap.values())
      .map((g) => {
        const getStartMinutes = (p) => {
          const keyA = String(g.lineId)
          const keyB = Number(g.lineId)
          const lineTimes = p?.line_times || {}
          const slot = lineTimes?.[keyA] || lineTimes?.[keyB] || null
          const minutes = timeToMinutes(slot?.inicio_estimado || null)
          return Number.isFinite(minutes) ? minutes : Number.POSITIVE_INFINITY
        }

        // Orden operativo: prioridad al último orden guardado manualmente.
        // Fallback: hora estimada y luego ID (estable).
        const sorted = [...g.processes].sort((a, b) => {
          const orderA = getManualLineOrder(a, g.lineId)
          const orderB = getManualLineOrder(b, g.lineId)
          if (orderA !== null || orderB !== null) {
            if (orderA !== null && orderB !== null && orderA !== orderB) return orderA - orderB
            if (orderA !== null && orderB === null) return -1
            if (orderA === null && orderB !== null) return 1
          }
          const sa = getStartMinutes(a)
          const sb = getStartMinutes(b)
          if (sa !== sb) return sa - sb
          return Number(a?.id || 0) - Number(b?.id || 0)
        })

        const baseOrderIds = normalizeIdList(sorted.map((p) => p?.id))
        const draftOrderIds = normalizeIdList(draftOrdersByGroup?.[g.key])
        const processesForRender = draftOrderIds.length ? applyProcessOrder(sorted, draftOrderIds) : sorted
        const currentOrderIds = normalizeIdList(processesForRender.map((p) => p?.id))
        const hasDraft = !areIdListsEqual(currentOrderIds, baseOrderIds)
        const shiftStartMinutes = timeToMinutes(processesForRender?.[0]?.shift?.hora_inicio || null) ?? Number.POSITIVE_INFINITY
        const printable = processesForRender.find((p) => (p?.estado?.value ?? p?.estado) === 'CONFIRMADO') || null
        return {
          ...g,
          processes: processesForRender,
          baseOrderIds,
          currentOrderIds,
          hasDraft,
          shiftStartMinutes,
          printableProcessId: printable?.id || null,
        }
      })
      .sort((a, b) => {
        // Fecha desc, luego línea asc, luego hora de turno asc.
        if (a.dateKey !== b.dateKey) return String(b.dateKey).localeCompare(String(a.dateKey))
        if (a.lineName !== b.lineName) return String(a.lineName).localeCompare(String(b.lineName), 'es', { sensitivity: 'base' })
        if (Number(a.shiftStartMinutes || 0) !== Number(b.shiftStartMinutes || 0)) {
          return Number(a.shiftStartMinutes || 0) - Number(b.shiftStartMinutes || 0)
        }
        return String(a.shiftLabel).localeCompare(String(b.shiftLabel), 'es', { sensitivity: 'base' })
      })

    // 2) Secciones por fecha (mantiene “ordenado por fecha”).
    const byDate = new Map()
    for (const g of groups) {
      const k = g.dateKey || 'SIN_FECHA'
      if (!byDate.has(k)) byDate.set(k, { dateKey: k, groups: [] })
      byDate.get(k).groups.push(g)
    }

    return Array.from(byDate.values()).sort((a, b) => String(b.dateKey).localeCompare(String(a.dateKey)))
  }, [processes?.data, draftOrdersByGroup])

  useEffect(() => {
    const t = setTimeout(() => {
      get(route('planning.processes.index', { especie: (data.especie || '').trim() }), {
        preserveState: true,
        replace: true,
      })
    }, 250)
    return () => clearTimeout(t)
  }, [data.especie])

  return (
    <div className="min-h-screen w-full bg-slate-50 py-6 px-6 lg:px-8">
      <Card className="border-slate-200/70 shadow-sm">
        <CardHeader className="space-y-0 pb-0">
          <div className="rounded-lg border bg-gradient-to-r from-white via-slate-50 to-sky-50 px-4 py-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div className="min-w-0">
                <CardTitle className="text-2xl font-bold flex items-center gap-2">
                  <Layers className="h-5 w-5 text-slate-700" />
                  Planificación de Proceso
                </CardTitle>
                <div className="mt-1 text-sm text-gray-600">
                  Ordena procesos por día/turno y genera instructivos por línea/cámara.
                </div>
              </div>

              <div className="flex flex-wrap items-center gap-2">
                <Link href={route('planning.processes.create')}>
                  <Button>Nuevo proceso</Button>
                </Link>
                <Link href={route('planning.batches.create')}>
                  <Button variant="outline">Planificar semana</Button>
                </Link>
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
            </div>
          )}
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          )}

          <div className="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between mt-4 mb-4">
            <div className="w-full sm:max-w-md">
              <label className="text-sm font-medium text-gray-700">Filtrar por especie</label>
              <div className="mt-1 flex items-center gap-2">
                <Input
                  value={data.especie}
                  onChange={(e) => setData('especie', e.target.value)}
                  placeholder="Ej: CEREZA"
                />
                {String(data.especie || '').trim() ? (
                  <Button variant="outline" onClick={() => setData('especie', '')}>Limpiar</Button>
                ) : null}
              </div>
              <div className="text-xs text-gray-500 mt-1">Tip: escribe parte del nombre.</div>
            </div>
            <div className="flex flex-col items-start gap-2 sm:items-end">
              <div className="inline-flex rounded-md border bg-white p-1">
                <button
                  type="button"
                  onClick={() => setView('table')}
                  className={`inline-flex items-center gap-2 rounded px-3 py-2 text-sm font-semibold ${
                    view === 'table' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  <Layers className="h-4 w-4" />
                  Actual
                </button>
                <button
                  type="button"
                  onClick={() => setView('gantt')}
                  className={`inline-flex items-center gap-2 rounded px-3 py-2 text-sm font-semibold ${
                    view === 'gantt' ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'
                  }`}
                >
                  <GanttChartSquare className="h-4 w-4" />
                  Gantt
                </button>
              </div>

              <div className="flex items-center gap-2 text-sm text-gray-700">
                <Badge variant="outline" className="bg-white">
                  Total: <span className="ml-1 font-semibold">{processes?.total ?? 0}</span>
                </Badge>
                <Badge variant="outline" className="bg-white text-gray-600">
                  <Calendar className="h-3.5 w-3.5 mr-1" />
                  America/Santiago
                </Badge>
              </div>
            </div>
          </div>

          {(processes?.data || []).length === 0 ? (
            <div className="rounded border py-10 text-center text-sm text-gray-500">
              No hay procesos. Crea uno para comenzar.
            </div>
          ) : (
            <div className="space-y-6">
              {dateSections.map((section) => (
                <div key={section.dateKey} className="rounded border bg-white">
                  <div className="flex items-center justify-between gap-3 border-b bg-gradient-to-r from-slate-50 via-white to-slate-50 px-4 py-2">
                    <div className="font-bold text-gray-800">
                      {section.dateKey === 'SIN_FECHA' ? 'Sin fecha' : formatDate(section.dateKey)}
                    </div>
                    <Badge variant="outline" className="text-gray-600">
                      {(() => {
                        const ids = new Set()
                        for (const g of section.groups) {
                          for (const p of (g.processes || [])) ids.add(p?.id)
                        }
                        return ids.size
                      })()} proceso(s)
                    </Badge>
                  </div>

                  <div className="p-4 space-y-4">
                    {section.groups.map((g) => (
                      view === 'gantt' ? (
                        <LineGroupGantt
                          key={g.key}
                          group={g}
                          onDelete={handleDeleteProcess}
                          onMoveOrder={moveProcessOrder}
                          onSaveOrder={saveGroupOrder}
                          onResetOrder={resetGroupOrderDraft}
                          savingOrder={savingGroupKey === g.key}
                        />
                      ) : (
                      <div key={g.key} className="rounded border overflow-hidden bg-white">
                        <div className="flex items-center justify-between gap-3 border-b bg-gray-50 px-3 py-2">
                          <div className="min-w-0">
                            <div className="font-semibold truncate">{g.lineName}</div>
                            <div className="text-xs text-gray-600 truncate">
                              Turno: <span className="font-medium">{g.shiftLabel}</span>
                            </div>
                          </div>

                          <div className="flex items-center gap-2">
                            {g.lineId > 0 ? (
                              <Button
                                size="sm"
                                onClick={() => saveGroupOrder(g)}
                                disabled={!g.hasDraft || savingGroupKey === g.key}
                              >
                                <Save className="h-4 w-4 mr-2" />
                                {savingGroupKey === g.key ? 'Guardando...' : 'Guardar orden'}
                              </Button>
                            ) : null}

                            {g.lineId > 0 && g.hasDraft ? (
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => resetGroupOrderDraft(g.key)}
                                disabled={savingGroupKey === g.key}
                              >
                                <RotateCcw className="h-4 w-4 mr-2" />
                                Restablecer
                              </Button>
                            ) : null}

                            {g.lineId > 0 ? (
                              <a
                                href={route('planning.lines.day', { packingLine: g.lineId, date: g.dateKey, shift_id: g.shiftId })}
                                target="_blank"
                                rel="noopener noreferrer"
                              >
                                <Button size="sm" variant="outline">Ver línea</Button>
                              </a>
                            ) : null}

                            {g.lineId > 0 && g.printableProcessId ? (
                              <a
                                href={`${route('planning.processes.instruction', g.printableProcessId)}?line_id=${g.lineId}`}
                                target="_blank"
                                rel="noopener noreferrer"
                              >
                                <Button size="sm" variant="secondary">
                                  <Printer className="h-4 w-4 mr-2" />
                                  Imprimir línea
                                </Button>
                              </a>
                            ) : (
                              <Badge variant="outline" className="text-gray-500">Sin imprimir</Badge>
                            )}
                          </div>
                        </div>

                        <Table>
                          <TableHeader>
                            <TableRow>
                              <TableHead>Proceso</TableHead>
                              <TableHead>Productor / Variedad</TableHead>
                              <TableHead>Especie</TableHead>
                              <TableHead>Estado</TableHead>
                              <TableHead className="text-right">Acciones</TableHead>
                            </TableRow>
                          </TableHeader>
                          <TableBody>
                            {g.processes.map((p) => (
                              <TableRow
                                key={`${g.key}-${p.id}`}
                                className={
                                  (p.estado?.value ?? p.estado) === 'CONFLICTO'
                                    ? 'bg-red-50/40'
                                    : (p.estado?.value ?? p.estado) === 'CONFIRMADO'
                                      ? 'bg-green-50/30'
                                      : ''
                                }
                              >
                                <TableCell className="font-medium">
                                  <div className="flex items-center gap-2">
                                    {g.lineId > 0 ? (
                                      <span className="inline-flex items-center gap-1 rounded border bg-white px-1 py-0.5">
                                        <Button
                                          size="icon"
                                          variant="ghost"
                                          className="h-5 w-5"
                                          disabled={savingGroupKey === g.key || (g.currentOrderIds || []).findIndex((id) => Number(id) === Number(p.id)) <= 0}
                                          onClick={() => moveProcessOrder(g, p.id, 'up')}
                                        >
                                          <ChevronUp className="h-3.5 w-3.5" />
                                        </Button>
                                        <Button
                                          size="icon"
                                          variant="ghost"
                                          className="h-5 w-5"
                                          disabled={savingGroupKey === g.key || (g.currentOrderIds || []).findIndex((id) => Number(id) === Number(p.id)) >= ((g.currentOrderIds || []).length - 1)}
                                          onClick={() => moveProcessOrder(g, p.id, 'down')}
                                        >
                                          <ChevronDown className="h-3.5 w-3.5" />
                                        </Button>
                                      </span>
                                    ) : null}
                                    <span>#{p.id}</span>
                                  </div>
                                </TableCell>
                                <TableCell>
                                  {(() => {
                                    const lineLots = getProcessLineLots(p, g.lineId)
                                    if (!lineLots.length) {
                                      return p.first_lot ? (
                                        <div className="min-w-0">
                                          <div className="font-medium truncate">{p.first_lot.producer || '-'}</div>
                                          <div className="text-xs text-gray-600 flex flex-wrap items-center gap-2">
                                            {p.first_lot.n_g_recepcion ? (
                                              <Badge variant="outline">Lote {p.first_lot.n_g_recepcion}</Badge>
                                            ) : (
                                              <Badge variant="outline" className="text-gray-500">Lote -</Badge>
                                            )}
                                            <span className="truncate">{p.first_lot.variedad || '-'}</span>
                                            {p.first_lot.csg ? (
                                              <Badge variant="outline">CSG {p.first_lot.csg}</Badge>
                                            ) : (
                                              <Badge variant="outline" className="text-gray-500">CSG -</Badge>
                                            )}
                                            {p.first_lot.sdp ? (
                                              <Badge variant="secondary">SDP {p.first_lot.sdp}</Badge>
                                            ) : (
                                              <Badge variant="outline" className="text-gray-500">SDP -</Badge>
                                            )}
                                          </div>
                                          <div className="text-[11px] text-gray-500 mt-1">(resumen proceso)</div>
                                        </div>
                                      ) : (
                                        <span className="text-sm text-gray-400">Sin lotes</span>
                                      )
                                    }

                                    const producers = Array.from(new Set(lineLots.map((lot) => String(lot?.producer || '').trim()).filter(Boolean)))
                                    const varieties = Array.from(new Set(lineLots.map((lot) => String(lot?.variedad || '').trim()).filter(Boolean)))
                                    const csgValues = Array.from(new Set(lineLots.map((lot) => String(lot?.csg || '').trim()).filter(Boolean)))
                                    const sdpValues = Array.from(new Set(lineLots.map((lot) => String(lot?.sdp || '').trim()).filter(Boolean)))

                                    return (
                                      <div className="min-w-0">
                                        <div className="font-medium truncate">{producers.join(', ') || '-'}</div>
                                        <div className="text-xs text-gray-600 flex flex-wrap items-center gap-2">
                                          {lineLots.map((lot) => (
                                            <Badge key={`${p.id}-${g.lineId}-${lot.id}`} variant="outline">
                                              Lote {lotDisplayLabel(lot)}
                                            </Badge>
                                          ))}
                                        </div>
                                        <div className="text-[11px] text-gray-500 mt-1 flex flex-wrap items-center gap-2">
                                          <span>{lineLots.length} lotes en esta línea</span>
                                          {varieties.length ? <span>· {varieties.join(', ')}</span> : null}
                                          {csgValues.length ? <span>· CSG {csgValues.join(', ')}</span> : null}
                                          {sdpValues.length ? <span>· SDP {sdpValues.join(', ')}</span> : null}
                                        </div>
                                      </div>
                                    )
                                  })()}
                                </TableCell>
                                <TableCell>{p.especie}</TableCell>
                                <TableCell>
                                  <StatusBadge status={p.estado?.value ?? p.estado} />
                                </TableCell>
                                <TableCell className="text-right">
                                  <div className="flex items-center justify-end gap-2">
                                    <Link href={route('planning.processes.show', p.id)}>
                                      <Button variant="outline" size="sm">Editar</Button>
                                    </Link>
                                    <Button variant="destructive" size="sm" onClick={() => handleDeleteProcess(p.id)}>
                                      Eliminar
                                    </Button>
                                    {(p.estado?.value ?? p.estado) === 'CONFIRMADO' ? (
                                      <a
                                        href={`${route('planning.processes.instruction', p.id)}${g.lineId > 0 ? `?line_id=${g.lineId}` : ''}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                      >
                                        <Button variant="secondary" size="sm">Imprimir</Button>
                                      </a>
                                    ) : (
                                      <Badge variant="outline" className="text-gray-500">Sin imprimir</Badge>
                                    )}
                                  </div>
                                </TableCell>
                              </TableRow>
                            ))}
                          </TableBody>
                        </Table>
                      </div>
                      )
                    ))}
                  </div>
                </div>
              ))}
            </div>
          )}

          {processes?.links?.length ? (
            <div className="flex items-center justify-between mt-4">
              <div className="text-sm text-gray-600">
                Mostrando {processes.from ?? 0} a {processes.to ?? 0} de {processes.total ?? 0}
              </div>
              <nav className="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                {processes.links.map((link, idx) => (
                  <Link
                    key={`${link.url}-${idx}`}
                    href={link.url || '#'}
                    disabled={!link.url}
                    preserveState
                    preserveScroll
                    className={`relative inline-flex items-center px-3 py-2 border text-sm font-medium ${
                      link.active ? 'z-10 bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                    } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </nav>
            </div>
          ) : null}
        </CardContent>
      </Card>
    </div>
  )
}

Index.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
