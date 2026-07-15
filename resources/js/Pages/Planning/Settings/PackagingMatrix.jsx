import React, { useEffect, useMemo, useRef, useState } from 'react'
import { router, useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import { Textarea } from '@/Components/ui/textarea'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from '@/Components/ui/command'
import axios from 'axios'

const DEFAULT_CAROZOS_CALIBRES = [28, 30, 32, 36, 40, 44, 48, 52, 56, 60, 66, 72, 78, 88, 98, 108, 120]
const DEFAULT_CHERRIES_CALIBRES = ['L','LD','XL','XLD','J','JD','2J','2JD','3J','3JD','4J','4JD','5J','5JD','6J','6JD','7J','7JD']

function normalizeCalibres(value, matrix) {
  if (!Array.isArray(value)) return []
  const list = value
    .map((v) => String(v ?? '').trim())
    .filter(Boolean)
    .map((v) => matrix === 'otros' ? v : v.toUpperCase().replace(/\s+/g, ''))

  const unique = Array.from(new Set(list))
  if (matrix === 'cherries') {
    const order = new Map(DEFAULT_CHERRIES_CALIBRES.map((k, idx) => [k, idx]))
    return unique.sort((a, b) => (order.get(a) ?? 999) - (order.get(b) ?? 999))
  }

  if (matrix === 'otros') {
    return unique.sort((a, b) => a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }))
  }

  // carozos: dejamos solo números y ordenamos por tamaño
  return unique
    .filter((v) => /^\d{2,3}$/.test(v))
    .sort((a, b) => Number(a) - Number(b))
}

function PackagingPicker({ value, onPick, disabled }) {
  const [open, setOpen] = useState(false)
  const [q, setQ] = useState('')
  const [loading, setLoading] = useState(false)
  const [options, setOptions] = useState([])
  const lastFetch = useRef(0)

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
        setOptions([])
      } finally {
        if (lastFetch.current === now) setLoading(false)
      }
    }, 250)

    return () => clearTimeout(t)
  }, [q, open])

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <Button type="button" variant="outline" disabled={disabled} className="w-full justify-between">
          <span className="truncate">{value ? String(value) : 'Buscar embalaje…'}</span>
          <span className="text-xs text-gray-500">Ctrl+K</span>
        </Button>
      </PopoverTrigger>
      <PopoverContent className="w-[520px] p-0" align="start">
        <Command>
          <CommandInput placeholder="Buscar por nombre (n_item)..." value={q} onValueChange={setQ} />
          <CommandEmpty>{loading ? 'Buscando…' : 'Sin resultados'}</CommandEmpty>
          <CommandGroup heading="Resultados">
            {(options || []).map((o) => (
              <CommandItem
                key={String(o.id || o.c_item || o.n_item)}
                value={`${o.c_item} ${o.n_item}`}
                onSelect={() => {
                  onPick(o)
                  setOpen(false)
                  setQ('')
                }}
              >
                <div className="min-w-0">
                  <div className="font-semibold">{o.c_item}</div>
                  <div className="text-xs text-gray-600 truncate">{o.n_item}</div>
                </div>
                {o.cp2_cajas_por_pallet ? (
                  <div className="ml-auto text-xs text-gray-500">CP2: {o.cp2_cajas_por_pallet}</div>
                ) : null}
              </CommandItem>
            ))}
          </CommandGroup>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

export default function PackagingMatrix({ rules = [], especies = [], destinos = [], calibres = [], speciesCalibers = {}, filters = {} }) {
  const { props } = usePage()
  const [editing, setEditing] = useState(null)
  const importRef = useRef(null)
  const [importing, setImporting] = useState(false)

  const activeMatrix = String(filters?.matrix || 'carozos')

  const { data, setData, post, patch, processing, errors, reset, transform } = useForm({
    matrix: activeMatrix,
    especie: especies?.[0] ? String(especies[0]) : '',
    destino: '',
    nota: '',
    variedad: '',
    color: '',
    require_sdp: false,
    c_item: '',
    desc_embalaje: '',
    peso_caja: '',
    allowed_calibres: [],
    calibres_note: '',
    sobre_calibre_note: '',
    priority: '',
    activo: true,
  })

  const CALIBRES = useMemo(() => {
    if (activeMatrix === 'otros') {
      const especie = String(data?.especie || filters?.especie || '')
      if (especie && speciesCalibers[especie]) {
        return speciesCalibers[especie]
      }
      return []
    }
    if (Array.isArray(calibres) && calibres.length) return calibres
    return activeMatrix === 'cherries' ? DEFAULT_CHERRIES_CALIBRES : DEFAULT_CAROZOS_CALIBRES
  }, [calibres, activeMatrix, data?.especie, filters?.especie, speciesCalibers])

  const startCreate = () => {
    setEditing(null)
    reset()
    setData({
      matrix: activeMatrix,
      especie: especies?.[0] ? String(especies[0]) : '',
      destino: '',
      nota: '',
      variedad: '',
      color: '',
      require_sdp: false,
      c_item: '',
      desc_embalaje: '',
      peso_caja: '',
      allowed_calibres: [],
      calibres_note: '',
      sobre_calibre_note: '',
      priority: '',
      activo: true,
    })
  }

  const startEdit = (r) => {
    setEditing(r)
    setData({
      matrix: activeMatrix,
      especie: r.especie ?? '',
      destino: r.destino ?? '',
      nota: r.nota ?? '',
      variedad: r.variedad ?? '',
      color: r.color ?? '',
      require_sdp: Boolean(r.require_sdp),
      c_item: r.c_item ?? '',
      desc_embalaje: r.desc_embalaje ?? '',
      peso_caja: r.peso_caja ?? '',
      allowed_calibres: normalizeCalibres(r.allowed_calibres || [], activeMatrix),
      calibres_note: r.calibres_note ?? '',
      sobre_calibre_note: r.sobre_calibre_note ?? '',
      priority: r.priority ?? '',
      activo: Boolean(r.activo),
    })
  }

  const duplicateRule = (r) => {
    setEditing(null)
    setData({
      matrix: activeMatrix,
      especie: r.especie ?? '',
      destino: r.destino ?? '',
      nota: r.nota ?? '',
      variedad: r.variedad ?? '',
      color: r.color ?? '',
      require_sdp: Boolean(r.require_sdp),
      c_item: r.c_item ?? '',
      desc_embalaje: r.desc_embalaje ?? '',
      peso_caja: r.peso_caja ?? '',
      allowed_calibres: normalizeCalibres(r.allowed_calibres || [], activeMatrix),
      calibres_note: r.calibres_note ?? '',
      sobre_calibre_note: r.sobre_calibre_note ?? '',
      priority: '',
      activo: Boolean(r.activo),
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }

  const submit = (e) => {
    e.preventDefault()
    transform((d) => ({
      ...d,
      destino: String(d.destino || '').trim() || null,
      nota: String(d.nota || '').trim() || null,
      variedad: String(d.variedad || '').trim() || null,
      color: String(d.color || '').trim() || null,
      peso_caja: String(d.peso_caja || '').trim() === '' ? null : d.peso_caja,
      priority: String(d.priority || '').trim() === '' ? null : Number(d.priority),
      allowed_calibres: normalizeCalibres(d.allowed_calibres, activeMatrix),
    }))
    if (editing?.id) {
      patch(route('planning.settings.packaging-matrix.update', editing.id), { preserveScroll: true, onSuccess: () => setEditing(null) })
      return
    }
    post(route('planning.settings.packaging-matrix.store'), { preserveScroll: true, onSuccess: () => startCreate() })
  }

  const toggleCalibre = (c) => {
    const val = activeMatrix === 'otros' ? String(c) : String(c).toUpperCase().replace(/\s+/g, '')
    const set = new Set(normalizeCalibres(data.allowed_calibres, activeMatrix))
    if (set.has(val)) set.delete(val)
    else set.add(val)
    setData('allowed_calibres', Array.from(set))
  }

  const applyFilters = (patch) => {
    const next = { ...(filters || {}), ...patch }
    router.get(route('planning.settings.packaging-matrix.index'), next, { preserveState: true, replace: true })
  }

  const importCsv = () => {
    if (!window.confirm('Esto reemplaza TODAS las reglas actuales por las del CSV. ¿Continuar?')) return
    router.post(route('planning.settings.packaging-matrix.import'), { matrix: activeMatrix }, { preserveScroll: true })
  }

  const importFile = (e) => {
    e.preventDefault()
    const file = importRef.current?.files?.[0]
    if (!file) return
    if (!window.confirm('Esto reemplaza TODAS las reglas actuales por las del archivo. ¿Continuar?')) return

    setImporting(true)
    const form = new FormData()
    form.append('file', file)
    form.append('matrix', activeMatrix)

    router.post(route('planning.settings.packaging-matrix.import-upload'), form, {
      forceFormData: true,
      preserveScroll: true,
      onFinish: () => setImporting(false),
    })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <Card>
        <CardHeader className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between space-y-0 pb-2">
          <div>
            <CardTitle className="text-2xl font-bold flex items-center gap-2">
              Configuración · Matriz Embalajes
              <Badge variant="outline" className="bg-sky-50 text-sky-800 border-sky-200">
                {activeMatrix === 'cherries' ? 'Cherries' : activeMatrix === 'otros' ? 'Otros' : 'Carozos'}
              </Badge>
            </CardTitle>
            <div className="text-sm text-gray-600">
              Sugerencia automática al generar/agregar lotes. Todo es editable en el proceso.
            </div>
          </div>
            <div className="flex flex-wrap items-center gap-2">
              <a href={route('planning.settings.packaging-matrix.export', { matrix: activeMatrix })} className="inline-flex">
                <Button variant="outline">Exportar CSV</Button>
              </a>
            <Button
              type="button"
              variant="outline"
              onClick={() => applyFilters({ matrix: 'carozos' })}
              className={activeMatrix === 'carozos' ? 'border-green-300 bg-green-50' : ''}
            >
              Carozos
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => applyFilters({ matrix: 'cherries' })}
              className={activeMatrix === 'cherries' ? 'border-green-300 bg-green-50' : ''}
            >
              Cherries
            </Button>
            <Button
              type="button"
              variant="outline"
              onClick={() => applyFilters({ matrix: 'otros' })}
              className={activeMatrix === 'otros' ? 'border-green-300 bg-green-50' : ''}
            >
              Otros
            </Button>
            {activeMatrix === 'carozos' ? (
              <Button variant="outline" onClick={importCsv}>Importar CSV (servidor)</Button>
            ) : null}
            <Button variant="outline" onClick={startCreate}>Nueva regla</Button>
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
          {props?.flash?.warning && (
            <div className="mb-3 rounded border border-amber-200 bg-amber-50 text-amber-900 px-3 py-2 text-sm">
              {props.flash.warning}
            </div>
          )}

          <div className="rounded border bg-white mb-4">
            <div className="px-4 py-3 border-b bg-gray-50">
              <div className="font-semibold">Importar archivo</div>
              <div className="text-sm text-gray-600">
                {activeMatrix === 'otros'
                  ? <>Sube el CSV con columnas de calibres (ej: <span className="font-semibold">8mm</span>, <span className="font-semibold">10mm</span>, <span className="font-semibold">12mm</span>), delimitador <span className="font-semibold">;</span>. Reemplaza todas las reglas de la matriz.</>
                  : <>Sube el CSV con el mismo formato de <span className="font-semibold">matriz-carozos-embalajes.csv</span> (delimitador <span className="font-semibold">;</span>). Reemplaza todas las reglas de la matriz seleccionada.</>
                }
              </div>
            </div>
            <form onSubmit={importFile} className="p-4 flex flex-col md:flex-row md:items-center gap-3">
              <input ref={importRef} type="file" accept=".csv,text/csv,text/plain" className="block" />
              <Button type="submit" variant="outline" disabled={importing}>
                {importing ? 'Importando…' : 'Importar archivo CSV'}
              </Button>
            </form>
          </div>

          <form onSubmit={submit} className="rounded border p-4 bg-gray-50 mb-4">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-3">
              <div className="lg:col-span-2">
                <Label>Especie</Label>
                <select
                  className="mt-1 w-full rounded border px-2 py-2 text-sm"
                  value={String(data.especie || '')}
                  onChange={(e) => setData('especie', e.target.value)}
                >
                  <option value="">Seleccionar especie</option>
                  {(especies || []).map((e) => (
                    <option key={String(e)} value={String(e)}>
                      {String(e)}
                    </option>
                  ))}
                </select>
                {errors.especie && <div className="text-sm text-red-600 mt-1">{errors.especie}</div>}
              </div>

              <div className="lg:col-span-2">
                <Label>Destino</Label>
                <input
                  list="destinos"
                  className="mt-1 w-full rounded border px-2 py-2 text-sm"
                  value={String(data.destino || '')}
                  onChange={(e) => setData('destino', e.target.value)}
                  placeholder="Ej: CHINA"
                />
                <datalist id="destinos">
                  {(destinos || []).map((d) => (
                    <option key={d} value={String(d)} />
                  ))}
                </datalist>
                {errors.destino && <div className="text-sm text-red-600 mt-1">{errors.destino}</div>}
              </div>

              <div className="lg:col-span-2">
                <Label>Nota</Label>
                <Input value={String(data.nota || '')} onChange={(e) => setData('nota', e.target.value)} placeholder="Ej: Nota 2 / Premium / vacío" />
                {errors.nota && <div className="text-sm text-red-600 mt-1">{errors.nota}</div>}
              </div>

              <div className="lg:col-span-3">
                <Label>Variedad (opcional)</Label>
                <Input value={String(data.variedad || '')} onChange={(e) => setData('variedad', e.target.value)} placeholder="Vacío = cualquiera" />
                {errors.variedad && <div className="text-sm text-red-600 mt-1">{errors.variedad}</div>}
              </div>

              <div className="lg:col-span-3">
                <Label>Color (opcional)</Label>
                <Input value={String(data.color || '')} onChange={(e) => setData('color', e.target.value)} placeholder="Ej: Blancos / Amarillos" />
                {errors.color && <div className="text-sm text-red-600 mt-1">{errors.color}</div>}
              </div>

              <div className="lg:col-span-12 flex flex-wrap items-center justify-between gap-3 pt-1">
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={Boolean(data.require_sdp)} onChange={(e) => setData('require_sdp', e.target.checked)} />
                  Requiere SDP
                </label>

                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={Boolean(data.activo)} onChange={(e) => setData('activo', e.target.checked)} />
                  Activo
                </label>
              </div>

              <div className="lg:col-span-4">
                <Label>Embalaje (c_item)</Label>
                <div className="mt-1">
                  <PackagingPicker
                    value={data.c_item ? `${data.c_item}${data.desc_embalaje ? ` · ${data.desc_embalaje}` : ''}` : ''}
                    disabled={processing}
                    onPick={(o) => {
                      setData('c_item', o.c_item)
                      setData('desc_embalaje', o.n_item)
                    }}
                  />
                </div>
                {errors.c_item && <div className="text-sm text-red-600 mt-1">{errors.c_item}</div>}
              </div>

              <div className="lg:col-span-2">
                <Label>Peso caja (kg)</Label>
                <Input type="number" step="0.01" value={String(data.peso_caja || '')} onChange={(e) => setData('peso_caja', e.target.value)} />
                {errors.peso_caja && <div className="text-sm text-red-600 mt-1">{errors.peso_caja}</div>}
              </div>

              <div className="lg:col-span-2">
                <Label>Prioridad</Label>
                <Input type="number" value={String(data.priority || '')} onChange={(e) => setData('priority', e.target.value)} placeholder="Menor = primero" />
                {errors.priority && <div className="text-sm text-red-600 mt-1">{errors.priority}</div>}
              </div>

              <div className="lg:col-span-12">
                <div className="flex items-center justify-between gap-2">
                  <Label>Calibres permitidos</Label>
                  <div className="flex items-center gap-2">
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('allowed_calibres', normalizeCalibres(CALIBRES, activeMatrix))} disabled={processing}>Todos</Button>
                    <Button type="button" size="sm" variant="outline" onClick={() => setData('allowed_calibres', [])} disabled={processing}>Ninguno</Button>
                  </div>
                </div>
                <div className="mt-2 flex flex-wrap gap-2 rounded border bg-white p-2">
                  {CALIBRES.length === 0 && activeMatrix === 'otros' ? (
                    <div className="text-sm text-gray-500 py-1">Selecciona una especie para ver sus calibres.</div>
                  ) : null}
                  {CALIBRES.map((c) => {
                    const normalized = normalizeCalibres(data.allowed_calibres, activeMatrix)
                    const key = activeMatrix === 'otros' ? String(c) : String(c).toUpperCase().replace(/\s+/g, '')
                    const checked = normalized.includes(key)
                    return (
                      <button
                        key={String(c)}
                        type="button"
                        onClick={() => toggleCalibre(c)}
                        className={`px-2 py-1 rounded-full border text-xs font-semibold ${checked ? 'bg-green-50 text-green-800 border-green-200' : 'bg-slate-50 text-slate-700 border-slate-200'}`}
                        aria-pressed={checked}
                        title={checked ? 'Incluido' : 'No incluido'}
                      >
                        {c}
                      </button>
                    )
                  })}
                </div>
                {errors.allowed_calibres && <div className="text-sm text-red-600 mt-1">{errors.allowed_calibres}</div>}
              </div>

              <div className="lg:col-span-8">
                <Label>Nota calibres (opcional)</Label>
                <Textarea value={String(data.calibres_note || '')} onChange={(e) => setData('calibres_note', e.target.value)} placeholder="Texto humano (series, etc.)" />
              </div>

              <div className="lg:col-span-4">
                <Label>Sobre calibre (opcional)</Label>
                <Textarea value={String(data.sobre_calibre_note || '')} onChange={(e) => setData('sobre_calibre_note', e.target.value)} placeholder="Ej: Silver RED al 88" />
              </div>
            </div>

            <div className="flex items-center justify-end gap-2 mt-4">
              {editing ? (
                <Button type="button" variant="outline" onClick={startCreate} disabled={processing}>Cancelar</Button>
              ) : null}
              <Button type="submit" disabled={processing}>
                {processing ? 'Guardando…' : editing ? 'Actualizar' : 'Crear'}
              </Button>
            </div>
          </form>

          <div className="rounded border bg-white mb-4">
            <div className="px-4 py-3 border-b bg-gray-50">
              <div className="font-semibold">Filtros</div>
              <div className="text-sm text-gray-600">Ajusta para encontrar rápido la regla correcta.</div>
            </div>
            <div className="p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
              <div>
                <Label>Especie</Label>
                <select className="mt-1 w-full rounded border px-2 py-2 text-sm" value={String(filters.especie || '')} onChange={(e) => applyFilters({ especie: e.target.value })}>
                  <option value="">(Todas)</option>
                  {(especies || []).map((e) => (
                    <option key={e} value={String(e)}>{String(e)}</option>
                  ))}
                </select>
              </div>
              <div>
                <Label>Destino</Label>
                <select className="mt-1 w-full rounded border px-2 py-2 text-sm" value={String(filters.destino || '')} onChange={(e) => applyFilters({ destino: e.target.value })}>
                  <option value="">(Todos)</option>
                  {(destinos || []).map((d) => (
                    <option key={d} value={String(d)}>{String(d)}</option>
                  ))}
                </select>
              </div>
              <div>
                <Label>Buscar</Label>
                <Input className="mt-1" value={String(filters.q || '')} onChange={(e) => applyFilters({ q: e.target.value })} placeholder="c_item, variedad, color..." />
              </div>
              <div className="flex items-end">
                <label className="flex items-center gap-2 text-sm">
                  <input type="checkbox" checked={Boolean(Number(filters.only_active || 0))} onChange={(e) => applyFilters({ only_active: e.target.checked ? 1 : 0 })} />
                  Solo activas
                </label>
              </div>
            </div>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Especie</TableHead>
                <TableHead>Destino</TableHead>
                <TableHead>Nota</TableHead>
                <TableHead>Variedad / Color</TableHead>
                <TableHead>Embalaje</TableHead>
                <TableHead>Calibres</TableHead>
                <TableHead>Prioridad</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(rules || []).map((r) => (
                <TableRow key={r.id}>
                  <TableCell className="font-medium">{r.especie}</TableCell>
                  <TableCell>{r.destino || <span className="text-gray-500">(Cualquiera)</span>}</TableCell>
                  <TableCell>{r.nota || <span className="text-gray-500">(Todas)</span>}</TableCell>
                  <TableCell>
                    <div className="text-sm font-medium">{r.variedad || <span className="text-gray-500">(Cualquiera)</span>}</div>
                    <div className="text-xs text-gray-600 flex items-center gap-2">
                      <span>{r.color || '(Cualquier color)'}</span>
                      {r.require_sdp ? (
                        <Badge variant="outline" className="bg-slate-50 text-slate-800 border-slate-200">SDP</Badge>
                      ) : null}
                    </div>
                  </TableCell>
                  <TableCell>
                    <div className="font-semibold">{r.c_item}</div>
                    <div className="text-xs text-gray-600">{r.desc_embalaje || '-'}</div>
                    {r.cp2_cajas_por_pallet ? (
                      <div className="text-[11px] text-gray-500">CP2: {r.cp2_cajas_por_pallet}</div>
                    ) : null}
                  </TableCell>
                  <TableCell>
                    {Array.isArray(r.allowed_calibres) && r.allowed_calibres.length ? (
                      <div className="flex flex-wrap gap-1">
                        {normalizeCalibres(r.allowed_calibres, activeMatrix).slice(0, 8).map((c) => (
                          <span key={c} className="px-2 py-0.5 rounded-full border text-[11px] font-semibold bg-slate-50 text-slate-700 border-slate-200">
                            {c}
                          </span>
                        ))}
                        {normalizeCalibres(r.allowed_calibres, activeMatrix).length > 8 ? (
                          <span className="text-[11px] text-gray-500 self-center">
                            +{normalizeCalibres(r.allowed_calibres, activeMatrix).length - 8}
                          </span>
                        ) : null}
                      </div>
                    ) : (
                      <span className="text-sm text-gray-500">(Todos)</span>
                    )}
                    {r.peso_caja ? (
                      <div className="text-[11px] text-gray-500 mt-1">{r.peso_caja} kg</div>
                    ) : null}
                  </TableCell>
                  <TableCell className="text-sm">{r.priority ?? ''}</TableCell>
                  <TableCell>
                    {r.activo ? (
                      <Badge className="bg-green-50 text-green-800 border border-green-200">Sí</Badge>
                    ) : (
                      <Badge variant="outline" className="text-gray-600">No</Badge>
                    )}
                  </TableCell>
                  <TableCell className="text-right space-x-2">
                    <Button size="sm" variant="outline" onClick={() => duplicateRule(r)}>Duplicar</Button>
                    <Button size="sm" variant="outline" onClick={() => startEdit(r)}>Editar</Button>
                    <Button
                      size="sm"
                      variant="outline"
                      onClick={() => {
                        if (!window.confirm('¿Eliminar esta regla?')) return
                        router.delete(route('planning.settings.packaging-matrix.destroy', r.id), { preserveScroll: true })
                      }}
                    >
                      Eliminar
                    </Button>
                  </TableCell>
                </TableRow>
              ))}

              {(!rules || rules.length === 0) ? (
                <TableRow>
                  <TableCell colSpan={9} className="py-10 text-center text-sm text-gray-600">
                    No hay reglas. Usa “Importar CSV” o crea una regla.
                  </TableCell>
                </TableRow>
              ) : null}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

PackagingMatrix.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Configuración</h2>}
  />
)
