import { useState } from 'react'
import { Head, Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent } from '@/Components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { ArrowLeft, Check, Copy, FileText } from 'lucide-react'

const formatDate = (value) => value
  ? new Date(value).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
  : '-'

const formatQuantity = (value) => Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })

const DELIVERY_SIGNATURE_SRC = '/img/firma_entrega_materiales.png'

export default function PersonDeliveryShow({ delivery }) {
  const [copied, setCopied] = useState(false)
  const [deliverySignatureAvailable, setDeliverySignatureAvailable] = useState(true)

  const reference = delivery.numero_referencia ? String(delivery.numero_referencia) : null

  const copyReference = async () => {
    if (!reference) {
      return
    }

    await navigator.clipboard.writeText(reference)
    setCopied(true)
    window.setTimeout(() => setCopied(false), 2000)
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4 print:hidden">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">
            Acta {reference ? `N° ${reference}` : delivery.codigo}
          </h2>
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
      <Head title={reference ? `Acta N° ${reference}` : `Acta ${delivery.codigo}`} />

      <div className="py-10 print:py-0">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8 print:max-w-none print:px-0">
          <Card className="print:border-0 print:shadow-none">
            <CardContent className="p-8 print:p-0">
              <div className="space-y-8 text-slate-900">
                <div className="flex flex-col gap-4 border-b pb-6 sm:flex-row sm:items-start sm:justify-between">
                  <div>
                    <p className="text-sm uppercase tracking-wide text-slate-500">Acta de Entrega de Materiales</p>
                    {reference && (
                      <div className="mt-2 inline-flex items-center gap-3 rounded-md border-2 border-slate-900 px-4 py-2 print:border-slate-500">
                        <div>
                          <p className="text-[10px] uppercase tracking-widest text-slate-500">N° Referencia SAP</p>
                          <p className="text-3xl font-bold tabular-nums leading-none">{reference}</p>
                        </div>
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={copyReference}
                          title="Copiar referencia"
                          className="print:hidden"
                        >
                          {copied ? <Check className="w-4 h-4 text-green-600" /> : <Copy className="w-4 h-4" />}
                        </Button>
                      </div>
                    )}
                    <p className="mt-2 font-mono text-xs text-slate-500">{delivery.codigo}</p>
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
                  <Info label="Cargo" value={delivery.person_position || '-'} />
                  <Info label="Área" value={delivery.person_area || '-'} />
                  <Info label="Ubicación origen" value={delivery.origin_location?.nombre || '-'} />
                  <Info
                    label="Centro de costo"
                    value={delivery.cost_center ? `${delivery.cost_center.codigo} · ${delivery.cost_center.nombre}` : '-'}
                  />
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
                    <p className="text-xs uppercase tracking-wide text-slate-500">Firma responsable de entrega</p>
                    <div className="mt-3 flex h-40 items-center justify-center border-b">
                      {deliverySignatureAvailable ? (
                        <img
                          src={DELIVERY_SIGNATURE_SRC}
                          alt="Firma responsable de entrega"
                          className="max-h-36 max-w-full object-contain"
                          onError={() => setDeliverySignatureAvailable(false)}
                        />
                      ) : (
                        <span className="text-sm text-slate-400">Firma no cargada</span>
                      )}
                    </div>
                    <p className="mt-3 text-center text-sm font-medium">{delivery.creator?.name || '-'}</p>
                  </div>
                </div>

                <div className="rounded-md border p-4">
                  <p className="text-xs uppercase tracking-wide text-slate-500">Trazabilidad</p>
                  <dl className="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
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
