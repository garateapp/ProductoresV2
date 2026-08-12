import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { Alert, AlertTitle, AlertDescription } from '@/Components/ui/alert';
import Chart from 'react-apexcharts';
import { Truck, Factory, FileText, Award, FileCheck2, Zap,AlertCircle } from 'lucide-react';

const formatNumber = (value) => Number(value ?? 0).toLocaleString('es-CL');
const formatDate = (value) => (value ? new Date(value).toLocaleDateString('es-CL') : 'S/F');

const sortCalibres = (categories = [], species = '') => {
  const order = ['L', 'XL', 'J', '2J', '3J', '4J', '5J', '6J', '7J'];
  const isCherry = typeof species === 'string' && (species.toLowerCase().includes('cherr') || species.toLowerCase().includes('cereza'));
  const normalized = categories.map((c) => String(c ?? '').toUpperCase().trim());

  const position = (val) => {
    const idx = order.indexOf(val);
    if (isCherry && idx !== -1) return idx;
    if (!Number.isNaN(Number(val))) return 1000 + Number(val);
    return 2000;
  };

  return [...normalized].sort((a, b) => {
    const pa = position(a);
    const pb = position(b);
    if (pa === pb) return a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' });
    return pa - pb;
  });
};

const StatCard = ({ icon: Icon, title, value, accent = 'from-emerald-500/15 to-white' }) => (
  <Card className="border border-emerald-50 bg-gradient-to-br text-emerald-900 shadow-sm">
    <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
      <CardTitle className="text-sm font-medium text-emerald-900/70">{title}</CardTitle>
      <div className="rounded-full bg-emerald-100/70 p-2 text-emerald-700">
        <Icon className="h-5 w-5" />
      </div>
    </CardHeader>
    <CardContent>
      <div className="text-3xl font-semibold tracking-tight text-gray-900">{value}</div>
    </CardContent>
  </Card>
);

const EmptyState = ({ message }) => (
  <div className="rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
    {message}
  </div>
);

export default function Dashboard({
  auth,
  isProducer = false,
  recepciones = [],
  procesos = [],
  contracts = [],
  certifications = [],
  stats = {},
  charts = {},
}) {
  const [selectedCalibreSpecies, setSelectedCalibreSpecies] = useState('');
  const [selectedCalibreVariety, setSelectedCalibreVariety] = useState('');
  const calibreData = charts?.calibreCurve ?? {};
  const calibreCategoriesAll = Array.isArray(calibreData.categories) ? calibreData.categories.map((c) => String(c ?? '')) : [];
  const calibreSeries = Array.isArray(calibreData.series) ? calibreData.series : [];
  const calibreSpecies = Array.isArray(calibreData.species) ? calibreData.species.filter(Boolean) : [];
  const calibreVarietiesBySpecies = calibreData.varietiesBySpecies || {};
  const calibreCalibresBySpecies = calibreData.calibresBySpecies || {};

  const speciesColor = (name = '') => {
    const n = String(name).toLowerCase();
    if (n.includes('cherr') || n.includes('cereza')) return '#7F1F38';
    if (n.includes('apple') || n.includes('manzana')) return 'var(--corp-green)';
    if (n.includes('pear') || n.includes('pera')) return '#53A318';
    if (n.includes('mandarin') || n.includes('mandarina')) return 'var(--corp-orange)';
    if (n.includes('orange') || n.includes('naranja')) return '#ff8b24';
    if (n.includes('nectarin') || n.includes('nectarina')) return '#E91E63';
    if (n.includes('peach') || n.includes('durazno')) return '#F06292';
    if (n.includes('plum') || n.includes('ciruela')) return '#7E57C2';
    if (n.includes('membrill')) return '#FDD835';
    return '#607D8B';
  };

  const activeCalibreSpecies = selectedCalibreSpecies || calibreSpecies[0] || '';
  const calibreVarietyOptions = useMemo(() => {
    const options = Array.isArray(calibreVarietiesBySpecies?.[activeCalibreSpecies]) ? calibreVarietiesBySpecies[activeCalibreSpecies] : [];
    return options.filter(Boolean);
  }, [calibreVarietiesBySpecies, activeCalibreSpecies]);
  const activeCalibreVariety = calibreVarietyOptions.includes(selectedCalibreVariety) ? selectedCalibreVariety : '';
  const activeCalibreCategories = useMemo(() => {
    const speciesCalibres = Array.isArray(calibreCalibresBySpecies?.[activeCalibreSpecies])
      ? calibreCalibresBySpecies[activeCalibreSpecies].map((c) => String(c ?? ''))
      : [];
    const fallback = speciesCalibres.length ? speciesCalibres : calibreCategoriesAll;
    return sortCalibres(fallback, activeCalibreSpecies);
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
          data: activeCalibreCategories.map((cat) => {
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
    const kilos = activeCalibreCategories.map((cat, idx) =>
      filteredCalibreSeries.reduce((sum, serie) => sum + (serie.data[idx] || 0), 0)
    );
    const total = kilos.reduce((a, b) => a + b, 0);
    const percent = total > 0 ? kilos.map((v) => (v * 100) / total) : activeCalibreCategories.map(() => 0);
    return { kilos, percent, total };
  }, [activeCalibreCategories, filteredCalibreSeries]);

  const receptionSpark = useMemo(() => recepciones.map((item) => item.peso_neto ?? 0), [recepciones]);
  const processSpark = useMemo(() => procesos.map((item) => item.kilos_netos ?? 0), [procesos]);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Dashboard</h2>}
    >
      <Head title="Dashboard" />

      {!isProducer ? (
        <div className="py-12">
          <div className="mx-auto max-w-6xl overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-600 via-emerald-500 to-emerald-400 shadow-xl">
            <div className="grid gap-10 bg-white/5 p-10 text-white lg:grid-cols-3 lg:p-12">
              <div className="lg:col-span-2 space-y-4">
                <h3 className="text-3xl font-semibold tracking-tight">Bienvenido(a) a Gárate Hermanos</h3>
                <p className="text-white/80 text-base leading-relaxed">
                  Explora los módulos desde el menú superior. Si eres productor, solicita tu acceso para ver tu panel con
                  recepciones, procesos y reportes en un solo lugar.
                </p>
              </div>
              <div className="grid grid-cols-2 gap-4 lg:grid-cols-1">
                <div className="rounded-2xl bg-white/15 p-4 backdrop-blur">
                  <p className="text-xs uppercase tracking-wide text-white/70">Recepciones</p>
                  <p className="text-xl font-semibold">Control de calidad</p>
                </div>
                <div className="rounded-2xl bg-white/15 p-4 backdrop-blur">
                  <p className="text-xs uppercase tracking-wide text-white/70">Procesos</p>
                  <p className="text-xl font-semibold">Reportes y envíos</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      ) : (
        <div className="py-8">
          <div className="mx-auto max-w-6xl space-y-10 px-4 sm:px-6 lg:px-8">
            <Alert className="border-emerald-200 bg-emerald-50 text-emerald-900">
          <AlertCircle className="h-4 w-4 text-emerald-700" />
          <AlertTitle>Actualizaciones recientes</AlertTitle>
          <AlertDescription>
            <ul className="list-disc pl-5 space-y-1">
              <li>Se agrega gráfico de curva de calibre exportación por especie.</li>
              <li>Mejoras en buscadores.</li>
              <li>Optimización de carga.</li>
            </ul>
          </AlertDescription>
        </Alert>
            <section className="relative overflow-hidden rounded-3xl border bg-gradient-to-br from-emerald-600 via-emerald-500 to-emerald-400 p-8 text-white shadow-lg">
              <div className="relative z-10 flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                  <p className="text-sm uppercase tracking-wide text-white/70">Productor</p>
                  <h1 className="mt-1 text-3xl font-semibold">{auth.user?.name}</h1>
                  <p className="mt-2 max-w-2xl text-sm text-white/80">
                    Visualiza tus recepciones, procesos, contratos y certificaciones en un solo lugar.
                    Usa las pestañas interactivas para navegar entre secciones.
                  </p>
                </div>
                <div className="flex items-center gap-3 rounded-2xl bg-white/15 px-6 py-3 backdrop-blur">
                  <Zap className="h-8 w-8" />
                  <div>
                    <p className="text-xs uppercase tracking-wide text-white/70">Esta temporada</p>
                    <p className="text-lg font-semibold">{formatNumber(stats?.totalKilos)} kg recepcionados</p>
                  </div>
                </div>
              </div>
              <div className="pointer-events-none absolute -left-8 top-10 h-24 w-24 rounded-full bg-white/20 blur-3xl" />
              <div className="pointer-events-none absolute -right-6 bottom-6 h-24 w-24 rounded-full bg-white/20 blur-3xl" />
            </section>

            <section className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              <StatCard icon={Truck} title="Recepciones" value={formatNumber(stats?.totalRecepciones)} />
              <StatCard icon={Factory} title="Procesos" value={formatNumber(stats?.totalProcesos)} />
              <StatCard icon={FileText} title="Contratos" value={formatNumber(stats?.activeContracts)} />
              <StatCard icon={Award} title="Certificaciones" value={formatNumber(stats?.activeCertifications)} />
              <StatCard icon={Truck} title="Kg recepcionados" value={`${formatNumber(stats?.totalKilos)} kg`} />
              <StatCard icon={Factory} title="Kg procesados" value={`${formatNumber(stats?.totalKilosProcesados)} kg`} />
            </section>

            <section className="rounded-3xl border bg-white p-6 shadow-sm">
              <Tabs defaultValue="recepciones">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                  <div>
                    <h3 className="text-lg font-semibold text-gray-800">Actividad reciente</h3>
                    <p className="text-sm text-gray-500">Datos actualizados directamente desde tus movimientos</p>
                  </div>
                  <TabsList className="bg-emerald-50">
                    <TabsTrigger value="recepciones">Recepciones</TabsTrigger>
                    <TabsTrigger value="procesos">Procesos</TabsTrigger>
                  </TabsList>
                </div>
                <TabsContent value="recepciones" className="mt-6">
                  {recepciones.length === 0 ? (
                    <EmptyState message="Aún no registras recepciones." />
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                          <tr className="text-left text-xs uppercase tracking-wide text-gray-500">
                            <th className="px-3 py-2">N° Lote</th>
                            <th className="px-3 py-2">Fecha</th>
                            <th className="px-3 py-2">Especie / Variedad</th>
                            <th className="px-3 py-2 text-right">Kilos</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                          {recepciones.map((item) => (
                            <tr key={item.id} className="hover:bg-emerald-50/40">
                              <td className="px-3 py-3 font-medium text-gray-800">{item.numero_g_recepcion}</td>
                              <td className="px-3 py-3 text-gray-600">{formatDate(item.fecha_g_recepcion)}</td>
                              <td className="px-3 py-3">
                                <p className="font-semibold text-gray-800">{item.n_especie}</p>
                                <p className="text-xs text-gray-500">{item.n_variedad}</p>
                              </td>
                              <td className="px-3 py-3 text-right font-semibold text-emerald-700">
                                {formatNumber(item.peso_neto)} kg
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </TabsContent>
                <TabsContent value="procesos" className="mt-6">
                  {procesos.length === 0 ? (
                    <EmptyState message="Aún no registras procesos." />
                  ) : (
                    <div className="overflow-x-auto">
                      <table className="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                          <tr className="text-left text-xs uppercase tracking-wide text-gray-500">
                            <th className="px-3 py-2">Proceso</th>
                            <th className="px-3 py-2">Fecha</th>
                            <th className="px-3 py-2">Especie / Variedad</th>
                            <th className="px-3 py-2 text-right">Kg netos</th>
                          </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-100">
                          {procesos.map((item) => (
                            <tr key={item.id} className="hover:bg-emerald-50/40">
                              <td className="px-3 py-3 font-medium text-gray-800">{item.n_proceso}</td>
                              <td className="px-3 py-3 text-gray-600">{formatDate(item.fecha)}</td>
                              <td className="px-3 py-3">
                                <p className="font-semibold text-gray-800">{item.especie}</p>
                                <p className="text-xs text-gray-500">{item.variedad}</p>
                              </td>
                              <td className="px-3 py-3 text-right font-semibold text-emerald-700">
                                {formatNumber(item.kilos_netos)} kg
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  )}
                </TabsContent>
              </Tabs>
            </section>

            <section className="rounded-3xl border bg-white p-6 shadow-sm">
              <div className="flex flex-wrap items-center justify-between gap-4 mb-4">
                <div>
                  <h3 className="text-lg font-semibold text-gray-800">Curva de Calibre Exportación  por especie</h3>
                  <p className="text-sm text-gray-500">Barras en kilos y línea de % para cada calibre.</p>
                </div>
                <div className="flex flex-wrap gap-3">
                  <div className="flex flex-col text-sm">
                    <span className="text-gray-600 mb-1">Especie</span>
                    <select
                      className="border rounded px-2 py-1 min-w-[160px]"
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
                      className="border rounded px-2 py-1 min-w-[160px]"
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
              </div>

              {activeCalibreCategories.length && filteredCalibreSeries.length ? (
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
                    colors: [speciesColor(activeCalibreSpecies), '#ef4444'],
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
                  height={280}
                />
              ) : (
                <EmptyState message="Sin datos de calibre disponibles." />
              )}
            </section>

            <section className="grid gap-6 lg:grid-cols-2">
              <Card className="border-emerald-100 shadow-sm">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-lg">Contratos activos</CardTitle>
                    <Badge variant="secondary">{contracts.length} vigentes</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {contracts.length === 0 ? (
                    <EmptyState message="Aún no registras contratos." />
                  ) : (
                    contracts.map((contract) => (
                      <div
                        key={contract.id}
                        className="rounded-2xl border border-emerald-50 bg-emerald-50/60 p-4 transition hover:border-emerald-200"
                      >
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <p className="text-sm text-gray-500">Fecha de firma</p>
                            <p className="font-semibold text-gray-800">{formatDate(contract.fecha_contrato)}</p>
                          </div>
                          <Badge className="bg-emerald-600 text-white">
                            Vence {formatDate(contract.vencimiento)}
                          </Badge>
                        </div>
                        {contract.comision && (
                          <p className="mt-2 text-sm text-gray-600">Comisión: {contract.comision}%</p>
                        )}
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>

              <Card className="border-indigo-100 shadow-sm">
                <CardHeader>
                  <div className="flex items-center justify-between">
                    <CardTitle className="text-lg">Certificaciones</CardTitle>
                    <Badge variant="secondary">{certifications.length} registradas</Badge>
                  </div>
                </CardHeader>
                <CardContent className="space-y-4">
                  {certifications.length === 0 ? (
                    <EmptyState message="Aún no registras certificaciones." />
                  ) : (
                    certifications.map((cert) => (
                      <div
                        key={cert.id}
                        className="rounded-2xl border border-indigo-50 bg-indigo-50/60 p-4 transition hover:border-indigo-200"
                      >
                        <div className="flex items-center justify-between gap-3">
                          <div>
                            <p className="text-xs uppercase tracking-wide text-gray-500">
                              {cert.certificate_type?.name ?? 'Certificación'}
                            </p>
                            <p className="text-base font-semibold text-gray-800">{cert.certificate_code ?? 'Sin código'}</p>
                          </div>
                          <Badge className="bg-indigo-600 text-white">
                            Expira {formatDate(cert.expiration_date)}
                          </Badge>
                        </div>
                        <p className="mt-1 text-xs text-gray-500">
                          Casa certificadora: {cert.certifying_house?.name ?? 'N/D'}
                        </p>
                      </div>
                    ))
                  )}
                </CardContent>
              </Card>
            </section>
          </div>
        </div>
      )}
    </AuthenticatedLayout>
  );
}
