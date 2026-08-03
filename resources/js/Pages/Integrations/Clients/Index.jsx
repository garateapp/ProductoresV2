import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Search } from 'lucide-react';

export default function Index({ clients, filters }) {
  function search(e) {
    e.preventDefault();
    router.get(route('integrations.clients.index'), { q: e.target.q.value });
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Clientes</CardTitle>
          <Link href={route('integrations.clients.create')}>
            <Button>Nuevo Cliente</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <form onSubmit={search} className="mb-4 flex gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
              <Input name="q" defaultValue={filters.q} placeholder="Buscar clientes..." className="pl-10" />
            </div>
            <Button type="submit">Buscar</Button>
          </form>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>RUT</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Contacto</TableHead>
                <TableHead>Perfiles</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Creado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {clients.data.map(client => (
                <TableRow key={client.id}>
                  <TableCell className="font-medium">{client.codigo}</TableCell>
                  <TableCell>
                    <Link href={route('integrations.clients.show', client.id)} className="text-blue-600 hover:underline">
                      {client.nombre}
                    </Link>
                  </TableCell>
                  <TableCell>{client.rut}</TableCell>
                  <TableCell>{client.email}</TableCell>
                  <TableCell>{client.contacto}</TableCell>
                  <TableCell>{client.profiles_count}</TableCell>
                  <TableCell>
                    <Badge variant={client.activo ? 'default' : 'secondary'}>
                      {client.activo ? 'Activo' : 'Inactivo'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-sm text-gray-500">{client.created_at}</TableCell>
                </TableRow>
              ))}
              {clients.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={8} className="text-center text-gray-500">No hay clientes registrados.</TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {clients.links && (
            <div className="mt-4 flex justify-center gap-1">
              {clients.links.map((link, i) => (
                <Link key={i} href={link.url || '#'} dangerouslySetInnerHTML={{ __html: link.label }}
                  className={`px-3 py-1 rounded text-sm ${link.active ? 'bg-blue-600 text-white' : 'bg-gray-100 hover:bg-gray-200'} ${!link.url && 'opacity-50 cursor-not-allowed'}`}
                  preserveState />
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Clientes</h2>} />;
