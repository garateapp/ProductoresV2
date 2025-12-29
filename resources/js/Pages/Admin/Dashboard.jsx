import React, { useMemo, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import Chart from 'react-apexcharts';
import { Users, Building2, Truck, Factory, ShieldCheck, FileText as FileIcon, LayoutDashboard, Eye } from 'lucide-react';

export default function AdminDashboard({ auth, services = [], producers = [], recepciones = [], procesos = [], certifications = [], contracts = [], stats = {}, charts = {} }) {
  const [producerFilter, setProducerFilter] = useState('');
  const [recepFilter, setRecepFilter] = useState('');
  const [procFilter, setProcFilter] = useState('');
  const [selectedCalibreSpecies, setSelectedCalibreSpecies] = useState('');
  const [selectedCalibreVariety, setSelectedCalibreVariety] = useState('');

  // Color por especie (paleta corporativa + legible)
  const speciesColor = (name = '') => {
    const n = String(name).toLowerCase();
    if (n.includes('cherr') || n.includes('cereza')) return '#7F1F38'; // Cherry wine
    if (n.includes('apple') || n.includes('manzana')) return 'var(--corp-green)'; // Greenex green
    if (n.includes('pear') || n.includes('pera')) return '#53A318'; // Pear green
    if (n.includes('mandarin') || n.includes('mandarina')) return 'var(--corp-orange)'; // Mandarin orange
    if (n.includes('orange') || n.includes('naranja')) return '#ff8b24'; // Orange
    if (n.includes('nectarin') || n.includes('nectarina')) return '#E91E63'; // Pink
    if (n.includes('peach') || n.includes('durazno')) return '#F06292'; // Peach
    if (n.includes('plum') || n.includes('ciruela')) return '#7E57C2'; // Plum purple
    if (n.includes('membrill')) return '#FDD835'; // Yellow
    return '#607D8B'; // Default slate
  };

  const calibreData = charts?.calibreCurve ?? {};
  const calibreCategoriesAll = Array.isArray(calibreData.categories) ? calibreData.categories.map((c) => String(c ?? '')) : [];
  const calibreSeries = Array.isArray(calibreData.series) ? calibreData.series : [];
  const calibreSpecies = Array.isArray(calibreData.species) ? calibreData.species.filter(Boolean) : [];
  const calibreVarietiesBySpecies = calibreData.varietiesBySpecies || {};
  const calibreCalibresBySpecies = calibreData.calibresBySpecies || {};

  const activeCalibreSpecies = selectedCalibreSpecies || calibreSpecies[0] || '';
  const calibreVarietyOptions = useMemo(() => {
    const options = Array.isArray(calibreVarietiesBySpecies?.[activeCalibreSpecies])
      ? calibreVarietiesBySpecies[activeCalibreSpecies]
      : [];
    return options.filter(Boolean);
  }, [calibreVarietiesBySpecies, activeCalibreSpecies]);
  const activeCalibreVariety = calibreVarietyOptions.includes(selectedCalibreVariety) ? selectedCalibreVariety : '';
  const activeCalibreCategories = useMemo(() => {
    const speciesCalibres = Array.isArray(calibreCalibresBySpecies?.[activeCalibreSpecies])
      ? calibreCalibresBySpecies[activeCalibreSpecies].map((c) => String(c ?? ''))
      : [];
    const fallback = speciesCalibres.length ? speciesCalibres : calibreCategoriesAll;
    return fallback;
  }, [calibreCalibresBySpecies, activeCalibreSpecies, calibreCategoriesAll]);

  const filteredCalibreSeries = useMemo(() => {
    return calibreSeries
      .filter((serie) => (!activeCalibreSpecies || serie.especie === activeCalibreSpecies))
      .filter((serie) => (!activeCalibreVariety || (serie.variedad || 'SIN VARIEDAD') === activeCalibreVariety))
      .map((serie) => {
        const dataArr = Array.isArray(serie?.data) ? serie.data : [];
        const categoryMap = new Map(
          calibreCategoriesAll.map((cat, idx) => [String(cat ?? ''), dataArr[idx] ?? 0])
        );
        return {
          name: serie.name || `${serie.especie}${serie.variedad ? ` - ${serie.variedad}` : ''}`,
          data: activeCalibreCategories.map((_, idx) => {
            const cat = activeCalibreCategories[idx];
            const val = categoryMap.has(String(cat)) ? categoryMap.get(String(cat)) : 0;
            const num = typeof val === 'number' ? val : Number(val ?? 0);
            return Number.isFinite(num) ? num : 0;
          }),
          color: speciesColor(serie.especie),
        };
      });
  }, [calibreSeries, activeCalibreCategories, activeCalibreSpecies, activeCalibreVariety, calibreCategoriesAll]);

  const aggregatedCalibreData = useMemo(() => {
    if (!activeCalibreCategories.length) {
      return { kilos: [], percent: [], total: 0 };
    }
    const kilos = activeCalibreCategories.map((_, idx) =>
      filteredCalibreSeries.reduce((sum, serie) => sum + (serie.data[idx] || 0), 0)
    );
    const total = kilos.reduce((a, b) => a + b, 0);
    const percent = total > 0 ? kilos.map((v) => (v * 100) / total) : activeCalibreCategories.map(() => 0);
    return { kilos, percent, total };
  }, [activeCalibreCategories, filteredCalibreSeries]);

  const filteredProducers = useMemo(() => {
    const term = producerFilter.trim().toLowerCase();
    if (!term) return producers;
    return producers.filter((p) => {
      const names = Array.isArray(p.names) ? p.names : [];
      const idprods = Array.isArray(p.idprods) ? p.idprods : [];
      const csgs = Array.isArray(p.csgs) ? p.csgs : [];
      const haystack = [
        p.name,
        p.rut,
        ...names,
        ...idprods,
        ...csgs,
      ]
        .filter(Boolean)
        .map((value) => String(value).toLowerCase())
        .join(' ');
      return haystack.includes(term);
    });
  }, [producerFilter, producers]);
  const filteredRecep = useMemo(() => {
    const term = recepFilter.trim().toLowerCase();
    if (!term) return recepciones;
    return recepciones.filter(r => `${r.numero_g_recepcion} ${r.n_emisor} ${r.n_especie} ${r.n_variedad}`.toLowerCase().includes(term));
  }, [recepFilter, recepciones]);
  const filteredProc = useMemo(() => {
    const term = procFilter.trim().toLowerCase();
    if (!term) return procesos;
    return procesos.filter(p => `${p.n_proceso} ${p.especie} ${p.variedad}`.toLowerCase().includes(term));
  }, [procFilter, procesos]);
  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard Administrador</h2>}>
      <Head title="Dashboard Administrador" />
      <div className="container mx-auto py-6 space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-6 gap-4">
          <Stat title="Servicios" value={stats.services ?? 0} color="green" icon={Building2} />
          <Stat title="Productores" value={stats.producers ?? 0} color="orange" icon={Users} />
          <Stat title="Recepciones" value={stats.recepciones ?? 0} color="green" icon={Truck} />
          <Stat title="Procesos" value={stats.procesos ?? 0} color="orange" icon={Factory} />
          <Stat title="Certificaciones" value={stats.certifications ?? 0} color="green" icon={ShieldCheck} />
          <Stat title="Contratos" value={stats.contracts ?? 0} color="orange" icon={FileIcon} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card className="bg-[#e8f5e9]">
            <CardHeader><CardTitle className="text-[#2e7d32]">Recepciones por especie (Kg)</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'bar', toolbar: { show: false } },
                  xaxis: {
                    categories: (charts?.recepBySpecies ?? []).map(i => i.especie),
                    labels: { show: true, style: { colors: '#374151', fontSize: '12px' } },
                    axisBorder: { show: true, color: '#e5e7eb' },
                    axisTicks: { show: true, color: '#e5e7eb' },
                  },
                  colors: (charts?.recepBySpecies ?? []).map(i => speciesColor(i.especie)),
                  plotOptions: { bar: { distributed: true } },
                  dataLabels: { enabled: false },
                  yaxis: { labels: { show: true, style: { colors: '#374151', fontSize: '12px' }, formatter: (val) => Number(val).toLocaleString('es-CL') } },
                  grid: { borderColor: '#f3f4f6' },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                }}
                series={[{ name: 'Kg', data: (charts?.recepBySpecies ?? []).map(i => i.kilos) }]}
                type="bar"
                height={280}
              />
            </CardContent>
          </Card>
          <Card className="bg-[#fff3e0]">
            <CardHeader><CardTitle className="text-[#fe790f]">Procesados por especie (Stacked)</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'bar', stacked: true, toolbar: { show: false } },
                  xaxis: {
                    categories: (charts?.procStackBySpecies ?? []).map(i => i.especie),
                    labels: { show: true, style: { colors: '#374151', fontSize: '12px' } },
                    axisBorder: { show: true, color: '#e5e7eb' },
                    axisTicks: { show: true, color: '#e5e7eb' },
                  },
                  plotOptions: { bar: { horizontal: false } },
                  dataLabels: { enabled: false },
                  legend: { position: 'top' },
                  colors: ['var(--corp-green)', 'var(--corp-orange)', '#1565c0', '#ef4444'],
                  yaxis: { labels: { show: true, style: { colors: '#374151', fontSize: '12px' }, formatter: (val) => Number(val).toLocaleString('es-CL') } },
                  grid: { borderColor: '#f3f4f6' },
                  tooltip: {
                    shared: true,
                    intersect: false,
                    custom: function({ series, seriesIndex, dataPointIndex, w }) {
                      try {
                        const categories = w?.config?.xaxis?.categories || [];
                        const name = categories[dataPointIndex] || '';
                        const ser = w?.config?.series || [];
                        const palette = w?.config?.colors || [];
                        const values = ser.map(s => Number(s?.data?.[dataPointIndex] || 0));
                        const labels = ser.map(s => s?.name || '');
                        const total = values.reduce((a,b) => a + b, 0);
                        const rows = values.map((v, i) => {
                          const pct = total > 0 ? (v * 100 / total) : 0;
                          const color = palette[i] || '#999999';
                          return `
                            <div style=\"display:flex;justify-content:space-between;align-items:center;gap:12px;\">
                              <span style=\"display:flex;align-items:center;gap:8px;\">
                                <span style=\"display:inline-block;width:10px;height:10px;border-radius:2px;background:${color};\"></span>
                                <span>${labels[i]}</span>
                              </span>
                              <span>${Number(v).toLocaleString('es-CL')} Kg (${pct.toFixed(2)}%)</span>
                            </div>`;
                        }).join('');
                        return `
                          <div style=\"min-width:240px;padding:8px 10px;border:1px solid #e5e7eb;border-radius:8px;background:#ffffffee;\">
                            <div style=\"font-weight:600;margin-bottom:6px;\">${name}</div>
                            ${rows}
                            <div style=\"margin-top:6px;border-top:1px solid #eee;padding-top:4px;font-weight:600;\">Total: ${Number(total).toLocaleString('es-CL')} Kg</div>
                          </div>`;
                      } catch (e) {
                        return '';
                      }
                    }
                  }
                }}
                series={[
                  { name: 'Exportación', data: (charts?.procStackBySpecies ?? []).map(i => Number(i.exp) || 0) },
                  { name: 'Comercial', data: (charts?.procStackBySpecies ?? []).map(i => Number(i.comercial) || 0) },
                  { name: 'Merma', data: (charts?.procStackBySpecies ?? []).map(i => Number(i.merma) || 0) },
                  { name: 'Desecho', data: (charts?.procStackBySpecies ?? []).map(i => Number(i.desecho) || 0) },
                ]}
                type="bar"
                height={300}
              />
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">

          {/* Línea: Kilos recepcionados por semana y especie */}
          <Card className="bg-[#f1f8e9]">
            <CardHeader>
              <CardTitle className="text-[#2e7d32]">Kilos recepcionados por semana</CardTitle>
            </CardHeader>
            <CardContent>
              {(() => {
                const raw = charts?.recepWeeklyBySpecies || { weeks: [], series: [] };
                const weeks = Array.isArray(raw.weeks) ? raw.weeks.map((w) => String(w ?? '')) : [];
                const series = Array.isArray(raw.series)
                  ? raw.series.map((s) => {
                      const name = typeof s?.name === 'string' ? s.name : '-';
                      const dataArr = Array.isArray(s?.data) ? s.data : [];
                      const data = weeks.map((_, i) => {
                        const v = dataArr[i];
                        const num = typeof v === 'number' ? v : v == null ? 0 : Number(v);
                        return Number.isFinite(num) ? num : 0;
                      });
                      return { name, data };
                    })
                  : [];
                const safeSeries = series.length ? series : [{ name: 'Sin datos', data: weeks.map(() => 0) }];
                const lineColors = safeSeries.map((s) => speciesColor?.(s.name) || '#2e7d32');
                const axisLabelColor = '#1f2937';
                const shouldRotateLabels = weeks.length > 8;

                return (
                  <Chart
                    options={{
                      chart: { type: 'line', toolbar: { show: false } },
                      xaxis: {
                        categories: weeks,
                        labels: {
                          show: true,
                          rotate: shouldRotateLabels ? -45 : 0,
                          offsetY: shouldRotateLabels ? 4 : 0,
                          trim: false,
                          hideOverlappingLabels: false,
                          style: { colors: axisLabelColor, fontSize: '12px' },
                        },
                        axisBorder: { show: true, color: '#e5e7eb' },
                        axisTicks: { show: true, color: '#e5e7eb' },
                      },
                      stroke: { curve: 'smooth', width: 3 },
                      colors: lineColors,
                      dataLabels: { enabled: false },
                      yaxis: {
                        labels: {
                          show: true,
                          style: { colors: axisLabelColor, fontSize: '12px' },
                          formatter: (val) => Number(val || 0).toLocaleString('es-CL'),
                        },
                      },
                      grid: { borderColor: '#f3f4f6' },
                      tooltip: { y: { formatter: (val) => `${Number(val || 0).toLocaleString('es-CL')} Kg` } },
                    }}
                    series={safeSeries}
                    type="line"
                    height={300}
                  />
                );
              })()}
            </CardContent>
          </Card>

          {/* Pie: Distribución por categoría */}
          <Card className="bg-[#fff8e1]">
            <CardHeader><CardTitle className="text-[#fe790f]">Distribución por categoría</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'pie' },
                  labels: ['Exportación', 'Comercial', 'Merma', 'Desecho'],
                  colors: ['var(--corp-green)', 'var(--corp-orange)', '#1565c0', '#ef4444'],
                  dataLabels: { enabled: true, formatter: (val, opts) => `${val.toFixed(1)}%` },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                  legend: { position: 'bottom' },
                }}
                series={[
                  Number(charts?.procCategoryTotals?.exp || 0),
                  Number(charts?.procCategoryTotals?.comercial || 0),
                  Number(charts?.procCategoryTotals?.merma || 0),
                  Number(charts?.procCategoryTotals?.desecho || 0),
                ]}
                type="pie"
                height={300}
              />
            </CardContent>
          </Card>
        </div>

        {/* Curva de calibre por especie (ancho completo) */}
        <Card className="bg-[#e3f2fd]">
          <CardHeader><CardTitle className="text-[#1565c0]">Curva de calibre por especie</CardTitle></CardHeader>
          <CardContent>
            {activeCalibreCategories.length ? (
              <>
                <div className="flex flex-wrap gap-3 mb-4">
                  <div className="flex flex-col text-sm">
                    <span className="text-gray-600 mb-1">Especie</span>
                    <select
                      className="border rounded px-2 py-1 min-w-[180px]"
                      value={activeCalibreSpecies}
                      onChange={(e) => {
                        setSelectedCalibreSpecies(e.target.value);
                        setSelectedCalibreVariety('');
                      }}
                    >
                      {calibreSpecies.map((sp) => (
                        <option key={sp} value={sp}>{sp}</option>
                      ))}
                    </select>
                  </div>
                  <div className="flex flex-col text-sm">
                    <span className="text-gray-600 mb-1">Variedad</span>
                    <select
                      className="border rounded px-2 py-1 min-w-[180px]"
                      value={activeCalibreVariety}
                      onChange={(e) => setSelectedCalibreVariety(e.target.value)}
                      disabled={!activeCalibreSpecies}
                    >
                      <option value="">Todas</option>
                      {calibreVarietyOptions.map((variedad) => (
                        <option key={variedad} value={variedad}>{variedad}</option>
                      ))}
                    </select>
                  </div>
                </div>

                {filteredCalibreSeries.length ? (
                  <Chart
                    options={{
                      chart: { type: 'line', toolbar: { show: true }, zoom: { enabled: true } },
                      xaxis: {
                        categories: activeCalibreCategories,
                        labels: {
                          rotate: activeCalibreCategories.length > 8 ? -45 : 0,
                          offsetY: activeCalibreCategories.length > 8 ? 4 : 0,
                          trim: false,
                          style: { colors: '#1f2937', fontSize: '12px' },
                        },
                        axisBorder: { show: true, color: '#e5e7eb' },
                        axisTicks: { show: true, color: '#e5e7eb' },
                      },
                      stroke: { curve: 'smooth', width: 3 },
                      colors: [
                        speciesColor(activeCalibreSpecies),
                        '#ef4444',
                      ],
                      dataLabels: { enabled: false },
                      yaxis: [
                        {
                          seriesName: 'Kg',
                          labels: {
                            show: true,
                            style: { colors: '#1f2937', fontSize: '12px' },
                            formatter: (val) => Number(val || 0).toLocaleString('es-CL'),
                          },
                          title: { text: 'Kg', style: { color: '#1f2937' } },
                        },
                        {
                          seriesName: '% participación',
                          opposite: true,
                          labels: {
                            show: true,
                            style: { colors: '#1f2937', fontSize: '12px' },
                            formatter: (val) => `${Number(val || 0).toFixed(1)}%`,
                          },
                          title: { text: '%', style: { color: '#1f2937' } },
                          min: 0,
                          max: 100,
                        },
                      ],
                      grid: { borderColor: '#f3f4f6' },
                      tooltip: {
                        shared: true,
                        intersect: false,
                        y: [
                          { formatter: (val) => `${Number(val || 0).toLocaleString('es-CL')} Kg` },
                          { formatter: (val) => `${Number(val || 0).toFixed(1)}%` },
                        ],
                      },
                      legend: { position: 'top' },
                    }}
                    series={[
                      {
                        name: activeCalibreVariety
                          ? `${activeCalibreSpecies} - ${activeCalibreVariety} (Kg)`
                          : `${activeCalibreSpecies || 'Total'} (Kg)`,
                        type: 'column',
                        data: aggregatedCalibreData.kilos,
                      },
                      {
                        name: '% participación',
                        type: 'line',
                        data: aggregatedCalibreData.percent,
                      },
                    ]}
                    type="line"
                    height={260}
                  />
                ) : (
                  <p className="text-sm text-gray-500">Sin series para la selección.</p>
                )}
              </>
            ) : (
              <p className="text-sm text-gray-500">Sin datos de calibre.</p>
            )}
          </CardContent>
        </Card>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader><CardTitle>Servicios</CardTitle></CardHeader>
            <CardContent>
              {services.length ? (
                <ul className="divide-y">
                  {services.slice(0, 10).map(s => (
                    <li key={s.id} className="py-2 flex items-center justify-between">
                      <span>{s.name} <span className="text-xs text-gray-500">({s.users_count} productores)</span></span>
                      <Link
                        href={route('services.dashboard', s.id)}
                        aria-label={`Ver dashboard del servicio ${s.name}`}
                        className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100"
                      >
                        <LayoutDashboard className="h-4 w-4" />
                      </Link>
                    </li>
                  ))}
                </ul>
              ) : (<p className="text-sm text-gray-500">Sin servicios.</p>)}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>Productores</CardTitle></CardHeader>
            <CardContent>
              <input className="border rounded px-2 py-1 mb-2 w-full" placeholder="Buscar productor..." value={producerFilter} onChange={e => setProducerFilter(e.target.value)} />
              {filteredProducers.length ? (
                <div className="max-h-[250px] overflow-y-auto rounded border">
                  <ul className="divide-y">
                    {filteredProducers.map((p) => (
                      <li key={p.key ?? p.primary_id} className="py-2 px-2 flex items-center justify-between">
                        <div className="mr-2">
                          <div className="font-medium text-sm text-gray-800">{p.name}</div>
                          <div className="text-xs text-gray-500">
                            {p.rut || 'Sin RUT'}{p.count > 1 ? ` Â· ${p.count} registros` : ''}
                          </div>
                          {(p.idprods?.length || p.csgs?.length) && (
                            <div className="text-[11px] text-gray-500">
                              {p.idprods?.length ? `ID Prod: ${p.idprods.join(', ')}` : ''}
                              {p.idprods?.length && p.csgs?.length ? ' Â· ' : ''}
                              {p.csgs?.length ? `CSG: ${p.csgs.join(', ')}` : ''}
                            </div>
                          )}
                        </div>
                        <Link
                          href={route('producers.dashboard', p.primary_id)}
                          aria-label={`Ver dashboard del productor ${p.name}`}
                          className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100"
                        >
                          <LayoutDashboard className="h-4 w-4" />
                        </Link>
                      </li>
                    ))}
                  </ul>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin productores.</p>)}
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader><CardTitle>Recepciones recientes</CardTitle></CardHeader>
            <CardContent>
              <input className="border rounded px-2 py-1 mb-2 w-full" placeholder="Buscar recepción..." value={recepFilter} onChange={e => setRecepFilter(e.target.value)} />
              {filteredRecep.length ? (
                <div className="max-h-[250px] overflow-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="px-3 py-2 text-left">Lote</th><th className="px-3 py-2 text-left">Fecha</th><th className="px-3 py-2 text-left">Productor</th><th className="px-3 py-2 text-left">Especie</th><th className="px-3 py-2 text-left">Variedad</th></tr></thead>
                    <tbody>
                      {filteredRecep.map(r => (
                        <tr key={r.id} className="border-b">
                          <td className="px-3 py-2">{r.numero_g_recepcion}</td>
                          <td className="px-3 py-2">{new Date(r.fecha_g_recepcion).toLocaleDateString('es-CL')}</td>
                          <td className="px-3 py-2">{r.n_emisor}</td>
                          <td className="px-3 py-2">{r.n_especie}</td>
                          <td className="px-3 py-2">{r.n_variedad}</td>
                          <td className="px-3 py-2 text-right">
                            <Link
                              href={route('control-calidad.preview-report', r.id)}
                              aria-label={`Ver reporte de recepción ${r.numero_g_recepcion}`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100"
                            >
                              <Eye className="h-4 w-4" />
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin recepciones recientes.</p>)}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>Procesos recientes</CardTitle></CardHeader>
            <CardContent>
              <input className="border rounded px-2 py-1 mb-2 w-full" placeholder="Buscar proceso..." value={procFilter} onChange={e => setProcFilter(e.target.value)} />
              {filteredProc.length ? (
                <div className="max-h-[250px] overflow-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50"><tr><th className="px-3 py-2 text-left">NÂ° Proceso</th><th className="px-3 py-2 text-left">Fecha</th><th className="px-3 py-2 text-left">Especie</th><th className="px-3 py-2 text-left">Variedad</th></tr></thead>
                    <tbody>
                      {filteredProc.map(p => (
                        <tr key={p.id} className="border-b">
                          <td className="px-3 py-2">{p.n_proceso}</td>
                          <td className="px-3 py-2">{new Date(p.fecha).toLocaleDateString('es-CL')}</td>
                          <td className="px-3 py-2">{p.especie}</td>
                          <td className="px-3 py-2">{p.variedad}</td>
                          <td className="px-3 py-2 text-right">
                            <Link
                              href={route('processed-fruit-quality.preview-report', p.id)}
                              aria-label={`Ver informe de proceso ${p.n_proceso}`}
                              className="inline-flex h-8 w-8 items-center justify-center rounded-full border border-indigo-100 bg-indigo-50 text-indigo-600 transition hover:bg-indigo-100"
                            >
                              <Eye className="h-4 w-4" />
                            </Link>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin procesos recientes.</p>)}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

function Stat({ title, value, color = 'green', icon: Icon }) {
  const isGreen = color === 'green';
  const borderClass = isGreen ? 'kpi-green' : 'kpi-orange';
  const colorStyle = { color: isGreen ? 'var(--corp-green)' : 'var(--corp-orange)' };
  return (
    <div className={`kpi-card ${borderClass} p-3`}>
      <div className="flex items-center justify-between">
        <div>
          <div className="text-xs font-semibold" style={colorStyle}>{title}</div>
          <div className="text-2xl font-bold mt-1">{value}</div>
        </div>
        {Icon && <Icon size={28} style={colorStyle} />}
      </div>
    </div>
  );
}
