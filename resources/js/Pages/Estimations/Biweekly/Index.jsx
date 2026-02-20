import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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

const BIWEEKLY_STATUSES = ['active', 'superseded', 'rejected'];

const statusLabel = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (normalized === 'active') return 'Activa';
  if (normalized === 'superseded') return 'Reemplazada';
  if (normalized === 'rejected') return 'Rechazada';
  return value || '-';
};

const statusBadgeClass = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (normalized === 'active') return 'bg-emerald-50 text-emerald-700 border-emerald-200';
  if (normalized === 'superseded') return 'bg-amber-50 text-amber-700 border-amber-200';
  if (normalized === 'rejected') return 'bg-rose-50 text-rose-700 border-rose-200';
  return 'bg-slate-100 text-slate-600 border-slate-200';
};

const formatDate = (value) => {
  if (!value) return '-';
  const [datePart] = String(value).split(' ');
  const [year, month, day] = datePart.split('-');
  if (!year || !month || !day) return value;
  return `${day}-${month}-${year}`;
};

export default function Index({ auth, versions, seasons, filters }) {
  const { import_errors: importErrors, import_feedback: importFeedback, flash } = usePage().props;
  const versionRows = Array.isArray(versions?.data) ? versions.data : (Array.isArray(versions) ? versions : []);
  const paginationLinks = Array.isArray(versions?.links)
    ? versions.links
    : Array.isArray(versions?.meta?.links)
      ? versions.meta.links
      : [];
  const { data, setData, get } = useForm({
    season_id: filters?.season_id || '',
    status: filters?.status || '',
  });

  const importForm = useForm({
    file: null,
    season_id: '',
    period_start_week: '',
    period_end_week: '',
    notes: '',
  });
  const [isPollingImportFeedback, setIsPollingImportFeedback] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      get(route('estimations.biweekly.index'), { preserveState: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
  }, [data.season_id, data.status]);

  useEffect(() => {
    if (!isPollingImportFeedback) return undefined;

    let ticks = 0;
    const interval = setInterval(() => {
      ticks += 1;

      router.reload({
        only: ['import_feedback', 'import_errors'],
        preserveState: true,
        preserveScroll: true,
      });

      if (ticks >= 90) {
        setIsPollingImportFeedback(false);
      }
    }, 4000);

    return () => clearInterval(interval);
  }, [isPollingImportFeedback]);

  useEffect(() => {
    const payload = importFeedback ?? importErrors;
    if (!payload) return;

    const list = Array.isArray(payload) ? payload : [payload];
    let hasSuccess = false;

    list.forEach((item) => {
      const label = item?.label ? ` (${item.label})` : '';
      const file = item?.file ? ` [${item.file}]` : '';

      if (item?.status === 'success') {
        hasSuccess = true;
        toast.success(`Importación finalizada${label}${file}: ${item?.message || 'Completada.'}`);
        return;
      }

      toast.error(`Error de importación${label}${file}: ${item?.message || 'Ocurrió un error.'}`);
    });

    setIsPollingImportFeedback(false);

    if (hasSuccess) {
      router.reload({
        only: ['versions'],
        preserveState: true,
        preserveScroll: true,
      });
    }
  }, [importFeedback, importErrors]);

  useEffect(() => {
    if (!flash?.error) return;
    toast.error(flash.error);
  }, [flash?.error]);

  useEffect(() => {
    if (!flash?.success) return;
    toast.success(flash.success);
  }, [flash?.success]);

  const seasonOptions = [{ value: '', label: '(Todas)' }, ...seasons.map(s => ({ value: String(s.id), label: s.code }))];
  const statusOptions = [
    { value: '', label: '(Todos)' },
    ...BIWEEKLY_STATUSES.map((status) => ({
      value: status,
      label: statusLabel(status),
    })),
  ];

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Estimaciones Bisemanales</h2>}>
      <Head title="Estimaciones Bisemanales" />
      <Toaster richColors position="top-right" />
      <div className="mx-auto w-full max-w-[1400px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="space-y-2">
            <CardTitle className="text-2xl font-bold">Versiones Bisemanales</CardTitle>
            <p className="text-sm text-slate-600">
              Administra el historial de cargas bisemanales por temporada y estado.
            </p>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
              <div className="mb-3 text-sm font-medium text-slate-700">Filtros de versiones</div>
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Combobox
                  value={data.season_id}
                  onChange={(val) => setData('season_id', val)}
                  options={seasonOptions}
                  placeholder="Temporada"
                  searchPlaceholder="Buscar temporada..."
                  className="w-full"
                />
                <Combobox
                  value={data.status}
                  onChange={(val) => setData('status', val)}
                  options={statusOptions}
                  placeholder="Estado"
                  searchPlaceholder="Buscar estado..."
                  className="w-full"
                />
              </div>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                importForm.post(route('estimations.biweekly.upload'), {
                  forceFormData: true,
                  onSuccess: (page) => {
                    const flashError = page?.props?.flash?.error;
                    if (flashError) {
                      return;
                    }

                    toast.success('Importación bisemanal en cola.');
                    setIsPollingImportFeedback(true);
                    importForm.reset();
                  },
                  onError: (errors) => {
                    toast.error(firstError(errors));
                  },
                });
              }}
              encType="multipart/form-data"
              className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
              <div className="mb-3 text-sm font-medium text-slate-700">Subir nueva versión bisemanal</div>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                  <label className="mb-1 block text-sm">Excel (.xlsx/.xls)</label>
                  <input
                    type="file"
                    accept=".xlsx,.xls"
                    onChange={(e) => importForm.setData('file', e.target.files?.[0] || null)}
                    className="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  {importForm.errors.file && <div className="mt-1 text-sm text-red-600">{importForm.errors.file}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Temporada</label>
                  <select
                    value={importForm.data.season_id}
                    onChange={(e) => importForm.setData('season_id', e.target.value)}
                    className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                  >
                    <option value="">Seleccione</option>
                    {seasons.map((season) => <option key={season.id} value={season.id}>{season.code}</option>)}
                  </select>
                  {importForm.errors.season_id && <div className="mt-1 text-sm text-red-600">{importForm.errors.season_id}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Semana inicio</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={importForm.data.period_start_week}
                    onChange={(e) => importForm.setData('period_start_week', e.target.value)}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm">Semana fin</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={importForm.data.period_end_week}
                    onChange={(e) => importForm.setData('period_end_week', e.target.value)}
                  />
                </div>
              </div>
              <div className="mt-4 flex justify-end">
                <Button type="submit" disabled={importForm.processing || !importForm.data.file || !importForm.data.season_id}>
                  {importForm.processing ? 'Subiendo...' : 'Subir Excel'}
                </Button>
              </div>
            </form>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <Table>
                <TableHeader className="bg-slate-50">
                  <TableRow>
                    <TableHead>N° Versión</TableHead>
                    <TableHead>Temporada</TableHead>
                    <TableHead>Periodo</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Usuario</TableHead>
                    <TableHead>Filas</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {versionRows.map((version) => (
                    <TableRow key={version.id} className="hover:bg-slate-50/80">
                      <TableCell>{version.id}</TableCell>
                      <TableCell>{version.season?.code || '-'}</TableCell>
                      <TableCell>{version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</TableCell>
                      <TableCell>{version.source || '-'}</TableCell>
                      <TableCell>
                        <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${statusBadgeClass(version.status)}`}>
                          {statusLabel(version.status)}
                        </span>
                      </TableCell>
                      <TableCell>{version.uploader?.name || '-'}</TableCell>
                      <TableCell>{version.rows_count ?? '-'}</TableCell>
                      <TableCell>{formatDate(version.created_at)}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-2">
                          <a
                            href={route('estimations.biweekly.download', version.id)}
                            className="inline-flex h-8 items-center rounded-md border border-slate-300 px-3 text-xs font-medium text-slate-700 hover:bg-slate-100"
                          >
                            Descargar
                          </a>
                          <Link
                            href={route('estimations.biweekly.show', version.id)}
                            className="inline-flex h-8 items-center rounded-md bg-indigo-600 px-3 text-xs font-medium text-white hover:bg-indigo-700"
                          >
                            Ver
                          </Link>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                  {versionRows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={9} className="py-8 text-center text-sm text-slate-500">
                        No hay versiones bisemanales para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {paginationLinks.map((link, idx) => (
                <Link
                  key={idx}
                  href={link.url || '#'}
                  className={`rounded-md border px-2.5 py-1.5 text-sm ${
                    link.active
                      ? 'border-indigo-600 bg-indigo-50 font-semibold text-indigo-700'
                      : 'border-slate-200 text-slate-600 hover:bg-slate-50'
                  } ${!link.url ? 'pointer-events-none opacity-60' : ''}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
