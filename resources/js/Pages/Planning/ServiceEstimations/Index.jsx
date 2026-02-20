import React, { useEffect, useMemo } from 'react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

const emptyRow = () => ({
  service_id: '',
  especie_id: '',
  variedad_id: '',
  dia: '',
  total_kilo: '',
  acopio: false,
  mexico: '',
});

const statusLabel = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (normalized === 'active') return 'Activa';
  if (normalized === 'superseded') return 'Reemplazada';
  if (normalized === 'rejected') return 'Rechazada';
  return value || '-';
};

const formatDate = (value) => {
  if (!value) return '-';
  const [datePart] = String(value).split(' ');
  const [year, month, day] = datePart.split('-');
  if (!year || !month || !day) return value;
  return `${day}-${month}-${year}`;
};

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error de validación.';
  return Array.isArray(value) ? value.join(', ') : value;
};

export default function Index({ auth, versions, seasons, services, especies, statuses, filters }) {
  const { flash } = usePage().props;
  const versionRows = Array.isArray(versions?.data) ? versions.data : [];
  const paginationLinks = Array.isArray(versions?.links) ? versions.links : [];

  const filterForm = useForm({
    season_id: filters?.season_id || '',
    status: filters?.status || '',
  });

  const form = useForm({
    season_id: '',
    period_start_week: '',
    period_end_week: '',
    notes: '',
    rows: [emptyRow()],
  });

  const especiesMap = useMemo(() => {
    const map = new Map();
    (especies || []).forEach((especie) => {
      map.set(String(especie.id), Array.isArray(especie.variedades) ? especie.variedades : []);
    });
    return map;
  }, [especies]);

  useEffect(() => {
    const timer = setTimeout(() => {
      filterForm.get(route('planning.service-estimations.index'), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
      });
    }, 300);

    return () => clearTimeout(timer);
  }, [filterForm.data.season_id, filterForm.data.status]);

  useEffect(() => {
    if (!flash?.success) return;
    toast.success(flash.success);
  }, [flash?.success]);

  useEffect(() => {
    if (!flash?.error) return;
    toast.error(flash.error);
  }, [flash?.error]);

  const addRow = () => {
    form.setData('rows', [...form.data.rows, emptyRow()]);
  };

  const removeRow = (index) => {
    const nextRows = form.data.rows.filter((_, rowIndex) => rowIndex !== index);
    form.setData('rows', nextRows.length > 0 ? nextRows : [emptyRow()]);
  };

  const setRowField = (index, field, value) => {
    const nextRows = form.data.rows.map((row, rowIndex) => {
      if (rowIndex !== index) return row;
      const updated = { ...row, [field]: value };
      if (field === 'especie_id') {
        updated.variedad_id = '';
      }
      return updated;
    });
    form.setData('rows', nextRows);
  };

  const handleSubmit = (event) => {
    event.preventDefault();

    const payloadRows = form.data.rows.map((row) => ({
      service_id: row.service_id,
      variedad_id: row.variedad_id,
      dia: row.dia,
      total_kilo: row.total_kilo,
      acopio: !!row.acopio,
      mexico: row.mexico === '' ? null : row.mexico === '1',
    }));

    form.transform((data) => ({
      season_id: data.season_id,
      period_start_week: data.period_start_week || null,
      period_end_week: data.period_end_week || null,
      notes: data.notes || null,
      source: 'planner_manual',
      rows: payloadRows,
    }));

    form.post(route('planning.service-estimations.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        form.setData('rows', [emptyRow()]);
      },
      onError: (errors) => {
        toast.error(firstError(errors));
      },
    });
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Estimaciones de Servicios</h2>}>
      <Head title="Estimaciones de Servicios" />
      <Toaster richColors position="top-right" />

      <div className="mx-auto w-full max-w-[1600px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="space-y-2">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <CardTitle className="text-2xl font-bold">Carga Manual de Servicios</CardTitle>
                <p className="mt-1 text-sm text-slate-600">
                  Ingresa la estimación informada por servicios (excepto 4 y 6). Se guarda versionado y se combina automáticamente con estimaciones de agrónomos para planificar.
                </p>
              </div>
              <Link href={route('planning.fruit-flow.index')} className="text-sm text-indigo-600 hover:underline">
                Volver a flujo de fruta
              </Link>
            </div>
          </CardHeader>
          <CardContent>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div className="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-4">
                <div>
                  <label className="mb-1 block text-sm font-medium">Temporada</label>
                  <select
                    value={form.data.season_id}
                    onChange={(event) => form.setData('season_id', event.target.value)}
                    className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                  >
                    <option value="">Seleccione</option>
                    {(seasons || []).map((season) => (
                      <option key={season.id} value={season.id}>{season.code}</option>
                    ))}
                  </select>
                  {form.errors.season_id ? <div className="mt-1 text-xs text-red-600">{form.errors.season_id}</div> : null}
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium">Semana inicio (opcional)</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={form.data.period_start_week}
                    onChange={(event) => form.setData('period_start_week', event.target.value)}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium">Semana fin (opcional)</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={form.data.period_end_week}
                    onChange={(event) => form.setData('period_end_week', event.target.value)}
                  />
                </div>

                <div>
                  <label className="mb-1 block text-sm font-medium">Notas</label>
                  <Input
                    value={form.data.notes}
                    onChange={(event) => form.setData('notes', event.target.value)}
                    placeholder="Comentario opcional"
                  />
                </div>
              </div>

              <div className="overflow-hidden rounded-xl border border-slate-200">
                <Table>
                  <TableHeader className="bg-slate-50">
                    <TableRow>
                      <TableHead>Servicio</TableHead>
                      <TableHead>Especie</TableHead>
                      <TableHead>Variedad</TableHead>
                      <TableHead>Día</TableHead>
                      <TableHead>Total kilo</TableHead>
                      <TableHead>Acopio</TableHead>
                      <TableHead>México</TableHead>
                      <TableHead className="text-right">Acciones</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {form.data.rows.map((row, index) => {
                      const rowVariedades = especiesMap.get(String(row.especie_id)) || [];
                      return (
                        <TableRow key={index}>
                          <TableCell>
                            <select
                              value={row.service_id}
                              onChange={(event) => setRowField(index, 'service_id', event.target.value)}
                              className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                            >
                              <option value="">Seleccione</option>
                              {(services || []).map((service) => (
                                <option key={service.id} value={service.id}>
                                  {service.name} · {service.owner_name || 'Sin dueño'}
                                </option>
                              ))}
                            </select>
                          </TableCell>
                          <TableCell>
                            <select
                              value={row.especie_id}
                              onChange={(event) => setRowField(index, 'especie_id', event.target.value)}
                              className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                            >
                              <option value="">Seleccione</option>
                              {(especies || []).map((especie) => (
                                <option key={especie.id} value={especie.id}>{especie.name}</option>
                              ))}
                            </select>
                          </TableCell>
                          <TableCell>
                            <select
                              value={row.variedad_id}
                              onChange={(event) => setRowField(index, 'variedad_id', event.target.value)}
                              className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                            >
                              <option value="">Seleccione</option>
                              {rowVariedades.map((variedad) => (
                                <option key={variedad.id} value={variedad.id}>{variedad.name}</option>
                              ))}
                            </select>
                          </TableCell>
                          <TableCell>
                            <Input
                              type="date"
                              value={row.dia}
                              onChange={(event) => setRowField(index, 'dia', event.target.value)}
                            />
                          </TableCell>
                          <TableCell>
                            <Input
                              type="number"
                              min="0"
                              step="0.001"
                              value={row.total_kilo}
                              onChange={(event) => setRowField(index, 'total_kilo', event.target.value)}
                            />
                          </TableCell>
                          <TableCell>
                            <label className="inline-flex items-center gap-2 text-sm">
                              <input
                                type="checkbox"
                                checked={!!row.acopio}
                                onChange={(event) => setRowField(index, 'acopio', event.target.checked)}
                              />
                              Sí
                            </label>
                          </TableCell>
                          <TableCell>
                            <select
                              value={row.mexico}
                              onChange={(event) => setRowField(index, 'mexico', event.target.value)}
                              className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                            >
                              <option value="">-</option>
                              <option value="1">Sí</option>
                              <option value="0">No</option>
                            </select>
                          </TableCell>
                          <TableCell className="text-right">
                            <Button type="button" variant="outline" size="sm" onClick={() => removeRow(index)}>
                              Quitar
                            </Button>
                          </TableCell>
                        </TableRow>
                      );
                    })}
                  </TableBody>
                </Table>
              </div>

              {form.errors.rows ? <div className="text-sm text-red-600">{form.errors.rows}</div> : null}

              <div className="flex flex-wrap items-center justify-between gap-2">
                <Button type="button" variant="secondary" onClick={addRow}>
                  Agregar fila
                </Button>
                <Button type="submit" disabled={form.processing || !form.data.season_id}>
                  {form.processing ? 'Guardando...' : 'Publicar versión de servicios'}
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader>
            <CardTitle className="text-xl font-bold">Historial de versiones (Servicios)</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-3">
              <div>
                <label className="mb-1 block text-sm font-medium">Temporada</label>
                <select
                  value={filterForm.data.season_id}
                  onChange={(event) => filterForm.setData('season_id', event.target.value)}
                  className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                >
                  <option value="">(Todas)</option>
                  {(seasons || []).map((season) => (
                    <option key={season.id} value={season.id}>{season.code}</option>
                  ))}
                </select>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Estado</label>
                <select
                  value={filterForm.data.status}
                  onChange={(event) => filterForm.setData('status', event.target.value)}
                  className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                >
                  <option value="">(Todos)</option>
                  {(statuses || []).map((status) => (
                    <option key={status} value={status}>{statusLabel(status)}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="overflow-hidden rounded-xl border border-slate-200">
              <Table>
                <TableHeader className="bg-slate-50">
                  <TableRow>
                    <TableHead>Versión</TableHead>
                    <TableHead>Temporada</TableHead>
                    <TableHead>Periodo</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Usuario</TableHead>
                    <TableHead>Filas</TableHead>
                    <TableHead>Fecha</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {versionRows.map((version) => (
                    <TableRow key={version.id}>
                      <TableCell>#{version.id}</TableCell>
                      <TableCell>{version.season?.code || '-'}</TableCell>
                      <TableCell>{version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</TableCell>
                      <TableCell>{statusLabel(version.status)}</TableCell>
                      <TableCell>{version.uploader?.name || '-'}</TableCell>
                      <TableCell>{version.rows_count ?? 0}</TableCell>
                      <TableCell>{formatDate(version.created_at)}</TableCell>
                    </TableRow>
                  ))}
                  {versionRows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={7} className="py-8 text-center text-sm text-slate-500">
                        No hay versiones de servicios para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {paginationLinks.map((link, index) => (
                <Link
                  key={index}
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
