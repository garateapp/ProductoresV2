import React from 'react';
import { Link } from '@inertiajs/react';
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

export default function Index({ exports, filters }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <h1 className="text-2xl font-bold">Exportaciones</h1>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Tipo</TableHead>
                <TableHead>Archivo</TableHead>
                <TableHead>Tamaño</TableHead>
                <TableHead>Registros</TableHead>
                <TableHead>Perfil</TableHead>
                <TableHead>Creado</TableHead>
                <TableHead>Acción</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {exports.data.map(e => (
                <TableRow key={e.id}>
                  <TableCell className="font-mono text-xs uppercase">{e.tipo}</TableCell>
                  <TableCell className="text-xs">{e.archivo}</TableCell>
                  <TableCell>{e.tamano_bytes ? `${(e.tamano_bytes / 1024).toFixed(1)} KB` : '—'}</TableCell>
                  <TableCell>{e.total_registros}</TableCell>
                  <TableCell>{e.perfil}</TableCell>
                  <TableCell className="text-xs">{e.created_at}</TableCell>
                  <TableCell>
                    {e.can_download ? (
                      <a href={route('integrations.exports.download', e.id)}><Button size="sm" variant="outline">Descargar</Button></a>
                    ) : '—'}
                  </TableCell>
                </TableRow>
              ))}
              {exports.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin exportaciones</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Exportaciones</h2>} />;
