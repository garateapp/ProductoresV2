import { Head, Link, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Eye, FileText, Plus } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'short', timeStyle: 'short' })
  : '-'

const condicionLabel = (value) => (value === 'nuevo' ? 'Nuevo' : 'Usado')

export default function TechEquipmentDeliveriesIndex({ acts }) {
  return (
    <AuthenticatedLayout
      header={
        <div className="flex flex-wrap items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Actas de Entrega de Equipos Tecnológicos</h2>
          <div className="flex gap-2">
            <Link href={route('inventory.tech-equipment-deliveries.history')}>
              <Button variant="outline">
                <Eye className="w-4 h-4 mr-2" />
                Historial de equipos
              </Button>
            </Link>
            <Link href={route('inventory.tech-equipment-deliveries.create')}>
              <Button>
                <Plus className="w-4 h-4 mr-2" />
                Nueva Acta
              </Button>
            </Link>
          </div>
        </div>
      }
    >
      <Head title="Actas de Entrega de Equipos" />

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
                    <TableHead>RUT</TableHead>
                    <TableHead>Departamento / Cargo</TableHead>
                    <TableHead>Condición</TableHead>
                    <TableHead>Equipos</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {acts.data.map((act) => (
                    <TableRow key={act.id}>
                      <TableCell className="font-mono">{act.codigo}</TableCell>
                      <TableCell>{formatDate(act.delivered_at)}</TableCell>
                      <TableCell className="font-medium">{act.person_name}</TableCell>
                      <TableCell className="font-mono">{act.person_rut || '-'}</TableCell>
                      <TableCell>
                        {act.departamento || '-'}{act.cargo ? ` · ${act.cargo}` : ''}
                      </TableCell>
                      <TableCell><Badge variant="outline">{condicionLabel(act.condicion)}</Badge></TableCell>
                      <TableCell>
                        <div className="max-w-xs space-y-1">
                          {act.items.map((item) => (
                            <div key={item.id} className="text-sm text-slate-700">
                              {item.equipment?.marca} · {item.equipment?.numero_serie}
                            </div>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell>
                        {act.returned_at ? <Badge>Devuelto</Badge> : <Badge variant="outline">Pendiente</Badge>}
                      </TableCell>
                      <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                          <Link href={route('inventory.tech-equipment-deliveries.show', act.id)}>
                            <Button variant="ghost" size="sm" title="Ver acta">
                              <Eye className="w-4 h-4" />
                            </Button>
                          </Link>
                          <a href={route('inventory.tech-equipment-deliveries.pdf', act.id)} target="_blank" rel="noopener noreferrer">
                            <Button variant="ghost" size="sm" title="Ver PDF">
                              <FileText className="w-4 h-4" />
                            </Button>
                          </a>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {acts.data.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={9} className="text-center text-slate-400 py-8">
                        No hay actas registradas.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
              {acts.links && (
                <div className="mt-4 flex flex-wrap gap-1">
                  {acts.links.map((link, index) =>
                    link.url ? (
                      <Button key={index} variant={link.active ? 'default' : 'outline'} size="sm" onClick={() => router.visit(link.url)}>
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                      </Button>
                    ) : (
                      <span key={index} className="px-1 text-slate-400">{link.label}</span>
                    ),
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
