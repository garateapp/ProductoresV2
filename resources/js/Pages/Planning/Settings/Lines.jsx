import React, { useMemo, useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { ChevronDown, ChevronRight } from 'lucide-react'

const ESTADO_COLORS = {
  BORRADOR: 'bg-yellow-50 text-yellow-800 border border-yellow-200',
  CONFLICTO: 'bg-red-50 text-red-800 border border-red-200',
  CONFIRMADO: 'bg-blue-50 text-blue-800 border border-blue-200',
  EN_PROCESO: 'bg-purple-50 text-purple-800 border border-purple-200',
}

function fmtDate(dateString) {
  if (!dateString) return '-'
  const d = new Date(`${dateString}T12:00:00Z`)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago' }).format(d)
}

export default function Lines({ lines = [], especies = [] }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const [expandedLine, setExpandedLine] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    nombre: '',
    tipo: 'AUTOMATIZADA',
    especies: especies?.[0] ? [especies[0]] : [],
    activo: true,
  })

  const allEspecies = useMemo(() => (especies || []).map(String).filter(Boolean), [especies])

  const startCreate = () => {
    setEditing(null)
    reset()
    setData({
      nombre: '',
      tipo: 'AUTOMATIZADA',
      especies: especies?.[0] ? [especies[0]] : [],
      activo: true,
    })
  }

  const startEdit = (line) => {
    setEditing(line)
    const current = Array.isArray(line.especies) && line.especies.length
      ? line.especies.map(String)
      : line.especie
        ? [String(line.especie)]
        : []
    setData({
      nombre: line.nombre ?? '',
      tipo: line.tipo?.value ?? line.tipo ?? 'AUTOMATIZADA',
      especies: current,
      activo: Boolean(line.activo),
    })
  }

  const submit = (e) => {
    e.preventDefault()
    if (editing?.id) {
      patch(route('planning.settings.lines.update', editing.id), {
        preserveScroll: true,
        onSuccess: () => setEditing(null),
      })
      return
    }
    post(route('planning.settings.lines.store'), {
      preserveScroll: true,
      onSuccess: () => startCreate(),
    })
  }

  const toggleEspecie = (value) => {
    const normalized = String(value)
    const current = new Set((data.especies || []).map(String))
    if (current.has(normalized)) current.delete(normalized)
    else current.add(normalized)
    setData('especies', Array.from(current))
  }

  const toggleExpand = (lineId) => {
    setExpandedLine((prev) => (prev === lineId ? null : lineId))
  }

  const getProcessGroups = (line) => {
    const lots = Array.isArray(line.processLots) ? line.processLots : []
    const groups = {}
    for (const lot of lots) {
      const pid = lot?.process?.id ?? lot?.process_id
      if (!pid) continue
      if (!groups[pid]) {
        groups[pid] = {
          process: lot.process,
          lots: [],
        }
      }
      groups[pid].lots.push(lot)
    }
    return Object.values(groups).sort((a, b) => {
      const fa = a.process?.fecha ?? ''
      const fb = b.process?.fecha ?? ''
      return fa.localeCompare(fb)
    })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Configuración · Líneas/Cámaras</CardTitle>
          <Button variant="outline" onClick={startCreate}>Nuevo</Button>
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

          <form onSubmit={submit} className="rounded border p-4 bg-gray-50 mb-4">
            <div className="grid grid-cols-1 md:grid-cols-6 gap-3">
              <div className="md:col-span-3">
                <Label>Nombre</Label>
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} placeholder="Ej: Línea 1 / Cámara 3" />
                {errors.nombre && <div className="text-sm text-red-600 mt-1">{errors.nombre}</div>}
              </div>
              <div>
                <Label>Tipo</Label>
                <select
                  className="mt-1 w-full rounded border px-2 py-2 text-sm"
                  value={data.tipo}
                  onChange={(e) => setData('tipo', e.target.value)}
                >
                  <option value="AUTOMATIZADA">AUTOMATIZADA</option>
                  <option value="HAND_PACK">HAND_PACK</option>
                </select>
                {errors.tipo && <div className="text-sm text-red-600 mt-1">{errors.tipo}</div>}
              </div>
              <div className="md:col-span-2">
                <Label>Especies (puede ser más de una)</Label>
                <div className="mt-1 grid grid-cols-1 sm:grid-cols-2 gap-2 rounded border bg-white p-2 max-h-40 overflow-y-auto">
                  {allEspecies.map((e) => {
                    const checked = (data.especies || []).includes(e)
                    return (
                      <label key={e} className="flex items-center gap-2 text-sm cursor-pointer select-none">
                        <input type="checkbox" checked={checked} onChange={() => toggleEspecie(e)} />
                        <span>{e}</span>
                      </label>
                    )
                  })}
                  {allEspecies.length === 0 ? (
                    <div className="text-sm text-gray-600">No hay especies cargadas.</div>
                  ) : null}
                </div>
                {errors.especies && <div className="text-sm text-red-600 mt-1">{errors.especies}</div>}
              </div>
            </div>

            <div className="flex items-center justify-between mt-4">
              <label className="flex items-center gap-2 text-sm">
                <input type="checkbox" checked={Boolean(data.activo)} onChange={(e) => setData('activo', e.target.checked)} />
                Activo
              </label>
              <div className="flex items-center gap-2">
                {editing ? (
                  <Button type="button" variant="outline" onClick={startCreate}>Cancelar</Button>
                ) : null}
                <Button type="submit" disabled={processing}>
                  {processing ? 'Guardando...' : editing ? 'Actualizar' : 'Crear'}
                </Button>
              </div>
            </div>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-8"></TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(lines || []).map((l) => {
                const isExpanded = expandedLine === l.id
                const groups = isExpanded ? getProcessGroups(l) : []
                const totalLots = Array.isArray(l.processLots) ? l.processLots.length : 0
                return (
                  <React.Fragment key={l.id}>
                    <TableRow
                      className={isExpanded ? 'bg-slate-50' : ''}
                    >
                      <TableCell className="w-8">
                        {totalLots > 0 ? (
                          <button
                            type="button"
                            onClick={() => toggleExpand(l.id)}
                            className="p-0.5 rounded hover:bg-gray-200 text-gray-500"
                            title={isExpanded ? 'Colapsar' : 'Ver procesos y lotes'}
                          >
                            {isExpanded ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                          </button>
                        ) : null}
                      </TableCell>
                      <TableCell>
                        <div className="text-sm font-medium">{l.especie}</div>
                        {Array.isArray(l.especies) && l.especies.length > 1 ? (
                          <div className="text-xs text-gray-500">{l.especies.join(', ')}</div>
                        ) : null}
                      </TableCell>
                      <TableCell className="font-medium">{l.nombre}</TableCell>
                      <TableCell>{l.tipo?.value ?? l.tipo}</TableCell>
                      <TableCell>
                        {l.activo ? (
                          <Badge className="bg-green-50 text-green-800 border border-green-200">Sí</Badge>
                        ) : (
                          <Badge variant="outline" className="text-gray-600">No</Badge>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="outline" size="sm" onClick={() => startEdit(l)}>Editar</Button>
                      </TableCell>
                    </TableRow>
                    {isExpanded && (
                      <TableRow>
                        <TableCell colSpan={6} className="p-0">
                          <div className="px-6 py-3 bg-slate-50/80 border-t border-b">
                            {groups.length === 0 ? (
                              <div className="text-sm text-gray-500 py-2">Sin procesos activos.</div>
                            ) : (
                              <div className="space-y-3">
                                {groups.map((g) => (
                                  <div key={g.process?.id} className="rounded border bg-white p-3">
                                    <div className="flex items-center gap-3 mb-2">
                                      <span className="text-sm font-semibold">
                                        Proceso N° {g.process?.id}
                                      </span>
                                      <Badge className={ESTADO_COLORS[g.process?.estado] || 'bg-gray-50 text-gray-800'}>
                                        {g.process?.estado || '-'}
                                      </Badge>
                                      <span className="text-xs text-gray-600">
                                        {fmtDate(g.process?.fecha)}
                                      </span>
                                      <span className="text-xs text-gray-600">
                                        {g.process?.especie || '-'}
                                      </span>
                                      <span className="text-xs text-gray-500">
                                        ({g.lots.length} lote{g.lots.length !== 1 ? 's' : ''})
                                      </span>
                                    </div>
                                    <Table>
                                      <TableHeader>
                                        <TableRow>
                                          <TableHead className="py-1 text-xs">N° Recepción</TableHead>
                                          <TableHead className="py-1 text-xs">Destino</TableHead>
                                          <TableHead className="py-1 text-xs">Variedad</TableHead>
                                          <TableHead className="py-1 text-xs">Bins</TableHead>
                                          <TableHead className="py-1 text-xs">Tipo Proceso</TableHead>
                                        </TableRow>
                                      </TableHeader>
                                      <TableBody>
                                        {g.lots.map((lot) => (
                                          <TableRow key={lot.id} className="hover:bg-gray-50">
                                            <TableCell className="py-1 text-xs font-medium">
                                              {lot.n_g_recepcion || '-'}
                                            </TableCell>
                                            <TableCell className="py-1 text-xs">
                                              {lot.destino || '-'}
                                            </TableCell>
                                            <TableCell className="py-1 text-xs">
                                              {lot.n_variedad || '-'}
                                            </TableCell>
                                            <TableCell className="py-1 text-xs">
                                              {Number(lot.cantidad_bins || 0).toLocaleString('es-CL')}
                                            </TableCell>
                                            <TableCell className="py-1 text-xs">
                                              {lot.tipo_proceso || '-'}
                                            </TableCell>
                                          </TableRow>
                                        ))}
                                      </TableBody>
                                    </Table>
                                  </div>
                                ))}
                              </div>
                            )}
                          </div>
                        </TableCell>
                      </TableRow>
                    )}
                  </React.Fragment>
                )
              })}
              {(!lines || lines.length === 0) && (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-sm text-gray-600">
                    No hay líneas/cámaras creadas.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

Lines.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Configuración</h2>}
  />
)
