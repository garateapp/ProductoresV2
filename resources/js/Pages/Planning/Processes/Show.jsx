import React, { useEffect, useMemo, useRef, useState } from 'react'
import { Link, router, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Textarea } from '@/Components/ui/textarea'
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from '@/Components/ui/command'
import Combobox from '@/Components/ui/combobox'
import axios from 'axios'
import { ArrowDown, ArrowLeftRight, ArrowUp, Bug, Check, GripVertical, Plus, Printer, RefreshCw, Trash2, Wand2 } from 'lucide-react'
import { DragDropContext, Draggable, Droppable } from '@hello-pangea/dnd'

function statusLabel(status) {
  const value = String(status?.value ?? status ?? '')
  return value || '-'
}

function StatusPill({ status }) {
  const value = statusLabel(status)
  const map = {
    BORRADOR: 'bg-slate-100 text-slate-800 border-slate-200',
    CONFLICTO: 'bg-red-50 text-red-800 border-red-200',
    CONFIRMADO: 'bg-green-50 text-green-800 border-green-200',
    EN_PROCESO: 'bg-blue-50 text-blue-800 border-blue-200',
    CERRADO: 'bg-slate-200 text-slate-900 border-slate-300',
  }
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold ${map[value] || 'bg-slate-50 text-slate-700 border-slate-200'}`}>
      {value}
    </span>
  )
}

function formatDate(dateString) {
  if (!dateString) return '-'
  // Usamos mediodía UTC para evitar “corrimientos” de día por zona horaria.
  const d = new Date(`${dateString}T12:00:00Z`)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago' }).format(d)
}

function formatTime(dateTimeString) {
  if (!dateTimeString) return '-'
  const raw = String(dateTimeString)
  // Normaliza `YYYY-MM-DD HH:mm:ss` -> `YYYY-MM-DDTHH:mm:ss` para parseo consistente.
  const normalized = raw.includes(' ') && !raw.includes('T') ? raw.replace(' ', 'T') : raw
  const d = new Date(normalized)
  if (Number.isNaN(d.getTime())) return '-'
  return new Intl.DateTimeFormat('es-CL', { timeZone: 'America/Santiago', hour: '2-digit', minute: '2-digit' }).format(d)
}

function MexicoFlagIcon({ className = '' }) {
  return (
    <svg className={className} viewBox="0 0 24 16" role="img" aria-label="México">
      <rect x="0.5" y="0.5" width="23" height="15" rx="2" fill="#ffffff" stroke="#e5e7eb" />
      <rect x="0.5" y="0.5" width="7.66" height="15" rx="2" fill="#0f9d58" opacity="0.95" />
      <rect x="15.84" y="0.5" width="7.66" height="15" rx="2" fill="#db4437" opacity="0.95" />
      <circle cx="12" cy="8" r="1.3" fill="#111827" opacity="0.35" />
    </svg>
  )
}

function normalizeSizeLabel(value) {
  return String(value || '').trim()
}

function parseSizeSortable(value) {
  const s = normalizeSizeLabel(value)
  const m = s.match(/(\d+(\.\d+)?)/)
  if (!m) return null
  return Number(m[1])
}

function SizeCurveValues({ payload, loading, onLoad }) {
  const [expanded, setExpanded] = useState(false)

  const header = (right) => (
    <div className="mt-2 flex items-center justify-between gap-2">
      <div className="text-[11px] text-gray-500">Calibres (cantidad)</div>
      {right}
    </div>
  )

  if (loading) {
    return (
      <div className="mt-2">
        {header(
          <Button type="button" size="sm" variant="outline" disabled>
            Cargando…
          </Button>
        )}
        <div className="h-7 w-full rounded bg-slate-100 animate-pulse mt-1" />
      </div>
    )
  }

  if (!payload) {
    return (
      <div className="mt-2">
        {header(
          <Button type="button" size="sm" variant="outline" onClick={onLoad}>
            Ver
          </Button>
        )}
      </div>
    )
  }

  if (payload?.type === 'cherries') {
    return (
      <div className="mt-2">
        {header(
          <Button type="button" size="sm" variant="outline" onClick={onLoad} title="Volver a cargar">
            Reintentar
          </Button>
        )}
        <div className="text-[11px] text-gray-600 mt-1">
          Disponible (cherries).
        </div>
      </div>
    )
  }

  const data = Array.isArray(payload?.data) ? payload.data : []
  const points = data
    .map((d) => ({ label: normalizeSizeLabel(d?.calibre), value: Number(d?.count ?? d?.cantidad ?? 0) }))
    .filter((p) => p.label && Number.isFinite(p.value) && p.value > 0)

  if (points.length === 0) {
    return (
      <div className="mt-2">
        {header(
          <Button type="button" size="sm" variant="outline" onClick={onLoad}>
            Reintentar
          </Button>
        )}
        <div className="text-[11px] text-gray-500 mt-1">
          Sin datos.
        </div>
      </div>
    )
  }

  const sorted = points.slice().sort((a, b) => {
    const an = parseSizeSortable(a.label)
    const bn = parseSizeSortable(b.label)
    if (an === null && bn === null) return a.label.localeCompare(b.label, 'es')
    if (an === null) return 1
    if (bn === null) return -1
    if (an !== bn) return an - bn
    return a.label.localeCompare(b.label, 'es')
  })

  const visible = expanded ? sorted : sorted.slice(0, 6)
  const hiddenCount = Math.max(0, sorted.length - visible.length)

  return (
    <div className="mt-2">
      {header(
        <div className="flex items-center gap-2">
          <Button type="button" size="sm" variant="outline" onClick={onLoad} title="Volver a cargar">
            Reintentar
          </Button>
          {sorted.length > 6 ? (
            <Button type="button" size="sm" variant="outline" onClick={() => setExpanded((v) => !v)}>
              {expanded ? 'Ocultar' : `Ver más (${hiddenCount})`}
            </Button>
          ) : null}
        </div>
      )}

      <div className="mt-1 flex flex-wrap gap-1">
        {visible.map((p) => (
          <span
            key={p.label}
            className="inline-flex items-center gap-1 rounded-full border bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-700"
            title={`${p.label}: ${Math.round(p.value)}`}
          >
            <span className="text-slate-600">{p.label}</span>
            <span className="text-slate-900">{Math.round(p.value)}</span>
          </span>
        ))}
      </div>
    </div>
  )
}


function PackagingPicker({ lot, onPick, disabled, destinos = [] }) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(false)
  const [options, setOptions] = useState([])
  const [suggestions, setSuggestions] = useState([])
  const [suggestionsLoading, setSuggestionsLoading] = useState(false)
  const lastSuggestFetch = useRef(0)
  const lastFetch = useRef(0)

  useEffect(() => {
    if (!open) return
    const n = String(lot?.n_g_recepcion || '').trim()
    if (!n) {
      setSuggestions([])
      return
    }

    const now = Date.now()
    lastSuggestFetch.current = now
    setSuggestionsLoading(true)

    const t = setTimeout(async () => {
      try {
        const res = await axios.get(route('planning.packaging.suggestions'), { params: { n_g_recepcion: n, limit: 12, destinos } })
        if (lastSuggestFetch.current !== now) return
        setSuggestions(res?.data?.data || [])
      } catch (e) {
        setSuggestions([])
      } finally {
        if (lastSuggestFetch.current === now) setSuggestionsLoading(false)
      }
    }, 50)

    return () => clearTimeout(t)
  }, [open, lot?.n_g_recepcion, JSON.stringify(destinos || [])])

  useEffect(() => {
    if (!open) return
    const query = q.trim()
    if (!query) {
      setOptions([])
      return
    }

    const now = Date.now()
    lastFetch.current = now
    setLoading(true)

    const t = setTimeout(async () => {
      try {
        const res = await axios.get(route('planning.packaging.search'), { params: { q: query } })
        if (lastFetch.current !== now) return
        setOptions(res?.data?.data || [])
      } catch (e) {
        // no UI noise: en planta es mejor no interrumpir
        setOptions([])
      } finally {
        if (lastFetch.current === now) setLoading(false)
      }
    }, 250)

    return () => clearTimeout(t)
  }, [q, open])

  const currentLabel = lot?.c_embalaje
    ? `${lot.c_embalaje}${lot.n_embalaje ? ` · ${lot.n_embalaje}` : ''}`
    : 'Asignar embalaje'

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" size="sm" disabled={disabled} className="justify-between">
          {currentLabel}
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0">
        <Command>
          <CommandInput placeholder="Buscar embalaje..." onValueChange={(v) => setQ(String(v || ''))} />
          <CommandEmpty>{loading ? 'Buscando...' : (q.trim() ? 'Sin resultados' : '')}</CommandEmpty>
          <div className="max-h-64 overflow-y-auto">
            {!q.trim() ? (
              <CommandGroup heading="Sugeridos por matriz">
                {suggestionsLoading ? (
                  <div className="px-3 py-2 text-xs text-gray-600">Cargando sugerencias…</div>
                ) : null}
                {!suggestionsLoading && (!suggestions || suggestions.length === 0) ? (
                  <div className="px-3 py-2 text-xs text-gray-500">Sin sugerencias para este lote.</div>
                ) : null}

                {(suggestions || []).map((opt) => (
                  <CommandItem
                    key={`sug-${String(opt.c_item || opt.id || opt.n_item)}`}
                    value={`${opt.c_item ?? ''} ${opt.n_item ?? ''}`}
                    onSelect={() => {
                      onPick({
                        c_embalaje: opt.c_item ?? null,
                        n_embalaje: opt.n_item ?? null,
                        cp2_cajas_por_pallet: opt.cp2_cajas_por_pallet ?? null,
                      })
                      setOpen(false)
                      setQ('')
                    }}
                  >
                    <Wand2 className="mr-2 h-4 w-4 opacity-70" />
                    <div className="flex-1">
                      <div className="font-medium">{opt.c_item}</div>
                      <div className="text-xs text-gray-500">{opt.n_item || '-'}</div>
                    </div>
                    <div className="text-xs text-gray-600">CP2: {opt.cp2_cajas_por_pallet ?? '-'}</div>
                  </CommandItem>
                ))}
              </CommandGroup>
            ) : null}

            <CommandGroup heading={q.trim() ? 'Resultados' : 'Buscar en catálogo'}>
              {options.map((opt) => (
                <CommandItem
                  key={String(opt.id ?? opt.c_item ?? opt.n_item)}
                  value={String(opt.c_item ?? opt.n_item ?? '')}
                  onSelect={() => {
                    onPick({
                      c_embalaje: opt.c_item ?? null,
                      n_embalaje: opt.n_item ?? null,
                      cp2_cajas_por_pallet: opt.cp2_cajas_por_pallet ?? null,
                    })
                    setOpen(false)
                    setQ('')
                  }}
                >
                  <Check className="mr-2 h-4 w-4 opacity-70" />
                  <div className="flex-1">
                    <div className="font-medium">{opt.c_item}</div>
                    <div className="text-xs text-gray-500">{opt.n_item}</div>
                  </div>
                  <div className="text-xs text-gray-600">CP2: {opt.cp2_cajas_por_pallet ?? '-'}</div>
                </CommandItem>
              ))}
            </CommandGroup>
          </div>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

export default function Show({ process, planningMode = null, lines = [], allLines = [], inventory = [], inventoryFilters = {}, inventoryFilterOptions = {}, allowSplit, badges = {}, lineDay = null }) {
  const { props } = usePage()
  const isLineDay = Boolean(lineDay)
  const status = statusLabel(process?.estado)
  const isConfirmed = status === 'CONFIRMADO'
  const isLocked = isLineDay || ['CERRADO'].includes(status)
  const isRepack = useMemo(() => {
    const raw = String(planningMode || process?.planning_mode || 'normal').trim().toLowerCase()
    return raw === 'reembalaje'
  }, [planningMode, process?.planning_mode])

  const [lots, setLots] = useState(process?.lots || [])
  const [removedIds, setRemovedIds] = useState([])
  const [dirty, setDirty] = useState(false)
  const [saving, setSaving] = useState(false)
  const [pedidos, setPedidos] = useState(String(process?.pedidos || ''))
  const [splitting, setSplitting] = useState(null) // { lot, bins, toLineId }
  const [editingLines, setEditingLines] = useState(null) // { ids: number[] }
  const destinosAvailable = useMemo(() => (props?.packagingDestinosAvailable || []), [props?.packagingDestinosAvailable])
  const destinosStorageKey = useMemo(() => `planning.packaging.destinos.${process?.id}`, [process?.id])
  const [selectedDestinos, setSelectedDestinos] = useState([])
  const [packagingEdit, setPackagingEdit] = useState(null) // { lot, nextPack, reason }
  const confirmedReasonStorageKey = useMemo(() => `planning.process.confirmed_change_reason.${process?.id}`, [process?.id])
  const [changeReason, setChangeReason] = useState('')
  const [splitByLot, setSplitByLot] = useState(false)
  const [inventoryDraftFilters, setInventoryDraftFilters] = useState(() => ({ ...(inventoryFilters || {}) }))
  const repackDefaultEspecie = String(process?.especie || '').trim()

  useEffect(() => {
    setLots(process?.lots || [])
    setDirty(false)
    setRemovedIds([])
    setPedidos(String(process?.pedidos || ''))
    setInventoryDraftFilters({ ...(inventoryFilters || {}) })
  }, [process?.id, process?.updated_at])

  useEffect(() => {
    // Mantener el borrador consistente cuando se actualiza desde backend (paginación/volver/actualización).
    setInventoryDraftFilters({ ...(inventoryFilters || {}) })
  }, [
    inventoryFilters?.q,
    inventoryFilters?.especie,
    inventoryFilters?.variedad,
    inventoryFilters?.productor,
    inventoryFilters?.embalaje,
    inventoryFilters?.nota_calidad,
    inventoryFilters?.brix_min,
    inventoryFilters?.brix_max,
  ])

  useEffect(() => {
    if (!isConfirmed) {
      setChangeReason('')
      return
    }
    try {
      const raw = window.localStorage.getItem(confirmedReasonStorageKey)
      if (typeof raw === 'string') setChangeReason(raw)
    } catch (e) {
      // ignore
    }
  }, [confirmedReasonStorageKey, isConfirmed])

  useEffect(() => {
    if (!isConfirmed) return
    try {
      window.localStorage.setItem(confirmedReasonStorageKey, String(changeReason || ''))
    } catch (e) {
      // ignore
    }
  }, [confirmedReasonStorageKey, isConfirmed, changeReason])

  useEffect(() => {
    try {
      const raw = window.localStorage.getItem(destinosStorageKey)
      if (!raw) return
      const parsed = JSON.parse(raw)
      if (Array.isArray(parsed)) setSelectedDestinos(parsed.map((d) => String(d)).filter(Boolean))
    } catch (e) {
      // ignore
    }
  }, [destinosStorageKey])

  useEffect(() => {
    try {
      window.localStorage.setItem(destinosStorageKey, JSON.stringify(selectedDestinos || []))
    } catch (e) {
      // ignore
    }
  }, [destinosStorageKey, selectedDestinos])

  // Mantener selección consistente con destinos disponibles (si cambia matriz/carga).
  useEffect(() => {
    if (!Array.isArray(destinosAvailable) || destinosAvailable.length === 0) return
    setSelectedDestinos((prev) => prev.filter((d) => destinosAvailable.includes(d)))
  }, [JSON.stringify(destinosAvailable || [])])

  const toggleDestino = (d) => {
    const v = String(d || '').trim()
    if (!v) return
    setSelectedDestinos((prev) => {
      const set = new Set(prev || [])
      if (set.has(v)) set.delete(v)
      else set.add(v)
      return Array.from(set)
    })
  }

  const availableLineOptions = useMemo(() => (Array.isArray(allLines) && allLines.length > 0 ? allLines : (lines || [])), [allLines, lines])
  const currentLineIds = useMemo(() => new Set((lines || []).map((l) => Number(l.id))), [lines])
  const hasAnyLineExtra = useMemo(() => (lines || []).some((l) => Number(l?.extra_horas || 0) > 0), [lines])
  const lineIdsWithLots = useMemo(() => {
    const set = new Set()
    ;(lots || []).forEach((l) => set.add(Number(l.packing_line_id)))
    return set
  }, [lots])
  const missingDestinoLots = useMemo(() => {
    return (lots || [])
      .filter((l) => String(l?.n_g_recepcion || '').trim() !== '')
      .filter((l) => String(l?.destino || '').trim() === '')
      .map((l) => String(l?.n_g_recepcion || '').trim())
  }, [lots])

  const normalizedIncludedLineIds = useMemo(() => {
    const raw = Array.isArray(process?.included_packing_line_ids) ? process.included_packing_line_ids : []
    const parsed = raw.map((id) => Number(id)).filter((id) => Number.isFinite(id) && id > 0)
    if (parsed.length > 0) return parsed
    // `null` o vacío = "todas" para esta especie
    return (availableLineOptions || []).map((l) => Number(l.id)).filter((id) => Number.isFinite(id) && id > 0)
  }, [process?.included_packing_line_ids, availableLineOptions])

  const lotsByLine = useMemo(() => {
    const grouped = {}
    ;(lines || []).forEach((l) => { grouped[l.id] = [] })
    ;(lots || []).forEach((lot) => {
      const lineId = lot.packing_line_id
      if (!grouped[lineId]) grouped[lineId] = []
      grouped[lineId].push(lot)
    })
    Object.keys(grouped).forEach((k) => {
      grouped[k] = grouped[k].slice().sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
    })
    return grouped
  }, [lots, lines])

  const assignedInventoryKeys = useMemo(() => {
    const set = new Set()
    ;(lots || []).forEach((l) => {
      const key = isRepack
        ? String(l?.source_key || '').trim()
        : String(l?.n_g_recepcion || '').trim()
      if (key) set.add(key)
    })
    return set
  }, [lots, isRepack])

  const visibleInventory = useMemo(() => {
    return (inventory || []).filter((item) => {
      const key = String(item?.inventory_key || item?.source_key || item?.n_g_recepcion || '').trim()
      if (!key) return false
      return !assignedInventoryKeys.has(key)
    })
  }, [inventory, assignedInventoryKeys])

  const crossProcessOwnership = useMemo(() => {
    const map = new Map()
    ;(inventory || []).forEach((item) => {
      const ap = item?.assigned_to_process
      if (ap && ap.process_id) {
        const key = String(item?.inventory_key || item?.source_key || item?.n_g_recepcion || '').trim()
        if (key) map.set(key, ap)
      }
    })
    return map
  }, [inventory])

  const [sizeCurves, setSizeCurves] = useState({})
  const [sizeCurvesLoading, setSizeCurvesLoading] = useState({})

  const fetchSizeCurves = async (ngs, { refresh = false } = {}) => {
    const list = (ngs || []).map((v) => String(v || '').trim()).filter(Boolean)
    const unique = Array.from(new Set(list))
    if (unique.length === 0) return

    setSizeCurvesLoading((prev) => {
      const next = { ...(prev || {}) }
      unique.forEach((n) => { next[n] = true })
      return next
    })

    try {
      const res = await axios.post(route('planning.quality.size-distribution'), { n_g_recepcions: unique, refresh })
      const data = res?.data?.data || {}
      setSizeCurves((prev) => ({ ...(prev || {}), ...(data || {}) }))
    } catch (e) {
      // sin ruido en UI
    } finally {
      setSizeCurvesLoading((prev) => {
        const next = { ...(prev || {}) }
        unique.forEach((n) => { next[n] = false })
        return next
      })
    }
  }

  // Precarga (pocos) para que en planta se vea rápido sin muchos clics.
  useEffect(() => {
    if (isRepack) return
    const first = (visibleInventory || []).slice(0, 12).map((i) => String(i?.n_g_recepcion || '').trim()).filter(Boolean)
    const missing = first.filter((n) => !sizeCurves?.[n] && !sizeCurvesLoading?.[n])
    if (missing.length > 0) fetchSizeCurves(missing)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [visibleInventory, isRepack])

  const eligibleLinesForSplit = useMemo(() => {
    // Mostrar todas las líneas/cámaras de la especie (no solo las incluidas),
    // porque en operación real a veces se necesita dividir o reubicar rápido.
    // Si una línea no tiene capacidad configurada, igual se permite asignar.
    return (availableLineOptions || []).slice()
  }, [availableLineOptions])

  const [targetLineId, setTargetLineId] = useState(lines?.[0]?.id ?? null)
  useEffect(() => {
    if (!targetLineId && lines?.[0]?.id) setTargetLineId(lines[0].id)
  }, [lines])

  const selectedRepackEspecie = String(inventoryDraftFilters?.especie || '').trim()
  const selectedRepackEspecieKey = selectedRepackEspecie.toLowerCase()

  const toOptionsWithAll = (items, allLabel = 'Todos') => {
    const base = Array.isArray(items) ? items : []
    return [{ value: '', label: allLabel, searchValue: allLabel }, ...base]
  }

  const ensureSelectedOption = (options, selectedValue) => {
    const selected = String(selectedValue || '').trim()
    if (!selected) return options
    if ((options || []).some((o) => String(o?.value) === selected)) return options
    return [...(options || []), { value: selected, label: selected, searchValue: selected }]
  }

  const repackProductorOptions = ensureSelectedOption(
    toOptionsWithAll(inventoryFilterOptions?.productores, 'Todos'),
    inventoryDraftFilters?.productor,
  )

  const repackEmbalajeOptions = ensureSelectedOption(
    toOptionsWithAll(inventoryFilterOptions?.embalajes, 'Todos'),
    inventoryDraftFilters?.embalaje,
  )

  const repackEspecieOptions = ensureSelectedOption(
    toOptionsWithAll(inventoryFilterOptions?.especies, 'Todas'),
    selectedRepackEspecie,
  )

  const variedadesByEspecie = (inventoryFilterOptions?.variedadesByEspecie && typeof inventoryFilterOptions.variedadesByEspecie === 'object')
    ? inventoryFilterOptions.variedadesByEspecie
    : {}

  const variedadesBySelectedEspecie = selectedRepackEspecieKey && Array.isArray(variedadesByEspecie[selectedRepackEspecieKey])
    ? variedadesByEspecie[selectedRepackEspecieKey]
    : (Array.isArray(inventoryFilterOptions?.variedades) ? inventoryFilterOptions.variedades : [])

  const repackVariedadOptions = ensureSelectedOption(
    toOptionsWithAll(variedadesBySelectedEspecie, 'Todas'),
    inventoryDraftFilters?.variedad,
  )

  const setLot = (lotId, patch) => {
    setLots((prev) => prev.map((l) => (l.id === lotId ? { ...l, ...patch } : l)))
    setDirty(true)
  }

  const requestPackagingChange = (lot, nextPack) => {
    const currentC = lot?.c_embalaje ? String(lot.c_embalaje) : ''
    const currentN = lot?.n_embalaje ? String(lot.n_embalaje) : ''
    const nextC = nextPack?.c_embalaje ? String(nextPack.c_embalaje) : ''
    const nextN = nextPack?.n_embalaje ? String(nextPack.n_embalaje) : ''

    if (currentC === nextC && currentN === nextN) {
      setLot(lot.id, nextPack)
      return
    }

    setPackagingEdit({ lot, nextPack, reason: '' })
  }

  const addExtraPackagingRow = (lotId) => {
    if (isLocked) return
    setLots((prev) => {
      const next = prev.map((l) => {
        if (Number(l.id) !== Number(lotId)) return l
        const arr = Array.isArray(l.extra_packagings) ? [...l.extra_packagings] : []
        arr.push({ c_embalaje: null, n_embalaje: null, cp2_cajas_por_pallet: null, indications: '', destino: null })
        return { ...l, extra_packagings: arr }
      })
      setDirty(true)
      return next
    })
  }

  const removeExtraPackagingRow = (lotId, index) => {
    if (isLocked) return
    setLots((prev) => {
      const next = prev.map((l) => {
        if (Number(l.id) !== Number(lotId)) return l
        const arr = Array.isArray(l.extra_packagings) ? [...l.extra_packagings] : []
        arr.splice(Number(index), 1)
        return { ...l, extra_packagings: arr }
      })
      setDirty(true)
      return next
    })
  }

  const setExtraPackagingRow = (lotId, index, patch) => {
    if (isLocked) return
    setLots((prev) => {
      const next = prev.map((l) => {
        if (Number(l.id) !== Number(lotId)) return l
        const arr = Array.isArray(l.extra_packagings) ? [...l.extra_packagings] : []
        const cur = arr[Number(index)] || { c_embalaje: null, n_embalaje: null, cp2_cajas_por_pallet: null, indications: '', destino: null }
        arr[Number(index)] = { ...cur, ...(patch || {}) }
        return { ...l, extra_packagings: arr }
      })
      setDirty(true)
      return next
    })
  }

  const renumberLine = (lineId, nextLots) => {
    const lineLots = nextLots.filter((l) => l.packing_line_id === lineId).sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
    const map = new Map(lineLots.map((l, idx) => [l.id, idx + 1]))
    return nextLots.map((l) => (l.packing_line_id === lineId ? { ...l, orden: map.get(l.id) } : l))
  }

  const moveWithinLine = (lineId, lotId, dir) => {
    setLots((prev) => {
      const lineLots = prev.filter((l) => l.packing_line_id === lineId).sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
      const idx = lineLots.findIndex((l) => l.id === lotId)
      if (idx < 0) return prev
      const swapWith = dir === 'up' ? idx - 1 : idx + 1
      if (swapWith < 0 || swapWith >= lineLots.length) return prev
      const a = lineLots[idx]
      const b = lineLots[swapWith]

      const next = prev.map((l) => {
        if (l.id === a.id) return { ...l, orden: b.orden }
        if (l.id === b.id) return { ...l, orden: a.orden }
        return l
      })
      setDirty(true)
      return next
    })
  }

  const applyDragResult = (source, destination, draggableId) => {
    if (!destination) return

    const fromLineId = Number(source.droppableId)
    const toLineId = Number(destination.droppableId)
    const lotId = Number(String(draggableId).replace('lot:', ''))

    setLots((prev) => {
      const moving = prev.find((l) => l.id === lotId)
      if (!moving) return prev

      const sourceList = prev
        .filter((l) => Number(l.packing_line_id) === fromLineId)
        .sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
        .map((l) => l.id)

      const sourceIndex = sourceList.indexOf(lotId)
      if (sourceIndex === -1) return prev

      // Reorden dentro de la misma línea (corrige bug de duplicación de IDs).
      if (fromLineId === toLineId) {
        const nextOrder = sourceList.slice()
        nextOrder.splice(sourceIndex, 1)
        const insertIndex = Math.max(0, Math.min(destination.index, nextOrder.length))
        nextOrder.splice(insertIndex, 0, lotId)

        const next = prev.map((l) => {
          if (Number(l.packing_line_id) !== fromLineId) return l
          const idx = nextOrder.indexOf(l.id)
          if (idx === -1) return l
          return { ...l, orden: idx + 1 }
        })

        setDirty(true)
        return next
      }

      // Movimiento entre líneas.
      const targetList = prev
        .filter((l) => Number(l.packing_line_id) === toLineId)
        .sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
        .map((l) => l.id)

      const nextSource = sourceList.slice()
      nextSource.splice(sourceIndex, 1)

      const nextTarget = targetList.slice()
      const insertIndex = Math.max(0, Math.min(destination.index, nextTarget.length))
      nextTarget.splice(insertIndex, 0, lotId)

      const next = prev.map((l) => {
        if (l.id === lotId) {
          return { ...l, packing_line_id: toLineId, orden: nextTarget.indexOf(lotId) + 1 }
        }
        if (Number(l.packing_line_id) === fromLineId) {
          const idx = nextSource.indexOf(l.id)
          if (idx !== -1) return { ...l, orden: idx + 1 }
        }
        if (Number(l.packing_line_id) === toLineId) {
          const idx = nextTarget.indexOf(l.id)
          if (idx !== -1) return { ...l, orden: idx + 1 }
        }
        return l
      })

      setDirty(true)
      return next
    })
  }

  const onDragEnd = (result) => {
    const { destination, source, draggableId } = result || {}
    if (!destination) return
    if (
      destination.droppableId === source.droppableId
      && destination.index === source.index
    ) return
    if (isLocked) return

    const dragId = String(draggableId || '')

    // Drag desde inventario hacia una línea/cámara → crea el lote en backend
    if (source.droppableId === 'inv' && dragId.startsWith('inv:')) {
      const encoded = dragId.replace('inv:', '')
      let inventoryKey = encoded
      try {
        inventoryKey = decodeURIComponent(encoded)
      } catch (e) {
        inventoryKey = encoded
      }
      const toLineId = Number(destination.droppableId)
      if (!Number.isFinite(toLineId) || !inventoryKey) return
      addFromInventory(inventoryKey, toLineId)
      return
    }

    // Movimientos de lotes ya creados (entre líneas o reorden)
    if (dragId.startsWith('lot:')) {
      // No usamos "dropear" a inventario como acción (se mantiene botón Quitar).
      if (destination.droppableId === 'inv') return
      applyDragResult(source, destination, dragId)
    }
  }

  const removeLot = (lotId) => {
    if (isLocked) return
    setLots((prev) => {
      const lot = prev.find((l) => l.id === lotId)
      const next = prev.filter((l) => l.id !== lotId)
      setDirty(true)
      setRemovedIds((ids) => (ids.includes(lotId) ? ids : [...ids, lotId]))
      return lot ? renumberLine(lot.packing_line_id, next) : next
    })
  }

  const moveToLine = (lotId, nextLineId) => {
    setLots((prev) => {
      const lot = prev.find((l) => l.id === lotId)
      if (!lot) return prev
      const fromLine = lot.packing_line_id
      const maxOrden = Math.max(0, ...prev.filter((l) => l.packing_line_id === nextLineId).map((l) => l.orden || 0))
      let next = prev.map((l) => (l.id === lotId ? { ...l, packing_line_id: nextLineId, orden: maxOrden + 1 } : l))
      next = renumberLine(fromLine, next)
      next = renumberLine(nextLineId, next)
      setDirty(true)
      return next
    })
  }

  const buildSavePayload = () => {
    const payloadLots = []
    ;(lines || []).forEach((line) => {
      const lineLots = (lotsByLine[line.id] || []).slice().sort((a, b) => (a.orden ?? 0) - (b.orden ?? 0))
      lineLots.forEach((lot, idx) => {
        payloadLots.push({
          id: lot.id,
          packing_line_id: line.id,
          orden: idx + 1,
          destino: lot.destino ?? null,
          c_embalaje: lot.c_embalaje ?? null,
          n_embalaje: lot.n_embalaje ?? null,
          cp2_cajas_por_pallet: lot.cp2_cajas_por_pallet ?? null,
          packaging_change_reason: lot.packaging_change_reason ?? null,
          packaging_indications: lot.packaging_indications ?? null,
          extra_packagings: Array.isArray(lot.extra_packagings) ? lot.extra_packagings : [],
        })
      })
    })
    return payloadLots
  }

  const pedidosDirty = useMemo(() => {
    return String(pedidos || '') !== String(process?.pedidos || '')
  }, [pedidos, process?.pedidos])

  const save = async () => {
    if (saving || isLocked) return
    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }
    setSaving(true)
    try {
      await new Promise((resolve) => {
        router.patch(
          route('planning.processes.lots.update', process.id),
          {
            lots: buildSavePayload(),
            ...(removedIds.length > 0 ? { remove_ids: removedIds } : {}),
            pedidos: String(pedidos || '').trim() || null,
            ...(isConfirmed ? { change_reason: confirmedReason } : {}),
          },
          {
            preserveScroll: true,
            onFinish: () => resolve(),
          },
        )
      })
      setDirty(false)
      setRemovedIds([])
    } finally {
      setSaving(false)
    }
  }

  const recalcTimes = async () => {
    // Reusar guardado para recalcular tiempos.
    await save()
  }

  const generate = () => {
    if (isLocked) return
    if (['CONFIRMADO', 'CERRADO'].includes(status)) return
    if (!confirm('¿Generar propuesta automáticamente? Esto reemplazará la propuesta actual.')) return
    router.post(route('planning.processes.generate', process.id), {}, { preserveScroll: true })
  }

  const confirmProcess = async () => {
    if (isLocked) return
    if (['CONFIRMADO', 'CERRADO'].includes(status)) return
    if (missingDestinoLots.length > 0) {
      const preview = missingDestinoLots.slice(0, 8)
      const more = missingDestinoLots.length > 8 ? ` (+${missingDestinoLots.length - 8} más)` : ''
      alert(`Antes de confirmar debes seleccionar el destino por lote.\nLotes: ${preview.join(', ')}${more}`)
      return
    }
    if (dirty) {
      if (!confirm('Tienes cambios pendientes. ¿Guardar y luego confirmar?')) return
      await save()
    }
    if (pedidosDirty) {
      if (!confirm('Tienes cambios pendientes en “Pedidos”. ¿Guardar y luego confirmar?')) return
      await save()
    }
    const msg = splitByLot
      ? '¿Confirmar y separar en 1 proceso por lote? Se validará inventario, se reservarán los lotes y se generarán procesos individuales.'
      : '¿Confirmar esta planificación? Se validará inventario, se reservarán los lotes y todos los lotes quedarán en un solo proceso.'
    if (!confirm(msg)) return
    router.post(route('planning.processes.confirm', process.id), { split_by_lot: splitByLot }, { preserveScroll: true })
  }

  const deleteProcess = () => {
    if (isLocked) return
    if (!confirm(`¿Eliminar el proceso #${process?.id || ''}? Esta acción no se puede deshacer.`)) return
    router.delete(route('planning.processes.destroy', process.id), {
      data: { especie: String(process?.especie || '').trim() || null },
      preserveScroll: true,
    })
  }

  const [editingExtraHours, setEditingExtraHours] = useState(null) // { lineId, value }
  const openExtraHoursForLine = (lineId) => {
    if (isLocked) return
    const line = (lines || []).find((l) => Number(l.id) === Number(lineId))
    const current = Number(line?.extra_horas || 0)
    setEditingExtraHours({ lineId: Number(lineId), value: String(current) })
  }
  const saveExtraHoursForLine = () => {
    if (!editingExtraHours) return
    const lineId = Number(editingExtraHours.lineId)
    const raw = String(editingExtraHours.value ?? '').trim()
    const val = raw === '' ? 0 : Number(raw.replace(',', '.'))
    if (!Number.isFinite(val) || val < 0) {
      alert('Valor inválido.')
      return
    }
    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }
    router.patch(route('planning.processes.lots.update', process.id), {
      line_extra_hours: [{ packing_line_id: lineId, extra_horas: val }],
      ...(isConfirmed ? { change_reason: confirmedReason } : {}),
    }, {
      preserveScroll: true,
      onSuccess: () => setEditingExtraHours(null),
    })
  }

  const openEditLines = () => {
    if (isLocked) return
    setEditingLines({ ids: normalizedIncludedLineIds })
  }

  const saveIncludedLines = () => {
    if (!editingLines) return
    const ids = Array.from(new Set((editingLines.ids || []).map((v) => Number(v)).filter((n) => Number.isFinite(n) && n > 0)))
    if (ids.length === 0) {
      alert('Debes dejar al menos 1 línea/cámara.')
      return
    }
    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }
    router.patch(route('planning.processes.lots.update', process.id), {
      included_packing_line_ids: ids,
      ...(isConfirmed ? { change_reason: confirmedReason } : {}),
    }, {
      preserveScroll: true,
      onSuccess: () => setEditingLines(null),
    })
  }

  const applyInventoryFilters = (patch) => {
    // Solo cambia el borrador. La búsqueda se ejecuta con el botón “Buscar”.
    setInventoryDraftFilters((prev) => ({ ...(prev || {}), ...(patch || {}) }))
  }

  const runInventorySearch = () => {
    const next = { ...(inventoryDraftFilters || {}) }
    router.get(route('planning.processes.show', process.id), next, {
      preserveState: true,
      replace: true,
      only: ['inventory', 'inventoryFilters', 'inventoryFilterOptions'],
    })
  }

  const clearInventorySearch = () => {
    const cleared = isRepack
      ? { q: '', especie: repackDefaultEspecie, variedad: '', productor: '', embalaje: '' }
      : { q: '', variedad: '', nota_calidad: '', brix_min: '', brix_max: '' }
    setInventoryDraftFilters(cleared)
    router.get(route('planning.processes.show', process.id), cleared, {
      preserveState: true,
      replace: true,
      only: ['inventory', 'inventoryFilters', 'inventoryFilterOptions'],
    })
  }

  const addFromInventory = (inventoryKey, lineIdOverride = null) => {
    const lineId = lineIdOverride ?? targetLineId
    if (!lineId) return
    const key = String(inventoryKey || '').trim()
    if (!key) return
    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }
    const payload = isRepack
      ? {
        add_source_type: 'reembalaje',
        add_source_key: key,
        add_packing_line_id: lineId,
        ...(isConfirmed ? { change_reason: confirmedReason } : {}),
      }
      : {
        add_n_g_recepcion: key,
        add_packing_line_id: lineId,
        ...(isConfirmed ? { change_reason: confirmedReason } : {}),
      }

    router.patch(route('planning.processes.lots.update', process.id), payload, {
      preserveScroll: true,
      onSuccess: (page) => {
        // Si el backend redirige a un proceso nuevo (1 lote = 1 proceso), no hacemos sync local.
        if (page?.props?.process?.id && Number(page.props.process.id) !== Number(process.id)) return
        const nextLots = page?.props?.process?.lots
        if (Array.isArray(nextLots)) {
          setLots(nextLots)
          setDirty(false)
        }
      },
      onError: () => {},
    })
  }

  const moveFromOtherProcess = (lotId, fromProcessId, inventoryKey, lineIdOverride = null) => {
    const lineId = lineIdOverride ?? targetLineId
    if (!lineId) return
    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }
    router.patch(route('planning.processes.lots.update', process.id), {
      move_lot_id: lotId,
      move_from_process_id: fromProcessId,
      add_packing_line_id: lineId,
      ...(isConfirmed ? { change_reason: confirmedReason } : {}),
    }, {
      preserveScroll: true,
      onSuccess: (page) => {
        const nextLots = page?.props?.process?.lots
        if (Array.isArray(nextLots)) {
          setLots(nextLots)
          setDirty(false)
        }
        // Refrescar inventario para que el lote ahora aparezca como asignado a este proceso.
        runInventorySearch()
      },
    })
  }

  const applySplit = () => {
    if (!splitting?.lot) return
    const bins = Number(splitting.bins)
    const toLineId = Number(splitting.toLineId)
    if (!Number.isFinite(bins) || bins <= 0) return
    if (!Number.isFinite(toLineId)) return
    if (bins >= Number(splitting.lot.cantidad_bins || 0)) {
      alert('Los bins a dividir deben ser menores que el total del lote.')
      return
    }

    const confirmedReason = isConfirmed ? String(changeReason || '').trim() : null
    if (isConfirmed && !confirmedReason) {
      alert('Debes indicar el motivo del cambio para editar un proceso confirmado.')
      return
    }

    router.patch(
      route('planning.processes.lots.update', process.id),
      {
        split_id: splitting.lot.id,
        split_bins: bins,
        split_to_packing_line_id: toLineId,
        ...(isConfirmed ? { change_reason: confirmedReason } : {}),
      },
      {
        preserveScroll: true,
        onSuccess: (page) => {
          if (page?.props?.process?.id && Number(page.props.process.id) !== Number(process.id)) return
          const nextLots = page?.props?.process?.lots
          if (Array.isArray(nextLots)) {
            setLots(nextLots)
            setDirty(false)
          }
          setSplitting(null)
        },
      },
    )
  }

  const totalBinsByLine = (lineId) => (lotsByLine[lineId] || []).reduce((acc, l) => acc + (Number(l.cantidad_bins) || 0), 0)
  const totalKgsByLine = (lineId) => (lotsByLine[lineId] || []).reduce((acc, l) => acc + (Number(l.peso_neto) || 0), 0)

  return (
    <div className="min-h-screen w-full bg-slate-50 py-6 px-6 lg:px-8">
      <Card className="border-slate-200/70 shadow-sm mb-4">
        <CardHeader className="pb-3 bg-gradient-to-r from-white via-slate-50 to-indigo-50">
          <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <div className="text-xl font-bold">
                {isLineDay ? (
                  <>Línea {lineDay?.line?.nombre ? String(lineDay.line.nombre) : '-'}</>
                ) : (
                  <>Proceso #{process.id}</>
                )}
              </div>
              <div className="text-sm text-gray-600 flex flex-wrap items-center gap-2">
                {isLineDay ? (
                  <>
                    <span className="font-medium">{formatDate(lineDay?.date || process.fecha)}</span>
                    <span>·</span>
                    <span>
                      {lineDay?.shift?.codigo ? String(lineDay.shift.codigo) : (process.shift?.codigo || '-')}
                      {lineDay?.shift?.nombre ? ` · ${String(lineDay.shift.nombre)}` : (process.shift?.nombre ? ` · ${process.shift.nombre}` : '')}
                    </span>
                    <span>·</span>
                    <Badge variant="outline" className="bg-white text-gray-700">
                      {Array.isArray(lineDay?.process_ids) ? lineDay.process_ids.length : 0} proceso(s)
                    </Badge>
                    <Badge variant="outline" className="bg-white text-gray-500">Vista por línea</Badge>
                  </>
                ) : (
                  <>
                    <Badge variant="secondary" className="bg-white/70 text-slate-800 border border-slate-200">
                      {process.especie}
                    </Badge>
                    <Badge variant="outline" className={isRepack ? 'bg-orange-50 text-orange-800 border-orange-200' : 'bg-white text-gray-700'}>
                      {isRepack ? 'Reembalaje' : 'Normal'}
                    </Badge>
                    <span>·</span>
                    <span>{formatDate(process.fecha)}</span>
                    <span>·</span>
                    <span>
                      {process.shift?.codigo} {process.shift?.nombre ? `· ${process.shift.nombre}` : ''} ({process.shift?.horas}h)
                    </span>
                    {hasAnyLineExtra ? (
                      <Badge variant="outline" className="bg-white">Horas extra: por línea</Badge>
                    ) : null}
                    <span>·</span>
                    <StatusPill status={process.estado} />
                    {allowSplit ? (
                      <Badge variant="outline" className="bg-white">Dividir: ON</Badge>
                    ) : (
                      <Badge variant="outline" className="bg-white text-gray-500">Dividir: OFF</Badge>
                    )}
                  </>
                )}
              </div>
            </div>
          </div>
        </CardHeader>
        <CardContent className="pt-0">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <div className="flex flex-wrap items-center gap-2">
          <Link href={route('planning.processes.index')}>
            <Button variant="outline">Volver</Button>
          </Link>
          {!isLineDay ? (
            <Button variant="destructive" onClick={deleteProcess} disabled={isLocked || saving}>
              Eliminar proceso
            </Button>
          ) : null}
            </div>

            <div className="flex flex-wrap items-center gap-2">
          {!isLineDay ? (
            <>
              <Button variant="outline" onClick={openEditLines} disabled={isLocked} title="Elegir líneas/cámaras incluidas en este proceso">
                Líneas
              </Button>
              <Popover>
                <PopoverTrigger asChild>
                  <Button variant="outline" title="Filtra sugerencias de embalaje según destino (matriz)">
                    Destinos
                    {selectedDestinos.length ? (
                      <Badge variant="outline" className="ml-2">{selectedDestinos.length}</Badge>
                    ) : (
                      <span className="ml-2 text-xs text-gray-500">(todos)</span>
                    )}
                  </Button>
                </PopoverTrigger>
                <PopoverContent className="w-80" align="end">
                  <div className="font-semibold">Destinos para sugerir embalaje</div>
                  <div className="text-xs text-gray-600 mt-1">
                    Como el destino no siempre está en inventario, selecciona los destinos de la matriz. Esto mejora las sugerencias al asignar embalaje.
                  </div>

                  <div className="mt-3 flex flex-wrap gap-2">
                    {(destinosAvailable || []).map((d) => {
                      const checked = selectedDestinos.includes(String(d))
                      return (
                        <button
                          key={String(d)}
                          type="button"
                          onClick={() => toggleDestino(d)}
                          className={`px-2 py-1 rounded-full border text-xs font-semibold ${checked ? 'bg-green-50 text-green-800 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200'}`}
                          aria-pressed={checked}
                          title={checked ? 'Incluido' : 'No incluido'}
                        >
                          {d}
                        </button>
                      )
                    })}
                    {(!destinosAvailable || destinosAvailable.length === 0) ? (
                      <div className="text-xs text-gray-500">No hay destinos definidos en la matriz.</div>
                    ) : null}
                  </div>

                  <div className="mt-4 flex items-center justify-end gap-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => setSelectedDestinos([])}>
                      Limpiar
                    </Button>
                    <Button type="button" size="sm" onClick={() => setSelectedDestinos(destinosAvailable || [])}>
                      Todos
                    </Button>
                  </div>
                </PopoverContent>
              </Popover>
              <Button onClick={generate} disabled={isLocked || ['CONFIRMADO', 'CERRADO'].includes(status)} className="min-w-28">
                <Wand2 className="h-4 w-4 mr-2" />
                Generar
              </Button>
              <Button variant="secondary" onClick={recalcTimes} disabled={isLocked || saving} className="min-w-28" title="Recalcula los horarios estimados">
                <RefreshCw className={`h-4 w-4 mr-2 ${saving ? 'animate-spin' : ''}`} />
                Recalcular
              </Button>
              <label className="flex items-center gap-1.5 text-xs text-gray-600 select-none" title="Si está activado, al confirmar se creará 1 proceso por cada lote. Si está desactivado, todos los lotes quedan en un solo proceso.">
                <input
                  type="checkbox"
                  className="h-3.5 w-3.5 rounded border-gray-300"
                  checked={splitByLot}
                  onChange={(e) => setSplitByLot(e.target.checked)}
                  disabled={isLocked || ['CONFIRMADO', 'CERRADO'].includes(status)}
                />
                Separar por lote
              </label>
              <Button
                variant="destructive"
                onClick={confirmProcess}
                disabled={isLocked || ['CONFIRMADO', 'CERRADO'].includes(status) || missingDestinoLots.length > 0}
                className={status === 'CONFLICTO' ? 'bg-amber-600 hover:bg-amber-700' : ''}
                title={missingDestinoLots.length > 0 ? 'Falta seleccionar destino por lote' : status === 'CONFLICTO' ? 'Re-intentar confirmar para resolver conflictos' : 'Confirmar'}
              >
                {status === 'CONFLICTO' ? 'Re-confirmar' : 'Confirmar'}
              </Button>
              {(() => {
                const lineId = lines?.[0]?.id
                const htmlUrl = `${route('planning.processes.instruction', process.id)}${lineId ? `?line_id=${lineId}` : ''}`
                const pdfUrl = `${route('planning.processes.instruction', process.id)}?format=pdf${lineId ? `&line_id=${lineId}` : ''}`
                return (
                  <>
                    <a href={htmlUrl}>
                      <Button variant="outline" className="min-w-28" disabled={status !== 'CONFIRMADO'}>
                        <Printer className="h-4 w-4 mr-2" />
                        Imprimir
                      </Button>
                    </a>
                    <a href={`${pdfUrl}&download=1`}>
                      <Button variant="secondary" className="min-w-28" disabled={status !== 'CONFIRMADO'} title="Descargar PDF">
                        Descargar PDF
                      </Button>
                    </a>
                  </>
                )
              })()}
            </>
          ) : null}
        </div>
          </div>
        </CardContent>
      </Card>

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
      {props?.flash?.warning && (
        <div className="mb-3 rounded border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2 text-sm">
          {props.flash.warning}
        </div>
      )}

      {status === 'CONFLICTO' && !isLineDay ? (
        <Alert className="mb-4 border-red-200 bg-red-50 text-red-900">
          <AlertTitle>Proceso en Conflicto</AlertTitle>
          <AlertDescription>
            Este proceso tiene lotes con conflicto de inventario (bins insuficientes o reserva duplicada).
            Los lotes en conflicto están marcados en rojo. Puedes re-intentar "Confirmar" para resolver los conflictos, o ajustar la planificación.
          </AlertDescription>
        </Alert>
      ) : null}

      {isConfirmed && !isLineDay ? (
        <Alert className="mb-4 border-amber-200 bg-amber-50 text-amber-900">
          <AlertTitle>Edición de proceso confirmado</AlertTitle>
          <AlertDescription>
            Para guardar cambios debes indicar un motivo. Al guardar se generará una nueva versión del instructivo.
            <div className="mt-3">
              <Label className="text-amber-900">Motivo del cambio (obligatorio)</Label>
              <Textarea
                value={changeReason}
                onChange={(e) => setChangeReason(e.target.value)}
                placeholder="Ej: ajuste por disponibilidad / cambio de destino / corrección de horas..."
                className="mt-1 bg-white"
                rows={2}
              />
            </div>
          </AlertDescription>
        </Alert>
      ) : null}

      {!isLineDay ? (
        <Card className="border-slate-200/70 shadow-sm mb-4">
          <CardHeader className="pb-3 bg-white border-b">
            <CardTitle className="text-base font-bold">Datos del proceso</CardTitle>
            <div className="text-sm text-gray-600">
              Exportadora: <span className="font-semibold">{process?.exportadora ? String(process.exportadora) : '-'}</span>
            </div>
          </CardHeader>
          <CardContent className="pt-4">
            <div>
              <Label>Pedidos (texto libre)</Label>
              <Textarea
                value={pedidos}
                onChange={(e) => setPedidos(e.target.value)}
                placeholder="Ej: pedido particular, cliente, prioridad, observación operativa..."
                disabled={isLocked}
              />
              <div className="mt-2 flex items-center justify-end">
                <Button
                  variant="outline"
                  onClick={() => {
                    if (!pedidosDirty) return
                    save()
                  }}
                  disabled={isLocked || saving || !pedidosDirty}
                >
                  Guardar pedidos
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      ) : null}

      {missingDestinoLots.length > 0 && !isLocked ? (
        <div className="mb-3 rounded border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2 text-sm">
          Falta seleccionar <span className="font-semibold">Destino</span> en {missingDestinoLots.length} lote(s). Selecciónalo en la línea antes de confirmar.
        </div>
      ) : null}

      {(dirty || pedidosDirty) && !isLocked ? (
        <Alert className="mb-4">
          <AlertTitle>Cambios pendientes</AlertTitle>
          <AlertDescription className="flex items-center justify-between gap-3">
            <span>Hay cambios pendientes. Presiona “Guardar cambios” para aplicar y recalcular tiempos.</span>
            <Button onClick={save} disabled={saving}>
              {saving ? 'Guardando...' : 'Guardar cambios'}
            </Button>
          </AlertDescription>
        </Alert>
      ) : null}

      <DragDropContext onDragEnd={onDragEnd}>
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
        {/* Inventario */}
        {!isLineDay ? (
          <Card className="lg:col-span-4 border-slate-200/70 shadow-sm">
          <CardHeader className="pb-2 bg-gradient-to-r from-white via-slate-50 to-white border-b">
            <CardTitle className="text-lg font-bold">Inventario disponible</CardTitle>
            <div className="text-sm text-gray-600">
              {isRepack ? 'Stock para reembalaje por folio (especie/variedad).' : 'Busca y agrega manualmente si lo necesitas.'}
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 gap-3 mb-3">
              <div>
                <Label>{isRepack ? 'Buscar (folio / n° proceso / lote)' : 'Buscar (n° recepción)'}</Label>
                <Input
                  value={inventoryDraftFilters.q || ''}
                  onChange={(e) => applyInventoryFilters({ q: e.target.value })}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault()
                      runInventorySearch()
                    }
                  }}
                  placeholder="Ej: 123456"
                />
              </div>

              {isRepack ? (
                <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                  <div>
                    <Label>Productor</Label>
                    <Combobox
                      value={inventoryDraftFilters.productor || ''}
                      onChange={(value) => applyInventoryFilters({ productor: value })}
                      options={repackProductorOptions}
                      placeholder="Todos"
                      searchPlaceholder="Buscar productor..."
                      emptyMessage="Sin productores"
                      className="w-full"
                    />
                  </div>
                  <div>
                    <Label>Embalaje</Label>
                    <Combobox
                      value={inventoryDraftFilters.embalaje || ''}
                      onChange={(value) => applyInventoryFilters({ embalaje: value })}
                      options={repackEmbalajeOptions}
                      placeholder="Todos"
                      searchPlaceholder="Buscar embalaje..."
                      emptyMessage="Sin embalajes"
                      className="w-full"
                    />
                  </div>
                  <div>
                    <Label>Especie</Label>
                    <Combobox
                      value={inventoryDraftFilters.especie || ''}
                      onChange={(value) => applyInventoryFilters({ especie: value, variedad: '' })}
                      options={repackEspecieOptions}
                      placeholder="Todas"
                      searchPlaceholder="Buscar especie..."
                      emptyMessage="Sin especies"
                      className="w-full"
                    />
                  </div>
                  <div>
                    <Label>Variedad</Label>
                    <Combobox
                      value={inventoryDraftFilters.variedad || ''}
                      onChange={(value) => applyInventoryFilters({ variedad: value })}
                      options={repackVariedadOptions}
                      placeholder="Todas"
                      searchPlaceholder="Buscar variedad..."
                      emptyMessage="Sin variedades"
                      className="w-full"
                    />
                  </div>
                </div>
              ) : (
                <div>
                  <Label>Variedad</Label>
                  <Input
                    value={inventoryDraftFilters.variedad || ''}
                    onChange={(e) => applyInventoryFilters({ variedad: e.target.value })}
                    onKeyDown={(e) => {
                      if (e.key === 'Enter') {
                        e.preventDefault()
                        runInventorySearch()
                      }
                    }}
                    placeholder="Ej: SANTINA"
                  />
                </div>
              )}

              <div className="grid grid-cols-2 gap-2">
                {!isRepack ? (
                  <div>
                    <Label>Nota Calidad</Label>
                    <Input
                      value={inventoryDraftFilters.nota_calidad || ''}
                      onChange={(e) => applyInventoryFilters({ nota_calidad: e.target.value })}
                      onKeyDown={(e) => {
                        if (e.key === 'Enter') {
                          e.preventDefault()
                          runInventorySearch()
                        }
                      }}
                      placeholder="Ej: 2 o S/N"
                    />
                  </div>
                ) : <div />}
                <div className="text-sm">
                  <Label>Agregar a</Label>
                  <select
                    className="mt-1 w-full rounded border px-2 py-2 text-sm"
                    value={targetLineId ?? ''}
                    onChange={(e) => setTargetLineId(Number(e.target.value))}
                  >
                    {(lines || []).map((l) => (
                      <option key={l.id} value={l.id}>{l.nombre}</option>
                    ))}
                  </select>
                </div>
              </div>

              {!isRepack ? (
                <details className="rounded border bg-gray-50 px-3 py-2">
                  <summary className="cursor-pointer select-none text-sm font-medium text-gray-800">
                    Filtro opcional: Brix
                  </summary>
                  <div className="grid grid-cols-2 gap-2 mt-2">
                    <div>
                      <Label>Brix min</Label>
                      <Input
                        value={inventoryDraftFilters.brix_min || ''}
                        onChange={(e) => applyInventoryFilters({ brix_min: e.target.value })}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') {
                            e.preventDefault()
                            runInventorySearch()
                          }
                        }}
                        placeholder="Ej: 16"
                      />
                    </div>
                    <div>
                      <Label>Brix max</Label>
                      <Input
                        value={inventoryDraftFilters.brix_max || ''}
                        onChange={(e) => applyInventoryFilters({ brix_max: e.target.value })}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter') {
                            e.preventDefault()
                            runInventorySearch()
                          }
                        }}
                        placeholder="Ej: 22"
                      />
                    </div>
                  </div>
                </details>
              ) : null}

              <div className="flex items-center justify-end gap-2">
                <Button type="button" variant="outline" onClick={clearInventorySearch}>
                  Limpiar
                </Button>
                <Button type="button" onClick={runInventorySearch}>
                  Buscar
                </Button>
              </div>
            </div>

            <Droppable droppableId="inv" isDropDisabled>
              {(invProvided) => (
                <div
                  ref={invProvided.innerRef}
                  {...invProvided.droppableProps}
                  className="space-y-2 max-h-[65vh] overflow-y-auto pr-1"
                >
                  {visibleInventory.map((item, index) => {
                    const inventoryKey = String(item?.inventory_key || item?.source_key || item?.n_g_recepcion || '')
                    const draggableInvId = `inv:${encodeURIComponent(inventoryKey)}`
                    return (
                    <Draggable
                      key={draggableInvId}
                      draggableId={draggableInvId}
                      index={index}
                      isDragDisabled={isLocked}
                    >
                      {(draggableProvided, snapshot) => (
                        <div
                          ref={draggableProvided.innerRef}
                          {...draggableProvided.draggableProps}
                          className={`rounded border p-3 bg-white ${snapshot.isDragging ? 'shadow-lg' : ''}`}
                        >
                          <div className="mb-2 flex flex-wrap items-center gap-2">
                            <div className="text-xs font-semibold text-gray-800">
                              {String(item.n_productor || item.productor || '').trim() || 'Sin productor'}
                              {item.csg_productor ? <span className="text-gray-500"> · CSG {item.csg_productor}</span> : null}
                              {item.sdp_centrocosto ? <span className="text-gray-500"> · SDP {item.sdp_centrocosto}</span> : null}
                              {item.n_exportadora ? <span className="text-gray-500"> · Exporta: {item.n_exportadora}</span> : null}
                              {crossProcessOwnership.has(inventoryKey) ? (
                                <span className="inline-flex items-center gap-1 rounded-full bg-orange-50 px-2 py-0.5 text-[11px] font-semibold text-orange-800 border border-orange-200">
                                  <ArrowLeftRight className="h-3 w-3" />
                                  En Proceso N° {crossProcessOwnership.get(inventoryKey).process_id}
                                </span>
                              ) : null}
                            </div>
                            {badges?.inventory?.[item.n_g_recepcion]?.mexico ? (
                              <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800" title="México">
                                <MexicoFlagIcon className="h-4 w-6" />
                                México
                              </span>
                            ) : null}
                            {badges?.inventory?.[item.n_g_recepcion]?.mosca ? (
                              <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800" title="Radio Mosca">
                                <Bug className="h-3.5 w-3.5" />
                                Mosca
                              </span>
                            ) : null}
                          </div>
                          <div className="flex items-start justify-between gap-2">
                            <div className="flex items-start gap-2 min-w-0">
                              <div
                                {...draggableProvided.dragHandleProps}
                                className={`mt-1 rounded border px-1 py-2 bg-gray-50 text-gray-600 ${isLocked ? 'opacity-40' : 'cursor-grab active:cursor-grabbing'}`}
                                title={isLocked ? 'Proceso bloqueado' : 'Arrastrar a una línea'}
                                aria-label="Arrastrar lote desde inventario"
                              >
                                <GripVertical className="h-4 w-4" />
                              </div>

                              <div className="min-w-0">
                                <div className="font-semibold">{isRepack ? (item.folio || inventoryKey) : item.n_g_recepcion}</div>
                                <div className="text-xs text-gray-600">
                                  {isRepack ? (
                                    <>
                                      {item.variedad || '-'} · Destino {item.destino || '-'} · N° Proceso {item.n_g_proceso || '-'} · N° Lote {item.n_g_recepcion || item.loter_unitec || '-'}
                                    </>
                                  ) : (
                                    <>
                                      {item.variedad || '-'} · NC {item.setup_nota_calidad ?? '-'} · Cal {item.setup_calibre ?? '-'} · Color {item.setup_color ?? '-'} · Brix {item.brix ?? '-'}
                                    </>
                                  )}
                                </div>
                                {!isRepack && item.exportable_percentage !== null && item.exportable_percentage !== undefined ? (
                                  <div className="mt-1 text-xs">
                                    <span className="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 font-semibold text-emerald-800">
                                      % Exportación: {Number(item.exportable_percentage).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%
                                    </span>
                                  </div>
                                ) : null}
                                {!isRepack && (Array.isArray(item.defectos_calidad) && item.defectos_calidad.length > 0) ? (
                                  <div className="mt-1 text-xs text-gray-700">
                                    <span className="font-semibold">Defectos de calidad:</span>{' '}
                                    {item.defectos_calidad.map((d) => `${String(d.detalle_item || '-')}: ${Number(d.porcentaje_muestra || 0).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`).join(' · ')}
                                  </div>
                                ) : null}
                                {!isRepack && (Array.isArray(item.defectos_condicion) && item.defectos_condicion.length > 0) ? (
                                  <div className="mt-1 text-xs text-gray-700">
                                    <span className="font-semibold">Defectos de condición:</span>{' '}
                                    {item.defectos_condicion.map((d) => `${String(d.detalle_item || '-')}: ${Number(d.porcentaje_muestra || 0).toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })}%`).join(' · ')}
                                  </div>
                                ) : null}
                                <div className="text-xs text-gray-500 mt-1">
                                  {item.antiguedad !== null ? `Antigüedad: ${item.antiguedad}` : ''} {item.fecha_recepcion ? `· Recepción: ${item.fecha_recepcion}` : ''} {isRepack && item.n_embalaje ? `· Embalaje: ${item.n_embalaje}` : ''}
                                </div>
                                {!isRepack ? (
                                  <SizeCurveValues
                                    payload={sizeCurves?.[String(item.n_g_recepcion || '').trim()]}
                                    loading={Boolean(sizeCurvesLoading?.[String(item.n_g_recepcion || '').trim()])}
                                    onLoad={() => fetchSizeCurves([item.n_g_recepcion], { refresh: true })}
                                  />
                                ) : null}
                              </div>
                            </div>

                            <div className="text-right shrink-0">
                              <div className="text-sm font-semibold">{Number(item.cantidad_bins || 0).toLocaleString('es-CL')} bins</div>
                              <div className="text-xs text-gray-500">{item.peso_neto ? `${Number(item.peso_neto).toLocaleString('es-CL')} kg` : ''}</div>
                            </div>
                          </div>

                          {item.quality_warning ? (
                            <div className="mt-2 text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1">
                              Sin datos de calidad asociados (se planifica igual, pero revisa).
                            </div>
                          ) : null}

                          <div className="mt-2 flex items-center justify-between">
                            <div className="text-xs text-gray-500">
                              Tip: arrastra a la línea/cámara para agregar.
                            </div>
                            {crossProcessOwnership.has(inventoryKey) ? (
                              <Button
                                size="sm"
                                variant="outline"
                                className="border-orange-300 text-orange-700 hover:bg-orange-50"
                                onClick={() => {
                                  const ap = crossProcessOwnership.get(inventoryKey)
                                  moveFromOtherProcess(ap.lot_id, ap.process_id, inventoryKey)
                                }}
                                disabled={isLocked}
                                title="Mover desde otro proceso a este"
                              >
                                <ArrowLeftRight className="mr-1 h-3.5 w-3.5" />
                                Mover del Proc. N° {crossProcessOwnership.get(inventoryKey).process_id}
                              </Button>
                            ) : (
                              <Button
                                size="sm"
                                onClick={() => addFromInventory(inventoryKey)}
                                disabled={isLocked}
                                title="Agregar al programa"
                              >
                                Agregar
                              </Button>
                            )}
                          </div>
                        </div>
                      )}
                    </Draggable>
                    )
                  })}
                  {invProvided.placeholder}

                  {(visibleInventory || []).length === 0 ? (
                    <div className="rounded border bg-gray-50 p-4 text-sm text-gray-600">
                      Sin resultados con los filtros actuales (o todos ya están asignados).
                    </div>
                  ) : null}
                </div>
              )}
            </Droppable>
          </CardContent>
        </Card>
        ) : null}

        {/* Programa por línea */}
        <div className={`${isLineDay ? 'lg:col-span-12' : 'lg:col-span-8'} space-y-4`}>
          {(lines || []).map((line) => {
            const lineLots = lotsByLine[line.id] || []
            const used = totalBinsByLine(line.id)
            const usedKgs = totalKgsByLine(line.id)
            const cap = line.capacidad_bins_turno
            const capText = cap ? `${used}/${cap} bins` : `${used} bins`
            const capColor = cap && used > cap ? 'text-red-700' : 'text-gray-700'
            const typeKey = String(line.tipo || '').toUpperCase()
            const baseAccent = typeKey.includes('AUTO') ? 'border-l-sky-500' : 'border-l-violet-500'
            const accent = cap && used > cap ? 'border-l-red-500' : baseAccent
            const ratio = cap && cap > 0 ? Math.min(100, Math.max(0, (used / cap) * 100)) : null
            const barColor = cap && used > cap ? 'bg-red-500' : (ratio !== null && ratio >= 90 ? 'bg-amber-500' : 'bg-emerald-500')

            return (
              <Card key={line.id} className={`border-slate-200/70 shadow-sm border-l-4 ${accent}`}>
                <CardHeader className="pb-2 bg-gradient-to-r from-white via-slate-50 to-white border-b">
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <CardTitle className="text-lg font-bold">{line.nombre}</CardTitle>
                      <div className="text-sm text-gray-600">
                        <span className="font-medium text-slate-700">{line.tipo}</span> · {line.bins_por_hora ? `${line.bins_por_hora} bins/h` : 'Sin capacidad'} ·{' '}
                        {Number(line.extra_horas || 0) > 0 ? (
                          <span className="text-gray-600">{`+${Number(line.extra_horas).toLocaleString('es-CL')}h extra · `}</span>
                        ) : null}
                        <span className={capColor}>{capText}</span>
                        <span className="text-gray-500">
                          {usedKgs > 0 ? ` · ${Number(usedKgs).toLocaleString('es-CL')} kg` : ''}
                        </span>
                      </div>
                      {ratio !== null ? (
                        <div className="mt-2">
                          <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                            <div className={`h-full ${barColor}`} style={{ width: `${ratio}%` }} />
                          </div>
                          <div className="mt-1 text-[11px] text-gray-500">
                            Uso de capacidad: {Math.round(ratio)}%
                          </div>
                        </div>
                      ) : null}
                      <div className="text-xs text-gray-500 mt-1">
                        Arrastra y suelta para ordenar (o mover entre líneas).
                      </div>
                      {cap && used > cap ? (
                        <div className="text-xs text-red-700 mt-1 flex items-center gap-2">
                          <span>Capacidad excedida: quita, divide o agrega horas extra.</span>
                          <button
                            type="button"
                            className="underline"
                            onClick={() => openExtraHoursForLine(line.id)}
                            disabled={isLocked}
                          >
                            Agregar horas
                          </button>
                        </div>
                      ) : null}
                    </div>
                    <div className="text-sm text-gray-500">
                      {lineLots.length} lotes
                      <button
                        type="button"
                        className="ml-3 underline"
                        onClick={() => openExtraHoursForLine(line.id)}
                        disabled={isLocked}
                        title="Horas extra por línea/cámara"
                      >
                        Horas extra
                      </button>

                      {(destinosAvailable || []).length > 0 ? (
                        <div className="mt-2">
                          <div className="text-[11px] text-gray-500">Destino rápido (solo vacíos)</div>
                          <select
                            className="mt-1 w-full max-w-[220px] rounded border px-2 py-1 text-xs"
                            defaultValue=""
                            disabled={isLocked}
                            onChange={(e) => {
                              const v = String(e.target.value || '').trim()
                              if (!v) return
                              setLots((prev) => prev.map((l) => {
                                if (Number(l.packing_line_id) !== Number(line.id)) return l
                                if (String(l?.destino || '').trim() !== '') return l
                                return { ...l, destino: v }
                              }))
                              setDirty(true)
                              e.target.value = ''
                            }}
                            title="Asigna destino a lotes sin destino en esta línea"
                          >
                            <option value="">Asignar destino…</option>
                            {(destinosAvailable || []).map((d) => (
                              <option key={String(d)} value={String(d)}>{String(d)}</option>
                            ))}
                          </select>
                        </div>
                      ) : null}
                    </div>
                  </div>
                </CardHeader>
                  <CardContent>
                    <Droppable droppableId={String(line.id)}>
                      {(droppableProvided) => (
                        <div
                          ref={droppableProvided.innerRef}
                          {...droppableProvided.droppableProps}
                          className="space-y-2"
                        >
                          {lineLots.map((lot, index) => (
                            <Draggable key={lot.id} draggableId={`lot:${lot.id}`} index={index}>
                              {(draggableProvided, snapshot) => (
                                (() => {
                                  const lotStatus = String(lot.estado?.value ?? lot.estado)
                                  const isConflict = lotStatus === 'CONFLICTO'
                                  const missingDestino = !isLocked && String(lot?.destino || '').trim() === ''
                                  const lotAccent = isConflict ? 'border-l-red-500' : (missingDestino ? 'border-l-amber-500' : 'border-l-slate-200')
                                  const lotBg = isConflict ? 'bg-red-50' : (missingDestino ? 'bg-amber-50/30' : 'bg-white')
                                  return (
                                <div
                                  ref={draggableProvided.innerRef}
                                  {...draggableProvided.draggableProps}
                                  className={`rounded border border-slate-200 p-3 border-l-4 ${lotAccent} ${lotBg} ${snapshot.isDragging ? 'shadow-lg' : ''}`}
                                >
                                  <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex items-start gap-2">
                                      <div
                                        {...draggableProvided.dragHandleProps}
                                        className={`mt-1 rounded border px-1 py-2 bg-gray-50 text-gray-600 ${isLocked ? 'opacity-40' : 'cursor-grab active:cursor-grabbing'}`}
                                        title={isLocked ? 'Proceso bloqueado' : 'Arrastrar'}
                                        aria-label="Arrastrar para ordenar"
                                      >
                                        <GripVertical className="h-4 w-4" />
                                      </div>

                                      <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                          <div className="font-semibold">
                                            {String(lot?.source_type || '').toLowerCase() === 'reembalaje'
                                              ? `Folio ${String(lot?.source_key || lot?.n_g_recepcion || '-')}`
                                              : lot.n_g_recepcion}
                                          </div>
                                          {(lot.split_index ?? 1) > 1 ? (
                                            <Badge variant="outline">Parte {lot.split_index}</Badge>
                                          ) : null}
                                          <Badge variant="outline" className="text-gray-600">#{lot.orden}</Badge>
                                          <Badge
                                            variant="outline"
                                            className={String(lot.estado?.value ?? lot.estado) === 'CONFLICTO' ? 'border-red-300 text-red-800' : 'text-gray-600'}
                                          >
                                            {String(lot.estado?.value ?? lot.estado)}
                                          </Badge>
                                          {String(lot?.destino || '').trim() ? (
                                            <span className="inline-flex items-center rounded-full bg-sky-50 px-2 py-0.5 text-[11px] font-semibold text-sky-800 border border-sky-200">
                                              {String(lot.destino)}
                                            </span>
                                          ) : !isLocked ? (
                                            <span className="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[11px] font-semibold text-red-800 border border-red-200">
                                              Sin destino
                                            </span>
                                          ) : null}
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-2">
                                          <div className="text-xs font-semibold text-gray-800">
                                            {String(lot.n_productor || '').trim() || 'Sin productor'}
                                            {lot.csg_productor ? <span className="text-gray-500"> · CSG {lot.csg_productor}</span> : null}
                                            {lot.sdp_centrocosto ? <span className="text-gray-500"> · SDP {lot.sdp_centrocosto}</span> : null}
                                            {lot.exportadora ? <span className="text-gray-500"> · Exp: {lot.exportadora}</span> : null}
                                          </div>
                                          {badges?.lots?.[String(lot.id)]?.mexico ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-800" title="México">
                                              <MexicoFlagIcon className="h-4 w-6" />
                                              México
                                            </span>
                                          ) : null}
                                          {badges?.lots?.[String(lot.id)]?.mosca ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-800" title="Radio Mosca">
                                              <Bug className="h-3.5 w-3.5" />
                                              Mosca
                                            </span>
                                          ) : null}
                                        </div>
                                        <div className="text-xs text-gray-600 mt-1">
                                          {lot.n_variedad || '-'} · NC {lot.setup_nota_calidad ?? '-'} · Cal {lot.setup_calibre ?? '-'} · Color {lot.setup_color ?? '-'} · Brix {lot.brix ?? '-'}
                                        </div>
                                        {String(lot?.source_type || '').toLowerCase() === 'reembalaje' ? (
                                          <div className="text-xs text-gray-600 mt-1">
                                            Origen · N° Proceso {lot.source_n_g_proceso || '-'} · N° Lote {lot.source_lote || lot.n_g_recepcion || '-'} · Embalaje {lot.source_n_embalaje || lot.n_embalaje || '-'}
                                          </div>
                                        ) : null}
                                        <div className="text-xs text-gray-500 mt-1">
                                          {Number(lot.cantidad_bins || 0).toLocaleString('es-CL')} bins
                                          {lot.peso_neto ? ` · ${Number(lot.peso_neto).toLocaleString('es-CL')} kg` : ''}
                                          {lot.inicio_estimado ? ` · ${formatTime(lot.inicio_estimado)}-${formatTime(lot.fin_estimado)}` : ''}
                                        </div>
                                      </div>
                                    </div>

                                    <div className="flex flex-col items-end gap-2">
                                      <div className="flex items-center gap-1">
                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="icon"
                                          onClick={() => moveWithinLine(line.id, lot.id, 'up')}
                                          disabled={isLocked}
                                          aria-label="Subir"
                                          title="Subir"
                                        >
                                          <ArrowUp className="h-4 w-4" />
                                        </Button>
                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="icon"
                                          onClick={() => moveWithinLine(line.id, lot.id, 'down')}
                                          disabled={isLocked}
                                          aria-label="Bajar"
                                          title="Bajar"
                                        >
                                          <ArrowDown className="h-4 w-4" />
                                        </Button>
                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="icon"
                                          onClick={() => removeLot(lot.id)}
                                          disabled={isLocked}
                                          aria-label="Quitar"
                                          title="Quitar"
                                        >
                                          <Trash2 className="h-4 w-4" />
                                        </Button>
                                        <Button
                                          type="button"
                                          variant="outline"
                                          size="sm"
                                          onClick={() => {
                                            const total = Number(lot.cantidad_bins || 0) || 0
                                            const defaultBins = Math.max(1, Math.floor(total / 2))
                                            const preferred =
                                              eligibleLinesForSplit.find((l) => l.id !== lot.packing_line_id)?.id
                                              ?? lot.packing_line_id
                                              ?? eligibleLinesForSplit?.[0]?.id
                                              ?? line.id
                                            setSplitting({ lot, bins: defaultBins, toLineId: preferred })
                                          }}
                                          disabled={isLocked || Number(lot.cantidad_bins || 0) <= 1}
                                          title="Parte el lote para asignar una parte a otra línea/cámara"
                                        >
                                          Dividir
                                        </Button>
                                      </div>

                                      <div className="flex items-center gap-2">
                                        <select
                                          className="rounded border px-2 py-1 text-xs"
                                          value={lot.packing_line_id}
                                          onChange={(e) => moveToLine(lot.id, Number(e.target.value))}
                                          disabled={isLocked}
                                          title="Mover a otra línea/cámara"
                                        >
                                          {(lines || []).map((l) => (
                                            <option key={l.id} value={l.id}>{l.nombre}</option>
                                          ))}
                                        </select>

                                        <select
                                          className={`rounded border px-2 py-1 text-xs ${!isLocked && String(lot?.destino || '').trim() === '' ? 'border-red-300 bg-red-50' : ''}`}
                                          value={lot?.destino ? String(lot.destino) : ''}
                                          onChange={(e) => {
                                            const v = String(e.target.value || '').trim()
                                            setLot(lot.id, { destino: v ? v : null })
                                          }}
                                          disabled={isLocked}
                                          title="Destino (obligatorio antes de confirmar)"
                                        >
                                          <option value="">Destino…</option>
                                          {(destinosAvailable || []).map((d) => (
                                            <option key={String(d)} value={String(d)}>{String(d)}</option>
                                          ))}
                                        </select>

                                        <div className="flex items-start gap-2">
                                          <Button
                                            type="button"
                                            size="icon"
                                            variant="outline"
                                            disabled={isLocked}
                                            title="Agregar otro embalaje a este lote"
                                            onClick={() => addExtraPackagingRow(lot.id)}
                                          >
                                            <Plus className="h-4 w-4" />
                                          </Button>

                                          <div className="flex flex-col gap-2">
                                            <div className="flex items-center gap-2">
                                              <PackagingPicker
                                                lot={lot}
                                                disabled={isLocked}
                                                destinos={String(lot?.destino || '').trim() ? [String(lot.destino)] : selectedDestinos}
                                                onPick={(pack) => {
                                                  requestPackagingChange(lot, pack)
                                                }}
                                              />
                                              {(destinosAvailable || []).length > 0 ? (
                                                <select
                                                  className="rounded border px-2 py-1 text-xs"
                                                  value={String(lot?.destino || '')}
                                                  onChange={(e) => {
                                                    const v = String(e.target.value || '').trim()
                                                    setLot(lot.id, { destino: v ? v : null })
                                                  }}
                                                  disabled={isLocked}
                                                  title="Destino de este embalaje"
                                                >
                                                  <option value="">Destino…</option>
                                                  {(destinosAvailable || []).map((d) => (
                                                    <option key={d} value={d}>{d}</option>
                                                  ))}
                                                </select>
                                              ) : null}
                                              <Input
                                                className="w-64"
                                                placeholder="Indicaciones embalaje…"
                                                value={String(lot.packaging_indications || '')}
                                                onChange={(e) => setLot(lot.id, { packaging_indications: e.target.value })}
                                                disabled={isLocked}
                                              />
                                            </div>

                                            {(Array.isArray(lot.extra_packagings) ? lot.extra_packagings : []).map((p, pIdx) => {
                                              const rowLot = {
                                                ...lot,
                                                c_embalaje: p?.c_embalaje ?? null,
                                                n_embalaje: p?.n_embalaje ?? null,
                                                cp2_cajas_por_pallet: p?.cp2_cajas_por_pallet ?? null,
                                              }
                                              return (
                                                <div key={`ex-${lot.id}-${pIdx}`} className="flex items-center gap-2">
                                                  <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="outline"
                                                    onClick={() => removeExtraPackagingRow(lot.id, pIdx)}
                                                    disabled={isLocked}
                                                    title="Quitar este embalaje extra"
                                                  >
                                                    <Trash2 className="h-4 w-4" />
                                                  </Button>

                                                  <PackagingPicker
                                                    lot={rowLot}
                                                    disabled={isLocked}
                                                    destinos={String(p?.destino || lot?.destino || '').trim() ? [String(p?.destino || lot.destino)] : selectedDestinos}
                                                    onPick={(pack) => {
                                                      setExtraPackagingRow(lot.id, pIdx, {
                                                        c_embalaje: pack?.c_embalaje ?? null,
                                                        n_embalaje: pack?.n_embalaje ?? null,
                                                        cp2_cajas_por_pallet: pack?.cp2_cajas_por_pallet ?? null,
                                                      })
                                                    }}
                                                  />
                                                  {(destinosAvailable || []).length > 0 ? (
                                                    <select
                                                      className="rounded border px-2 py-1 text-xs"
                                                      value={String(p?.destino || '')}
                                                      onChange={(e) => setExtraPackagingRow(lot.id, pIdx, { destino: e.target.value || null })}
                                                      disabled={isLocked}
                                                    >
                                                      <option value="">Destino lote</option>
                                                      {(destinosAvailable || []).map((d) => (
                                                        <option key={d} value={d}>{d}</option>
                                                      ))}
                                                    </select>
                                                  ) : null}
                                                  <Input
                                                    className="w-64"
                                                    placeholder="Indicaciones…"
                                                    value={String(p?.indications || '')}
                                                    onChange={(e) => setExtraPackagingRow(lot.id, pIdx, { indications: e.target.value })}
                                                    disabled={isLocked}
                                                  />
                                                </div>
                                              )
                                            })}
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                                  )
                                })()
                              )}
                            </Draggable>
                          ))}
                          {droppableProvided.placeholder}

                          {lineLots.length === 0 ? (
                            <div className="rounded border bg-gray-50 p-4 text-sm text-gray-600">
                              Sin lotes aún. Presiona “Generar” o agrega desde Inventario.
                            </div>
                          ) : null}
                        </div>
                      )}
                    </Droppable>
                  </CardContent>
                </Card>
              )
            })}
        </div>
      </div>
      </DragDropContext>

      {/* Horas extra por línea */}
      {editingExtraHours ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-lg bg-white border shadow-lg p-4">
            <div className="text-lg font-bold">Horas extra</div>
            <div className="text-sm text-gray-600 mt-1">
              Línea/Cámara:{' '}
              <span className="font-semibold">
                {(lines || []).find((l) => Number(l.id) === Number(editingExtraHours.lineId))?.nombre || '-'}
              </span>
            </div>

            <div className="mt-4">
              <Label>Horas extra (ej: 1.5)</Label>
              <Input
                type="number"
                min={0}
                step="0.5"
                value={editingExtraHours.value}
                onChange={(e) => setEditingExtraHours((s) => ({ ...s, value: e.target.value }))}
              />
              <div className="text-xs text-gray-500 mt-1">
                Esto aumenta capacidad y puede extender el horario estimado solo para esta línea.
              </div>
            </div>

            <div className="mt-4 flex items-center justify-end gap-2">
              <Button variant="outline" onClick={() => setEditingExtraHours(null)}>Cancelar</Button>
              <Button onClick={saveExtraHoursForLine}>Guardar</Button>
            </div>
          </div>
        </div>
      ) : null}

      {/* Split modal simple */}
      {splitting?.lot ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-md rounded-lg bg-white border shadow-lg p-4">
            <div className="text-lg font-bold">Dividir lote</div>
            <div className="text-sm text-gray-600 mt-1">
              Recepción <span className="font-semibold">{splitting.lot.n_g_recepcion}</span>
              {splitting.lot.n_variedad ? (
                <span> · Variedad <span className="font-semibold">{splitting.lot.n_variedad}</span></span>
              ) : null}
              {' '}· Total {Number(splitting.lot.cantidad_bins || 0).toLocaleString('es-CL')} bins
            </div>

            <div className="mt-4 grid grid-cols-1 gap-3">
              <div>
                <Label>Bins a mover</Label>
                <Input
                  type="number"
                  min={1}
                  max={Math.max(1, Number(splitting.lot.cantidad_bins || 0) - 1)}
                  value={splitting.bins}
                  onChange={(e) => setSplitting((s) => ({ ...s, bins: e.target.value }))}
                />
                <div className="text-xs text-gray-500 mt-1">El lote original se reduce y se crea una “parte” nueva.</div>
              </div>

              <div>
                <Label>Enviar a línea/cámara</Label>
                <select
                  className="mt-1 w-full rounded border px-2 py-2 text-sm"
                  value={splitting.toLineId}
                  onChange={(e) => setSplitting((s) => ({ ...s, toLineId: e.target.value }))}
                >
                  {eligibleLinesForSplit.map((l) => (
                    <option key={l.id} value={l.id}>
                      {l.nombre}
                      {Number(l.bins_por_hora || 0) > 0 ? ` · ${l.bins_por_hora} bins/h` : ' · (sin capacidad)'}
                      {!currentLineIds.has(Number(l.id)) ? ' · (se agregará)' : ''}
                      {l.id === splitting.lot.packing_line_id ? ' (actual)' : ''}
                    </option>
                  ))}
                </select>
                {eligibleLinesForSplit.length <= 1 ? (
                  <div className="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1 mt-2">
                    Solo hay 1 línea/cámara disponible para esta especie. Revisa Configuración → Líneas/Cámaras o usa el botón “Líneas” para incluir más.
                  </div>
                ) : null}
              </div>
            </div>

            <div className="mt-4 flex items-center justify-end gap-2">
              <Button variant="outline" onClick={() => setSplitting(null)}>Cancelar</Button>
              <Button onClick={applySplit}>Aplicar</Button>
            </div>
          </div>
        </div>
      ) : null}

      {/* Editar líneas incluidas */}
      {editingLines ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-lg rounded-lg bg-white border shadow-lg p-4">
            <div className="text-lg font-bold">Líneas/Cámaras incluidas</div>
            <div className="text-sm text-gray-600 mt-1">
              Selecciona en qué líneas/cámaras se trabajará este proceso. No puedes quitar una línea que ya tiene lotes.
            </div>

            <div className="mt-4 max-h-80 overflow-y-auto space-y-2">
              {(availableLineOptions || []).map((l) => {
                const id = Number(l.id)
                const checked = (editingLines.ids || []).includes(id)
                const locked = lineIdsWithLots.has(id)
                return (
                  <label
                    key={String(l.id)}
                    className={`flex items-start gap-3 rounded border px-3 py-2 ${locked ? 'bg-gray-50' : 'hover:bg-gray-50 cursor-pointer'}`}
                  >
                    <input
                      type="checkbox"
                      checked={checked}
                      disabled={locked && checked}
                      onChange={() => {
                        setEditingLines((prev) => {
                          const next = new Set(prev?.ids || [])
                          if (next.has(id)) next.delete(id)
                          else next.add(id)
                          return { ids: Array.from(next) }
                        })
                      }}
                    />
                    <div className="flex-1">
                      <div className="font-medium">{l.nombre}</div>
                      <div className="text-xs text-gray-600">
                        {l.tipo ? String(l.tipo) : ''}
                        {Number(l.bins_por_hora || 0) > 0 ? ` · ${l.bins_por_hora} bins/h` : ' · (sin capacidad)'}
                        {locked ? ' · (tiene lotes)' : ''}
                      </div>
                    </div>
                  </label>
                )
              })}
              {(!availableLineOptions || availableLineOptions.length === 0) ? (
                <div className="text-sm text-gray-600">
                  No hay líneas/cámaras activas para esta especie.
                </div>
              ) : null}
            </div>

            <div className="mt-4 flex items-center justify-end gap-2">
              <Button variant="outline" onClick={() => setEditingLines(null)}>Cancelar</Button>
              <Button onClick={saveIncludedLines}>Guardar</Button>
            </div>
          </div>
        </div>
      ) : null}

      {/* Motivo cambio de embalaje */}
      {packagingEdit ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <div className="w-full max-w-lg rounded-lg bg-white border shadow-lg p-4">
            <div className="text-lg font-bold">Cambiar embalaje</div>
            <div className="text-sm text-gray-600 mt-1">
              Este cambio quedará registrado con tu usuario, fecha/hora y motivo.
            </div>

            <div className="mt-3 rounded border bg-gray-50 px-3 py-2 text-sm">
              <div><span className="text-gray-500">Lote:</span> <span className="font-semibold">{packagingEdit.lot?.n_g_recepcion}</span></div>
              <div className="mt-1">
                <span className="text-gray-500">Actual:</span>{' '}
                <span className="font-semibold">
                  {packagingEdit.lot?.c_embalaje ? String(packagingEdit.lot.c_embalaje) : '-'}
                </span>{' '}
                <span className="text-gray-600">{packagingEdit.lot?.n_embalaje ? `· ${packagingEdit.lot.n_embalaje}` : ''}</span>
              </div>
              <div className="mt-1">
                <span className="text-gray-500">Nuevo:</span>{' '}
                <span className="font-semibold">{packagingEdit.nextPack?.c_embalaje ? String(packagingEdit.nextPack.c_embalaje) : '-'}</span>{' '}
                <span className="text-gray-600">{packagingEdit.nextPack?.n_embalaje ? `· ${packagingEdit.nextPack.n_embalaje}` : ''}</span>
              </div>
            </div>

            <div className="mt-4">
              <Label>Motivo (obligatorio)</Label>
              <Textarea
                value={String(packagingEdit.reason || '')}
                onChange={(e) => setPackagingEdit((p) => ({ ...p, reason: e.target.value }))}
                placeholder="Ej: cambio por disponibilidad / requisito cliente / etiqueta..."
              />
            </div>

            <div className="mt-4 flex items-center justify-end gap-2">
              <Button variant="outline" onClick={() => setPackagingEdit(null)}>Cancelar</Button>
              <Button
                onClick={() => {
                  const reason = String(packagingEdit.reason || '').trim()
                  if (!reason) {
                    alert('Debes indicar un motivo.')
                    return
                  }
                  setLot(packagingEdit.lot.id, { ...packagingEdit.nextPack, packaging_change_reason: reason })
                  setPackagingEdit(null)
                }}
              >
                Aplicar
              </Button>
            </div>
          </div>
        </div>
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
