import { Head, Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { ArrowLeft, FileText } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
  : '-'

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

export default function PersonDeliveryShow({ delivery }) {
  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4 print:hidden">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Acta {delivery.codigo}</h2>
          <div className="flex gap-2">
            <Link href={route('inventory.person-deliveries.index')}>
              <Button variant="outline" type="button">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Volver
              </Button>
            </Link>
            <a href={route('inventory.person-deliveries.pdf', delivery.id)} target="_blank" rel="noopener noreferrer">
              <Button type="button">
                <FileText className="w-4 h-4 mr-2" />
                Ver PDF
              </Button>
            </a>
          </div>
        </div>
      }
    >
      <Head title={`Acta ${delivery.codigo}`} />

      <div className="py-10 print:py-0">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8 print:max-w-none print:px-0">
          <Card className="print:border-0 print:shadow-none">
            <CardContent className="p-8 print:p-0">
              <div className="space-y-8 text-slate-900">
                <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="text-sm uppercase tracking-wide text-slate-500">Acta de Entrega de Materiales</p>
                    <h1 className="text-3xl font-semibold">{delivery.codigo}</h1>
                  </div>
                  <div className="text-sm text-slate-600 sm:text-right">
                    <div>{formatDate(delivery.delivered_at)}</div>
                    {delivery.movement && (
                      <div className="mt-2">
                        Movimiento <span className="font-mono">{delivery.movement.folio}</span>
                      </div>
                    )}
                  </div>
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                  <Info label="Persona que recibe" value={delivery.person_name} />
                  <Info label="Cargo" value={delivery.person_position} />
                  <Info label="Ubicación origen" value={delivery.origin_location?.nombre || '-'} />
                  <Info label="Entregado por" value={delivery.creator?.name || '-'} />
                </div>

                <div className="overflow-hidden rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Código</TableHead>
                        <TableHead>Material</TableHead>
                        <TableHead>Unidad</TableHead>
                        <TableHead className="text-right">Cantidad</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {delivery.items.map((item) => (
                        <TableRow key={item.id}>
                          <TableCell className="font-mono">{item.material?.codigo || '-'}</TableCell>
                          <TableCell>{item.material?.nombre || '-'}</TableCell>
                          <TableCell>{item.material?.unit || '-'}</TableCell>
                          <TableCell className="text-right">{formatQuantity(item.cantidad)}</TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>

                {delivery.notes && (
                  <div className="rounded-md border bg-slate-50 p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Observación</p>
                    <p className="mt-1 whitespace-pre-wrap">{delivery.notes}</p>
                  </div>
                )}

                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  <div className="rounded-md border p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Firma receptor</p>
                    <div className="mt-3 flex h-40 items-center justify-center border-b">
                      {delivery.signature_data_url ? (
                        <img src={delivery.signature_data_url} alt="Firma receptor" className="max-h-36 max-w-full object-contain" />
                      ) : (
                        <span className="text-sm text-slate-400">Sin firma</span>
                      )}
                    </div>
                    <p className="mt-3 text-center text-sm font-medium">{delivery.person_name}</p>
                  </div>

                  <div className="rounded-md border p-4">
                    <p className="text-xs uppercase tracking-wide text-slate-500">Trazabilidad</p>
                    <dl className="mt-3 space-y-3 text-sm">
                      <div>
                        <dt className="text-slate-500">Estado del movimiento</dt>
                        <dd>{delivery.movement ? <Badge variant="outline">{delivery.movement.estado}</Badge> : '-'}</dd>
                      </div>
                      <div>
                        <dt className="text-slate-500">Hash ledger</dt>
                        <dd className="break-all font-mono text-xs">{delivery.movement?.ledger_hash || '-'}</dd>
                      </div>
                    </dl>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

function Info({ label, value }) {
  return (
    <div className="rounded-md border p-4">
      <p className="text-xs uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 text-lg font-medium">{value}</p>
    </div>
  )
}
