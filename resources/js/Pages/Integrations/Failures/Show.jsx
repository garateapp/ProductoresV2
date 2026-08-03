import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ record }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Registro Fallido #{record.id}</h1>
        <Link href={route('integrations.failures.index')}><Button variant="ghost">Volver</Button></Link>
      </div>
      <Card>
        <CardHeader><CardTitle>Detalles</CardTitle></CardHeader>
        <CardContent className="space-y-2 text-sm">
          <div><strong>Source:</strong> {record.source_identifier}</div>
          <div><strong>Perfil:</strong> {record.perfil}</div>
          <div><strong>Estado:</strong> <Badge variant="destructive">{record.estado_label}</Badge></div>
          <div><strong>Intentos:</strong> {record.intentos}</div>
          <div><strong>Errores:</strong> <pre className="bg-gray-100 p-2 rounded text-xs mt-1">{JSON.stringify(record.errores, null, 2)}</pre></div>
          {record.input_original && <div><strong>Input Original:</strong> <pre className="bg-gray-100 p-2 rounded text-xs mt-1">{JSON.stringify(record.input_original, null, 2)}</pre></div>}
        </CardContent>
      </Card>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Registro Fallido</h2>} />;
