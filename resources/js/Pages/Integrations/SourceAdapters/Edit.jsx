import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ adapter, tipos_conexion }) {
  const { data, setData, put, errors, processing } = useForm({
    key: adapter.key,
    nombre: adapter.nombre,
    descripcion: adapter.descripcion || '',
    tipo_conexion: adapter.tipo_conexion,
    configuracion: JSON.stringify(adapter.configuracion, null, 2),
    esquema_entrada: adapter.esquema_entrada ? JSON.stringify(adapter.esquema_entrada, null, 2) : '',
    activo: adapter.activo,
  });

  function submit(e) {
    e.preventDefault();
    put(route('integrations.source-adapters.update', adapter.id), {
      ...data,
      configuracion: JSON.parse(data.configuracion),
      esquema_entrada: data.esquema_entrada ? JSON.parse(data.esquema_entrada) : null,
    });
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Editar Adapter: {adapter.nombre}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="key">Key</Label>
                <Input id="key" value={data.key} onChange={e => setData('key', e.target.value)} />
                {errors.key && <div className="text-red-500 text-sm">{errors.key}</div>}
              </div>
              <div>
                <Label htmlFor="tipo_conexion">Tipo de Conexión</Label>
                <Select value={data.tipo_conexion} onValueChange={v => setData('tipo_conexion', v)}>
                  <SelectTrigger><SelectValue placeholder="Seleccionar tipo..." /></SelectTrigger>
                  <SelectContent>
                    {tipos_conexion.map(t => <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>)}
                  </SelectContent>
                </Select>
                {errors.tipo_conexion && <div className="text-red-500 text-sm">{errors.tipo_conexion}</div>}
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input id="nombre" value={data.nombre} onChange={e => setData('nombre', e.target.value)} />
                {errors.nombre && <div className="text-red-500 text-sm">{errors.nombre}</div>}
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Textarea id="descripcion" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)} />
              </div>
              <div className="md:col-span-2">
                <Label>Configuración (JSON)</Label>
                <Textarea
                  value={data.configuracion}
                  onChange={e => setData('configuracion', e.target.value)}
                  className="font-mono text-sm min-h-[200px]"
                />
                {errors.configuracion && <div className="text-red-500 text-sm">{errors.configuracion}</div>}
              </div>
              <div className="md:col-span-2">
                <Label>Esquema de Entrada (JSON opcional)</Label>
                <Textarea
                  value={data.esquema_entrada}
                  onChange={e => setData('esquema_entrada', e.target.value)}
                  className="font-mono text-sm min-h-[100px]"
                  placeholder='[{"name": "id", "type": "integer"}]'
                />
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

Edit.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Source Adapter</h2>} />;
