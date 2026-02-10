import React, { useMemo } from 'react'
import { Link, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Textarea } from '@/Components/ui/textarea'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { FileDown, Save, ArrowLeft } from 'lucide-react'

function fmtDate(dateString) {
  if (!dateString) return '-'
  const d = new Date(`${dateString}T12:00:00Z`)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago' }).format(d)
}

function fmtTime(dateTimeString) {
  if (!dateTimeString) return ''
  const raw = String(dateTimeString)
  const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw
  const d = new Date(normalized)
  if (Number.isNaN(d.getTime())) return ''
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago', hour: '2-digit', minute: '2-digit', second: '2-digit' }).format(d)
}

function InstructionCss() {
  return (
    <style>{`
      .instructionDoc{font-family:Arial,Helvetica,sans-serif;color:#111}
      .instructionDoc h1{font-size:20px;margin:0 0 8px 0;font-weight:900}
      .instructionDoc h2{font-size:16px;margin:18px 0 8px 0;font-weight:900}
      .instructionDoc .table-wrap{overflow:auto;border:1px solid #ddd;border-radius:10px;padding:10px;background:#fff}
      .instructionDoc table{border-collapse:collapse;width:max-content;min-width:100%}
      .instructionDoc th,.instructionDoc td{border:1px solid #e5e5e5;padding:6px 10px;vertical-align:top;white-space:pre-wrap;font-size:11px}
      .instructionDoc th{position:sticky;top:0;background:#f7f7f7;font-weight:800}
      .instructionDoc tr:nth-child(even) td{background:#fcfcfc}
      .instructionDoc .meta-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:8px;margin:10px 0 10px 0}
      .instructionDoc .meta-box{border:1px solid #e5e5e5;border-radius:10px;padding:8px 10px;background:#fff}
      .instructionDoc .meta-label{font-size:11px;color:#6b7280;margin-bottom:2px}
      .instructionDoc .meta-value{font-weight:900;color:#111827;font-size:12px}
      @media (max-width: 900px){ .instructionDoc .meta-grid{grid-template-columns:repeat(2,minmax(0,1fr));} }
    `}</style>
  )
}

function LotsTable({ lots }) {
  const rows = Array.isArray(lots) ? lots : []
  const sumBins = rows.reduce((acc, r) => acc + Number(r?.cantidad_bins || 0), 0)
  const sumKgs = rows.reduce((acc, r) => acc + Number(r?.peso_neto || 0), 0)

  return (
    <div className="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Hr Inicio Proceso</th>
            <th>N° Proceso</th>
            <th>Lote</th>
            <th>Tipo Proceso</th>
            <th>Variedad Original</th>
            <th>Productor Real</th>
            <th>CSG</th>
            <th>Categoria</th>
            <th>Fecha Recepción</th>
            <th>Cantidad (bins)</th>
            <th>Kilos</th>
            <th>SDP</th>
            <th>Nota Calidad</th>
            <th>Exportadora/Cliente</th>
            <th>Variedad Rotulada</th>
            <th>Hrs Estimadas</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((r) => (
            <tr key={String(r?.id || `${r?.process_id}-${r?.n_g_recepcion}`)}>
              <td>{fmtTime(r?.inicio) || ''}</td>
              <td>{r?.process_id || ''}</td>
              <td>{r?.n_g_recepcion || ''}</td>
              <td>{r?.tipo_proceso || ''}</td>
              <td>{r?.variedad_original || ''}</td>
              <td>{r?.productor_real || ''}</td>
              <td>{r?.csg_productor || ''}</td>
              <td>{r?.categoria_origen || ''}</td>
              <td>{r?.fecha_recepcion ? fmtDate(String(r.fecha_recepcion).slice(0, 10)) : ''}</td>
              <td>{Number(r?.cantidad_bins || 0) ? Number(r.cantidad_bins).toLocaleString('es-CL') : ''}</td>
              <td>{Number(r?.peso_neto || 0) ? Math.round(Number(r.peso_neto)).toLocaleString('es-CL') : ''}</td>
              <td>{r?.sdp_centrocosto || ''}</td>
              <td>{r?.nota_calidad || ''}</td>
              <td>{r?.exportadora || ''}</td>
              <td>{r?.n_variedad || ''}</td>
              <td />
            </tr>
          ))}
          <tr>
            <td colSpan={9} className="font-bold">TOTAL</td>
            <td className="font-bold">{sumBins ? sumBins.toLocaleString('es-CL') : ''}</td>
            <td className="font-bold">{sumKgs ? Math.round(sumKgs).toLocaleString('es-CL') : ''}</td>
            <td colSpan={5} />
          </tr>
        </tbody>
      </table>
    </div>
  )
}

export default function Edit({ process, shift, lineId, latestVersion, sheet, downloadUrl }) {
  const lots = sheet?.lots || []
  const packaging = Array.isArray(sheet?.packagingSummary) ? sheet.packagingSummary : []

  const initialRows = useMemo(() => {
    return packaging.map((r) => ({
      key: String(r?.key || ''),
      calibres: String(r?.calibres || '').trim() === '-' ? '' : String(r?.calibres || ''),
      observaciones: String(r?.observaciones || '').trim() === '-' ? '' : String(r?.observaciones || ''),
      pedido: String(r?.pedido || '').trim() === '-' ? '' : String(r?.pedido || ''),
    }))
  }, [packaging])

  const { data, setData, post, processing, errors } = useForm({
    line_id: Number(lineId || 0),
    change_reason: '',
    rows: initialRows,
  })

  const onSubmit = (e) => {
    e.preventDefault()
    post(route('planning.processes.instruction.update', process.id), {
      preserveScroll: true,
    })
  }

  return (
    <div className="space-y-4">
      <InstructionCss />

      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="text-lg font-bold truncate">Editar instructivo · {sheet?.lineName || `Línea ${lineId}`}</div>
          <div className="text-sm text-gray-600 truncate">
            {fmtDate(process?.fecha)} · {shift ? `${shift.codigo || ''}${shift.nombre ? ` · ${shift.nombre}` : ''}` : '-'}
            {latestVersion?.version ? ` · Versión vigente ${latestVersion.version}` : ''}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link href={`${route('planning.processes.instruction', process.id)}?line_id=${lineId}`}>
            <Button variant="outline">
              <ArrowLeft className="h-4 w-4 mr-2" />
              Volver
            </Button>
          </Link>
          <a href={downloadUrl}>
            <Button variant="secondary">
              <FileDown className="h-4 w-4 mr-2" />
              Descargar PDF
            </Button>
          </a>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Guardar cambios (crea nueva versión)</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={onSubmit} className="space-y-4">
            <div>
              <Label>Motivo del cambio (obligatorio)</Label>
              <Textarea
                value={data.change_reason}
                onChange={(e) => setData('change_reason', e.target.value)}
                placeholder="Ej: Ajuste de calibres por indicación comercial"
              />
              {errors.change_reason ? <div className="text-sm text-red-600 mt-1">{errors.change_reason}</div> : null}
              <div className="text-xs text-gray-500 mt-1">Este motivo quedará registrado en la versión del instructivo.</div>
            </div>

            <div className="instructionDoc">
              <h1>INSTRUCTIVO DE EMBALAJE</h1>
              <div className="meta-grid">
                <div className="meta-box">
                  <div className="meta-label">Especie</div>
                  <div className="meta-value">{sheet?.speciesLabel || 'VARIAS'}</div>
                </div>
                <div className="meta-box">
                  <div className="meta-label">Exportadora</div>
                  <div className="meta-value">{sheet?.exportadoraLabel || '-'}</div>
                </div>
                <div className="meta-box">
                  <div className="meta-label">Fecha Proceso</div>
                  <div className="meta-value">{fmtDate(process?.fecha)}</div>
                </div>
                <div className="meta-box">
                  <div className="meta-label">Turno</div>
                  <div className="meta-value">{shift ? `${shift.codigo || ''}${shift.nombre ? ` · ${shift.nombre}` : ''}` : '-'}</div>
                </div>
                <div className="meta-box">
                  <div className="meta-label">Línea Proceso</div>
                  <div className="meta-value">{sheet?.lineName || '-'}</div>
                </div>
                <div className="meta-box">
                  <div className="meta-label">Kilos</div>
                  <div className="meta-value">{Number(sheet?.kilos || 0) ? Math.round(Number(sheet.kilos)).toLocaleString('es-CL') : '0'}</div>
                </div>
                <div className="meta-box" style={{ gridColumn: 'span 6' }}>
                  <div className="meta-label">Pedidos</div>
                  <div className="meta-value">{sheet?.pedidosLabel || '-'}</div>
                </div>
              </div>

              <h2>Procesos / lotes</h2>
              <LotsTable lots={lots} />

              <h2>Destino + Embalajes (editable)</h2>
              <div className="table-wrap">
                <table>
                  <thead>
                    <tr>
                      <th>Destino</th>
                      <th>Código Embalaje</th>
                      <th>Descripcion de Embalaje</th>
                      <th>Etiqueta</th>
                      <th>Peso Estandar</th>
                      <th>Envases/Pallet</th>
                      <th>Altura</th>
                      <th>Calibres</th>
                      <th>Indicaciones</th>
                      <th>Observaciones</th>
                      <th>count</th>
                      <th>Pedido</th>
                    </tr>
                  </thead>
                  <tbody>
                    {packaging.length ? packaging.map((r, idx) => (
                      <tr key={String(r?.key || idx)}>
                        <td>{r?.destino || ''}</td>
                        <td className="font-bold">{r?.c_item || ''}</td>
                        <td>{r?.desc_embalaje || ''}</td>
                        <td>{r?.etiqueta || ''}</td>
                        <td>{r?.peso_caja != null ? Number(r.peso_caja).toLocaleString('es-CL', { maximumFractionDigits: 1 }) : ''}</td>
                        <td>{r?.cp2 ?? ''}</td>
                        <td>{r?.altura || ''}</td>
                        <td style={{ minWidth: 220 }}>
                          <Input
                            value={data.rows?.[idx]?.calibres ?? ''}
                            onChange={(e) => {
                              setData('rows', (prev) => {
                                const next = Array.isArray(prev) ? [...prev] : []
                                next[idx] = { ...next[idx], key: String(r?.key || ''), calibres: e.target.value }
                                return next
                              })
                            }}
                            placeholder="Ej: 36 AL 56 o L, XL, 2J"
                          />
                        </td>
                        <td style={{ minWidth: 220 }}>
                          <div className="text-xs text-slate-700 whitespace-pre-wrap">
                            {String(r?.indications || '')}
                          </div>
                        </td>
                        <td style={{ minWidth: 320 }}>
                          <Textarea
                            value={data.rows?.[idx]?.observaciones ?? ''}
                            onChange={(e) => {
                              setData('rows', (prev) => {
                                const next = Array.isArray(prev) ? [...prev] : []
                                next[idx] = { ...next[idx], key: String(r?.key || ''), observaciones: e.target.value }
                                return next
                              })
                            }}
                            rows={2}
                            placeholder="Observaciones..."
                          />
                        </td>
                        <td>{r?.count || ''}</td>
                        <td style={{ minWidth: 220 }}>
                          <Input
                            value={data.rows?.[idx]?.pedido ?? ''}
                            onChange={(e) => {
                              setData('rows', (prev) => {
                                const next = Array.isArray(prev) ? [...prev] : []
                                next[idx] = { ...next[idx], key: String(r?.key || ''), pedido: e.target.value }
                                return next
                              })
                            }}
                            placeholder="Ej: Pedido 123 / Cliente X"
                          />
                        </td>
                      </tr>
                    )) : (
                      <tr>
                        <td colSpan={12} className="text-gray-600">Sin embalajes asignados todavía para esta línea.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="mt-3 text-sm">
                <span className="font-bold">Comentarios:</span> Camara {sheet?.lineName || ''}/
              </div>
            </div>

            <div className="flex items-center justify-end gap-2">
              <Button type="submit" disabled={processing}>
                <Save className="h-4 w-4 mr-2" />
                Guardar
              </Button>
            </div>

            {errors?.rows ? <div className="text-sm text-red-600">{String(errors.rows)}</div> : null}
          </form>
        </CardContent>
      </Card>
    </div>
  )
}

Edit.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
