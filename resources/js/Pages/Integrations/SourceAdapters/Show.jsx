import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ adapter }) {
  function handleDelete() {
    if (confirm('¿Está seguro de eliminar este adapter?')) {
      router.delete(route('integrations.source-adapters.destroy', adapter.id));
    }
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex justify-between items-start">
        <div>
          <h1 className="text-2xl font-bold">{adapter.nombre}</h1>
          <p className="text-gray-500 font-mono text-sm">{adapter.key}</p>
        </div>
        <div className="flex gap-2">
          <Link href={route('integrations.source-adapters.edit', adapter.id)}>
            <Button variant="outline">Editar</Button>
          </Link>
          <Button variant="destructive" onClick={handleDelete}>Eliminar</Button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader><CardTitle className="text-sm">Tipo de Conexión</CardTitle></CardHeader>
          <CardContent>
            <Badge variant="outline">{adapter.tipo_conexion_label}</Badge>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Estado</CardTitle></CardHeader>
          <CardContent>
            <Badge variant={adapter.activo ? 'default' : 'secondary'}>
              {adapter.activo ? 'Activo' : 'Inactivo'}
            </Badge>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Creado por</CardTitle></CardHeader>
          <CardContent><p>{adapter.creador || '—'}</p></CardContent>
        </Card>
      </div>

      {adapter.descripcion && (
        <Card>
          <CardHeader><CardTitle className="text-sm">Descripción</CardTitle></CardHeader>
          <CardContent><p>{adapter.descripcion}</p></CardContent>
        </Card>
      )}

      <Card>
        <CardHeader><CardTitle>Configuración</CardTitle></CardHeader>
        <CardContent>
          <pre className="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm max-h-96">
            {JSON.stringify(adapter.configuracion, null, 2)}
          </pre>
        </CardContent>
      </Card>

      {adapter.esquema_entrada && (
        <Card>
          <CardHeader><CardTitle>Esquema de Entrada</CardTitle></CardHeader>
          <CardContent>
            <pre className="bg-gray-900 text-green-400 p-4 rounded-lg overflow-x-auto text-sm max-h-60">
              {JSON.stringify(adapter.esquema_entrada, null, 2)}
            </pre>
          </CardContent>
        </Card>
      )}

      {adapter.profiles?.length > 0 && (
        <Card>
          <CardHeader><CardTitle>Perfiles que usan este Adapter</CardTitle></CardHeader>
          <CardContent>
            <div className="space-y-2">
              {adapter.profiles.map(p => (
                <div key={p.id} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                  <Link href={route('integrations.profiles.show', p.id)} className="text-blue-600 hover:underline font-medium">
                    {p.nombre}
                  </Link>
                  <span className="text-gray-500 text-sm">({p.cliente})</span>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      <div className="text-sm text-gray-400 space-y-1">
        <p>Creado: {adapter.created_at}</p>
        {adapter.updated_at && <p>Actualizado: {adapter.updated_at}</p>}
      </div>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle del Source Adapter</h2>} />;
