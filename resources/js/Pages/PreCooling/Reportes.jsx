import { Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Download } from 'lucide-react'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

export default function Reportes({ estadoTuneles, saldosCamaras }) {
  const totales = estadoTuneles.reduce(
    (acc, t) => ({
      total: acc.total + t.total,
      ingresado: acc.ingresado + t.ingresado,
      iniciado: acc.iniciado + t.iniciado,
      salido: acc.salido + t.salido,
      cajas: acc.cajas + t.cajas,
    }),
    { total: 0, ingresado: 0, iniciado: 0, salido: 0, cajas: 0 }
  )

  const totalCajasSaldos = saldosCamaras.reduce((acc, c) => acc + c.cajas, 0)
  const totalSaldos = saldosCamaras.reduce((acc, c) => acc + c.total, 0)

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Reportes</h2>
        </div>
      }
    >
      <Head title="Prefrío · Reportes" />

      <div className="py-12">
        <div className="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">Estado de túneles</CardTitle>
              <a href={route('prefrio.reportes.exportar', { tipo: 'estado-tuneles' })}>
                <Button variant="secondary" size="sm">
                  <Download className="w-4 h-4 mr-1" /> Exportar CSV
                </Button>
              </a>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Túnel</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead>Capacidad</TableHead>
                    <TableHead className="text-center">Ingresadas</TableHead>
                    <TableHead className="text-center">Iniciadas</TableHead>
                    <TableHead className="text-center">Salidas</TableHead>
                    <TableHead className="text-center">Total</TableHead>
                    <TableHead className="text-right">Cajas</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {estadoTuneles.map((t) => (
                    <TableRow key={t.id}>
                      <TableCell>
                        <div className="font-semibold">{t.codigo}</div>
                        <div className="text-xs text-gray-500">{t.nombre}</div>
                      </TableCell>
                      <TableCell>
                        {t.activo ? (
                          <Badge className="bg-green-100 text-green-800">Activo</Badge>
                        ) : (
                          <Badge className="bg-gray-100 text-gray-600">Inactivo</Badge>
                        )}
                      </TableCell>
                      <TableCell>{t.capacidad}</TableCell>
                      <TableCell className="text-center">{t.ingresado}</TableCell>
                      <TableCell className="text-center">{t.iniciado}</TableCell>
                      <TableCell className="text-center">{t.salido}</TableCell>
                      <TableCell className="text-center font-semibold">{t.total}</TableCell>
                      <TableCell className="text-right">{t.cajas}</TableCell>
                    </TableRow>
                  ))}
                  {estadoTuneles.length > 0 && (
                    <TableRow className="font-semibold bg-gray-50">
                      <TableCell colSpan={3}>Total</TableCell>
                      <TableCell className="text-center">{totales.ingresado}</TableCell>
                      <TableCell className="text-center">{totales.iniciado}</TableCell>
                      <TableCell className="text-center">{totales.salido}</TableCell>
                      <TableCell className="text-center">{totales.total}</TableCell>
                      <TableCell className="text-right">{totales.cajas}</TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
              {estadoTuneles.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-6">Sin datos de túneles.</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle className="text-base">
                Saldos por cámara
                {totalSaldos > 0 && (
                  <span className="ml-2 text-xs font-normal text-gray-500">
                    {totalSaldos} saldos · {totalCajasSaldos} cajas
                  </span>
                )}
              </CardTitle>
              <a href={route('prefrio.reportes.exportar', { tipo: 'saldos' })}>
                <Button variant="secondary" size="sm">
                  <Download className="w-4 h-4 mr-1" /> Exportar CSV
                </Button>
              </a>
            </CardHeader>
            <CardContent className="space-y-4">
              {saldosCamaras.length === 0 && (
                <p className="text-sm text-gray-500 text-center py-6">Sin saldos registrados.</p>
              )}
              {saldosCamaras.map((camara) => (
                <div key={camara.camara_id} className="rounded-md border">
                  <div className="flex items-center justify-between px-3 py-2 bg-gray-50 rounded-t-md">
                    <span className="font-semibold text-sm">
                      {camara.codigo} · {camara.nombre}
                    </span>
                    <span className="text-xs text-gray-500">
                      {camara.total} saldos · {camara.cajas} cajas · {camara.pallets} pallets
                    </span>
                  </div>
                  <div className="overflow-x-auto">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Ubicación</TableHead>
                          <TableHead>Nivel</TableHead>
                          <TableHead>Folio</TableHead>
                          <TableHead>Proceso</TableHead>
                          <TableHead>Cajas</TableHead>
                          <TableHead>Especie</TableHead>
                          <TableHead>Variedad</TableHead>
                          <TableHead>Productor</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {camara.saldos.map((s, i) => (
                          <TableRow key={i}>
                            <TableCell className="font-mono text-xs">
                              {s.banda}/{s.fila}/{s.columna}/{s.altura}
                            </TableCell>
                            <TableCell>
                              <Badge variant="secondary">{s.nivel}</Badge>
                            </TableCell>
                            <TableCell className="font-mono">{s.folio}</TableCell>
                            <TableCell>{s.tipo_proceso || '—'}</TableCell>
                            <TableCell>{s.cajas ?? '—'}</TableCell>
                            <TableCell>{s.especie || '—'}</TableCell>
                            <TableCell>{s.variedad || '—'}</TableCell>
                            <TableCell>{s.productor || '—'}</TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                </div>
              ))}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
