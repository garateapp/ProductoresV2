import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ profiles, filters, clients, statuses }) {
  const { data, setData, get } = useForm({
    q: filters.q || '',
    estado: filters.estado || '',
    client_id: filters.client_id || '',
  });

  function search() {
    get(route('integrations.profiles.index'), { preserveState: true });
  }

  function clearFilters() {
    setData({ q: '', estado: '', client_id: '' });
    get(route('integrations.profiles.index'), { preserveState: true });
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Perfiles de Integración</h1>
        <Link href={route('integrations.profiles.create')}>
          <Button>Nuevo Perfil</Button>
        </Link>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Filtros</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
              <Label>Buscar</Label>
              <Input value={data.q} onChange={e => setData('q', e.target.value)} placeholder="Nombre o código..." />
            </div>
            <div>
              <Label>Estado</Label>
              <Select value={data.estado} onValueChange={v => setData('estado', v)}>
                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                  {statuses.map(s => <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Cliente</Label>
              <Select value={data.client_id} onValueChange={v => setData('client_id', v)}>
                <SelectTrigger><SelectValue placeholder="Todos" /></SelectTrigger>
                <SelectContent>
                  {clients.map(c => <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div className="flex items-end gap-2">
              <Button onClick={search}>Buscar</Button>
              <Button variant="outline" onClick={clearFilters}>Limpiar</Button>
            </div>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Cliente</TableHead>
                <TableHead>Dirección</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Versión</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead>Creado</TableHead>
                <TableHead>Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {profiles.data.map(profile => (
                <TableRow key={profile.id}>
                  <TableCell className="font-mono text-xs">{profile.codigo}</TableCell>
                  <TableCell>
                    <Link href={route('integrations.profiles.show', profile.id)} className="hover:underline font-medium">
                      {profile.nombre}
                    </Link>
                  </TableCell>
                  <TableCell>{profile.cliente}</TableCell>
                  <TableCell>{profile.direccion}</TableCell>
                  <TableCell>
                    <Badge>{profile.estado_label}</Badge>
                  </TableCell>
                  <TableCell>v{profile.version}</TableCell>
                  <TableCell>{profile.activo ? 'Sí' : 'No'}</TableCell>
                  <TableCell className="text-xs">{profile.created_at}</TableCell>
                  <TableCell>
                    <div className="flex gap-1">
                      <Link href={route('integrations.profiles.edit', profile.id)}>
                        <Button variant="outline" size="sm">Editar</Button>
                      </Link>
                      <Link href={route('integrations.profiles.show', profile.id)}>
                        <Button variant="ghost" size="sm">Ver</Button>
                      </Link>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
              {profiles.data.length === 0 && (
                <TableRow><TableCell colSpan={9} className="text-center text-muted-foreground">No hay perfiles</TableCell></TableRow>
              )}
            </TableBody>
          </Table>

          {profiles.links && (
            <Pagination className="mt-4">
              <PaginationContent>
                {profiles.links.map((link, i) => (
                  <PaginationItem key={i}>
                    {link.url ? (
                      <PaginationLink href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                    ) : (
                      <PaginationLink isActive={link.active} dangerouslySetInnerHTML={{ __html: link.label }} />
                    )}
                  </PaginationItem>
                ))}
              </PaginationContent>
            </Pagination>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Perfiles de Integración</h2>} />;
