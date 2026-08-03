import React from 'react';
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

export default function Index({ logs, filters, eventos, entidad_tipos }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <h1 className="text-2xl font-bold">Auditoría</h1>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Evento</TableHead>
                <TableHead>Entidad</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Usuario</TableHead>
                <TableHead>Motivo</TableHead>
                <TableHead>IP</TableHead>
                <TableHead>Fecha</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {logs.data.map(log => (
                <TableRow key={log.id}>
                  <TableCell><Badge variant="outline">{log.evento}</Badge></TableCell>
                  <TableCell className="text-xs">{log.entidad_tipo}</TableCell>
                  <TableCell className="max-w-[200px] truncate">{log.entidad_nombre}</TableCell>
                  <TableCell>{log.usuario}</TableCell>
                  <TableCell className="max-w-[150px] truncate text-xs">{log.motivo || '—'}</TableCell>
                  <TableCell className="font-mono text-xs">{log.ip_address}</TableCell>
                  <TableCell className="text-xs">{log.created_at}</TableCell>
                </TableRow>
              ))}
              {logs.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin registros de auditoría</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Auditoría</h2>} />;
