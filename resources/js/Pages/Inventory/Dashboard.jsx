import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Badge } from '@/Components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

function number(value) {
  return Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 2 })
}

export default function InventoryDashboard({ summary, stocks = [], movements = [], productions = [], lowStockMaterials = [] }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="grid gap-4 md:grid-cols-5">
        <Card><CardHeader><CardTitle className="text-sm">Materiales</CardTitle></CardHeader><CardContent className="text-3xl font-bold">{summary?.materials ?? 0}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-sm">Ubicaciones activas</CardTitle></CardHeader><CardContent className="text-3xl font-bold">{summary?.locations ?? 0}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-sm">Movimientos hoy</CardTitle></CardHeader><CardContent className="text-3xl font-bold">{summary?.movements_today ?? 0}</CardContent></Card>
        <Card><CardHeader><CardTitle className="text-sm">Recepciones pendientes</CardTitle></CardHeader><CardContent className="text-3xl font-bold">{summary?.pending_receipts ?? 0}</CardContent></Card>
        <Card className={summary?.low_stock_count > 0 ? "border-red-500" : ""}>
          <CardHeader><CardTitle className="text-sm text-red-600">Alerta Stock Bajo</CardTitle></CardHeader>
          <CardContent className="text-3xl font-bold text-red-600">{summary?.low_stock_count ?? 0}</CardContent>
        </Card>
      </div>
...
      <Card>
        <CardHeader>
            <CardTitle className="text-red-600 flex items-center gap-2">Alertas de Stock Crítico</CardTitle>
        </CardHeader>
        <CardContent>
            {lowStockMaterials.length > 0 ? (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Material</TableHead>
                            <TableHead className="text-right">Stock Actual</TableHead>
                            <TableHead className="text-right">Stock Mínimo</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {lowStockMaterials.map(m => (
                            <TableRow key={m.id} className="bg-red-50">
                                <TableCell className="font-bold">{m.codigo} · {m.nombre}</TableCell>
                                <TableCell className="text-right text-red-700 font-bold">{number(m.sap_on_hand)}</TableCell>
                                <TableCell className="text-right">{number(m.stock_minimo)}</TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            ) : (
                <p className="text-center text-sm text-gray-500 py-4">Todo el stock está dentro de niveles normales.</p>
            )}
        </CardContent>
      </Card>

      <div className="grid gap-6 xl:grid-cols-2">
        <Card>
          <CardHeader>
            <CardTitle>Stock por ubicación</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Ubicación</TableHead>
                  <TableHead>Material</TableHead>
                  <TableHead className="text-right">Stock</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {stocks.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell>{item.location}</TableCell>
                    <TableCell>{item.material}</TableCell>
                    <TableCell className="text-right font-medium">{number(item.stock_actual)}</TableCell>
                  </TableRow>
                ))}
                {stocks.length === 0 && (
                  <TableRow><TableCell colSpan={3} className="py-10 text-center text-sm text-gray-500">Sin stock interno registrado.</TableCell></TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Últimos movimientos</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Folio</TableHead>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Origen / Destino</TableHead>
                  <TableHead>Estado</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {movements.map((movement) => (
                  <TableRow key={movement.id}>
                    <TableCell>
                      <div className="font-medium">{movement.folio}</div>
                      <div className="text-xs text-gray-500">{movement.fecha_movimiento}</div>
                    </TableCell>
                    <TableCell>{movement.tipo}</TableCell>
                    <TableCell>{movement.origen || '-'} → {movement.destino || '-'}</TableCell>
                    <TableCell><Badge variant="outline">{movement.estado}</Badge></TableCell>
                  </TableRow>
                ))}
                {movements.length === 0 && (
                  <TableRow><TableCell colSpan={4} className="py-10 text-center text-sm text-gray-500">Sin movimientos registrados.</TableCell></TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Producción reciente y desviación</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Fecha</TableHead>
                <TableHead>Turno / Línea</TableHead>
                <TableHead>Embalaje</TableHead>
                <TableHead className="text-right">Teórico</TableHead>
                <TableHead className="text-right">Real</TableHead>
                <TableHead className="text-right">Desviación</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {productions.map((item) => (
                <TableRow key={item.id}>
                  <TableCell>{item.fecha}</TableCell>
                  <TableCell>{item.turno} · {item.linea}</TableCell>
                  <TableCell>{item.embalaje}</TableCell>
                  <TableCell className="text-right">{number(item.teorico_total)}</TableCell>
                  <TableCell className="text-right">{number(item.real_total)}</TableCell>
                  <TableCell className={`text-right font-medium ${Number(item.desviacion_total) > 0 ? 'text-red-600' : 'text-emerald-700'}`}>{number(item.desviacion_total)}</TableCell>
                </TableRow>
              ))}
              {productions.length === 0 && (
                <TableRow><TableCell colSpan={6} className="py-10 text-center text-sm text-gray-500">Sin producciones registradas.</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  )
}

InventoryDashboard.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario y consumo de materiales</h2>}
  />
)
