import React from 'react';
import { Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Show({ client }) {
  function handleDelete() {
    if (confirm('¿Está seguro de eliminar este cliente?')) {
      router.delete(route('integrations.clients.destroy', client.id));
    }
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex justify-between items-start">
        <div>
          <h1 className="text-2xl font-bold">{client.nombre}</h1>
          <p className="text-gray-500">{client.codigo}</p>
        </div>
        <div className="flex gap-2">
          <Link href={route('integrations.clients.edit', client.id)}>
            <Button variant="outline">Editar</Button>
          </Link>
          <Button variant="destructive" onClick={handleDelete}>Eliminar</Button>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader><CardTitle className="text-sm">RUT</CardTitle></CardHeader>
          <CardContent><p>{client.rut || '—'}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Email</CardTitle></CardHeader>
          <CardContent><p>{client.email || '—'}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Contacto</CardTitle></CardHeader>
          <CardContent><p>{client.contacto || '—'}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Estado</CardTitle></CardHeader>
          <CardContent>
            <Badge variant={client.activo ? 'default' : 'secondary'}>
              {client.activo ? 'Activo' : 'Inactivo'}
            </Badge>
          </CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Creado por</CardTitle></CardHeader>
          <CardContent><p>{client.creador || '—'}</p></CardContent>
        </Card>
        <Card>
          <CardHeader><CardTitle className="text-sm">Perfiles</CardTitle></CardHeader>
          <CardContent><p>{client.profiles_count}</p></CardContent>
        </Card>
      </div>

      {client.descripcion && (
        <Card>
          <CardHeader><CardTitle className="text-sm">Descripción</CardTitle></CardHeader>
          <CardContent><p>{client.descripcion}</p></CardContent>
        </Card>
      )}

      {client.profiles?.length > 0 && (
        <Card>
          <CardHeader><CardTitle>Perfiles de Integración</CardTitle></CardHeader>
          <CardContent>
            <div className="space-y-2">
              {client.profiles.map(p => (
                <div key={p.id} className="flex justify-between items-center p-2 bg-gray-50 rounded">
                  <div>
                    <Link href={route('integrations.profiles.show', p.id)} className="text-blue-600 hover:underline font-medium">
                      {p.nombre}
                    </Link>
                    <span className="text-gray-500 text-sm ml-2">({p.codigo})</span>
                  </div>
                  <div className="flex gap-2 items-center">
                    <Badge variant="outline">{p.direccion}</Badge>
                    <Badge>{p.estado}</Badge>
                  </div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      <div className="text-sm text-gray-400 space-y-1">
        <p>Creado: {client.created_at}</p>
        {client.updated_at && <p>Actualizado: {client.updated_at}</p>}
      </div>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle del Cliente</h2>} />;
