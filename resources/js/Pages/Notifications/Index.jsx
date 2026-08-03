import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function Index({ auth, notifications }) {
  const rows = Array.isArray(notifications?.data) ? notifications.data : (Array.isArray(notifications) ? notifications : []);
  const paginationLinks = Array.isArray(notifications?.links)
    ? notifications.links
    : Array.isArray(notifications?.meta?.links)
      ? notifications.meta.links
      : [];

  const handleReadAll = () => {
    router.post(route('notifications.read-all'));
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Notificaciones</h2>}>
      <Head title="Notificaciones" />
      <div className="container mx-auto py-8 space-y-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Bandeja de Notificaciones</CardTitle>
            <Button variant="secondary" onClick={handleReadAll}>Marcar todo como leído</Button>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tipo</TableHead>
                  <TableHead>Archivo</TableHead>
                  <TableHead>Mensaje</TableHead>
                  <TableHead>Fecha</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.length === 0 && (
                  <TableRow>
                    <TableCell colSpan={6} className="text-center text-sm text-gray-500">Sin notificaciones.</TableCell>
                  </TableRow>
                )}
                {rows.map((n) => (
                  <TableRow key={n.id} className={!n.read_at ? 'bg-amber-50/40' : ''}>
                    <TableCell className="font-semibold">
                      {n.kind === 'material_request_created' ? (
                        <Link href={route('inventory.material-requests.index')} className="text-greenex-dark-green hover:underline">
                          {n.label || n.type}
                        </Link>
                      ) : n.kind === 'return_created' ? (
                        <Link href={route('inventory.returns.index')} className="text-greenex-dark-green hover:underline">
                          {n.label || n.type}
                        </Link>
                      ) : n.kind === 'inventory_transfer_dispatched' || n.kind === 'inventory_transfer_return_pending' ? (
                        <Link href={route('inventory.movements.index')} className="text-greenex-dark-green hover:underline">
                          {n.label || n.type}
                        </Link>
                      ) : (
                        n.label || n.type
                      )}
                    </TableCell>
                    <TableCell>{n.file || '-'}</TableCell>
                    <TableCell className="max-w-[420px] text-sm text-gray-700">
                      {n.kind === 'material_request_created' ? (
                        <Link href={route('inventory.material-requests.index')} className="hover:underline">
                          <span className="font-medium">{n.codigo}</span>
                          <span className="text-gray-500"> &mdash; {n.origin || '?'} → {n.destination || '?'}</span>
                        </Link>
                      ) : n.kind === 'return_created' ? (
                        <Link href={route('inventory.returns.index')} className="hover:underline">
                          <span className="font-medium">{n.codigo}</span>
                          <span className="text-gray-500"> &mdash; {n.origin || '?'} → {n.destination || '?'}</span>
                        </Link>
                      ) : n.kind === 'inventory_transfer_dispatched' || n.kind === 'inventory_transfer_return_pending' ? (
                        <Link href={route('inventory.movements.index')} className="hover:underline">
                          <span className="font-medium">{n.folio}</span>
                          <span className="text-gray-500"> &mdash; {n.origin || '?'} → {n.destination || '?'}</span>
                        </Link>
                      ) : (
                        n.message || '-'
                      )}
                    </TableCell>
                    <TableCell className="text-sm">{n.created_at || '-'}</TableCell>
                    <TableCell className="text-sm">{n.read_at ? 'Leído' : 'Nuevo'}</TableCell>
                    <TableCell className="text-right">
                      {!n.read_at && (
                        <Button size="sm" variant="secondary" onClick={() => router.post(route('notifications.read', n.id))}>
                          Marcar leído
                        </Button>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <div className="mt-4 flex gap-2">
              {paginationLinks.map((link, idx) => (
                <Link key={idx} href={link.url || '#'} className={`px-2 ${link.active ? 'font-bold' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
