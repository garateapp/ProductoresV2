import { Head, Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Eye, FileText, Plus } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })
  : '-'

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

export default function PersonDeliveriesIndex({ deliveries }) {
  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Entrega de Materiales a Personas</h2>
          <Link href={route('inventory.person-deliveries.create')}>
            <Button>
              <Plus className="w-4 h-4 mr-2" />
              Nueva Entrega
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Entrega de Materiales a Personas" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Actas Generadas</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Acta</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Persona</TableHead>
                    <TableHead>Cargo</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Movimiento</TableHead>
                    <TableHead>Materiales</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {deliveries.data.map((delivery) => (
                    <TableRow key={delivery.id}>
                      <TableCell className="font-mono">{delivery.codigo}</TableCell>
                      <TableCell>{formatDate(delivery.delivered_at)}</TableCell>
                      <TableCell className="font-medium">{delivery.person_name}</TableCell>
                      <TableCell>{delivery.person_position}</TableCell>
                      <TableCell>{delivery.origin_location?.nombre || '-'}</TableCell>
                      <TableCell>
                        {delivery.movement ? (
                          <div className="space-y-1">
                            <div className="font-mono text-xs">{delivery.movement.folio}</div>
                            <Badge variant="outline">{delivery.movement.estado}</Badge>
                          </div>
                        ) : '-'}
                      </TableCell>
                      <TableCell>
                        <div className="max-w-sm space-y-1">
                          {delivery.items.map((item) => (
                            <div key={item.id} className="text-sm text-slate-700">
                              {item.material?.codigo} · {formatQuantity(item.cantidad)}
                            </div>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          <Link href={route('inventory.person-deliveries.show', delivery.id)}>
                            <Button variant="ghost" size="sm" title="Ver acta">
                              <Eye className="w-4 h-4" />
                            </Button>
                          </Link>
                          <a href={route('inventory.person-deliveries.pdf', delivery.id)} target="_blank" rel="noopener noreferrer">
                            <Button variant="ghost" size="sm" title="Ver PDF">
                              <FileText className="w-4 h-4" />
                            </Button>
                          </a>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
