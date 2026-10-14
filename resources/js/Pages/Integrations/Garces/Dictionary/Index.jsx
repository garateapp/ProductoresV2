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

const emptyForm = {
  tipo: '',
  clave_interna: '',
  clave_garces: '',
  vigencia_desde: '',
  vigencia_hasta: '',
  prioridad: 0,
  activo: true,
  notas: '',
};

export default function Index({ mappings, filters, types }) {
  const [editing, setEditing] = useState(null);
  const [importText, setImportText] = useState('');
  const { data, setData, post, put, processing, errors, reset } = useForm(emptyForm);

  const submit = (e) => {
    e.preventDefault();
    if (editing) {
      put(route('integrations.garces.dictionary.update', editing), {
        onSuccess: () => { setEditing(null); reset(); },
      });
    } else {
      post(route('integrations.garces.dictionary.store'), {
        onSuccess: () => reset(),
      });
    }
  };

  const startEdit = (m) => {
    setEditing(m.id);
    setData({
      tipo: m.tipo ?? '',
      clave_interna: m.clave_interna ?? '',
      clave_garces: m.clave_garces ?? '',
      vigencia_desde: m.vigencia_desde ?? '',
      vigencia_hasta: m.vigencia_hasta ?? '',
      prioridad: m.prioridad ?? 0,
      activo: !!m.activo,
      notas: m.notas ?? '',
    });
  };

  const destroy = (m) => {
    if (confirm(`¿Eliminar el mapeo ${m.clave_interna} → ${m.clave_garces}?`)) {
      router.delete(route('integrations.garces.dictionary.destroy', m.id));
    }
  };

  const runImport = () => {
    if (!importText.trim()) return;
    router.post(route('integrations.garces.dictionary.import'), { csv: importText }, {
      onSuccess: () => setImportText(''),
    });
  };

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">Diccionario de códigos Garces</h1>
          <p className="text-muted-foreground">Homologación interno → Garces/SisPro</p>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader><CardTitle>{editing ? 'Editar mapeo' : 'Nuevo mapeo'}</CardTitle></CardHeader>
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
                  <Label>Clave interna</Label>
                  <Input value={data.clave_interna} onChange={(e) => setData('clave_interna', e.target.value)} />
                  {errors.clave_interna && <p className="text-xs text-red-600">{errors.clave_interna}</p>}
                </div>
                <div className="space-y-1">
                  <Label>Clave Garces</Label>
                  <Input value={data.clave_garces} onChange={(e) => setData('clave_garces', e.target.value)} />
                  {errors.clave_garces && <p className="text-xs text-red-600">{errors.clave_garces}</p>}
                </div>
                <div className="space-y-1">
                  <Label>Vigencia desde</Label>
                  <Input type="date" value={data.vigencia_desde} onChange={(e) => setData('vigencia_desde', e.target.value)} />
                </div>
                <div className="space-y-1">
                  <Label>Vigencia hasta</Label>
                  <Input type="date" value={data.vigencia_hasta} onChange={(e) => setData('vigencia_hasta', e.target.value)} />
                </div>
                <div className="space-y-1">
                  <Label>Prioridad</Label>
                  <Input type="number" min="0" value={data.prioridad} onChange={(e) => setData('prioridad', e.target.value)} />
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
                  <Button type="button" variant="ghost" onClick={() => { setEditing(null); reset(); }}>
                    Cancelar
                  </Button>
                )}
              </div>
            </form>
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>Importar CSV</CardTitle></CardHeader>
          <CardContent className="space-y-3">
            <p className="text-xs text-muted-foreground">
              Encabezado: tipo,clave_interna,clave_garces,vigencia_desde,vigencia_hasta,prioridad,activo,notas
            </p>
            <input
              type="file"
              accept=".csv,.txt"
              onChange={(e) => {
                const file = e.target.files?.[0];
                if (file) file.text().then(setImportText);
              }}
              className="text-sm"
            />
            <Textarea
              rows={6}
              placeholder="o pega el CSV aquí"
              value={importText}
              onChange={(e) => setImportText(e.target.value)}
            />
            <Button type="button" onClick={runImport} disabled={!importText.trim()}>Importar</Button>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader><CardTitle>Mapeos ({mappings.total})</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Tipo</TableHead>
                <TableHead>Interno</TableHead>
                <TableHead>Garces</TableHead>
                <TableHead>Vigencia</TableHead>
                <TableHead>Prior.</TableHead>
                <TableHead>Activo</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {mappings.data.map((m) => (
                <TableRow key={m.id}>
                  <TableCell className="text-xs">{m.tipo}</TableCell>
                  <TableCell className="font-mono text-xs">{m.clave_interna}</TableCell>
                  <TableCell className="font-mono text-xs">{m.clave_garces}</TableCell>
                  <TableCell className="text-xs">
                    {m.vigencia_desde || '—'} / {m.vigencia_hasta || '—'}
                  </TableCell>
                  <TableCell className="text-xs">{m.prioridad}</TableCell>
                  <TableCell className="text-xs">{m.activo ? 'Sí' : 'No'}</TableCell>
                  <TableCell className="text-right space-x-2">
                    <Button size="sm" variant="outline" onClick={() => startEdit(m)}>Editar</Button>
                    <Button size="sm" variant="destructive" onClick={() => destroy(m)}>Eliminar</Button>
                  </TableCell>
                </TableRow>
              ))}
              {mappings.data.length === 0 && (
                <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground">Sin mapeos</TableCell></TableRow>
              )}
            </TableBody>
          </Table>
          <GarcesPagination links={mappings.links} />
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Diccionario Garces</h2>} />
);
