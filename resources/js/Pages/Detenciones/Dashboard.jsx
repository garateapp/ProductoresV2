import { useMemo, useState } from 'react'
import { Head, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import Chart from 'react-apexcharts'
import { AlertTriangle, Clock, CalendarX, Activity, Gauge, Wrench } from 'lucide-react'

const BRAND_GREEN = 'var(--corp-green)'
const BRAND_ORANGE = 'var(--corp-orange)'

const CHART_COLORS = [
  BRAND_GREEN,
  BRAND_ORANGE,
  '#1565c0',
  '#ef4444',
  '#7E57C2',
  '#009688',
  '#FDD835',
  '#E91E63',
  '#5D4037',
  '#607D8B',
]

const fmtMin = (min = 0) => {
  const m = Number(min || 0)
  const h = Math.floor(m / 60)
  const rest = m % 60
  return h > 0 ? `${h}h ${rest}m` : `${rest}m`
}

const StatCard = ({ icon: Icon, title, value, sub, accent = 'from-emerald-500/15 to-white' }) => (
  <Card className="border border-emerald-50 bg-gradient-to-br text-emerald-900 shadow-sm">
    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
      <CardTitle className="text-sm font-medium text-emerald-900/70">{title}</CardTitle>
      <div className={`rounded-full bg-gradient-to-br p-2 text-emerald-700 ${accent}`}>
        <Icon className="h-5 w-5" />
      </div>
    </CardHeader>
    <CardContent>
      <div className="text-3xl font-semibold tracking-tight text-gray-900">{value}</div>
      {sub && <div className="mt-1 text-xs text-gray-500">{sub}</div>}
    </CardContent>
  </Card>
)

export default function DetencionesDashboard({ auth, resumen = {} }) {
  const kpis = resumen.kpis || {}
  const charts = resumen.charts || {}
  const maquinas = Array.isArray(resumen.maquinas) ? resumen.maquinas : []

  const [fechaDesde, setFechaDesde] = useState(resumen.fechaDesde || '')
  const [fechaHasta, setFechaHasta] = useState(resumen.fechaHasta || '')

  const aplicarFiltros = () => {
    router.get(
      route('detenciones.dashboard.index'),
      { fecha_desde: fechaDesde, fecha_hasta: fechaHasta },
      { preserveState: true, preserveScroll: true, replace: true }
    )
  }

  const porMaquinaOptions = useMemo(() => {
    const data = charts.porMaquina || {}
    const labels = Array.isArray(data.labels) ? data.labels : []
    return {
      chart: { type: 'line', toolbar: { show: false }, zoom: { enabled: false } },
      plotOptions: { bar: { columnWidth: '45%', borderRadius: 4 } },
      dataLabels: { enabled: false },
      stroke: { width: [0, 3], curve: 'smooth' },
      colors: [BRAND_GREEN, BRAND_ORANGE],
      fill: { opacity: [0.85, 1] },
      xaxis: {
        categories: labels,
        labels: { style: { colors: '#374151', fontSize: '12px' }, rotate: -30 },
        axisBorder: { show: true, color: '#e5e7eb' },
        axisTicks: { show: true, color: '#e5e7eb' },
      },
      yaxis: [
        {
          title: { text: 'Detenciones' },
          labels: {
            style: { colors: '#374151', fontSize: '12px' },
            formatter: (val) => Math.round(val),
          },
        },
        {
          seriesName: 'Minutos',
          opposite: true,
          title: { text: 'Minutos' },
          labels: { style: { colors: '#374151', fontSize: '12px' } },
        },
      ],
      grid: { borderColor: '#f3f4f6' },
      tooltip: { shared: true, intersect: false },
      legend: { position: 'top', horizontalAlign: 'right' },
    }
  }, [charts.porMaquina])

  const porMaquinaSeries = useMemo(() => {
    const data = charts.porMaquina || {}
    return [
      { name: 'Detenciones', type: 'column', data: Array.isArray(data.detenciones) ? data.detenciones : [] },
      { name: 'Minutos', type: 'line', data: Array.isArray(data.minutos) ? data.minutos : [] },
    ]
  }, [charts.porMaquina])

  const porTipoOptions = useMemo(() => ({
    chart: { type: 'donut', toolbar: { show: false } },
    labels: Array.isArray(charts.porTipo?.labels) ? charts.porTipo.labels : [],
    colors: CHART_COLORS,
    stroke: { width: 2, colors: ['#fff'] },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '12px' },
    plotOptions: {
      pie: {
        donut: { size: '70%', labels: { show: true, name: { fontSize: '14px' }, value: { fontSize: '16px', formatter: (v) => fmtMin(v) } } },
      },
    },
    tooltip: { y: { formatter: (v) => fmtMin(v) } },
  }), [charts.porTipo])

  const tendenciaOptions = useMemo(() => ({
    chart: { type: 'area', height: 300, toolbar: { show: false }, zoom: { enabled: true } },
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    colors: [BRAND_GREEN],
    fill: { type: 'gradient', gradient: { shadeIntensity: 0.3, opacityFrom: 0.7, opacityTo: 0.1, stops: [0, 90, 100] } },
    xaxis: {
      categories: Array.isArray(charts.tendencia?.categories) ? charts.tendencia.categories : [],
      labels: { style: { colors: '#374151', fontSize: '12px' }, rotate: -45 },
      axisBorder: { show: true, color: '#e5e7eb' },
      axisTicks: { show: true, color: '#e5e7eb' },
    },
    yaxis: {
      labels: {
        style: { colors: '#374151', fontSize: '12px' },
        formatter: (val) => Math.round(val),
      },
    },
    grid: { borderColor: '#f3f4f6' },
    tooltip: { y: { formatter: (v) => `${Math.round(v)} detenciones` } },
    legend: { position: 'top' },
  }), [charts.tendencia])

  const tendenciaSeries = useMemo(() => [
    { name: 'Detenciones', data: Array.isArray(charts.tendencia?.series) ? charts.tendencia.series : [] },
  ], [charts.tendencia])

  const topCausasOptions = useMemo(() => ({
    chart: { type: 'bar', toolbar: { show: false } },
    plotOptions: { bar: { horizontal: true, barHeight: '55%', borderRadius: 3 } },
    dataLabels: { enabled: true, formatter: (val) => fmtMin(val), style: { fontSize: '12px', fontWeight: 600 } },
    colors: [BRAND_ORANGE],
    xaxis: {
      categories: Array.isArray(charts.topCausas?.labels) ? charts.topCausas.labels : [],
      labels: { style: { colors: '#374151', fontSize: '12px' } },
    },
    yaxis: { labels: { style: { colors: '#374151', fontSize: '11px' } } },
    grid: { borderColor: '#f3f4f6' },
    tooltip: { y: { formatter: (v) => fmtMin(v) } },
  }), [charts.topCausas])

  const topCausasSeries = useMemo(() => [
    {
      name: 'Minutos perdidos',
      data: Array.isArray(charts.topCausas?.series)
        ? charts.topCausas.series
        : charts.topCausas?.series && typeof charts.topCausas.series === 'object'
          ? Object.values(charts.topCausas.series)
          : [],
    },
  ], [charts.topCausas])

  const hasPorMaquina = (charts.porMaquina?.labels || []).length > 0
  const hasPorTipo = (charts.porTipo?.labels || []).length > 0
  const hasTendencia = (charts.tendencia?.categories || []).length > 0
  const hasTopCausas = (charts.topCausas?.labels || []).length > 0

  const EmptyState = ({ text }) => (
    <div className="flex h-56 flex-col items-center justify-center gap-2 text-gray-400">
      <CalendarX className="h-8 w-8" />
      <p className="text-sm">{text}</p>
    </div>
  )

  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Detenciones de Máquinas · Dashboard</h2>
          <div className="flex flex-wrap items-end gap-2">
            <div>
              <Label className="text-xs text-gray-500">Desde</Label>
              <Input type="date" className="w-40" value={fechaDesde} onChange={(e) => setFechaDesde(e.target.value)} />
            </div>
            <div>
              <Label className="text-xs text-gray-500">Hasta</Label>
              <Input type="date" className="w-40" value={fechaHasta} onChange={(e) => setFechaHasta(e.target.value)} />
            </div>
            <Button variant="secondary" onClick={aplicarFiltros}>Filtrar</Button>
          </div>
        </div>
      }
    >
      <Head title="Detenciones · Dashboard" />
      <div className="py-8">
        <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <StatCard icon={AlertTriangle} title="Detenciones" value={kpis.detenciones ?? 0} accent="from-orange-500/15 to-white" />
            <StatCard icon={Clock} title="Minutos perdidos" value={kpis.minutosPerdidosFormatted ?? '0m'} sub={`${kpis.minutosPerdidos ?? 0} min`} accent="from-rose-500/15 to-white" />
            <StatCard icon={Activity} title="Disponibilidad" value={kpis.disponibilidad != null ? `${kpis.disponibilidad}%` : '—'} sub="turnos cerrados" />
            <StatCard icon={Gauge} title="En curso" value={kpis.enCurso ?? 0} sub="detenciones activas" />
            <StatCard
              icon={Wrench}
              title="Máquina más afectada"
              value={kpis.maquinaAfectada?.nombre || '—'}
              sub={kpis.maquinaAfectada ? fmtMin(kpis.maquinaAfectada.minutos) : 'sin datos en el periodo'}
            />
          </div>

          <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader>
                <CardTitle className="text-base">Detenciones por máquina</CardTitle>
              </CardHeader>
              <CardContent>
                {hasPorMaquina
                  ? <Chart options={porMaquinaOptions} series={porMaquinaSeries} type="line" height={300} />
                  : <EmptyState text="Sin detenciones en el periodo seleccionado." />}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Tiempo perdido por tipo de motivo</CardTitle>
              </CardHeader>
              <CardContent>
                {hasPorTipo
                  ? <Chart options={porTipoOptions} series={charts.porTipo.series || []} type="donut" height={300} />
                  : <EmptyState text="Sin datos para el periodo seleccionado." />}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Tendencia de detenciones por día</CardTitle>
              </CardHeader>
              <CardContent>
                {hasTendencia
                  ? <Chart options={tendenciaOptions} series={tendenciaSeries} type="area" height={300} />
                  : <EmptyState text="Sin datos para el periodo seleccionado." />}
              </CardContent>
            </Card>

            <Card>
              <CardHeader>
                <CardTitle className="text-base">Top causas de detención (minutos)</CardTitle>
              </CardHeader>
              <CardContent>
                {hasTopCausas
                  ? <Chart options={topCausasOptions} series={topCausasSeries} type="bar" height={300} />
                  : <EmptyState text="Sin datos para el periodo seleccionado." />}
              </CardContent>
            </Card>
          </div>

          <Card>
            <CardHeader>
              <CardTitle className="text-base">Resumen por máquina</CardTitle>
            </CardHeader>
            <CardContent>
              {maquinas.length ? (
                <div className="overflow-x-auto">
                  <table className="w-full text-sm">
                    <thead>
                      <tr className="border-b text-left text-xs uppercase tracking-wide text-gray-500">
                        <th className="py-2 pr-4">Máquina</th>
                        <th className="py-2 pr-4">Detenciones</th>
                        <th className="py-2 pr-4">En curso</th>
                        <th className="py-2 text-right">Minutos perdidos</th>
                      </tr>
                    </thead>
                    <tbody>
                      {maquinas.map((m) => (
                        <tr key={m.id} className="border-b last:border-0">
                          <td className="py-2 pr-4 font-medium">
                            <span className="font-mono text-xs text-gray-500">{m.codigo}</span>{' · '}{m.nombre}
                          </td>
                          <td className="py-2 pr-4">{m.detenciones}</td>
                          <td className="py-2 pr-4">
                            {m.enCurso > 0 ? <span className="rounded bg-rose-100 px-2 py-0.5 text-xs text-rose-700">{m.enCurso}</span> : '—'}
                          </td>
                          <td className="py-2 text-right">{fmtMin(m.minutos)}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (
                <EmptyState text="Sin máquinas registradas." />
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}