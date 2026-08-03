import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Search } from 'lucide-react';

export default function Index({ adapters, filters, tipos_conexion }) {
  function search(e) {
    e.preventDefault();
    router.get(route('integrations.source-adapters.index'), { q: e.target.q.value });
  }

  function filterByTipo(value) {
    router.get(route('integrations.source-adapters.index'), { ...filters, tipo_conexion: value === 'all' ? '' : value });
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Source Adapters</CardTitle>
          <Link href={route('integrations.source-adapters.create')}>
            <Button>Nuevo Adapter</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex gap-2">
            <form onSubmit={search} className="flex-1 flex gap-2">
              <div className="relative flex-1">
                <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <Input name="q" defaultValue={filters.q} placeholder="Buscar adapters..." className="pl-10" />
              </div>
              <Button type="submit">Buscar</Button>
            </form>
            <Select value={filters.tipo_conexion || 'all'} onValueChange={filterByTipo}>
              <SelectTrigger className="w-48"><SelectValue placeholder="Tipo" /></SelectTrigger>
              <SelectContent>
                <SelectItem value="all">Todos</SelectItem>
                {tipos_conexion.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
              </SelectContent>
            </Select>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Key</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Perfiles</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Creado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {adapters.data.map(adapter => (
                <TableRow key={adapter.id}>
                  <TableCell className="font-mono text-sm">{adapter.key}</TableCell>
                  <TableCell>
                    <Link href={route('integrations.source-adapters.show', adapter.id)} className="text-blue-600 hover:underline">
                      {adapter.nombre}
                    </Link>
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">{adapter.tipo_conexion_label}</Badge>
                  </TableCell>
                  <TableCell>{adapter.profiles_count}</TableCell>
                  <TableCell>
                    <Badge variant={adapter.activo ? 'default' : 'secondary'}>
                      {adapter.activo ? 'Activo' : 'Inactivo'}
                    </Badge>
                  </TableCell>
                  <TableCell className="text-sm text-gray-500">{adapter.created_at}</TableCell>
                </TableRow>
              ))}
              {adapters.data.length === 0 && (
                <TableRow>
                  <TableCell colSpan={6} className="text-center text-gray-500">No hay adapters registrados.</TableCell>
                </TableRow>
              )}
            </TableBody>
          </Table>

          {adapters.links && (
            <div className="mt-4 flex justify-center gap-1">
              {adapters.links.map((link, i) => (
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

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Source Adapters</h2>} />;
