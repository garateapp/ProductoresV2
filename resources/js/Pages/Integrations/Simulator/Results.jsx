import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Results({ profile_nombre, profile_codigo, results }) {
  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Resultados: {profile_nombre}</h1>
          <p className="text-muted-foreground">{profile_codigo}</p>
        </div>
        <Link href={route('integrations.simulator.index')}><Button variant="outline">Nueva Prueba</Button></Link>
      </div>
      {results.map((r, i) => (
        <Card key={i}>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              Resultado #{i + 1}
              {r.success ? <Badge>Exitoso</Badge> : <Badge variant="destructive">Fallido</Badge>}
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-2 text-sm">
            <div><strong>Input:</strong> <pre className="bg-gray-100 p-2 rounded text-xs mt-1">{JSON.stringify(r.input, null, 2)}</pre></div>
            <div><strong>Output:</strong> <pre className="bg-gray-100 p-2 rounded text-xs mt-1">{JSON.stringify(r.output, null, 2)}</pre></div>
            {r.errors.length > 0 && <div><strong>Errores:</strong> <pre className="bg-red-50 p-2 rounded text-xs mt-1">{JSON.stringify(r.errors, null, 2)}</pre></div>}
            {r.warnings.length > 0 && <div><strong>Advertencias:</strong> <pre className="bg-yellow-50 p-2 rounded text-xs mt-1">{JSON.stringify(r.warnings, null, 2)}</pre></div>}
          </CardContent>
        </Card>
      ))}
    </div>
  );
}

Results.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Resultados Simulador</h2>} />;
