import React from 'react';
import { Link } from '@inertiajs/react';
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

export default function Show({ run, records }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Ejecución #{run.id}</h1>
        <Link href={route('integrations.runs.index')}><Button variant="ghost">Volver</Button></Link>
      </div>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Perfil</CardTitle></CardHeader><CardContent>{run.profile}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Estado</CardTitle></CardHeader><CardContent><Badge>{run.estado_label}</Badge></CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Registros</CardTitle></CardHeader><CardContent>{run.total_registros}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Duración</CardTitle></CardHeader><CardContent>{run.duracion_segundos ? `${run.duracion_segundos}s` : '—'}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Exitosos</CardTitle></CardHeader><CardContent className="text-green-600">{run.exitosos}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Fallidos</CardTitle></CardHeader><CardContent className="text-red-600">{run.fallidos}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Pendientes</CardTitle></CardHeader><CardContent>{run.pendientes}</CardContent></Card>
        <Card><CardHeader className="pb-2"><CardTitle className="text-sm">Inicio</CardTitle></CardHeader><CardContent className="text-xs">{run.started_at || run.created_at}</CardContent></Card>
      </div>
      <Card>
        <CardHeader><CardTitle>Registros</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow><TableHead>ID</TableHead><TableHead>Source</TableHead><TableHead>Estado</TableHead><TableHead>Errores</TableHead></TableRow>
            </TableHeader>
            <TableBody>
              {records.map(r => (
                <TableRow key={r.id}>
                  <TableCell className="font-mono text-xs">{r.id}</TableCell>
                  <TableCell>{r.source_identifier}</TableCell>
                  <TableCell><Badge variant={r.estado === 'success' ? 'default' : r.estado === 'failed' ? 'destructive' : 'secondary'}>{r.estado_label}</Badge></TableCell>
                  <TableCell className="max-w-[200px] truncate text-xs">{r.errores?.[0] || '—'}</TableCell>
                </TableRow>
              ))}
              {records.length === 0 && <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground">Sin registros</TableCell></TableRow>}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Ejecución</h2>} />;
