import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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
import GarcesPagination from '@/Components/Garces/GarcesPagination';

const emptyForm = { tipo: '', codigo_interno: '', nombre: '', activo: true, notas: '' };

export default function Index({ catalogs, types }) {
  const [editing, setEditing] = useState(null);
  const { data, setData, post, put, processing, errors, reset } = useForm(emptyForm);

  const submit = (e) => {
    e.preventDefault();
    if (editing) {
      put(route('integrations.garces.catalog.update', editing), {
        onSuccess: () => { setEditing(null); reset(); },
      });
    } else {
      post(route('integrations.garces.catalog.store'), { onSuccess: () => reset() });
    }
  };

  const startEdit = (c) => {
    setEditing(c.id);
    setData({
      tipo: c.tipo ?? '',
      codigo_interno: c.codigo_interno ?? '',
      nombre: c.nombre ?? '',
      activo: !!c.activo,
      notas: c.notas ?? '',
    });
  };

  const destroy = (c) => {
    if (confirm(`¿Eliminar el código ${c.codigo_interno}?`)) {
      router.delete(route('integrations.garces.catalog.destroy', c.id));
    }
  };

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Catálogo de códigos internos</h1>
        <p className="text-muted-foreground">Valores internos disponibles para homologar</p>
      </div>

      <Card>
        <CardHeader><CardTitle>{editing ? 'Editar código' : 'Nuevo código'}</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 md:grid-cols-3">
              <div className="space-y-1">
                <Label>Tipo</Label>
                <Select value={data.tipo} onValueChange={(v) => setData('tipo', v)}>
                  <SelectTrigger><SelectValue placeholder="Selecciona" /></SelectTrigger>
                  <SelectContent>
                    {types.map((t) => <SelectItem key={t} value={t}>{t}</SelectItem>)}
                  </SelectContent>
                </Select>
                {errors.tipo && <p className="text-xs text-red-600">{errors.tipo}</p>}
              </div>
              <div className="space-y-1">
                <Label>Código interno</Label>
                <Input value={data.codigo_interno} onChange={(e) => setData('codigo_interno', e.target.value)} />
                {errors.codigo_interno && <p className="text-xs text-red-600">{errors.codigo_interno}</p>}
              </div>
              <div className="space-y-1">
                <Label>Nombre</Label>
                <Input value={data.nombre} onChange={(e) => setData('nombre', e.target.value)} />
              </div>
            </div>
            <div className="space-y-1">
              <Label>Notas</Label>
              <Textarea value={data.notas} onChange={(e) => setData('notas', e.target.value)} />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={data.activo} onChange={(e) => setData('activo', e.target.checked)} />
              Activo
            </label>
            <div className="flex gap-2">
              <Button type="submit" disabled={processing}>{editing ? 'Actualizar' : 'Guardar'}</Button>
              {editing && (
                <Button type="button" variant="ghost" onClick={() => { setEditing(null); reset(); }}>Cancelar</Button>
              )}
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader><CardTitle>Códigos ({catalogs.total})</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Tipo</TableHead>
                <TableHead>Código</TableHead>
                <TableHead>Nombre</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {catalogs.data.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="text-xs">{c.tipo}</TableCell>
                  <TableCell className="font-mono text-xs">{c.codigo_interno}</TableCell>
                  <TableCell className="text-xs">{c.nombre || '—'}</TableCell>
                  <TableCell className="text-xs">{c.activo ? 'Sí' : 'No'}</TableCell>
                  <TableCell className="text-right space-x-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(c)}>Editar</Button>
                    <Button size="sm" variant="destructive" onClick={() => destroy(c)}>Eliminar</Button>
                  </TableCell>
                </TableRow>
              ))}
              {catalogs.data.length === 0 && (
                <TableRow><TableCell colSpan={5} className="text-center text-muted-foreground">Sin códigos</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
          <GarcesPagination links={catalogs.links} />
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Catálogo Garces</h2>} />
);
