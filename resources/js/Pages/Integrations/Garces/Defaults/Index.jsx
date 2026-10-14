import React, { useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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

const emptyForm = {
  code_subsidiary: '',
  code_season: '',
  subsidiary_garces: '',
  season_garces: '',
  tipo_tarja: '',
  tipo_fruta: '',
  tipo_pallet: '',
  is_export: 1,
  activo: true,
};

export default function Index({ defaults, isExportDefault }) {
  const [editing, setEditing] = useState(null);
  const { data, setData, post, put, processing, errors, reset } = useForm(emptyForm);

  const submit = (e) => {
    e.preventDefault();
    if (editing) {
      put(route('integrations.garces.defaults.update', editing), {
        onSuccess: () => { setEditing(null); reset(); },
      });
    } else {
      post(route('integrations.garces.defaults.store'), { onSuccess: () => reset() });
    }
  };

  const startEdit = (d) => {
    setEditing(d.id);
    setData({
      code_subsidiary: d.code_subsidiary ?? '',
      code_season: d.code_season ?? '',
      subsidiary_garces: d.subsidiary_garces ?? '',
      season_garces: d.season_garces ?? '',
      tipo_tarja: d.tipo_tarja ?? '',
      tipo_fruta: d.tipo_fruta ?? '',
      tipo_pallet: d.tipo_pallet ?? '',
      is_export: d.is_export ?? isExportDefault,
      activo: !!d.activo,
    });
  };

  const destroy = (d) => {
    if (confirm(`¿Eliminar el default ${d.code_subsidiary}/${d.code_season}?`)) {
      router.delete(route('integrations.garces.defaults.destroy', d.id));
    }
  };

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div>
        <h1 className="text-2xl font-bold">Defaults de pallet por planta y temporada</h1>
        <p className="text-muted-foreground">Valores por defecto para tarja, fruta y pallet</p>
      </div>

      <Card>
        <CardHeader><CardTitle>{editing ? 'Editar default' : 'Nuevo default'}</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="grid gap-4 md:grid-cols-4">
              <div className="space-y-1">
                <Label>Planta (code_subsidiary)</Label>
                <Input value={data.code_subsidiary} onChange={(e) => setData('code_subsidiary', e.target.value)} />
                {errors.code_subsidiary && <p className="text-xs text-red-600">{errors.code_subsidiary}</p>}
              </div>
              <div className="space-y-1">
                <Label>Temporada (code_season)</Label>
                <Input value={data.code_season} onChange={(e) => setData('code_season', e.target.value)} />
                {errors.code_season && <p className="text-xs text-red-600">{errors.code_season}</p>}
              </div>
              <div className="space-y-1">
                <Label>Planta Garces</Label>
                <Input value={data.subsidiary_garces} onChange={(e) => setData('subsidiary_garces', e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>Temporada Garces</Label>
                <Input value={data.season_garces} onChange={(e) => setData('season_garces', e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>Tipo tarja</Label>
                <Input value={data.tipo_tarja} onChange={(e) => setData('tipo_tarja', e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>Tipo fruta</Label>
                <Input value={data.tipo_fruta} onChange={(e) => setData('tipo_fruta', e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>Tipo pallet</Label>
                <Input value={data.tipo_pallet} onChange={(e) => setData('tipo_pallet', e.target.value)} />
              </div>
              <div className="space-y-1">
                <Label>isExport</Label>
                <Input type="number" min="0" max="1" value={data.is_export} onChange={(e) => setData('is_export', e.target.value)} />
              </div>
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
        <CardHeader><CardTitle>Defaults ({defaults.total})</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Planta</TableHead>
                <TableHead>Temporada</TableHead>
                <TableHead>Tarja</TableHead>
                <TableHead>Fruta</TableHead>
                <TableHead>Pallet</TableHead>
                <TableHead>isExport</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {defaults.data.map((d) => (
                <TableRow key={d.id}>
                  <TableCell className="font-mono text-xs">{d.code_subsidiary}</TableCell>
                  <TableCell className="font-mono text-xs">{d.code_season}</TableCell>
                  <TableCell className="text-xs">{d.tipo_tarja || '—'}</TableCell>
                  <TableCell className="text-xs">{d.tipo_fruta || '—'}</TableCell>
                  <TableCell className="text-xs">{d.tipo_pallet || '—'}</TableCell>
                  <TableCell className="text-xs">{d.is_export}</TableCell>
                  <TableCell className="text-xs">{d.activo ? 'Sí' : 'No'}</TableCell>
                  <TableCell className="text-right space-x-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(d)}>Editar</Button>
                    <Button size="sm" variant="destructive" onClick={() => destroy(d)}>Eliminar</Button>
                  </TableCell>
                </TableRow>
              ))}
              {defaults.data.length === 0 && (
                <TableRow><TableCell colSpan={8} className="text-center text-muted-foreground">Sin defaults</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
          <GarcesPagination links={defaults.links} />
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Defaults Pallet Garces</h2>} />
);
