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

export default function Index({ records, filters, profiles }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <h1 className="text-2xl font-bold">Fallos</h1>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>ID</TableHead>
                <TableHead>Source</TableHead>
                <TableHead>Perfil</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Errores</TableHead>
                <TableHead>Intentos</TableHead>
                <TableHead>Fecha</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {records.data.map(r => (
                <TableRow key={r.id}>
                  <TableCell className="font-mono text-xs">{r.id}</TableCell>
                  <TableCell>{r.source_identifier}</TableCell>
                  <TableCell>{r.perfil}</TableCell>
                  <TableCell><Badge variant="destructive">{r.estado_label}</Badge></TableCell>
                  <TableCell className="max-w-[200px] truncate text-xs">{r.errores?.[0] || '—'}</TableCell>
                  <TableCell>{r.intentos}</TableCell>
                  <TableCell className="text-xs">{r.created_at}</TableCell>
                </TableRow>
              ))}
              {records.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin fallos</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Fallos</h2>} />;
