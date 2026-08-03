import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ mappings, filters, clients, campos }) {
  const { data, setData, put, processing } = useForm({ valor_asignado: '' });
  const [editingId, setEditingId] = React.useState(null);

  function resolve(mappingId) {
    put(route('integrations.pending-mappings.update', mappingId), { preserveScroll: true });
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <h1 className="text-2xl font-bold">Pendientes de Homologación</h1>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Cliente</TableHead>
                <TableHead>Campo</TableHead>
                <TableHead>Valor Interno</TableHead>
                <TableHead>Frecuencia</TableHead>
                <TableHead>Valor Asignado</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Acción</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {mappings.data.map(m => (
                <TableRow key={m.id}>
                  <TableCell>{m.cliente}</TableCell>
                  <TableCell className="font-mono text-xs">{m.campo}</TableCell>
                  <TableCell className="font-mono text-xs">{m.valor_interno}</TableCell>
                  <TableCell>{m.frecuencia}</TableCell>
                  <TableCell>
                    {editingId === m.id ? (
                      <input
                        className="border rounded px-2 py-1 text-sm w-32"
                        value={data.valor_asignado}
                        onChange={e => setData('valor_asignado', e.target.value)}
                      />
                    ) : (
                      m.valor_asignado || '—'
                    )}
                  </TableCell>
                  <TableCell>{m.resolved ? <Badge>Resuelto</Badge> : <Badge variant="secondary">Pendiente</Badge>}</TableCell>
                  <TableCell>
                    {!m.resolved && (
                      editingId === m.id ? (
                        <div className="flex gap-1">
                          <Button size="sm" onClick={() => resolve(m.id)} disabled={processing}>Guardar</Button>
                          <Button size="sm" variant="ghost" onClick={() => setEditingId(null)}>Cancelar</Button>
                        </div>
                      ) : (
                        <Button size="sm" variant="outline" onClick={() => { setEditingId(m.id); setData('valor_asignado', m.valor_asignado || ''); }}>Resolver</Button>
                      )
                    )}
                  </TableCell>
                </TableRow>
              ))}
              {mappings.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin pendientes</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Pendientes de Homologación</h2>} />;
