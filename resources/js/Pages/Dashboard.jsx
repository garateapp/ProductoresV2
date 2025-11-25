import { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Badge } from '@/Components/ui/badge';
import { Truck, Factory, FileText, Award, FileCheck2, Zap } from 'lucide-react';

const formatNumber = (value) => Number(value ?? 0).toLocaleString('es-CL');
const formatDate = (value) => (value ? new Date(value).toLocaleDateString('es-CL') : 'S/F');

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
}) {
  useState('unused'); // placeholder to keep consistency if needed
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
                <h3 className="text-3xl font-semibold tracking-tight">Bienvenido(a) a Greenex</h3>
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
