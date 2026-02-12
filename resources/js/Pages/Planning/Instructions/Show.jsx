import React, { useMemo } from 'react'
import { Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import { Printer, FileDown, Pencil } from 'lucide-react'

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

function statusTone(status) {
  const value = String(status?.value ?? status ?? '')
  const map = {
    BORRADOR: 'bg-slate-100 text-slate-800 border-slate-200',
    CONFLICTO: 'bg-red-50 text-red-800 border-red-200',
    CONFIRMADO: 'bg-green-50 text-green-800 border-green-200',
    EN_PROCESO: 'bg-blue-50 text-blue-800 border-blue-200',
    CERRADO: 'bg-slate-200 text-slate-900 border-slate-300',
  }
  return map[value] || 'bg-slate-50 text-slate-700 border-slate-200'
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

function defectSummaryText(rows) {
  const data = Array.isArray(rows) ? rows : []
  if (data.length === 0) return ''
  return data
    .map((d) => `${String(d?.detalle_item || '-')}: ${Number(d?.porcentaje_muestra || 0).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`)
    .join(', ')
}

function commentsByLots(lots) {
  const rows = Array.isArray(lots) ? lots : []
  const lines = rows
    .map((lot) => {
      const lote = String(lot?.n_g_recepcion || '').trim()
      if (!lote) return null
      const cal = defectSummaryText(lot?.defectos_calidad)
      const con = defectSummaryText(lot?.defectos_condicion)
      if (!cal && !con) return null
      return `${lote}: ${cal ? `Calidad [${cal}]` : ''}${cal && con ? ' · ' : ''}${con ? `Condición [${con}]` : ''}`
    })
    .filter(Boolean)

  return lines
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
            <th>Hr Término Proceso</th>
            <th>N° Proceso</th>
            <th>Lote</th>
            <th>Tipo Proceso</th>
            <th>Variedad Original</th>
            <th>Productor Real</th>
            <th>CSG</th>
            <th>Categoria</th>
            <th>Pulpa</th>
            <th>Fecha Recepción</th>
            <th>% Exportación</th>
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
              <td>{fmtTime(r?.fin) || ''}</td>
              <td>{r?.process_id || ''}</td>
              <td>{r?.n_g_recepcion || ''}</td>
              <td>{r?.tipo_proceso || 'Normal'}</td>
              <td>{r?.variedad_original || ''}</td>
              <td>{r?.productor_real || ''}</td>
              <td>{r?.csg_productor || ''}</td>
              <td>{r?.categoria_origen || 'CAT 1'}</td>
              <td>{r?.pulpa || ''}</td>
              <td>{r?.fecha_recepcion ? fmtDate(String(r.fecha_recepcion).slice(0, 10)) : ''}</td>
              <td>
                {r?.porcentaje_exportacion !== null && r?.porcentaje_exportacion !== undefined
                  ? `${Number(r.porcentaje_exportacion).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`
                  : ''}
              </td>
              <td>{Number(r?.cantidad_bins || 0) ? Number(r.cantidad_bins).toLocaleString('es-CL') : ''}</td>
              <td>{Number(r?.peso_neto || 0) ? Math.round(Number(r.peso_neto)).toLocaleString('es-CL') : ''}</td>
              <td>{r?.sdp_centrocosto || ''}</td>
              <td>{r?.nota_calidad || ''}</td>
              <td>{r?.exportadora || ''}</td>
              <td>{r?.n_variedad || ''}</td>
              <td>{(fmtTime(r?.inicio) && fmtTime(r?.fin)) ? '' : ''}</td>
            </tr>
          ))}
          <tr>
            <td colSpan={12} className="font-bold">TOTAL</td>
            <td className="font-bold">{sumBins ? sumBins.toLocaleString('es-CL') : ''}</td>
            <td className="font-bold">{sumKgs ? Math.round(sumKgs).toLocaleString('es-CL') : ''}</td>
            <td colSpan={5} />
          </tr>
        </tbody>
      </table>
    </div>
  )
}

function PackagingTable({ rows }) {
  const data = Array.isArray(rows) ? rows : []
  return (
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
          {data.length ? data.map((r) => (
            <tr key={String(r?.key || `${r?.destino}-${r?.c_item}`)}>
              <td>{r?.destino || ''}</td>
              <td className="font-bold">{r?.c_item || ''}</td>
              <td>{r?.desc_embalaje || ''}</td>
              <td>{r?.etiqueta || ''}</td>
              <td>{r?.peso_caja != null ? Number(r.peso_caja).toLocaleString('es-CL', { maximumFractionDigits: 1 }) : ''}</td>
              <td>{r?.cp2 ?? ''}</td>
              <td>{r?.altura || ''}</td>
              <td>{r?.calibres || ''}</td>
              <td>{r?.indications || ''}</td>
              <td>{r?.observaciones || ''}</td>
              <td>{r?.count || ''}</td>
              <td>{r?.pedido || ''}</td>
            </tr>
          )) : (
            <tr>
              <td colSpan={12} className="text-gray-600">Sin embalajes sugeridos (falta destino y/o embalaje en los lotes).</td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  )
}

export default function Show({ process, shift, lineSheets, metaByLineId }) {
  const sheets = Array.isArray(lineSheets) ? lineSheets : []
  const status = process?.estado

  const headerMini = useMemo(() => {
    const date = fmtDate(process?.fecha)
    const shiftLabel = shift ? `${shift.codigo || ''}${shift.nombre ? ` · ${shift.nombre}` : ''}` : '-'
    return `${date} · ${shiftLabel}`
  }, [process?.fecha, shift])

  return (
    <div className="space-y-4">
      <InstructionCss />

      <div className="flex items-start justify-between gap-3">
        <div className="min-w-0">
          <div className="text-lg font-bold truncate">Instructivo</div>
          <div className="text-sm text-gray-600 truncate">{headerMini}</div>
          <div className={`inline-flex items-center mt-2 rounded-full border px-2.5 py-1 text-xs font-semibold ${statusTone(status)}`}>
            {String(status || '-')}
          </div>
        </div>
        <div className="flex items-center gap-2">
          <Link href={route('planning.processes.show', process.id)}>
            <Button variant="outline">Volver</Button>
          </Link>
        </div>
      </div>

      {sheets.map((s) => {
        const comments = commentsByLots(s?.lots)
        const lineId = Number(s?.lineId || 0)
        const meta = metaByLineId?.[lineId] || null
        const version = meta?.version ? Number(meta.version) : null
        const pdfUrl = `${route('planning.processes.instruction', process.id)}?format=pdf&download=1&line_id=${lineId}${version ? `&version=${version}` : ''}`
        const editUrl = `${route('planning.processes.instruction.edit', process.id)}?line_id=${lineId}`

        return (
          <Card key={String(lineId || s?.lineName)}>
            <CardHeader className="flex flex-row items-start justify-between gap-3">
              <div className="min-w-0">
                <CardTitle className="truncate">{s?.lineName || 'Línea'}</CardTitle>
                <div className="text-xs text-gray-600 mt-1">
                  {s?.speciesLabel ? `Especie: ${s.speciesLabel}` : null}
                  {version ? ` · Versión ${version}` : ''}
                </div>
                {meta?.reason ? (
                  <div className="text-xs text-gray-600 mt-1">
                    <span className="font-semibold">Motivo:</span> {meta.reason}
                  </div>
                ) : null}
              </div>
                <div className="flex items-center gap-2">
                  <Link href={editUrl}>
                    <Button variant="outline">
                      <Pencil className="h-4 w-4 mr-2" />
                      Editar
                    </Button>
                  </Link>
                  <a href={pdfUrl}>
                    <Button variant="secondary">
                      <FileDown className="h-4 w-4 mr-2" />
                      Descargar PDF
                    </Button>
                  </a>
                </div>
            </CardHeader>
            <CardContent>
              <div className="instructionDoc">
                <h1>INSTRUCTIVO DE EMBALAJE</h1>
                <div className="meta-grid">
                  <div className="meta-box">
                    <div className="meta-label">Especie</div>
                    <div className="meta-value">{s?.speciesLabel || 'VARIAS'}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Exportadora</div>
                    <div className="meta-value">{s?.exportadoraLabel || '-'}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Fecha Proceso</div>
                    <div className="meta-value">{fmtDate(process?.fecha)}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Versión</div>
                    <div className="meta-value">{version || '-'}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Turno</div>
                    <div className="meta-value">{shift ? `${shift.nombre ? ` · ${shift.nombre}` : ''}` : '-'}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Línea Proceso</div>
                    <div className="meta-value">{s?.lineName || '-'}</div>
                  </div>
                  <div className="meta-box">
                    <div className="meta-label">Kilos</div>
                    <div className="meta-value">{Number(s?.kilos || 0) ? Math.round(Number(s.kilos)).toLocaleString('es-CL') : '0'}</div>
                  </div>
                  <div className="meta-box" style={{ gridColumn: 'span 6' }}>
                    <div className="meta-label">Pedidos</div>
                    <div className="meta-value">{s?.pedidosLabel || '-'}</div>
                  </div>
                </div>

                <h2>Procesos / lotes</h2>
                <LotsTable lots={s?.lots} />

                <h2>Destino + Embalajes</h2>
                <PackagingTable rows={s?.packagingSummary} />

                <div className="mt-3 text-sm">
                  <span className="font-bold">Comentarios:</span>
                  {comments.length > 0 ? (
                    <div className="mt-1 space-y-1">
                      {comments.map((line, idx) => (
                        <div key={`cmt-${lineId}-${idx}`} className="text-xs text-gray-700">{line}</div>
                      ))}
                    </div>
                  ) : (
                    <span> Camara {s?.lineName || ''}/</span>
                  )}
                </div>
              </div>
            </CardContent>
          </Card>
        )
      })}

      {(!sheets || sheets.length === 0) ? (
        <Card>
          <CardContent className="py-10 text-center text-sm text-gray-600">
            No hay datos para generar el instructivo.
          </CardContent>
        </Card>
      ) : null}
    </div>
  )
}

Show.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
