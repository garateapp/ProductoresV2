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

export default function Index({ versions, filters, profiles }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <h1 className="text-2xl font-bold">Comparar Versiones</h1>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Perfil</TableHead>
                <TableHead>Versión</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Publicado</TableHead>
                <TableHead>Creado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {versions.data.map(v => (
                <TableRow key={v.id}>
                  <TableCell>{v.profile}</TableCell>
                  <TableCell className="font-mono">v{v.version}</TableCell>
                  <TableCell><Badge variant={v.estado === 'publicado' ? 'default' : 'secondary'}>{v.estado}</Badge></TableCell>
                  <TableCell>{v.published_at || '—'}</TableCell>
                  <TableCell className="text-xs">{v.created_at}</TableCell>
                </TableRow>
              ))}
              {versions.data.length === 0 && (
                <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground">Sin versiones</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Comparar Versiones</h2>} />;
