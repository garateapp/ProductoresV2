import React, { useMemo, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
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

function formatDate(value) {
  if (!value) return '-'
  const raw = String(value)
  const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw
  const isDateOnly = /^\d{4}-\d{2}-\d{2}$/.test(normalized)
  const d = new Date(isDateOnly ? `${normalized}T12:00:00Z` : normalized)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago' }).format(d)
}

function formatDow(value) {
  if (!value) return ''
  const d = new Date(`${value}T12:00:00Z`)
  if (Number.isNaN(d.getTime())) return ''
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago', weekday: 'short' }).format(d)
}

function parseDateTimeParts(value) {
  if (!value) return null
  const raw = String(value)
  const sep = raw.includes('T') ? 'T' : ' '
  const parts = raw.split(sep)
  const date = parts[0] || ''
  const timeRaw = (parts[1] || '00:00:00').replace(/Z|([+-]\d\d:\d\d)$/g, '')
  const [hh, mm, ss] = timeRaw.split(':').map((n) => Number(n || 0))
  if (!date) return null
  const minutes = (Number(hh) || 0) * 60 + (Number(mm) || 0) + ((Number(ss) || 0) >= 30 ? 1 : 0)
  return { date, minutes }
}

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
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold ${map[value] || 'bg-slate-50 text-slate-700 border-slate-200'}`}>
      {value || '-'}
    </span>
  )
}

function lotColor(status) {
  const s = String(status || '').toUpperCase()
  if (s === 'CONFLICTO') return 'bg-red-200 border-red-300 text-red-900'
  if (s === 'CONFIRMADO') return 'bg-green-200 border-green-300 text-green-900'
  return 'bg-slate-200 border-slate-300 text-slate-900'
}

function GanttByLine({ gantt }) {
  const days = gantt?.days || []
  const lines = gantt?.lines || []
  const cells = gantt?.cells || []

  const cellsByKey = useMemo(() => {
    const map = new Map()
    ;(cells || []).forEach((c) => {
      map.set(`${c.line_id}|${c.date}`, c)
    })
    return map
  }, [cells])

  if (!days.length || !lines.length) {
    return (
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg font-bold">Gantt por línea</CardTitle>
          <div className="text-sm text-gray-600">No hay lotes para visualizar.</div>
        </CardHeader>
      </Card>
    )
  }

  return (
    <div className="space-y-4">
      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-lg font-bold">Gantt por línea</CardTitle>
          <div className="text-sm text-gray-600">
            Visualización rápida de la semana por línea/cámara. Para editar, usa “Abrir” en el día.
          </div>
        </CardHeader>
      </Card>

      <div className="overflow-x-auto">
        <div className="min-w-[980px]">
          <div className="grid" style={{ gridTemplateColumns: `260px repeat(${days.length}, minmax(220px, 1fr))` }}>
            <div className="sticky left-0 z-20 bg-white border-b px-3 py-2 text-xs font-semibold text-gray-600">
              Línea / Cámara
            </div>
            {days.map((d) => (
              <div key={d} className="border-b px-3 py-2 text-xs font-semibold text-gray-600">
                <div className="flex items-center justify-between">
                  <span>{formatDow(d)}</span>
                  <span>{formatDate(d)}</span>
                </div>
              </div>
            ))}

            {lines.map((line) => (
              <React.Fragment key={line.id}>
                <div className="sticky left-0 z-10 bg-white border-b px-3 py-3">
                  <div className="font-semibold text-gray-900">{line.nombre}</div>
                  <div className="text-xs text-gray-600">{line.tipo || ''}</div>
                </div>
                {days.map((d) => {
                  const cell = cellsByKey.get(`${line.id}|${d}`)
                  const shiftHoras = Number(cell?.shift_horas || 0)
                  const maxExtra = Number(cell?.max_extra_horas || 0)
                  const totalHoras = Math.max(0.1, shiftHoras + maxExtra)
                  const totalMin = totalHoras * 60
                  const shiftStart = String(cell?.shift_hora_inicio || '00:00:00').slice(0, 5)
                  const shiftStartParts = shiftStart.split(':').map(Number)
                  const shiftStartMin = (shiftStartParts[0] || 0) * 60 + (shiftStartParts[1] || 0)

                  const items = (cell?.items || []).slice()
                  items.sort((a, b) => {
                    const pa = parseDateTimeParts(a?.inicio_estimado)
                    const pb = parseDateTimeParts(b?.inicio_estimado)
                    return (pa?.minutes ?? 0) - (pb?.minutes ?? 0)
                  })

                  return (
                    <div key={`${line.id}-${d}`} className="border-b px-3 py-2">
                      <div className="text-[11px] text-gray-500 mb-1 flex items-center justify-between">
                        <span>{shiftHoras ? `${shiftStart} · ${shiftHoras}h${maxExtra ? ` +${maxExtra}h` : ''}` : 'Sin turno'}</span>
                        <span className="font-medium">{items.length ? `${items.length} lote(s)` : ''}</span>
                      </div>
                      <div className="relative h-12 rounded border bg-gray-50 overflow-hidden">
                        {items.map((it, idx) => {
                          const start = parseDateTimeParts(it?.inicio_estimado)
                          const end = parseDateTimeParts(it?.fin_estimado)
                          if (!start || !end) {
                            return (
                              <div
                                key={it.id}
                                className={`absolute left-1 right-1 top-1 h-5 rounded border px-1 text-[10px] leading-5 ${lotColor(it.estado)}`}
                                style={{ top: 4 + idx * 22 }}
                                title={`Recepción ${it.n_g_recepcion || '-'} · ${it.n_productor || '-'} · ${it.n_variedad || '-'} · (sin hora estimada)`}
                              >
                                <span className="font-semibold">{it.n_g_recepcion}</span>
                                {it.especie ? <span className="ml-1 opacity-70">· {it.especie}</span> : null}
                              </div>
                            )
                          }

                          const dayOffsetStart = start.date !== d ? (start.date > d ? 1440 : 0) : 0
                          const dayOffsetEnd = end.date !== d ? (end.date > d ? 1440 : 0) : 0
                          const startMin = Math.max(0, (start.minutes + dayOffsetStart) - shiftStartMin)
                          const endMin = Math.max(0, (end.minutes + dayOffsetEnd) - shiftStartMin)
                          const left = Math.max(0, Math.min(100, (startMin / totalMin) * 100))
                          const width = Math.max(2, Math.min(100 - left, ((endMin - startMin) / totalMin) * 100))

                          const label = `${it.n_g_recepcion || '-'}`
                          const tooltip = [
                            `Recepción ${it.n_g_recepcion || '-'}`,
                            it.especie ? `Especie: ${it.especie}` : null,
                            it.n_productor ? `Prod: ${it.n_productor}` : null,
                            it.csg_productor ? `CSG: ${it.csg_productor}` : null,
                            it.n_variedad ? `Var: ${it.n_variedad}` : null,
                            it.cantidad_bins ? `Bins: ${it.cantidad_bins}` : null,
                            it.estado ? `Estado: ${it.estado}` : null,
                          ].filter(Boolean).join(' · ')

                          return (
                            <div
                              key={it.id}
                              className={`absolute h-5 rounded border px-1 text-[10px] leading-5 truncate ${lotColor(it.estado)}`}
                              style={{ left: `${left}%`, width: `${width}%`, top: 4 + idx * 22 }}
                              title={tooltip}
                            >
                              <span className="font-semibold">{label}</span>
                              {it.especie ? <span className="ml-1 opacity-70">· {it.especie}</span> : null}
                            </div>
                          )
                        })}
                      </div>
                    </div>
                  )
                })}
              </React.Fragment>
            ))}
          </div>
        </div>
      </div>
    </div>
  )
}

export default function Show({ batch, processes, gantt }) {
  const { props } = usePage()
  const [view, setView] = useState('days')

  const weekLabel = `${batch?.week_start || '-'} → ${batch?.week_end || '-'}`
  const batchSpeciesLabel = batch?.especie ? batch.especie : 'Todas las especies'
  const visibleProcesses = (processes || []).filter((p) => Number(p?.lots_count || 0) > 0)

  return (
    <div className="container mx-auto py-10 space-y-4">
      <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div className="text-xl font-bold">Plan semanal</div>
          <div className="text-sm text-gray-600">
            {batchSpeciesLabel} · {weekLabel} · {batch?.shift?.codigo || '-'} {batch?.shift?.nombre ? `· ${batch.shift.nombre}` : ''}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link href={route('planning.batches.index')}>
            <Button variant="outline">Volver</Button>
          </Link>
          <Button
            variant="outline"
            onClick={() => router.post(route('planning.batches.generate', batch.id))}
          >
            Regenerar semana
          </Button>
          <Button onClick={() => router.post(route('planning.batches.confirm', batch.id))}>
            Confirmar semana
          </Button>
        </div>
      </div>

      <div className="flex items-center gap-2">
        <Button variant={view === 'days' ? 'default' : 'outline'} onClick={() => setView('days')}>
          Ver por días
        </Button>
        <Button variant={view === 'gantt' ? 'default' : 'outline'} onClick={() => setView('gantt')} disabled={!gantt}>
          Ver Gantt por línea
        </Button>
      </div>

      {props?.flash?.success && (
        <div className="rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
          {props.flash.success}
        </div>
      )}
      {props?.flash?.error && (
        <div className="rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
          {props.flash.error}
        </div>
      )}

      {view === 'gantt' ? (
        <GanttByLine gantt={gantt} />
      ) : (
        <Card>
          <CardHeader className="pb-2">
            <CardTitle className="text-lg font-bold">Días</CardTitle>
            <div className="text-sm text-gray-600">Abre un día para ajustar orden, dividir lotes y asignar embalaje.</div>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Especie</TableHead>
                  <TableHead>Turno</TableHead>
                  <TableHead>Lotes</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead className="text-right">Acciones</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {visibleProcesses.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell>{formatDate(p.fecha)}</TableCell>
                    <TableCell>{p.especie || '-'}</TableCell>
                    <TableCell>
                      <span className="font-medium">{p.shift?.codigo || '-'}</span>
                      <span className="text-gray-500">{p.shift?.nombre ? ` · ${p.shift.nombre}` : ''}</span>
                    </TableCell>
                    <TableCell>
                      <span className="font-semibold">{p.lots_count ?? 0}</span>
                      {Number(p.conflicts_count || 0) > 0 ? (
                        <Badge variant="outline" className="ml-2 border-red-200 text-red-700 bg-red-50">
                          {p.conflicts_count} conflicto(s)
                        </Badge>
                      ) : null}
                    </TableCell>
                    <TableCell>
                      <StatusBadge status={p.estado} />
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex items-center justify-end gap-2">
                        <Link href={route('planning.processes.show', p.id)}>
                          <Button size="sm" variant="outline">Abrir</Button>
                        </Link>
                        {p.estado === 'CONFIRMADO' ? (
                          <a href={route('planning.processes.instruction', p.id)} target="_blank" rel="noopener noreferrer">
                            <Button size="sm" variant="secondary">Imprimir</Button>
                          </a>
                        ) : (
                          <Badge variant="outline" className="text-gray-500">Sin imprimir</Badge>
                        )}
                      </div>
                    </TableCell>
                  </TableRow>
                ))}

                {(visibleProcesses.length === 0) ? (
                  <TableRow>
                    <TableCell colSpan={6} className="py-10 text-center text-sm text-gray-500">
                      No hay procesos con lotes en esta semana.
                    </TableCell>
                  </TableRow>
                ) : null}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <div className="text-xs text-gray-500">
        Nota: al confirmar, se revalidan existencias en SQLSRV y se bloquean reservas por lote.
      </div>
    </div>
  )
}

Show.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
