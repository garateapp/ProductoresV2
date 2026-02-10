import React from 'react'
import { Link, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'

function StatusBadge({ status }) {
  const value = String(status || '')
  const map = {
    BORRADOR: 'bg-slate-100 text-slate-800 border-slate-200',
    CONFLICTO: 'bg-red-50 text-red-800 border-red-200',
    CONFIRMADO: 'bg-green-50 text-green-800 border-green-200',
    EN_PROCESO: 'bg-blue-50 text-blue-800 border-blue-200',
    CERRADO: 'bg-slate-200 text-slate-900 border-slate-300',
  }
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-xs font-semibold ${map[value] || 'bg-slate-50 text-slate-700 border-slate-200'}`}>
      {value || '-'}
    </span>
  )
}

export default function Index({ batches }) {
  const { props } = usePage()

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Plan semanal</CardTitle>
          <div className="flex items-center gap-2">
            <Link href={route('planning.batches.create')}>
              <Button>Crear semana</Button>
            </Link>
          </div>
        </CardHeader>
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
            </div>
          )}
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          )}

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Semana</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Turno</TableHead>
                <TableHead>Procesos</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(batches?.data || []).map((b) => (
                <TableRow key={b.id}>
                  <TableCell>
                    <div className="font-medium">
                      {b.week_start ? new Date(`${b.week_start}T00:00:00`).toLocaleDateString('es-CL') : '-'}
                      {' '}→{' '}
                      {b.week_end ? new Date(`${b.week_end}T00:00:00`).toLocaleDateString('es-CL') : '-'}
                    </div>
                  </TableCell>
                  <TableCell>{b.especie || '-'}</TableCell>
                  <TableCell>
                    <span className="font-medium">{b.shift?.codigo || '-'}</span>
                    <span className="text-gray-500">{b.shift?.nombre ? ` · ${b.shift.nombre}` : ''}</span>
                  </TableCell>
                  <TableCell>{b.processes_count ?? 0}</TableCell>
                  <TableCell>
                    <StatusBadge status={b.estado?.value ?? b.estado} />
                  </TableCell>
                  <TableCell className="text-right">
                    <Link href={route('planning.batches.show', b.id)}>
                      <Button variant="outline" size="sm">Abrir</Button>
                    </Link>
                  </TableCell>
                </TableRow>
              ))}

              {(!batches?.data || batches.data.length === 0) && (
                <TableRow>
                  <TableCell colSpan={6} className="py-10 text-center text-sm text-gray-500">
                    No hay semanas creadas. Crea una para planificar varios días de una vez.
                  </TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {batches?.links?.length ? (
            <div className="flex items-center justify-between mt-4">
              <div className="text-sm text-gray-600">
                Mostrando {batches.from ?? 0} a {batches.to ?? 0} de {batches.total ?? 0}
              </div>
              <nav className="inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                {batches.links.map((link, idx) => (
                  <Link
                    key={`${link.url}-${idx}`}
                    href={link.url || '#'}
                    disabled={!link.url}
                    preserveState
                    preserveScroll
                    className={`relative inline-flex items-center px-3 py-2 border text-sm font-medium ${
                      link.active ? 'z-10 bg-indigo-50 border-indigo-300 text-indigo-700' : 'bg-white border-gray-300 text-gray-600 hover:bg-gray-50'
                    } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </nav>
            </div>
          ) : null}

          <div className="mt-4 text-xs text-gray-500">
            Tip: “Crear semana” genera 7 procesos (uno por día) y puedes modificar cada día entrando al detalle.
          </div>
        </CardContent>
      </Card>
    </div>
  )
}

Index.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación</h2>}
  />
)
