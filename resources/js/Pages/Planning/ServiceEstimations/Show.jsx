import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

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
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

export default function Show({ auth, version, rows, services, variedades }) {
  const { flash } = usePage().props;
  const tableRows = Array.isArray(rows) ? rows : [];
  const serviceOptions = Array.isArray(services) ? services : [];
  const variedadOptions = Array.isArray(variedades) ? variedades : [];
  const [rowEdits, setRowEdits] = useState({});
  const [savingRowId, setSavingRowId] = useState(null);
  const [serviceFilter, setServiceFilter] = useState('');
  const [variedadFilter, setVariedadFilter] = useState('');

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  useEffect(() => {
    const next = {};
    tableRows.forEach((row) => {
      next[row.id] = {
        service_id: row.service_id ? String(row.service_id) : '',
        variedad_id: row.variedad_id ? String(row.variedad_id) : '',
        planta: row.planta || '',
        tipo: row.tipo || '',
        acopio: !!row.acopio,
        mexico: row.mexico === null ? '' : row.mexico ? '1' : '0',
        dia: row.dia || '',
        total_kilo: row.total_kilo ?? '',
      };
    });
    setRowEdits(next);
  }, [tableRows]);

  const filteredRows = useMemo(() => {
    const serviceNeedle = serviceFilter.trim().toLowerCase();
    const variedadNeedle = variedadFilter.trim().toLowerCase();

    return tableRows.filter((row) => {
      if (serviceNeedle && !String(row.service_name || '').toLowerCase().includes(serviceNeedle)) {
        return false;
      }
      if (variedadNeedle && !String(row.variedad || '').toLowerCase().includes(variedadNeedle)) {
        return false;
      }
      return true;
    });
  }, [tableRows, serviceFilter, variedadFilter]);

  const updateRowEditField = (rowId, field, value) => {
    setRowEdits((prev) => ({
      ...prev,
      [rowId]: {
        ...(prev[rowId] || {}),
        [field]: value,
      },
    }));
  };

  const handleSaveRow = (row) => {
    const current = rowEdits[row.id];
    if (!current) return;

    const payload = {
      row_id: row.id,
      row: {
        service_id: current.service_id,
        variedad_id: current.variedad_id,
        planta: current.planta || null,
        tipo: current.tipo || null,
        acopio: !!current.acopio,
        mexico: current.mexico === '' ? null : current.mexico === '1',
        dia: current.dia,
        total_kilo: current.total_kilo,
      },
    };

    setSavingRowId(row.id);
    router.patch(
      route('planning.service-estimations.rows.update', {
        estimation_biweekly_version: version.id,
        estimation_biweekly_row: row.id,
      }),
      payload,
      {
        preserveScroll: true,
        onSuccess: () => setSavingRowId(null),
        onError: (errors) => {
          setSavingRowId(null);
          toast.error(firstError(errors));
        },
      },
    );
  };

  const handleDeleteRow = (row) => {
    if (!window.confirm(`¿Eliminar la fila del servicio ${row.service_name || '-'} (${row.variedad || '-'})?`)) {
      return;
    }

    router.delete(
      route('planning.service-estimations.rows.destroy', {
        estimation_biweekly_version: version.id,
        estimation_biweekly_row: row.id,
      }),
      {
        preserveScroll: true,
        onError: (errors) => {
          toast.error(firstError(errors));
        },
      },
    );
  };

  const handleDeleteVersion = () => {
    if (!window.confirm(`¿Eliminar la versión #${version.id}? Esta acción no se puede deshacer.`)) {
      return;
    }

    router.delete(route('planning.service-estimations.destroy', version.id), {
      preserveScroll: true,
      onError: (errors) => {
        toast.error(firstError(errors));
      },
    });
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Planificación · Editar estimación de servicios</h2>}>
      <Head title={`Estimación servicios #${version.id}`} />
      <Toaster richColors position="top-right" />

      <div className="mx-auto w-full max-w-[1700px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="space-y-2">
            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div>
                <CardTitle className="text-2xl font-bold">Versión #{version.id}</CardTitle>
                <p className="mt-1 text-sm text-slate-600">
                  Temporada {version.season?.code || '-'} · Estado {statusLabel(version.status)} · Creada {formatDate(version.created_at)}
                </p>
              </div>
              <div className="flex items-center gap-2">
                <Link href={route('planning.service-estimations.index')}>
                  <Button variant="outline">Volver</Button>
                </Link>
                <Button type="button" variant="destructive" onClick={handleDeleteVersion}>
                  Eliminar versión
                </Button>
              </div>
            </div>
            <div className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-4">
              <div><span className="font-semibold">Periodo:</span> {version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</div>
              <div><span className="font-semibold">Origen:</span> {version.source || '-'}</div>
              <div><span className="font-semibold">Usuario:</span> {version.uploader || '-'}</div>
              <div><span className="font-semibold">Filas:</span> {tableRows.length}</div>
            </div>
          </CardHeader>
        </Card>

        <Card className="border-slate-200 shadow-sm">
          <CardHeader>
            <CardTitle className="text-xl font-bold">Filas de estimación</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50/60 p-4 md:grid-cols-3">
              <div>
                <label className="mb-1 block text-sm font-medium">Servicio</label>
                <Input
                  value={serviceFilter}
                  onChange={(event) => setServiceFilter(event.target.value)}
                  placeholder="Filtrar servicio..."
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Variedad</label>
                <Input
                  value={variedadFilter}
                  onChange={(event) => setVariedadFilter(event.target.value)}
                  placeholder="Filtrar variedad..."
                />
              </div>
            </div>

            <div className="overflow-auto rounded-xl border border-slate-200">
              <Table className="min-w-[1550px]">
                <TableHeader className="bg-slate-50">
                  <TableRow>
                    <TableHead>Servicio</TableHead>
                    <TableHead>Productor dueño</TableHead>
                    <TableHead>Variedad</TableHead>
                    <TableHead>Día</TableHead>
                    <TableHead>Semana</TableHead>
                    <TableHead>Planta</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Acopio</TableHead>
                    <TableHead>México</TableHead>
                    <TableHead className="text-right">Total kilo</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredRows.map((row) => {
                    const edited = rowEdits[row.id] || {};
                    return (
                      <TableRow key={row.id}>
                        <TableCell>
                          <select
                            value={edited.service_id ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'service_id', event.target.value)}
                            className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                          >
                            <option value="">Seleccione</option>
                            {serviceOptions.map((service) => (
                              <option key={service.id} value={service.id}>
                                {service.name} · {service.owner_name || 'Sin dueño'}
                              </option>
                            ))}
                          </select>
                        </TableCell>
                        <TableCell>
                          <div className="text-sm">{row.producer_name || '-'}</div>
                          <div className="text-xs text-slate-500">{row.csg || '-'}</div>
                        </TableCell>
                        <TableCell>
                          <select
                            value={edited.variedad_id ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'variedad_id', event.target.value)}
                            className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                          >
                            <option value="">Seleccione</option>
                            {variedadOptions.map((variedad) => (
                              <option key={variedad.id} value={variedad.id}>
                                {variedad.especie} · {variedad.name}
                              </option>
                            ))}
                          </select>
                        </TableCell>
                        <TableCell>
                          <Input
                            type="date"
                            value={edited.dia ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'dia', event.target.value)}
                          />
                        </TableCell>
                        <TableCell>{row.semana || '-'}</TableCell>
                        <TableCell>
                          <Input
                            value={edited.planta ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'planta', event.target.value)}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            value={edited.tipo ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'tipo', event.target.value)}
                          />
                        </TableCell>
                        <TableCell>
                          <label className="inline-flex items-center gap-2 text-sm">
                            <input
                              type="checkbox"
                              checked={!!edited.acopio}
                              onChange={(event) => updateRowEditField(row.id, 'acopio', event.target.checked)}
                            />
                            Sí
                          </label>
                        </TableCell>
                        <TableCell>
                          <select
                            value={edited.mexico ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'mexico', event.target.value)}
                            className="h-9 w-full rounded-md border border-slate-300 px-2 text-sm"
                          >
                            <option value="">-</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                          </select>
                        </TableCell>
                        <TableCell className="text-right">
                          <Input
                            type="number"
                            min="0"
                            step="0.001"
                            value={edited.total_kilo ?? ''}
                            onChange={(event) => updateRowEditField(row.id, 'total_kilo', event.target.value)}
                            className="text-right"
                          />
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex items-center justify-end gap-2">
                            <Button type="button" size="sm" onClick={() => handleSaveRow(row)} disabled={savingRowId === row.id}>
                              Guardar
                            </Button>
                            <Button type="button" variant="destructive" size="sm" onClick={() => handleDeleteRow(row)}>
                              Eliminar
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    );
                  })}
                  {filteredRows.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={11} className="py-8 text-center text-sm text-slate-500">
                        No hay filas para los filtros seleccionados.
                      </TableCell>
                    </TableRow>
                  ) : null}
                </TableBody>
              </Table>
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
