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

export default function Index({ mappingSets, filters, clients }) {
  const { data, setData, post, processing } = useForm({
    client_id: '', codigo: '', nombre: '', descripcion: '',
  });
  const [showCreate, setShowCreate] = React.useState(false);

  function create(e) {
    e.preventDefault();
    post(route('integrations.mapping-sets.store'), { preserveState: true, onSuccess: () => setShowCreate(false) });
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Conjuntos de Mapeo</h1>
        <Button onClick={() => setShowCreate(!showCreate)}>{showCreate ? 'Cancelar' : 'Nuevo'}</Button>
      </div>
      {showCreate && (
        <Card>
          <CardHeader><CardTitle>Nuevo Conjunto</CardTitle></CardHeader>
          <CardContent>
            <form onSubmit={create} className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="text-sm font-medium">Código</label>
                  <input className="border rounded px-3 py-2 w-full text-sm" value={data.codigo} onChange={e => setData('codigo', e.target.value)} />
                </div>
                <div>
                  <label className="text-sm font-medium">Cliente</label>
                  <select className="border rounded px-3 py-2 w-full text-sm" value={data.client_id} onChange={e => setData('client_id', e.target.value)}>
                    <option value="">Seleccionar...</option>
                    {clients.map(c => <option key={c.id} value={c.id}>{c.nombre}</option>)}
                  </select>
                </div>
                <div className="col-span-2">
                  <label className="text-sm font-medium">Nombre</label>
                  <input className="border rounded px-3 py-2 w-full text-sm" value={data.nombre} onChange={e => setData('nombre', e.target.value)} />
                </div>
              </div>
              <Button type="submit" disabled={processing}>Crear</Button>
            </form>
          </CardContent>
        </Card>
      )}
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow><TableHead>Código</TableHead><TableHead>Nombre</TableHead><TableHead>Cliente</TableHead><TableHead>Estado</TableHead><TableHead>Versión</TableHead><TableHead>Acciones</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {mappingSets.data.map(ms => (
                <TableRow key={ms.id}>
                  <TableCell className="font-mono text-xs">{ms.codigo}</TableCell>
                  <TableCell>{ms.nombre}</TableCell>
                  <TableCell>{ms.cliente}</TableCell>
                  <TableCell>{ms.estado}</TableCell>
                  <TableCell>v{ms.version}</TableCell>
                  <TableCell>
                    <Link href={route('integrations.mapping-sets.show', ms.id)}><Button size="sm" variant="outline">Ver</Button></Link>
                  </TableCell>
                </TableRow>
              ))}
              {mappingSets.data.length === 0 && <TableRow><TableCell colSpan={6} className="text-center text-muted-foreground">Sin conjuntos</TableCell></TableRow>}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Conjuntos de Mapeo</h2>} />;
