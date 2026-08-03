import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Switch } from '@/Components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ client }) {
  const { data, setData, put, errors, processing } = useForm({
    codigo: client.codigo,
    nombre: client.nombre,
    rut: client.rut || '',
    email: client.email || '',
    contacto: client.contacto || '',
    descripcion: client.descripcion || '',
    activo: client.activo,
  });

  function submit(e) {
    e.preventDefault();
    put(route('integrations.clients.update', client.id));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Editar Cliente: {client.nombre}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input id="codigo" value={data.codigo} onChange={e => setData('codigo', e.target.value)} />
                {errors.codigo && <div className="text-red-500 text-sm">{errors.codigo}</div>}
              </div>
              <div>
                <Label htmlFor="rut">RUT</Label>
                <Input id="rut" value={data.rut} onChange={e => setData('rut', e.target.value)} />
                {errors.rut && <div className="text-red-500 text-sm">{errors.rut}</div>}
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input id="nombre" value={data.nombre} onChange={e => setData('nombre', e.target.value)} />
                {errors.nombre && <div className="text-red-500 text-sm">{errors.nombre}</div>}
              </div>
              <div>
                <Label htmlFor="email">Email</Label>
                <Input id="email" type="email" value={data.email} onChange={e => setData('email', e.target.value)} />
                {errors.email && <div className="text-red-500 text-sm">{errors.email}</div>}
              </div>
              <div>
                <Label htmlFor="contacto">Contacto</Label>
                <Input id="contacto" value={data.contacto} onChange={e => setData('contacto', e.target.value)} />
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Textarea id="descripcion" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)} />
              </div>
              <div className="flex items-center gap-2">
                <Switch id="activo" checked={data.activo} onCheckedChange={v => setData('activo', v)} />
                <Label htmlFor="activo">Activo</Label>
              </div>
            </div>
            <div className="flex gap-2">
              <Button type="submit" disabled={processing}>Guardar Cambios</Button>
              <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancelar</Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Edit.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Cliente</h2>} />;
