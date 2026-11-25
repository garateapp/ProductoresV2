import React, { useState } from 'react'
import { Head, router } from '@inertiajs/react'

function formatNumber(value, digits = 0) {
  const number = Number(value || 0)
  if (!Number.isFinite(number)) return '0'
  return number.toLocaleString('es-CL', {
    minimumFractionDigits: digits,
    maximumFractionDigits: digits,
  })
}

function filterValue(value) {
  return Array.isArray(value) ? value[0] || '' : value || ''
}

function formatInicio(value) {
  const text = String(value || '').trim()
  if (!text) return '—'
  const match = text.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2})/)
  return match ? `${match[1]} ${match[2]}` : text
}

const ESTADO_OPTIONS = [
  { value: 'planificado', label: 'Planificado' },
  { value: 'sin_planificar', label: 'Sin planificar' },
]

function FilterSelect({ label, value, options, onChange, allLabel }) {
  return (
    <label className="block">
      <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</span>
      <select
        className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
        value={filterValue(value)}
        onChange={(event) => onChange(event.target.value)}
      >
        <option value="">{allLabel}</option>
        {(options || []).map((option) => (
          <option key={String(option.value)} value={String(option.value)}>
            {option.label}
          </option>
        ))}
      </select>
    </label>
  )
}

function LotCard({ fila }) {
  const [open, setOpen] = useState(false)
  const planned = Boolean(fila.planned)
  const procesado = fila.estado === 'Procesado'

  return (
    <article
      className={`rounded-xl border bg-white p-3 shadow-sm ${
        procesado ? 'border-amber-200' : planned ? 'border-red-200' : 'border-gray-200'
      }`}
    >
      <div className="flex items-start justify-between gap-2">
        <div>
          <div className="text-sm font-bold text-gray-900">N° lote {fila.n_g_recepcion}</div>
          <div className="mt-0.5 text-xs text-gray-600">
            {planned ? `N° proceso ${fila.numproceso}` : 'Sin proceso'}
          </div>
        </div>
        <span
          className={`shrink-0 rounded-full px-2 py-1 text-[11px] font-semibold ${
            procesado
              ? 'bg-amber-50 text-amber-700'
              : planned
                ? 'bg-red-50 text-red-700'
                : 'bg-emerald-50 text-emerald-700'
          }`}
        >
          {fila.estado}
        </span>
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2">
        <div className="rounded-lg bg-gray-50 p-2">
          <div className="text-[11px] text-gray-500">Kilos</div>
          <div className="text-sm font-bold text-gray-900">{formatNumber(fila.peso_neto, 3)}</div>
        </div>
        <div className="rounded-lg bg-gray-50 p-2">
          <div className="text-[11px] text-gray-500">Bins</div>
          <div className="text-sm font-bold text-gray-900">{formatNumber(fila.cantidad_bins)}</div>
        </div>
        <div className="col-span-2 grid grid-cols-2 gap-2 text-xs">
          <div>
            <div className="text-gray-500">Variedad</div>
            <div className="font-semibold text-gray-800">{fila.n_variedad}</div>
          </div>
          <div>
            <div className="text-gray-500">Exportadora</div>
            <div className="font-semibold text-gray-800">{fila.n_exportadora}</div>
          </div>
        </div>
      </div>

      <button
        type="button"
        className="mt-3 w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold text-gray-700"
        aria-expanded={open}
        onClick={() => setOpen((current) => !current)}
      >
        {open ? 'Ocultar detalle' : 'Ver detalle'}
      </button>

      {open ? (
        <dl className="mt-3 grid grid-cols-1 gap-2 border-t border-gray-100 pt-3 text-xs">
          {Object.entries(fila.detalle || {}).map(([key, value]) => (
            <div key={key} className="flex justify-between gap-3">
              <dt className="capitalize text-gray-500">{key.replaceAll('_', ' ')}</dt>
              <dd className="text-right font-semibold text-gray-800">{String(value)}</dd>
            </div>
          ))}
        </dl>
      ) : null}
    </article>
  )
}

export default function MobilePlanningIndex({ filters = {}, options = {}, enCurso = [], grupos = [], resumen = {}, velocidad = [] }) {
  const apply = (patch) => {
    router.get(
      route('planning.mobile.index'),
      { ...filters, ...patch },
      { preserveState: true, preserveScroll: true, replace: true },
    )
  }

  const limpiar = () => {
    router.get(route('planning.mobile.index'), {}, { preserveState: true, preserveScroll: true, replace: true })
  }

  const planes = resumen.planificados || {}
  const disponibles = resumen.disponibles || {}
  const velocidadLineaUno = velocidad.filter((item) => String(item.linea_codigo) === '1')
  const kgHoraLineaUno = velocidadLineaUno.length
    ? velocidadLineaUno.reduce((total, item) => total + Number(item.kg_hora || 0), 0) / velocidadLineaUno.length
    : null
  const turnosVelocidad = velocidadLineaUno.map((item) => item.turno).filter(Boolean).join(' · ')

  return (
    <>
      <Head title="Planificación móvil" />

      <main className="min-h-screen bg-gray-50 pb-10">
        <div className="mx-auto w-full max-w-md px-3 py-4">
          <header className="mb-4">
            <h1 className="text-xl font-bold text-gray-900">Planificación del día</h1>
            <p className="mt-1 text-xs text-gray-600">Procesos, lotes y existencias disponibles</p>
          </header>

          <section className="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
            <div className="grid grid-cols-2 gap-3">
              <FilterSelect
                label="Variedad"
                value={filters.variedad}
                options={options.variedades}
                allLabel="Todas"
                onChange={(value) => apply({ variedad: value })}
              />
              <FilterSelect
                label="Línea"
                value={filters.linea}
                options={options.lineas}
                allLabel="Todas"
                onChange={(value) => apply({ linea: value })}
              />
              <FilterSelect
                label="Turno"
                value={filters.turno}
                options={options.turnos}
                allLabel="Todos"
                onChange={(value) => apply({ turno: value })}
              />
              <FilterSelect
                label="Exportadora"
                value={filters.exportadora}
                options={options.exportadoras}
                allLabel="Todas"
                onChange={(value) => apply({ exportadora: value })}
              />
              <FilterSelect
                label="Estado"
                value={filters.estado}
                options={ESTADO_OPTIONS}
                allLabel="Todo"
                onChange={(value) => apply({ estado: value })}
              />
            </div>

            <label className="mt-3 block">
              <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</span>
              <input
                type="date"
                className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900"
                value={filterValue(filters.fecha)}
                onChange={(event) => apply({ fecha: event.target.value })}
              />
            </label>

            <button
              type="button"
              className="mt-3 w-full rounded-lg bg-gray-900 px-3 py-2 text-xs font-semibold text-white"
              onClick={limpiar}
            >
              Limpiar filtros
            </button>
          </section>

          <section className="mt-4 grid grid-cols-2 gap-2">
            <div className="rounded-xl border border-gray-200 bg-white p-3">
              <div className="text-[11px] text-gray-500">Total kilos</div>
              <div className="text-lg font-bold text-gray-900">{formatNumber(resumen.kilos, 3)}</div>
            </div>
            <div className="rounded-xl border border-gray-200 bg-white p-3">
              <div className="text-[11px] text-gray-500">Total bins</div>
              <div className="text-lg font-bold text-gray-900">{formatNumber(resumen.bins)}</div>
            </div>
            <div className="rounded-xl border border-red-200 bg-red-50 p-3">
              <div className="text-[11px] text-red-700">Planificados</div>
              <div className="text-lg font-bold text-red-800">{formatNumber(planes.kilos, 3)} kg</div>
              <div className="text-[11px] text-red-700">{formatNumber(planes.lotes)} lotes</div>
            </div>
            <div className="rounded-xl border border-emerald-200 bg-emerald-50 p-3">
              <div className="text-[11px] text-emerald-700">Disponibles</div>
              <div className="text-lg font-bold text-emerald-800">{formatNumber(disponibles.kilos, 3)} kg</div>
              <div className="text-[11px] text-emerald-700">{formatNumber(disponibles.lotes)} lotes</div>
            </div>
            <div className="col-span-2 rounded-xl border border-sky-200 bg-sky-50 p-3">
              <div className="text-[11px] text-sky-700">Velocidad · Línea 1</div>
              <div className="text-lg font-bold text-sky-900">
                {kgHoraLineaUno === null ? '—' : `${formatNumber(kgHoraLineaUno, 0)} kg/h`}
              </div>
              <div className="text-[11px] text-sky-700">{turnosVelocidad || 'Unitec en vivo'}</div>
            </div>
          </section>

          {enCurso.length > 0 ? (
            <section className="mt-4 rounded-xl border border-emerald-200 bg-emerald-50/60 p-3">
              <div className="mb-2 flex items-center justify-between gap-2">
                <div>
                  <h2 className="text-sm font-bold text-emerald-900">Procesos en curso</h2>
                  <p className="text-[11px] text-emerald-700">Avance recuperado desde Unitec</p>
                </div>
                <span className="rounded-full bg-white px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                  {enCurso.length} en curso
                </span>
              </div>
              <div className="space-y-2">
                {enCurso.map((avance) => (
                  <article key={`en-curso-${avance.numero_proceso}`} className="rounded-lg border border-emerald-200 bg-white p-3">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <div className="text-sm font-bold text-gray-900">Proceso {avance.numero_proceso}</div>
                        <div className="text-[11px] text-gray-500">Inicio {formatInicio(avance.inicio)}</div>
                      </div>
                      <div className="text-right text-sm font-bold text-emerald-700">
                        {formatNumber(avance.porcentaje_avance, 1)}%
                      </div>
                    </div>
                    <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                      <div
                        className="h-full rounded-full bg-emerald-600"
                        style={{ width: `${Math.min(100, Math.max(0, Number(avance.porcentaje_avance || 0)))}%` }}
                      />
                    </div>
                    <div className="mt-1 text-[10px] text-emerald-700">
                      {formatNumber(avance.kilos_vaciados, 3)} / {formatNumber(avance.kilos_planificados, 3)} kg
                    </div>
                  </article>
                ))}
              </div>
            </section>
          ) : null}

          <section className="mt-4 space-y-4">
            {grupos.length === 0 ? (
              <div className="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                Sin datos para los filtros seleccionados.
              </div>
            ) : (
              grupos.map((turno) => (
                <div key={`turno-${turno.turno}`} className="rounded-xl border border-gray-200 bg-white p-3 shadow-sm">
                  <div className="flex items-start justify-between gap-2 border-b border-gray-100 pb-2">
                    <div>
                      <h2 className="text-sm font-bold text-gray-900">Turno {turno.turno}</h2>
                      <p className="text-[11px] text-gray-500">{formatNumber(turno.lotes)} lotes</p>
                    </div>
                    <div className="text-right">
                      <div className="text-sm font-bold text-gray-900">{formatNumber(turno.kilos, 3)} kg</div>
                      <div className="text-[11px] text-gray-500">{formatNumber(turno.bins)} bins</div>
                    </div>
                  </div>

                  <div className="mt-3 space-y-4">
                    {turno.lineas.map((linea) => (
                      <div key={`${turno.turno}-${linea.linea}`}>
                        <div className="mb-2 flex items-center justify-between gap-2">
                          <div className="text-xs font-bold text-gray-700">Línea {linea.linea}</div>
                          <div className="text-right text-[11px] text-gray-500">
                            {formatNumber(linea.kilos, 3)} kg · {formatNumber(linea.bins)} bins
                          </div>
                        </div>
                        <div className="space-y-3">
                          {linea.procesos.map((proceso) => (
                            <div key={`${turno.turno}-${linea.linea}-${proceso.proceso}`}>
                              <div className="mb-2 flex items-center justify-between gap-2">
                                <div className="flex items-center gap-2">
                                  <span className="text-xs font-semibold text-gray-800">
                                    {proceso.proceso === 'Sin proceso' ? 'Sin proceso' : `Proceso ${proceso.proceso}`}
                                  </span>
                                  {proceso.procesando ? (
                                    <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                      En proceso
                                    </span>
                                  ) : null}
                                </div>
                                <div className="text-right text-[11px] text-gray-500">
                                  {formatNumber(proceso.kilos, 3)} kg · {formatNumber(proceso.bins)} bins
                                </div>
                              </div>
                              {proceso.procesando ? (
                                <div className="mb-2 rounded-lg bg-emerald-50 p-2">
                                  <div className="mb-1 flex items-center justify-between text-[11px] text-emerald-800">
                                    <span>Avance</span>
                                    <span className="font-bold">{formatNumber(proceso.porcentaje_avance, 1)}%</span>
                                  </div>
                                  <div className="h-2 w-full overflow-hidden rounded-full bg-emerald-100">
                                    <div
                                      className="h-full rounded-full bg-emerald-600"
                                      style={{
                                        width: `${Math.min(100, Math.max(0, Number(proceso.porcentaje_avance || 0)))}%`,
                                      }}
                                    />
                                  </div>
                                  <div className="mt-1 text-[10px] text-emerald-700">
                                    {formatNumber(proceso.kilos_vaciados, 3)} / {formatNumber(proceso.kilos_planificados, 3)} kg
                                  </div>
                                </div>
                              ) : null}
                              <div className="space-y-2">
                                {proceso.filas.map((fila) => (
                                  <LotCard key={String(fila.clave)} fila={fila} />
                                ))}
                              </div>
                            </div>
                          ))}
                        </div>
                      </div>
                    ))}
                  </div>
                </div>
              ))
            )}
          </section>
        </div>
      </main>
    </>
  )
}
