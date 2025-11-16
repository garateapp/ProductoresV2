import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import Chart from 'react-apexcharts';
import { Users, Truck, Factory, ShieldCheck, FileText as FileIcon } from 'lucide-react';

export default function Dashboard({ auth, service, stats, recepciones = [], procesos = [], certifications = [], markets = [], contracts = [], charts = {} }) {
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

  const recepBySpecies = charts?.recepBySpecies ?? [];
  const procStackBySpecies = charts?.procStackBySpecies ?? [];
  const recepWeekly = charts?.recepWeeklyBySpecies ?? { weeks: [], series: [] };
  const pieTotals = charts?.procCategoryTotals ?? {};
  const weeks = Array.isArray(recepWeekly.weeks) ? recepWeekly.weeks : [];
  const lineColors = (recepWeekly.series || []).map(s => speciesColor(s.name));
  const axisLabelColor = '#1f2937';
  const shouldRotateLabels = weeks.length > 6;
  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard Servicio</h2>}>
      <Head title={`Dashboard Servicio - ${service.name}`} />
      <div className="container mx-auto py-6 space-y-6">
        <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
          <Stat title="Productores" value={stats?.producers ?? 0} color="orange" icon={Users} />
          <Stat title="Recepciones" value={stats?.recepciones ?? 0} color="green" icon={Truck} />
          <Stat title="Procesos" value={stats?.procesos ?? 0} color="orange" icon={Factory} />
          <Stat title="Certificaciones" value={stats?.certifications ?? 0} color="green" icon={ShieldCheck} />
          <Stat title="Contratos" value={stats?.contracts ?? 0} color="orange" icon={FileIcon} />
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card className="bg-[#e8f5e9]">
            <CardHeader><CardTitle className="text-[#2e7d32]">Recepciones por especie (Kg)</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'bar', toolbar: { show: false } },
                  xaxis: {
                    categories: recepBySpecies.map(i => i.especie),
                    labels: { show: true, style: { colors: '#374151', fontSize: '12px' } },
                    axisBorder: { show: true, color: '#e5e7eb' },
                    axisTicks: { show: true, color: '#e5e7eb' },
                  },
                  colors: recepBySpecies.map(i => speciesColor(i.especie)),
                  plotOptions: { bar: { distributed: true } },
                  dataLabels: { enabled: false },
                  yaxis: { labels: { show: true, style: { colors: '#374151', fontSize: '12px' }, formatter: (val) => Number(val).toLocaleString('es-CL') } },
                  grid: { borderColor: '#f3f4f6' },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                }}
                series={[{ name: 'Kg', data: recepBySpecies.map(i => Number(i.kilos) || 0) }]}
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
                    categories: procStackBySpecies.map(i => i.especie),
                    labels: { show: true, style: { colors: '#374151', fontSize: '12px' } },
                    axisBorder: { show: true, color: '#e5e7eb' },
                    axisTicks: { show: true, color: '#e5e7eb' },
                  },
                  plotOptions: { bar: { horizontal: false } },
                  colors: ['var(--corp-green)', 'var(--corp-orange)', '#1565c0', '#ef4444', '#9ca3af'],
                  dataLabels: { enabled: false },
                  yaxis: { labels: { show: true, style: { colors: '#374151', fontSize: '12px' }, formatter: (val) => Number(val).toLocaleString('es-CL') } },
                  legend: { position: 'bottom' },
                  grid: { borderColor: '#f3f4f6' },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                }}
                series={[
                  { name: 'Exportación', data: procStackBySpecies.map(i => Number(i.exp) || 0) },
                  { name: 'Comercial', data: procStackBySpecies.map(i => Number(i.comercial) || 0) },
                  { name: 'Merma', data: procStackBySpecies.map(i => Number(i.merma) || 0) },
                  { name: 'Desecho', data: procStackBySpecies.map(i => Number(i.desecho) || 0) },
                  { name: 'Sin procesar', data: procStackBySpecies.map(i => Number(i.sin_procesar ?? i.kilos_netos) || 0) },
                ]}
                type="bar"
                height={300}
              />
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card className="bg-[#f1f8e9]">
            <CardHeader><CardTitle className="text-[#2e7d32]">Kilos recepcionados por semana</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'line', toolbar: { show: false }, foreColor: axisLabelColor },
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
                      formatter: (val) => Number(val).toLocaleString('es-CL'),
                    },
                  },
                  grid: { borderColor: '#f3f4f6' },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                  legend: { labels: { colors: axisLabelColor } },
                }}
                series={recepWeekly.series || []}
                type="line"
                height={300}
              />
            </CardContent>
          </Card>
          <Card className="bg-[#fff8e1]">
            <CardHeader><CardTitle className="text-[#fe790f]">Distribución por categoría</CardTitle></CardHeader>
            <CardContent>
              <Chart
                options={{
                  chart: { type: 'pie' },
                  labels: ['Exportación', 'Comercial', 'Merma', 'Desecho', 'Sin procesar'],
                  colors: ['var(--corp-green)', 'var(--corp-orange)', '#1565c0', '#ef4444', '#9ca3af'],
                  dataLabels: { enabled: true, formatter: (val) => `${val.toFixed(1)}%` },
                  legend: { position: 'bottom' },
                  tooltip: { y: { formatter: (val) => `${Number(val).toLocaleString('es-CL')} Kg` } },
                }}
                series={[
                  Number(pieTotals.exp ?? 0),
                  Number(pieTotals.comercial ?? 0),
                  Number(pieTotals.merma ?? 0),
                  Number(pieTotals.desecho ?? 0),
                  Number(pieTotals.sin_procesar ?? pieTotals.kilos_netos ?? 0),
                ]}
                type="pie"
                height={300}
              />
            </CardContent>
          </Card>
        </div>
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader><CardTitle>Recepciones recientes</CardTitle></CardHeader>
            <CardContent>
              {Array.isArray(recepciones) && recepciones.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2 text-left">Lote</th>
                        <th className="px-3 py-2 text-left">Fecha</th>
                        <th className="px-3 py-2 text-left">Productor</th>
                        <th className="px-3 py-2 text-left">Especie</th>
                        <th className="px-3 py-2 text-left">Variedad</th>
                        <th className="px-3 py-2 text-right">Kilos</th>
                      </tr>
                    </thead>
                    <tbody>
                      {recepciones.map(r => (
                        <tr key={r.id} className="border-b">
                          <td className="px-3 py-2">{r.numero_g_recepcion}</td>
                          <td className="px-3 py-2">{new Date(r.fecha_g_recepcion).toLocaleDateString('es-CL')}</td>
                          <td className="px-3 py-2">{r.n_emisor}</td>
                          <td className="px-3 py-2">{r.n_especie}</td>
                          <td className="px-3 py-2">{r.n_variedad}</td>
                          <td className="px-3 py-2 text-right">{(r.peso_neto ?? 0).toLocaleString('es-CL')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin recepciones.</p>)}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>Procesos recientes</CardTitle></CardHeader>
            <CardContent>
              {Array.isArray(procesos) && procesos.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2 text-left">N° Proceso</th>
                        <th className="px-3 py-2 text-left">Fecha</th>
                        <th className="px-3 py-2 text-left">Especie</th>
                        <th className="px-3 py-2 text-left">Variedad</th>
                        <th className="px-3 py-2 text-right">Kg</th>
                      </tr>
                    </thead>
                    <tbody>
                      {procesos.map(p => (
                        <tr key={p.id} className="border-b">
                          <td className="px-3 py-2">{p.n_proceso}</td>
                          <td className="px-3 py-2">{new Date(p.fecha).toLocaleDateString('es-CL')}</td>
                          <td className="px-3 py-2">{p.especie}</td>
                          <td className="px-3 py-2">{p.variedad}</td>
                          <td className="px-3 py-2 text-right">{(p.kilos_netos ?? 0).toLocaleString('es-CL')}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin procesos.</p>)}
            </CardContent>
          </Card>
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <Card>
            <CardHeader><CardTitle>Certificaciones de Productores</CardTitle></CardHeader>
            <CardContent>
              {Array.isArray(certifications) && certifications.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2 text-left">Productor</th>
                        <th className="px-3 py-2 text-left">Certificadora</th>
                        <th className="px-3 py-2 text-left">Tipo</th>
                        <th className="px-3 py-2 text-left">Especie</th>
                        <th className="px-3 py-2 text-left">Vencimiento</th>
                        <th className="px-3 py-2 text-left">Documento</th>
                      </tr>
                    </thead>
                    <tbody>
                      {certifications.map(c => (
                        <tr key={c.id} className="border-b">
                          <td className="px-3 py-2">{c.user?.name}</td>
                          <td className="px-3 py-2">{c.certifying_house?.name}</td>
                          <td className="px-3 py-2">{c.certificate_type?.name}</td>
                          <td className="px-3 py-2">{c.especie?.name ?? '-'}</td>
                          <td className="px-3 py-2">{c.expiration_date ? new Date(c.expiration_date).toLocaleDateString('es-CL') : '-'}</td>
                          <td className="px-3 py-2">
                            {c.certificate_pdf_path ? (
                              <a href={c.certificate_pdf_path} target="_blank" rel="noopener noreferrer" className="text-indigo-600">Ver</a>
                            ) : ('-')}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin certificaciones.</p>)}
            </CardContent>
          </Card>
          <Card>
            <CardHeader><CardTitle>Acceso a Mercados</CardTitle></CardHeader>
            <CardContent>
              {Array.isArray(markets) && markets.length > 0 ? (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead className="bg-gray-50">
                      <tr>
                        <th className="px-3 py-2 text-left">Productor</th>
                        <th className="px-3 py-2 text-left">Especie</th>
                        <th className="px-3 py-2 text-left">País</th>
                        <th className="px-3 py-2 text-left">Estado</th>
                      </tr>
                    </thead>
                    <tbody>
                      {markets.map(m => (
                        <tr key={`${m.user_id}-${m.especie_id}-${m.country_id}`} className="border-b">
                          <td className="px-3 py-2">{m.user?.name}</td>
                          <td className="px-3 py-2">{m.especie?.name}</td>
                          <td className="px-3 py-2">{m.country?.name}</td>
                          <td className="px-3 py-2">{m.status ?? '-'}</td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              ) : (<p className="text-sm text-gray-500">Sin registros.</p>)}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader><CardTitle>Contratos</CardTitle></CardHeader>
          <CardContent>
            {Array.isArray(contracts) && contracts.length > 0 ? (
              <div className="overflow-x-auto">
                <table className="min-w-full text-sm">
                  <thead className="bg-gray-50">
                    <tr>
                      <th className="px-3 py-2 text-left">Productor</th>
                      <th className="px-3 py-2 text-left">Fecha</th>
                      <th className="px-3 py-2 text-left">Vencimiento</th>
                      <th className="px-3 py-2 text-left">Documento</th>
                    </tr>
                  </thead>
                  <tbody>
                    {contracts.map(c => (
                      <tr key={c.id} className="border-b">
                        <td className="px-3 py-2">{service.users.find(u => u.id === c.user_id)?.name ?? c.user_id}</td>
                        <td className="px-3 py-2">{c.fecha_contrato ? new Date(c.fecha_contrato).toLocaleDateString('es-CL') : '-'}</td>
                        <td className="px-3 py-2">{c.vencimiento ? new Date(c.vencimiento).toLocaleDateString('es-CL') : '-'}</td>
                        <td className="px-3 py-2">{c.contract_file_path ? (<a href={c.contract_file_path} target="_blank" rel="noopener noreferrer" className="text-indigo-600">Ver contrato</a>) : '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (<p className="text-sm text-gray-500">Sin contratos.</p>)}
          </CardContent>
        </Card>
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




