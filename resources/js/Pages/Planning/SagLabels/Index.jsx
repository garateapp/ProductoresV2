import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Button } from '@/Components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import { Head, router, usePage } from '@inertiajs/react'

export default function Index({ auth, processes }) {
  const props = usePage().props
  const rows = processes?.data || []

  const sendLabel = (processId) => {
    router.post(route('planning.sag-labels.send', processId), {}, { preserveScroll: true })
  }

  return (
    <AuthenticatedLayout
      user={auth?.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
    >
      <Head title="Enviar etiqueta a SAG" />

      <div className="py-6">
        <div className="mx-auto max-w-7xl space-y-4 sm:px-6 lg:px-8">
          {props?.flash?.success ? (
            <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
              {props.flash.success}
            </div>
          ) : null}
          {props?.flash?.error ? (
            <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              {props.flash.error}
            </div>
          ) : null}

          <div className="rounded border bg-white p-6 shadow-sm">
            <h1 className="text-xl font-semibold text-gray-900">Enviar etiqueta a SAG</h1>
            <p className="mt-2 text-sm text-gray-600">
              Procesos disponibles: {processes?.total ?? 0}
            </p>

            <div className="mt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Proceso</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Turno</TableHead>
                    <TableHead>Especie</TableHead>
                    <TableHead>Productor</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-gray-500">
                        No hay procesos disponibles.
                      </TableCell>
                    </TableRow>
                  ) : rows.map((process) => {
                    const isConfirmed = process?.estado === 'CONFIRMADO'

                    return (
                      <TableRow key={process.id}>
                        <TableCell className="font-medium">#{process.id}</TableCell>
                        <TableCell>{process.fecha || '-'}</TableCell>
                        <TableCell>{process.shift?.codigo || process.shift?.nombre || '-'}</TableCell>
                        <TableCell>{process.especie || '-'}</TableCell>
                        <TableCell>{process.first_lot?.producer || '-'}</TableCell>
                        <TableCell>{process.estado || '-'}</TableCell>
                        <TableCell className="text-right">
                          <Button
                            size="sm"
                            onClick={() => sendLabel(process.id)}
                            disabled={!isConfirmed}
                          >
                            Enviar etiqueta
                          </Button>
                        </TableCell>
                      </TableRow>
                    )
                  })}
                </TableBody>
              </Table>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
