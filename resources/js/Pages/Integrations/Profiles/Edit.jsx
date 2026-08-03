import React from 'react';
import { Link, useForm } from '@inertiajs/react';
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
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({
  profile, input_fields, output_fields, rules,
  clients, field_types, rule_types, error_policies
}) {
  const { data, setData, put, errors, processing } = useForm({
    client_id: String(profile.client_id || ''),
    codigo: profile.codigo || '',
    nombre: profile.nombre || '',
    descripcion: profile.descripcion || '',
    direccion: profile.direccion || 'entrada',
    source_adapter: profile.source_adapter || '',
    exporter: profile.exporter || '',
    tipo_salida: profile.tipo_salida || 'csv',
    zona_horaria: profile.zona_horaria || 'America/Santiago',
    error_config: profile.error_config || {},
    idempotency_config: profile.idempotency_config || {},
    retencion_config: profile.retencion_config || {},
  });

  function submit(e) {
    e.preventDefault();
    put(route('integrations.profiles.update', profile.id));
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Editar Perfil: {profile.nombre}</h1>
        <div className="flex gap-2">
          <Link href={route('integrations.profiles.show', profile.id)}>
            <Button variant="outline" size="sm">Ver</Button>
          </Link>
          <Link href={route('integrations.profiles.index')}>
            <Button variant="ghost" size="sm">Volver</Button>
          </Link>
        </div>
      </div>

      {profile.inmutable && (
        <Card className="border-yellow-400 bg-yellow-50">
          <CardContent className="pt-4 text-yellow-800 text-sm">
            Esta versión es inmutable. Cree una nueva versión para realizar cambios.
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader><CardTitle>Información General</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label htmlFor="codigo">Código</Label>
                <Input id="codigo" value={data.codigo} onChange={e => setData('codigo', e.target.value)} />
                {errors.codigo && <div className="text-red-500 text-sm">{errors.codigo}</div>}
              </div>
              <div>
                <Label>Cliente</Label>
                <Select value={data.client_id} onValueChange={v => setData('client_id', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {clients.map(c => <SelectItem key={c.id} value={String(c.id)}>{c.nombre}</SelectItem>)}
                  </SelectContent>
                </Select>
                {errors.client_id && <div className="text-red-500 text-sm">{errors.client_id}</div>}
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
              <div>
                <Label>Dirección</Label>
                <Select value={data.direccion} onValueChange={v => setData('direccion', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    <SelectItem value="entrada">Entrada</SelectItem>
                    <SelectItem value="salida">Salida</SelectItem>
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
            </div>
            <Button type="submit" disabled={processing}>Guardar Cambios</Button>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Campos de Entrada</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Clave</TableHead>
                <TableHead>Etiqueta</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Obligatorio</TableHead>
                <TableHead>Posición</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {input_fields.map(f => (
                <TableRow key={f.id}>
                  <TableCell className="font-mono text-xs">{f.clave}</TableCell>
                  <TableCell>{f.etiqueta}</TableCell>
                  <TableCell>{f.tipo_dato_label}</TableCell>
                  <TableCell>{f.obligatorio ? 'Sí' : 'No'}</TableCell>
                  <TableCell>{f.posicion}</TableCell>
                </TableRow>
              ))}
              {input_fields.length === 0 && (
                <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground">Sin campos de entrada</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Campos de Salida</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Clave Externa</TableHead>
                <TableHead>Etiqueta</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Obligatorio</TableHead>
                <TableHead>Posición</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {output_fields.map(f => (
                <TableRow key={f.id}>
                  <TableCell className="font-mono text-xs">{f.clave_externa}</TableCell>
                  <TableCell>{f.etiqueta}</TableCell>
                  <TableCell>{f.tipo_dato_label}</TableCell>
                  <TableCell>{f.obligatorio ? 'Sí' : 'No'}</TableCell>
                  <TableCell>{f.posicion}</TableCell>
                </TableRow>
              ))}
              {output_fields.length === 0 && (
                <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground">Sin campos de salida</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between">
          <CardTitle>Reglas de Transformación</CardTitle>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Orden</TableHead>
                <TableHead>Tipo</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Obligatoria</TableHead>
                <TableHead>Activo</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rules.map(r => (
                <TableRow key={r.id}>
                  <TableCell>{r.orden}</TableCell>
                  <TableCell>{r.tipo_label}</TableCell>
                  <TableCell>{r.nombre}</TableCell>
                  <TableCell>{r.obligatoria ? 'Sí' : 'No'}</TableCell>
                  <TableCell>{r.activo ? 'Sí' : 'No'}</TableCell>
                </TableRow>
              ))}
              {rules.length === 0 && (
                <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground">Sin reglas</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Edit.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Perfil</h2>} />;
