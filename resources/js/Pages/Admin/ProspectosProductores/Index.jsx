import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function ProspectosProductoresIndex({ prospectos }) {
  const { auth, flash } = usePage().props;

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Prospectos de Productor</h2>}
    >
      <Head title="Prospectos de Productor" />

      <div className="py-8">
        <div className="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
          {flash?.success && (
            <div className="rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
              {flash.success}
            </div>
          )}
          {flash?.error && (
            <div className="rounded border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">
              {flash.error}
            </div>
          )}

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Listado</CardTitle>
              <Button asChild>
                <Link href={route('prospectos-productores.create')}>Nuevo prospecto</Link>
              </Button>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Razon social</TableHead>
                    <TableHead>RUT</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>GGN</TableHead>
                    <TableHead>Creado</TableHead>
                    <TableHead>Validado</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {prospectos?.data?.length ? (
                    prospectos.data.map((prospecto) => {
                      const isValidated = Boolean(prospecto.validated_at);
                      const isCreated = Boolean(prospecto.producer_id);

                      return (
                        <TableRow key={prospecto.id}>
                          <TableCell>{prospecto.razon_social || '-'}</TableCell>
                          <TableCell>{prospecto.rut || '-'}</TableCell>
                          <TableCell>{prospecto.email || '-'}</TableCell>
                          <TableCell>{prospecto.ggn || '-'}</TableCell>
                          <TableCell>{prospecto.created_at || '-'}</TableCell>
                          <TableCell>{prospecto.validated_at || '-'}</TableCell>
                          <TableCell>
                            {isCreated ? 'Productor creado' : isValidated ? 'Validado' : 'Pendiente'}
                          </TableCell>
                          <TableCell>
                            <div className="flex items-center gap-2">
                              <Button variant="outline" size="sm" asChild>
                                <Link href={route('prospectos-productores.edit', prospecto.id)}>Revisar</Link>
                              </Button>
                              {isCreated && (
                                <Button variant="secondary" size="sm" asChild>
                                  <Link href={route('producers.edit', prospecto.producer_id)}>Productor</Link>
                                </Button>
                              )}
                            </div>
                          </TableCell>
                        </TableRow>
                      );
                    })
                  ) : (
                    <TableRow>
                      <TableCell colSpan={8} className="text-center text-gray-500">
                        No hay prospectos registrados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>

              <div className="flex items-center justify-between mt-4">
                <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                  <div>
                    <p className="text-sm text-gray-700">
                      Mostrando <span className="font-medium">{prospectos.from}</span> a{' '}
                      <span className="font-medium">{prospectos.to}</span> de{' '}
                      <span className="font-medium">{prospectos.total}</span> resultados
                    </p>
                  </div>
                  <div>
                    <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                      {prospectos.links.map((link, index) => (
                        <Link
                          key={`${link.url}-${index}`}
                          href={link.url || '#'}
                          disabled={!link.url}
                          preserveState={true}
                          preserveScroll={true}
                          className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${
                            link.active
                              ? 'z-10 bg-indigo-500 border-indigo-500 text-indigo-600'
                              : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                          } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      ))}
                    </nav>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
