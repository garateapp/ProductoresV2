import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ mappingSet, items }) {
  const { post } = useForm();

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{mappingSet.nombre}</h1>
          <p className="text-muted-foreground">{mappingSet.codigo}</p>
        </div>
        <div className="flex gap-2">
          {mappingSet.estado !== 'publicado' && (
            <Button onClick={() => post(route('integrations.mapping-sets.publish', mappingSet.id))}>Publicar</Button>
          )}
          <Link href={route('integrations.mapping-sets.index')}><Button variant="ghost">Volver</Button></Link>
        </div>
      </div>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Cliente</CardTitle></CardHeader><CardContent>{mappingSet.cliente}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Estado</CardTitle></CardHeader><CardContent>{mappingSet.estado}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Versión</CardTitle></CardHeader><CardContent>v{mappingSet.version}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Inmutable</CardTitle></CardHeader><CardContent>{mappingSet.inmutable ? 'Sí' : 'No'}</CardContent></Card>
      </div>
      <Card>
        <CardHeader><CardTitle>Items ({items.length})</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow><TableHead>Valor Interno</TableHead><TableHead>Valor Externo</TableHead><TableHead>Descripción</TableHead><TableHead>Activo</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {items.map(i => (
                <TableRow key={i.id}>
                  <TableCell className="font-mono text-xs">{i.valor_interno}</TableCell>
                  <TableCell className="font-mono text-xs">{i.valor_externo}</TableCell>
                  <TableCell className="text-xs">{i.descripcion || '—'}</TableCell>
                  <TableCell>{i.activo ? 'Sí' : 'No'}</TableCell>
                </TableRow>
              ))}
              {items.length === 0 && <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground">Sin ítems</TableCell></TableRow>}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Conjunto de Mapeo</h2>} />;
