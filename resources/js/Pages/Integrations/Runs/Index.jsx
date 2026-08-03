import React from 'react';
import { Link, useForm } from '@inertiajs/react';
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

export default function Index({ runs, filters, profiles, statuses }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Ejecuciones</h1>
      </div>
      <Card>
        <CardContent className="pt-6">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Perfil</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Total</TableHead>
                <TableHead>Exitosos</TableHead>
                <TableHead>Fallidos</TableHead>
                <TableHead>Usuario</TableHead>
                <TableHead>Fecha</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {runs.data.map(run => (
                <TableRow key={run.id}>
                  <TableCell>
                    <Link href={route('integrations.runs.show', run.id)} className="hover:underline font-medium">{run.profile}</Link>
                  </TableCell>
                  <TableCell><Badge>{run.estado_label}</Badge></TableCell>
                  <TableCell>{run.total}</TableCell>
                  <TableCell>{run.exitosos}</TableCell>
                  <TableCell>{run.fallidos}</TableCell>
                  <TableCell>{run.usuario}</TableCell>
                  <TableCell className="text-xs">{run.created_at}</TableCell>
                </TableRow>
              ))}
              {runs.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin ejecuciones</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Ejecuciones</h2>} />;
