import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Textarea } from '@/Components/ui/textarea'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { FileDown, Save, ArrowLeft } from 'lucide-react'
import Combobox from '@/Components/ui/combobox'
import { Toaster, toast } from 'sonner'

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
      const sourceType = String(lot?.source_type || '').trim().toLowerCase()
      const lote = sourceType === 'reembalaje'
        ? String(lot?.source_key || lot?.n_g_recepcion || '').trim()
        : String(lot?.n_g_recepcion || '').trim()
      if (!lote) return null
      const cal = defectSummaryText(lot?.defectos_calidad)
      const con = defectSummaryText(lot?.defectos_condicion)
      if (!cal && !con) return null
      return `${lote}: ${cal ? `Calidad [${cal}]` : ''}${cal && con ? ' · ' : ''}${con ? `Condición [${con}]` : ''}`
    })
    .filter(Boolean)

  return lines
}

function normalizeKey(value) {
  return String(value || '').trim().toLowerCase()
}

function normalizeComboboxOptions(options) {
  const source = Array.isArray(options) ? options : []
  const map = new Map()

  source.forEach((item) => {
    const value = String(item?.value ?? item?.label ?? item ?? '').trim()
    if (!value) return
    const key = normalizeKey(value)
    if (map.has(key)) return
    map.set(key, {
      value,
      label: String(item?.label ?? value),
      searchValue: String(item?.searchValue ?? item?.label ?? value),
    })
  })

  return Array.from(map.values()).sort((a, b) => a.label.localeCompare(b.label, 'es'))
}

function toErrorText(value) {
  if (Array.isArray(value)) return value.map((v) => String(v || '')).filter(Boolean).join(' ')
  return String(value || '').trim()
}

function buildRowErrorState(errorBag) {
  const errors = (errorBag && typeof errorBag === 'object') ? errorBag : {}
  const lotErrorsByIndex = {}
  const packagingErrorsByIndex = {}
  const globalErrors = []

  Object.entries(errors).forEach(([key, raw]) => {
    const message = toErrorText(raw)
    if (!message) return

    const match = key.match(/^(lots|rows)\.(\d+)\.([a-zA-Z0-9_]+)$/)
    if (!match) {
      if (key !== 'lots' && key !== 'rows') {
        globalErrors.push(message)
      }
      return
    }

    const scope = String(match[1] || '')
    const index = Number(match[2] || 0)
    const field = String(match[3] || '')
    if (Number.isNaN(index) || field === '') return

    if (scope === 'lots') {
      lotErrorsByIndex[index] = lotErrorsByIndex[index] || {}
      lotErrorsByIndex[index][field] = message
      return
    }

    packagingErrorsByIndex[index] = packagingErrorsByIndex[index] || {}
    packagingErrorsByIndex[index][field] = message
  })

  const lotRows = Object.keys(lotErrorsByIndex).map((idx) => Number(idx) + 1).sort((a, b) => a - b)
  const packagingRows = Object.keys(packagingErrorsByIndex).map((idx) => Number(idx) + 1).sort((a, b) => a - b)

  return {
    lotErrorsByIndex,
    packagingErrorsByIndex,
    lotRows,
    packagingRows,
    globalErrors,
  }
}

function getVarietyOptionsForLot(lot, varietiesBySpecies, defaultSpecies) {
  const map = (varietiesBySpecies && typeof varietiesBySpecies === 'object') ? varietiesBySpecies : {}
  const speciesKey = normalizeKey(lot?.especie || defaultSpecies)
  const fallback = Array.isArray(map['*']) ? map['*'] : []
  const source = Array.isArray(map[speciesKey]) ? map[speciesKey] : fallback
  const options = normalizeComboboxOptions(source)

  const currentValue = String(lot?.n_variedad || '').trim()
  if (currentValue && !options.some((o) => normalizeKey(o.value) === normalizeKey(currentValue))) {
    return [
      { value: currentValue, label: currentValue, searchValue: currentValue },
      ...options,
    ]
  }
  return options
}

function LotsTable({
  lots,
  editable = false,
  onUpdateLot,
  processTypeOptions = [],
  categoryOptions = [],
  lotErrorsByIndex = {},
  varietiesBySpecies = {},
  defaultSpecies = '',
}) {
  const rows = Array.isArray(lots) ? lots : []
  const hasMexico = rows.some((r) => String(r?.destino || '').trim().toUpperCase() === 'MEXICO')
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
            {hasMexico ? <th>Huerto</th> : null}
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
          {rows.map((r, idx) => {
            const rowErrors = lotErrorsByIndex?.[idx] || {}
            const rowHasError = Object.keys(rowErrors).length > 0
            const varietyOptions = getVarietyOptionsForLot(r, varietiesBySpecies, defaultSpecies)
            const varietyValue = String(r?.n_variedad || '')
            return (
            <tr key={String(r?.id || `${r?.process_id}-${r?.n_g_recepcion}-${r?.source_key || ''}`)}>
              <td>{fmtTime(r?.inicio) || ''}</td>
              <td>{fmtTime(r?.fin) || ''}</td>
              <td>{r?.process_id || ''}</td>
              <td>
                {String(r?.source_type || '').trim().toLowerCase() === 'reembalaje'
                  ? `Folio ${String(r?.source_key || r?.n_g_recepcion || '')}`
                  : (r?.n_g_recepcion || '')}
                {String(r?.source_type || '').trim().toLowerCase() === 'reembalaje' ? (
                  <div className="text-[10px] text-gray-600">
                    N° Proceso {r?.source_n_g_proceso || '-'} · Lote {r?.source_lote || r?.n_g_recepcion || '-'}
                  </div>
                ) : null}
                {rowHasError ? (
                  <div className="mt-1 text-[10px] font-semibold text-red-600">Fila {idx + 1} con errores</div>
                ) : null}
              </td>
              <td>
                {editable ? (
                  <select
                    className={`w-full rounded border px-2 py-1 text-xs ${rowErrors?.tipo_proceso ? 'border-red-500 bg-red-50' : ''}`}
                    value={String(r?.tipo_proceso || 'Normal')}
                    onChange={(e) => onUpdateLot?.(r?.id, { tipo_proceso: String(e.target.value || 'Normal') })}
                  >
                    {(processTypeOptions || []).map((opt) => (
                      <option key={String(opt)} value={String(opt)}>{String(opt)}</option>
                    ))}
                  </select>
                ) : (r?.tipo_proceso || 'Normal')}
                {rowErrors?.tipo_proceso ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.tipo_proceso}</div> : null}
              </td>
              <td>{r?.variedad_original || ''}</td>
              <td>{r?.productor_real || ''}</td>
              <td>{r?.csg_productor || ''}</td>
              <td>
                {editable ? (
                  <select
                    className={`w-full rounded border px-2 py-1 text-xs ${rowErrors?.categoria_origen ? 'border-red-500 bg-red-50' : ''}`}
                    value={String(r?.categoria_origen || 'Cat 1')}
                    onChange={(e) => onUpdateLot?.(r?.id, { categoria_origen: String(e.target.value || 'Cat 1') })}
                  >
                    {(categoryOptions || []).map((opt) => (
                      <option key={String(opt?.value || '')} value={String(opt?.value || '')}>
                        {String(opt?.label || opt?.value || '')}
                      </option>
                    ))}
                  </select>
                ) : (r?.categoria_origen || 'Cat 1')}
                {rowErrors?.categoria_origen ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.categoria_origen}</div> : null}
              </td>
              {hasMexico ? (
                <td>
                  {String(r?.destino || '').trim().toUpperCase() === 'MEXICO' && editable ? (
                    <select
                      className={`w-full rounded border px-2 py-1 text-xs ${rowErrors?.huerto ? 'border-red-500 bg-red-50' : ''}`}
                      value={String(r?.huerto || '')}
                      onChange={(e) => onUpdateLot?.(r?.id, { huerto: String(e.target.value || '') })}
                    >
                      <option value="">Seleccionar...</option>
                      <option value="Tipo A">Tipo A</option>
                      <option value="Tipo B">Tipo B</option>
                      <option value="Tipo C">Tipo C</option>
                      <option value="Tipo C*">Tipo C*</option>
                    </select>
                  ) : (String(r?.destino || '').trim().toUpperCase() === 'MEXICO' ? (r?.huerto || '') : '')}
                  {rowErrors?.huerto ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.huerto}</div> : null}
                </td>
              ) : null}
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
              <td>
                {editable ? (
                  varietyOptions.length > 0 ? (
                    <Combobox
                      value={varietyValue}
                      onChange={(val) => onUpdateLot?.(r?.id, { n_variedad: String(val || '') })}
                      options={varietyOptions}
                      placeholder="Seleccionar variedad"
                      searchPlaceholder="Buscar variedad..."
                      emptyMessage="Sin variedades"
                      className={`w-full ${rowErrors?.n_variedad ? 'border-red-500 bg-red-50' : ''}`}
                    />
                  ) : (
                    <Input
                      className={rowErrors?.n_variedad ? 'border-red-500 bg-red-50' : ''}
                      value={varietyValue}
                      onChange={(e) => onUpdateLot?.(r?.id, { n_variedad: String(e.target.value || '') })}
                      placeholder="Variedad rotulada"
                    />
                  )
                ) : (r?.n_variedad || '')}
                {rowErrors?.n_variedad ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.n_variedad}</div> : null}
              </td>
              <td />
            </tr>
            )
          })}
          <tr>
            <td colSpan={hasMexico ? 13 : 12} className="font-bold">TOTAL</td>
            <td className="font-bold">{sumBins ? sumBins.toLocaleString('es-CL') : ''}</td>
            <td className="font-bold">{sumKgs ? Math.round(sumKgs).toLocaleString('es-CL') : ''}</td>
            <td colSpan={5} />
          </tr>
        </tbody>
      </table>
    </div>
  )
}

function PackagingPicker({ value, disabled, onPick, className = 'w-full' }) {
  const [query, setQuery] = useState('')
  const [loading, setLoading] = useState(false)
  const [options, setOptions] = useState([])
  const lastFetchRef = useRef({ term: '', ts: 0 })

  useEffect(() => {
    let ignore = false
    const term = String(query || '').trim()
    if (term.length < 2) {
      setOptions([])
      return
    }
    const now = Date.now()
    if (lastFetchRef.current.term === term && (now - lastFetchRef.current.ts) < 800) {
      return
    }
    lastFetchRef.current = { term, ts: now }

    const t = setTimeout(async () => {
      try {
        setLoading(true)
        const res = await fetch(`${route('planning.packaging.search')}?q=${encodeURIComponent(term)}`, {
          headers: { Accept: 'application/json' },
        })
        const json = await res.json()
        const data = Array.isArray(json?.data) ? json.data : []
        const mapped = data.map((it) => {
          const code = String(it?.c_item || '')
          const name = String(it?.n_item || '')
          return {
            value: code,
            label: code && name ? `${code} — ${name}` : (code || name || '-'),
            searchValue: `${code} ${name}`.trim(),
            raw: it,
          }
        })
        if (!ignore) setOptions(mapped)
      } catch {
        if (!ignore) setOptions([])
      } finally {
        if (!ignore) setLoading(false)
      }
    }, 250)

    return () => {
      ignore = true
      clearTimeout(t)
    }
  }, [query])

  return (
    <Combobox
      value={String(value || '')}
      onChange={(val) => {
        const opt = options.find((o) => String(o.value) === String(val))
        const raw = opt?.raw || null
        if (raw) onPick(raw)
      }}
      options={options}
      placeholder={loading ? 'Buscando…' : (String(value || '') ? String(value) : 'Buscar embalaje…')}
      searchPlaceholder="Buscar por código o descripción…"
      emptyMessage={loading ? 'Buscando…' : 'Sin resultados'}
      className={className}
      disabled={disabled}
      searchValue={query}
      onSearchChange={setQuery}
    />
  )
}

export default function Edit({
  process,
  shift,
  lineId,
  latestVersion,
  sheet,
  downloadUrl,
  processTypeOptions = [],
  categoryOptions = [],
  varietiesBySpecies = {},
}) {
  const { props } = usePage()
  const lots = sheet?.lots || []
  const packaging = Array.isArray(sheet?.packagingSummary) ? sheet.packagingSummary : []

  const initialRows = useMemo(() => {
    return packaging.map((r) => ({
      key: String(r?.key || ''),
      destino: String(r?.destino || ''),
      c_item: String(r?.c_item || ''),
      desc_embalaje: String(r?.desc_embalaje || ''),
      etiqueta: String(r?.etiqueta || ''),
      peso_caja: r?.peso_caja != null && String(r.peso_caja) !== '' ? String(r.peso_caja) : '',
      cp2: r?.cp2 != null && String(r.cp2) !== '' ? String(r.cp2) : '',
      altura: String(r?.altura || ''),
      calibres: String(r?.calibres || '').trim() === '-' ? '' : String(r?.calibres || ''),
      indications: String(r?.indications || ''),
      observaciones: String(r?.observaciones || '').trim() === '-' ? '' : String(r?.observaciones || ''),
      count: String(r?.count || ''),
      pedido: String(r?.pedido || '').trim() === '-' ? '' : String(r?.pedido || ''),
    }))
  }, [packaging])

  const initialLots = useMemo(() => {
    return (Array.isArray(lots) ? lots : []).map((l) => ({
      ...l,
      tipo_proceso: String(l?.tipo_proceso || 'Normal'),
      categoria_origen: String(l?.categoria_origen || 'Cat 1'),
      n_variedad: String(l?.n_variedad || ''),
      pulpa: String(l?.pulpa || ''),
      huerto: String(l?.huerto || ''),
    }))
  }, [lots])

  const normalizedProcessTypeOptions = useMemo(() => {
    const base = Array.isArray(processTypeOptions) && processTypeOptions.length > 0 ? processTypeOptions : ['Normal', 'Reembalaje']
    const uniq = Array.from(new Set(base.map((v) => String(v)).filter(Boolean)))
    return uniq.length > 0 ? uniq : ['Normal', 'Reembalaje']
  }, [processTypeOptions])

  const normalizedCategoryOptions = useMemo(() => {
    const base = Array.isArray(categoryOptions) ? categoryOptions : []
    const mapped = base
      .map((o) => ({
        value: String(o?.value || '').trim(),
        label: String(o?.label || o?.value || '').trim(),
      }))
      .filter((o) => o.value !== '')

    const hasCat1 = mapped.some((o) => o.value.toUpperCase() === 'CAT 1')
    if (!hasCat1) {
      mapped.unshift({ value: 'Cat 1', label: 'Cat 1' })
    }
    return mapped
  }, [categoryOptions])

  const normalizedVarietiesBySpecies = useMemo(() => {
    const source = (varietiesBySpecies && typeof varietiesBySpecies === 'object') ? varietiesBySpecies : {}
    const out = {}
    Object.entries(source).forEach(([speciesKey, values]) => {
      const key = speciesKey === '*' ? '*' : normalizeKey(speciesKey)
      out[key] = normalizeComboboxOptions(values)
    })
    if (!Array.isArray(out['*'])) {
      out['*'] = []
    }
    return out
  }, [varietiesBySpecies])

  const { data, setData, post, processing, errors } = useForm({
    line_id: Number(lineId || 0),
    change_reason: '',
    lots: initialLots,
    rows: initialRows,
  })

  const lotComments = useMemo(() => commentsByLots(data.lots), [data.lots])
  const rowErrorState = useMemo(() => buildRowErrorState(errors), [errors])
  const rowErrorSummary = useMemo(() => {
    const parts = []
    if (rowErrorState.lotRows.length > 0) {
      parts.push(`Procesos/lotes: filas ${rowErrorState.lotRows.join(', ')}`)
    }
    if (rowErrorState.packagingRows.length > 0) {
      parts.push(`Destino + Embalajes: filas ${rowErrorState.packagingRows.join(', ')}`)
    }
    const text = parts.join(' · ')
    return {
      text,
      signature: text || '',
    }
  }, [rowErrorState])

  const lastToastSignatureRef = useRef('')

  useEffect(() => {
    const flashError = toErrorText(props?.flash?.error)
    if (flashError) {
      toast.error(flashError)
    }
  }, [props?.flash?.error])

  useEffect(() => {
    if (!rowErrorSummary.signature) {
      lastToastSignatureRef.current = ''
      return
    }

    if (lastToastSignatureRef.current === rowErrorSummary.signature) {
      return
    }

    lastToastSignatureRef.current = rowErrorSummary.signature
    toast.error(`Revisa errores en ${rowErrorSummary.text}`)
  }, [rowErrorSummary])

  const updateRow = (idx, key, patch) => {
    const current = Array.isArray(data.rows) ? data.rows : []
    const next = [...current]
    const base = (next[idx] && typeof next[idx] === 'object') ? next[idx] : {}
    next[idx] = { ...base, ...patch, key: String(key || base.key || '') }
    setData('rows', next)
  }

  const updateLot = (lotId, patch) => {
    const id = Number(lotId || 0)
    if (id <= 0) return
    const current = Array.isArray(data.lots) ? data.lots : []
    const next = current.map((l) => {
      if (Number(l?.id || 0) !== id) return l
      return { ...l, ...(patch || {}) }
    })
    setData('lots', next)
  }

  const onSubmit = (e) => {
    e.preventDefault()
    post(route('planning.processes.instruction.update', process.id), {
      preserveScroll: true,
      onError: (serverErrors) => {
        const state = buildRowErrorState(serverErrors)
        const parts = []
        if (state.lotRows.length > 0) {
          parts.push(`Procesos/lotes: filas ${state.lotRows.join(', ')}`)
        }
        if (state.packagingRows.length > 0) {
          parts.push(`Destino + Embalajes: filas ${state.packagingRows.join(', ')}`)
        }
        toast.error(parts.length > 0 ? `No se pudo guardar. ${parts.join(' · ')}` : 'No se pudo guardar. Revisa los campos editables.')
      },
    })
  }

  return (
    <div className="space-y-4 px-8">
      <InstructionCss />
      <Toaster richColors position="top-right" />

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
          {props?.flash?.success ? (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
            </div>
          ) : null}
          {props?.flash?.error ? (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          ) : null}

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
              <LotsTable
                lots={data.lots}
                editable
                onUpdateLot={updateLot}
                processTypeOptions={normalizedProcessTypeOptions}
                categoryOptions={normalizedCategoryOptions}
                lotErrorsByIndex={rowErrorState.lotErrorsByIndex}
                varietiesBySpecies={normalizedVarietiesBySpecies}
                defaultSpecies={sheet?.speciesLabel || process?.especie || ''}
              />
              {rowErrorState.lotRows.length > 0 ? (
                <div className="mt-2 text-xs font-semibold text-red-600">
                  Filas con error en Procesos / lotes: {rowErrorState.lotRows.join(', ')}
                </div>
              ) : null}

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
                    {packaging.length ? packaging.map((r, idx) => {
                      const rowKey = String(r?.key || '')
                      const row = data.rows?.[idx] || {}
                      const rowErrors = rowErrorState.packagingErrorsByIndex?.[idx] || {}
                      const rowHasError = Object.keys(rowErrors).length > 0
                      return (
                        <tr key={String(r?.key || idx)} className={rowHasError ? 'bg-red-50/40' : ''}>
                          <td style={{ minWidth: 120 }}>
                            <Input
                              className={rowErrors?.destino ? 'border-red-500 bg-red-50' : ''}
                              value={row?.destino ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { destino: e.target.value })
                              }}
                              placeholder="Ej: CHINA"
                            />
                            {rowHasError ? <div className="mt-1 text-[10px] font-semibold text-red-600">Fila {idx + 1} con errores</div> : null}
                            {rowErrors?.destino ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.destino}</div> : null}
                          </td>
                          <td style={{ minWidth: 140 }}>
                            <PackagingPicker
                              className={`w-full ${rowErrors?.c_item ? 'border-red-500 bg-red-50' : ''}`}
                              value={row?.c_item ?? ''}
                              disabled={processing}
                              onPick={(it) => {
                                updateRow(idx, rowKey, {
                                  c_item: String(it?.c_item || ''),
                                  desc_embalaje: String(it?.n_item || ''),
                                  etiqueta: String(it?.CP1 || it?.etiqueta || ''),
                                  cp2: it?.cp2_cajas_por_pallet != null ? String(it.cp2_cajas_por_pallet) : String(it?.CP2 || ''),
                                  altura: String(it?.CP3 || it?.altura || ''),
                                })
                              }}
                            />
                            {rowErrors?.c_item ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.c_item}</div> : null}
                          </td>
                          <td style={{ minWidth: 320 }}>
                            <Textarea
                              className={rowErrors?.desc_embalaje ? 'border-red-500 bg-red-50' : ''}
                              value={row?.desc_embalaje ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { desc_embalaje: e.target.value })
                              }}
                              rows={2}
                              placeholder="Descripción..."
                            />
                            <div className="mt-2">
                              <PackagingPicker
                                className={`w-full ${rowErrors?.desc_embalaje ? 'border-red-500 bg-red-50' : ''}`}
                                value={row?.c_item ?? ''}
                                disabled={processing}
                                onPick={(it) => {
                                  updateRow(idx, rowKey, {
                                    c_item: String(it?.c_item || ''),
                                    desc_embalaje: String(it?.n_item || ''),
                                    etiqueta: String(it?.CP1 || it?.etiqueta || ''),
                                    cp2: it?.cp2_cajas_por_pallet != null ? String(it.cp2_cajas_por_pallet) : String(it?.CP2 || ''),
                                    altura: String(it?.CP3 || it?.altura || ''),
                                  })
                                }}
                              />
                            </div>
                            {rowErrors?.desc_embalaje ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.desc_embalaje}</div> : null}
                          </td>
                          <td style={{ minWidth: 160 }}>
                            <Input
                              className={rowErrors?.etiqueta ? 'border-red-500 bg-red-50' : ''}
                              value={row?.etiqueta ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { etiqueta: e.target.value })
                              }}
                              placeholder="Etiqueta"
                            />
                            {rowErrors?.etiqueta ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.etiqueta}</div> : null}
                          </td>
                          <td style={{ minWidth: 120 }}>
                            <Input
                              className={rowErrors?.peso_caja ? 'border-red-500 bg-red-50' : ''}
                              type="number"
                              step="0.1"
                              value={row?.peso_caja ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { peso_caja: e.target.value })
                              }}
                              placeholder="Kg"
                            />
                            {rowErrors?.peso_caja ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.peso_caja}</div> : null}
                          </td>
                          <td style={{ minWidth: 130 }}>
                            <Input
                              className={rowErrors?.cp2 ? 'border-red-500 bg-red-50' : ''}
                              type="number"
                              value={row?.cp2 ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { cp2: e.target.value })
                              }}
                              placeholder="Env/Pallet"
                            />
                            {rowErrors?.cp2 ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.cp2}</div> : null}
                          </td>
                          <td style={{ minWidth: 120 }}>
                            <Input
                              className={rowErrors?.altura ? 'border-red-500 bg-red-50' : ''}
                              value={row?.altura ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { altura: e.target.value })
                              }}
                              placeholder="Altura"
                            />
                            {rowErrors?.altura ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.altura}</div> : null}
                          </td>
                          <td style={{ minWidth: 220 }}>
                            <Input
                              className={rowErrors?.calibres ? 'border-red-500 bg-red-50' : ''}
                              value={row?.calibres ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { calibres: e.target.value })
                              }}
                              placeholder="Ej: 36 AL 56 o L, XL, 2J"
                            />
                            {rowErrors?.calibres ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.calibres}</div> : null}
                          </td>
                          <td style={{ minWidth: 220 }}>
                            <Textarea
                              className={rowErrors?.indications ? 'border-red-500 bg-red-50' : ''}
                              value={row?.indications ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { indications: e.target.value })
                              }}
                              rows={2}
                              placeholder="Indicaciones (pallets, etc)..."
                            />
                            {rowErrors?.indications ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.indications}</div> : null}
                          </td>
                          <td style={{ minWidth: 320 }}>
                            <Textarea
                              className={rowErrors?.observaciones ? 'border-red-500 bg-red-50' : ''}
                              value={row?.observaciones ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { observaciones: e.target.value })
                              }}
                              rows={2}
                              placeholder="Observaciones..."
                            />
                            {rowErrors?.observaciones ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.observaciones}</div> : null}
                          </td>
                          <td style={{ minWidth: 140 }}>
                            <Input
                              className={rowErrors?.count ? 'border-red-500 bg-red-50' : ''}
                              value={row?.count ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { count: e.target.value })
                              }}
                              placeholder="Bins/Kg"
                            />
                            {rowErrors?.count ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.count}</div> : null}
                          </td>
                          <td style={{ minWidth: 220 }}>
                            <Input
                              className={rowErrors?.pedido ? 'border-red-500 bg-red-50' : ''}
                              value={row?.pedido ?? ''}
                              onChange={(e) => {
                                updateRow(idx, rowKey, { pedido: e.target.value })
                              }}
                              placeholder="Ej: Pedido 123 / Cliente X"
                            />
                            {rowErrors?.pedido ? <div className="mt-1 text-[10px] text-red-600">{rowErrors.pedido}</div> : null}
                          </td>
                        </tr>
                      )
                    }) : (
                      <tr>
                        <td colSpan={12} className="text-gray-600">Sin embalajes asignados todavía para esta línea.</td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
              {rowErrorState.packagingRows.length > 0 ? (
                <div className="mt-2 text-xs font-semibold text-red-600">
                  Filas con error en Destino + Embalajes: {rowErrorState.packagingRows.join(', ')}
                </div>
              ) : null}

              <div className="mt-3 text-sm">
                <span className="font-bold">Comentarios:</span>
                {lotComments.length > 0 ? (
                  <div className="mt-1 space-y-1">
                    {lotComments.map((line, idx) => (
                      <div key={`lot-cmt-${idx}`} className="text-xs text-gray-700">{line}</div>
                    ))}
                  </div>
                ) : (
                  <span> Camara {sheet?.lineName || ''}/</span>
                )}
              </div>
            </div>

            <div className="flex items-center justify-end gap-2">
              <Button type="submit" disabled={processing}>
                <Save className="h-4 w-4 mr-2" />
                Guardar
              </Button>
            </div>

            {rowErrorSummary.text ? <div className="text-sm text-red-600">{rowErrorSummary.text}</div> : null}
            {errors?.rows ? <div className="text-sm text-red-600">{String(errors.rows)}</div> : null}
            {errors?.lots ? <div className="text-sm text-red-600">{String(errors.lots)}</div> : null}
            {rowErrorState.globalErrors.length > 0 ? (
              <div className="text-sm text-red-600">{rowErrorState.globalErrors.join(' · ')}</div>
            ) : null}
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
