import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create({ tipos_conexion }) {
  const { data, setData, post, errors, processing } = useForm({
    key: '',
    nombre: '',
    descripcion: '',
    tipo_conexion: '',
    configuracion: '{}',
    esquema_entrada: '',
  });

  function submit(e) {
    e.preventDefault();
    post(route('integrations.source-adapters.store'), {
      ...data,
      configuracion: data.configuracion ? JSON.parse(data.configuracion) : {},
      esquema_entrada: data.esquema_entrada ? JSON.parse(data.esquema_entrada) : null,
    });
  }

  const configPlaceholders = {
    database: JSON.stringify({ connection: 'mysql', table: 'mi_tabla', columns: ['*'], key: 'id' }, null, 2),
    api_rest: JSON.stringify({ base_url: 'https://api.ejemplo.com/v1', endpoint: '/productos', auth_type: 'bearer', auth_token: '', pagination: { type: 'page', page_param: 'page', per_page: 100 }, data_path: 'data' }, null, 2),
    archivo: JSON.stringify({ format: 'csv', delimiter: ',', has_header: true, disk: 'local', path: 'imports/datos.csv' }, null, 2),
    ftp: JSON.stringify({ host: 'ftp.ejemplo.com', port: 21, username: 'user', password: '', protocol: 'ftp', remote_path: '/export/datos.csv', file_format: 'csv' }, null, 2),
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Nuevo Source Adapter</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="key">Key</Label>
                <Input id="key" value={data.key} onChange={e => setData('key', e.target.value)} placeholder="mi_adapter" />
                <p className="text-xs text-gray-400">Solo minúsculas, números y guión bajo</p>
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
                <Input id="nombre" value={data.nombre} onChange={e => setData('nombre', e.target.value)} placeholder="API Proveedores" />
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
                  placeholder={configPlaceholders[data.tipo_conexion] || '{\n  \n}'}
                />
                {errors.configuracion && <div className="text-red-500 text-sm">{errors.configuracion}</div>}
              </div>
              <div className="md:col-span-2">
                <Label>Esquema de Entrada (JSON opcional)</Label>
                <Textarea
                  value={data.esquema_entrada}
                  onChange={e => setData('esquema_entrada', e.target.value)}
                  className="font-mono text-sm min-h-[100px]"
                  placeholder={'[\n  {"name": "id", "type": "integer"},\n  {"name": "nombre", "type": "string"}\n]'}
                />
                <p className="text-xs text-gray-400">Define los campos que retorna este adapter. Si se deja vacío, se detectará automáticamente.</p>
              </div>
            </div>
            <div className="flex gap-2">
              <Button type="submit" disabled={processing}>Crear Adapter</Button>
              <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancelar</Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Create.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Nuevo Source Adapter</h2>} />;
