import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import Combobox from '@/Components/ui/combobox';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

export default function Index({ auth, versions, seasons, filters }) {
  const { import_errors: importErrors } = usePage().props;
  const versionRows = Array.isArray(versions?.data) ? versions.data : (Array.isArray(versions) ? versions : []);
  const paginationLinks = Array.isArray(versions?.links)
    ? versions.links
    : Array.isArray(versions?.meta?.links)
      ? versions.meta.links
      : [];
  const { data, setData, get } = useForm({
    season_id: filters?.season_id || '',
  });

  const importForm = useForm({
    file: null,
    season_id: '',
    period_start_week: '',
    period_end_week: '',
    notes: '',
  });

  useEffect(() => {
    const timer = setTimeout(() => {
      get(route('estimations.biweekly.index'), { preserveState: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
  }, [data.season_id]);

  useEffect(() => {
    if (!importErrors) return;
    const list = Array.isArray(importErrors) ? importErrors : [importErrors];
    list.forEach((item) => {
      const label = item?.label ? ` (${item.label})` : '';
      const file = item?.file ? ` [${item.file}]` : '';
      toast.error(`Error de importación${label}${file}: ${item?.message || 'Ocurrió un error.'}`);
    });
  }, [importErrors]);


  const seasonOptions = [{ value: '', label: '(Todas)' }, ...seasons.map(s => ({ value: String(s.id), label: s.code }))];

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Estimaciones Bisemanales</h2>}>
      <Head title="Estimaciones Bisemanales" />
      <Toaster richColors position="top-right" />
      <div className="container mx-auto py-10">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-2xl font-bold">Versiones Bisemanales</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
              <Combobox
                value={data.season_id}
                onChange={(val) => setData('season_id', val)}
                options={seasonOptions}
                placeholder="Temporada"
                searchPlaceholder="Buscar temporada..."
                className="w-48"
              />
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                importForm.post(route('estimations.biweekly.upload'), {
                  forceFormData: true,
                  onSuccess: () => {
                    toast.success('Importación bisemanal en cola.');
                    importForm.reset();
                  },
                  onError: (errors) => {
                    toast.error(firstError(errors));
                  },
                });
              }}
              encType="multipart/form-data"
              className="mb-6 flex flex-col md:flex-row md:items-end gap-2"
            >
              <div>
                <label className="block text-sm">Excel (.xlsx)</label>
                <input type="file" onChange={(e) => importForm.setData('file', e.target.files[0])} className="border rounded px-2 py-1" />
                {importForm.errors.file && <div className="text-red-600 text-sm">{importForm.errors.file}</div>}
              </div>
              <div>
                <label className="block text-sm">Temporada</label>
                <select value={importForm.data.season_id} onChange={(e) => importForm.setData('season_id', e.target.value)} className="border rounded px-2 py-2">
                  <option value="">Seleccione</option>
                  {seasons.map(s => <option key={s.id} value={s.id}>{s.code}</option>)}
                </select>
                {importForm.errors.season_id && <div className="text-red-600 text-sm">{importForm.errors.season_id}</div>}
              </div>
              <div>
                <label className="block text-sm">Semana inicio</label>
                <Input type="number" min="1" max="53" value={importForm.data.period_start_week} onChange={(e) => importForm.setData('period_start_week', e.target.value)} />
              </div>
              <div>
                <label className="block text-sm">Semana fin</label>
                <Input type="number" min="1" max="53" value={importForm.data.period_end_week} onChange={(e) => importForm.setData('period_end_week', e.target.value)} />
              </div>
              <Button type="submit" disabled={importForm.processing}>Subir Excel</Button>
            </form>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Temporada</TableHead>
                  <TableHead>Periodo</TableHead>
                  <TableHead>Origen</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Usuario</TableHead>
                  <TableHead>Filas</TableHead>
                  <TableHead>Fecha</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {versionRows.map((v) => (
                  <TableRow key={v.id}>
                    <TableCell>{v.season?.code || '-'}</TableCell>
                    <TableCell>{v.period_start_week ? `${v.period_start_week}-${v.period_end_week || ''}` : '-'}</TableCell>
                    <TableCell>{v.source || '-'}</TableCell>
                    <TableCell>{v.status || '-'}</TableCell>
                    <TableCell>{v.uploader?.name || '-'}</TableCell>
                    <TableCell>{v.rows_count ?? '-'}</TableCell>
                    <TableCell>{v.created_at || '-'}</TableCell>
                    <TableCell>
                      <Link href={route('estimations.biweekly.show', v.id)} className="text-indigo-600 hover:underline">Ver</Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <div className="mt-4 flex gap-2">
              {paginationLinks.map((link, idx) => (
                <Link key={idx} href={link.url || '#'} className={`px-2 ${link.active ? 'font-bold' : ''}`} dangerouslySetInnerHTML={{ __html: link.label }} />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
