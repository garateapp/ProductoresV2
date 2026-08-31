import { Head, Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { ArrowRight } from 'lucide-react'

const Kpi = ({ label, value, sub, accent }) => (
  <Card>
    <CardContent className="pt-6">
      <div className="text-xs text-gray-500 uppercase tracking-wide">{label}</div>
      <div className={`mt-1 text-3xl font-bold ${accent || 'text-gray-900'}`}>{value}</div>
      {sub && <div className="text-xs text-gray-500 mt-1">{sub}</div>}
    </CardContent>
  </Card>
)

export default function Dashboard({ resumen }) {
  const { tuneles, camaras, cargas, saldos } = resumen

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Dashboard</h2>
        </div>
      }
    >
      <Head title="Prefrío · Dashboard" />

      <div className="py-12">
        <div className="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Kpi
              label="Túneles"
              value={`${tuneles.con_carga_activa} / ${tuneles.activos}`}
              sub={`${tuneles.total} registrados · ${tuneles.con_carga_activa} con carga activa`}
              accent="text-greenex-vibrant-green"
            />
            <Kpi
              label="Cargas"
              value={cargas.total}
              sub={`${cargas.ingresado} ingresadas · ${cargas.iniciado} iniciadas · ${cargas.salido} salidas`}
              accent="text-greenex-orange"
            />
            <Kpi
              label="Folios"
              value={cargas.folios}
              sub="folios registrados en cargas"
              accent="text-greenex-vibrant-green"
            />
            <Kpi
              label="Saldos en cámaras"
              value={saldos.total}
              sub={`${saldos.cajas} cajas · ${saldos.pallets} pallets`}
              accent="text-sky-600"
            />
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Card>
              <CardContent className="pt-6">
                <div className="text-xs text-gray-500 uppercase tracking-wide">Cámaras</div>
                <div className="mt-1 text-3xl font-bold text-gray-900">
                  {camaras.activas} / {camaras.total}
                </div>
                <div className="text-xs text-gray-500 mt-1">cámaras activas</div>
              </CardContent>
            </Card>
            <Card>
              <CardContent className="pt-6 flex flex-col justify-center gap-3">
                <div className="text-sm text-gray-600">Accesos rápidos del módulo Prefrío.</div>
                <div className="flex flex-wrap gap-2">
                  <Link href={route('prefrio.matriz.tunel')}>
                    <Button size="sm">Matriz de túneles <ArrowRight className="w-4 h-4 ml-1" /></Button>
                  </Link>
                  <Link href={route('prefrio.matriz.camara')}>
                    <Button size="sm" variant="secondary">Matriz de cámaras <ArrowRight className="w-4 h-4 ml-1" /></Button>
                  </Link>
                  <Link href={route('prefrio.reportes.index')}>
                    <Button size="sm" variant="outline">Reportes <ArrowRight className="w-4 h-4 ml-1" /></Button>
                  </Link>
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
