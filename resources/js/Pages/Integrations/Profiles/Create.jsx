import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create({ clients, source_adapters, exporters }) {
  const { data, setData, post, errors, processing } = useForm({
    client_id: '',
    codigo: '',
    nombre: '',
    descripcion: '',
    direccion: 'entrada',
    source_adapter: '',
    exporter: '',
    tipo_salida: 'csv',
    zona_horaria: 'America/Santiago',
    error_config: {},
    idempotency_config: {},
    retencion_config: {},
  });

  function submit(e) {
    e.preventDefault();
    post(route('integrations.profiles.store'));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Nuevo Perfil de Integración</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input id="codigo" value={data.codigo} onChange={e => setData('codigo', e.target.value)} placeholder="INT-001" />
                {errors.codigo && <div className="text-red-500 text-sm">{errors.codigo}</div>}
              </div>
              <div>
                <Label htmlFor="client_id">Cliente</Label>
                <Select value={data.client_id} onValueChange={v => setData('client_id', v)}>
                  <SelectTrigger><SelectValue placeholder="Seleccionar cliente..." /></SelectTrigger>
                  <SelectContent>
                    {clients.map(c => <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>)}
                  </SelectContent>
                </Select>
                {errors.client_id && <div className="text-red-500 text-sm">{errors.client_id}</div>}
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="nombre">Nombre</Label>
                <Input id="nombre" value={data.nombre} onChange={e => setData('nombre', e.target.value)} placeholder="Integración SAP Recepciones" />
                {errors.nombre && <div className="text-red-500 text-sm">{errors.nombre}</div>}
              </div>
              <div className="md:col-span-2">
                <Label htmlFor="descripcion">Descripción</Label>
                <Textarea id="descripcion" value={data.descripcion} onChange={e => setData('descripcion', e.target.value)} />
                {errors.descripcion && <div className="text-red-500 text-sm">{errors.descripcion}</div>}
              </div>
              <div>
                <Label>Dirección</Label>
                <Select value={data.direccion} onValueChange={v => setData('direccion', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="entrada">Entrada (Importación)</SelectItem>
                    <SelectItem value="salida">Salida (Exportación)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Tipo de Salida</Label>
                <Select value={data.tipo_salida} onValueChange={v => setData('tipo_salida', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="csv">CSV</SelectItem>
                    <SelectItem value="json">JSON</SelectItem>
                    <SelectItem value="excel">Excel</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Source Adapter</Label>
                <Select value={data.source_adapter} onValueChange={v => setData('source_adapter', v)}>
                  <SelectTrigger><SelectValue placeholder="Default" /></SelectTrigger>
                  <SelectContent>
                    {source_adapters.map(a => <SelectItem key={a.value} value={a.value}>{a.label}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Exporter</Label>
                <Select value={data.exporter} onValueChange={v => setData('exporter', v)}>
                  <SelectTrigger><SelectValue placeholder="Default" /></SelectTrigger>
                  <SelectContent>
                    {exporters.map(e => <SelectItem key={e} value={e}>{e}</SelectItem>)}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label htmlFor="zona_horaria">Zona Horaria</Label>
                <Input id="zona_horaria" value={data.zona_horaria} onChange={e => setData('zona_horaria', e.target.value)} />
              </div>
            </div>

            <div className="flex gap-2">
              <Button type="submit" disabled={processing}>Crear Perfil</Button>
              <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancelar</Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Create.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Nuevo Perfil</h2>} />;
