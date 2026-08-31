import { Head, Link, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { ArrowLeft, History } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
  : '-'

const formatFecha = (value) => {
  if (!value) return '-'
  const parsed = new Date(String(value).length === 10 ? `${value}T00:00:00` : value)
  return isNaN(parsed) ? value : parsed.toLocaleDateString('es-CL')
}

export default function TechEquipmentHistory({ equipment }) {
  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Historial de Equipos Tecnológicos</h2>
          <Link href={route('inventory.tech-equipment-deliveries.index')}>
            <Button variant="outline">
              <ArrowLeft className="w-4 h-4 mr-2" />
              Volver a actas
            </Button>
          </Link>
        </div>
      }
    >
      <Head title="Historial de Equipos Tecnológicos" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
          {equipment.data.map((item) => {
            const history = item.deliveryItems || []
            const isAssigned = history.some((entry) => !entry.deliveryAct?.returned_at)

            return (
              <Card key={item.id}>
                <CardHeader className="flex flex-row items-start justify-between gap-4">
                  <div>
                    <CardTitle className="flex items-center gap-2">
                      {item.marca}
                      {isAssigned ? <Badge>Asignado</Badge> : <Badge variant="outline">Disponible</Badge>}
                    </CardTitle>
                    <p className="mt-1 text-sm text-slate-500">
                      <span className="font-mono">{item.numero_serie}</span>
                      {item.fecha ? ` · ${formatFecha(item.fecha)}` : ''}
                    </p>
                    {item.descripcion && <p className="mt-1 text-sm text-slate-600">{item.descripcion}</p>}
                  </div>
                  <History className="w-5 h-5 text-slate-400" />
                </CardHeader>
                <CardContent>
                  {history.length === 0 ? (
                    <p className="text-sm text-slate-400">Sin entregas registradas.</p>
                  ) : (
                    <div className="space-y-2">
                      {history.map((entry) => {
                        const act = entry.deliveryAct
                        if (!act) return null
                        return (
                          <div key={entry.id} className="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md border p-3 text-sm">
                            <div className="font-medium">Entrega <span className="font-mono">{act.codigo}</span></div>
                            <div className="text-slate-600">{formatDate(act.delivered_at)}</div>
                            <div className="text-slate-600">{act.person_name}{act.person_rut ? ` · ${act.person_rut}` : ''}</div>
                            <div className="text-slate-600">{act.departamento || '-'}{act.cargo ? ` · ${act.cargo}` : ''}</div>
                            <Badge variant="outline">{act.condicion === 'nuevo' ? 'Nuevo' : 'Usado'}</Badge>
                            {act.returned_at ? (
                              <Badge className="ml-auto">Devuelto {formatDate(act.returned_at)}</Badge>
                            ) : (
                              <Badge variant="outline" className="ml-auto">Pendiente devolución</Badge>
                            )}
                          </div>
                        )
                      })}
                    </div>
                  )}
                </CardContent>
              </Card>
            )
          })}

          {equipment.data.length === 0 && (
            <Card>
              <CardContent className="text-center text-slate-400 py-8">
                No hay equipos registrados.
              </CardContent>
            </Card>
          )}

          {equipment.links && (
            <div className="flex flex-wrap gap-1">
              {equipment.links.map((link, index) =>
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
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
