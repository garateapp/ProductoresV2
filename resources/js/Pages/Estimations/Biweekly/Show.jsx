import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Boxes } from 'lucide-react';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

const MexicoFlag = ({ className = '' }) => (
  <svg
    className={`h-5 w-8 ${className}`}
    viewBox="0 0 60 40"
    role="img"
    aria-label="Bandera de Mexico"
  >
    <rect x="0" y="0" width="20" height="40" fill="#006847" />
    <rect x="20" y="0" width="20" height="40" fill="#ffffff" />
    <rect x="40" y="0" width="20" height="40" fill="#ce1126" />
    <circle cx="30" cy="20" r="4" fill="#b8860b" opacity="0.75" />
    <rect x="0" y="0" width="60" height="40" rx="3" fill="none" stroke="#e5e7eb" />
  </svg>
);

const IconBadge = ({ children, active = true, label }) => (
  <span
    className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs ${active ? 'bg-slate-100 text-slate-700' : 'bg-gray-100 text-gray-400'}`}
    title={label}
  >
    {children}
  </span>
);

export default function Show({ auth, version, rows }) {
  const { flash } = usePage().props;
  const tableRows = Array.isArray(rows?.data) ? rows.data : [];
  const paginationLinks = Array.isArray(rows?.links)
    ? rows.links
    : Array.isArray(rows?.meta?.links)
      ? rows.meta.links
      : [];
  const [rowEdits, setRowEdits] = useState({});
  const [savingRowId, setSavingRowId] = useState(null);
  const [producerFilter, setProducerFilter] = useState('');
  const [variedadFilter, setVariedadFilter] = useState('');
  const [acopioFilter, setAcopioFilter] = useState('all');
  const [mexicoFilter, setMexicoFilter] = useState('all');

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  const filteredRows = useMemo(() => {
    const producerNeedle = producerFilter.trim().toLowerCase();
    const variedadNeedle = variedadFilter.trim().toLowerCase();

    return tableRows.filter((row) => {
      if (producerNeedle && !String(row.producer || '').toLowerCase().includes(producerNeedle)) {
        return false;
      }

      if (variedadNeedle && !String(row.variedad || '').toLowerCase().includes(variedadNeedle)) {
        return false;
      }

      if (acopioFilter === 'yes' && !row.acopio) {
        return false;
      }

      if (acopioFilter === 'no' && row.acopio) {
        return false;
      }

      if (mexicoFilter === 'yes' && row.mexico !== true) {
        return false;
      }

      if (mexicoFilter === 'no' && row.mexico !== false) {
        return false;
      }

      return true;
    });
  }, [tableRows, producerFilter, variedadFilter, acopioFilter, mexicoFilter]);

  useEffect(() => {
    const next = {};
    tableRows.forEach((row) => {
      next[row.id] = row.total_kilo ?? '';
    });
    setRowEdits(next);
  }, [tableRows]);

  const handleSave = (row) => {
    const totalKilo = rowEdits[row.id];
    const payload = {
      row_id: row.id,
      row: {
        producer_id: row.producer_id,
        agronomist_id: row.agronomist_id || null,
        variedad_id: row.variedad_id,
        planta: row.planta || '',
        sucursal: row.sucursal || '',
        csg: row.csg || '',
        especie: row.especie || '',
        tipo: row.tipo || '',
        acopio: !!row.acopio,
        mexico: row.mexico === null ? null : !!row.mexico,
        dia: row.dia,
        semana: row.semana,
        total_kilo: totalKilo === '' ? null : totalKilo,
      },
    };

    setSavingRowId(row.id);
    router.patch(route('estimations.biweekly.rows.update', { estimation_biweekly_version: version.id, estimation_biweekly_row: row.id }), payload, {
      preserveScroll: true,
      onSuccess: () => {
        setSavingRowId(null);
        toast.success('Cambios guardados.');
      },
      onError: (errors) => {
        setSavingRowId(null);
        toast.error(firstError(errors));
      },
    });
  };

  const formatDate = (value) => {
    if (!value) return '-';
    const [year, month, day] = String(value).split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}/${year}`;
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle Bisemanal</h2>}>
      <Head title="Detalle Bisemanal" />
      <Toaster richColors position="top-right" />
      <div className="mx-auto w-full max-w-[1400px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Versión #{version.id}</CardTitle>
            <Link href={route('estimations.biweekly.index')} className="text-indigo-600 hover:underline">Volver</Link>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div><strong>Temporada:</strong> {version.season?.code || '-'}</div>
              <div><strong>Periodo:</strong> {version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</div>
              <div><strong>Estado:</strong> {version.status}</div>
              <div><strong>Origen:</strong> {version.source}</div>
              <div><strong>Usuario:</strong> {version.uploader || '-'}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Estimaciones Bisemanales</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 text-sm text-gray-600">
              Filtra por productor y variedad, luego ajusta el total por fila y guarda los cambios.
            </div>
            <div className="mb-4 grid grid-cols-1 gap-3 rounded-xl border bg-slate-50 p-3 md:grid-cols-2 lg:grid-cols-4">
              <div>
                <label className="mb-1 block text-xs font-medium text-slate-600">Productor</label>
                <Input
                  value={producerFilter}
                  onChange={(e) => setProducerFilter(e.target.value)}
                  placeholder="Filtrar productor..."
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-slate-600">Variedad</label>
                <Input
                  value={variedadFilter}
                  onChange={(e) => setVariedadFilter(e.target.value)}
                  placeholder="Filtrar variedad..."
                />
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-slate-600">Acopio</label>
                <select
                  value={acopioFilter}
                  onChange={(e) => setAcopioFilter(e.target.value)}
                  className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="all">Todos</option>
                  <option value="yes">Sí</option>
                  <option value="no">No</option>
                </select>
              </div>
              <div>
                <label className="mb-1 block text-xs font-medium text-slate-600">México</label>
                <select
                  value={mexicoFilter}
                  onChange={(e) => setMexicoFilter(e.target.value)}
                  className="h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                >
                  <option value="all">Todos</option>
                  <option value="yes">Sí</option>
                  <option value="no">No</option>
                </select>
              </div>
            </div>
            <div className="mb-3 text-xs text-slate-500">
              Mostrando {filteredRows.length} de {tableRows.length} filas en esta página.
            </div>
            <div className="overflow-auto border rounded-xl bg-white shadow-sm">
              <Table className="min-w-[1300px] text-sm">
                <TableHeader className="sticky top-0 bg-white z-20 shadow-sm">
                  <TableRow>
                    <TableHead className="min-w-[360px] px-4 py-3">Productor</TableHead>
                    <TableHead className="min-w-[180px] px-3 py-3">Variedad</TableHead>
                    <TableHead className="min-w-[120px] px-3 py-3 text-center">Día</TableHead>
                    <TableHead className="min-w-[100px] px-3 py-3 text-center">Semana</TableHead>
                    <TableHead className="min-w-[120px] px-3 py-3 text-center">CSG</TableHead>
                    <TableHead className="min-w-[120px] px-3 py-3 text-center">Especie</TableHead>
                    <TableHead className="min-w-[120px] px-3 py-3 text-center">Planta</TableHead>
                    <TableHead className="min-w-[120px] px-3 py-3 text-center">Tipo</TableHead>
                    <TableHead className="min-w-[130px] px-3 py-3 text-center">Acopio</TableHead>
                    <TableHead className="min-w-[130px] px-3 py-3 text-center">México</TableHead>
                    <TableHead className="min-w-[170px] px-3 py-3 text-right">Total Kilo</TableHead>
                    <TableHead className="min-w-[130px] px-3 py-3 text-center">Acción</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredRows.map((row) => (
                    <TableRow key={row.id} className="hover:bg-gray-50/60 odd:bg-gray-50/40">
                      <TableCell className="px-4 py-4 align-top">
                        <div className="font-semibold text-sm" title={row.producer || ''}>
                          {row.producer || '-'}
                        </div>
                        <div className="mt-1 text-xs text-gray-500" title={row.agronomist || ''}>
                          {row.agronomist || '-'}
                        </div>
                        <div className="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                          Sucursal: {row.sucursal || '-'}
                        </div>
                      </TableCell>
                      <TableCell className="px-3 py-4">
                        <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                          {row.variedad || '-'}
                        </span>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">{formatDate(row.dia)}</TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.semana || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.csg || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.especie || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.planta || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.tipo || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <IconBadge active={!!row.acopio} label={row.acopio ? 'Acopio' : 'Sin acopio'}>
                          <Boxes className={`h-4 w-4 ${row.acopio ? 'text-slate-700' : 'text-gray-400'}`} />
                          {row.acopio ? 'Sí' : 'No'}
                        </IconBadge>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <IconBadge active={row.mexico !== null} label={row.mexico ? 'México' : 'No México'}>
                          <MexicoFlag className={row.mexico ? '' : 'opacity-40'} />
                          {row.mexico === null ? '-' : row.mexico ? 'Sí' : 'No'}
                        </IconBadge>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-right">
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          className="h-9 w-32 text-right text-sm"
                          value={rowEdits[row.id] ?? ''}
                          onChange={(e) => setRowEdits((prev) => ({ ...prev, [row.id]: e.target.value }))}
                        />
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <Button size="sm" className="w-24" onClick={() => handleSave(row)} disabled={savingRowId === row.id}>
                          Guardar
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {filteredRows.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={12} className="py-6 text-center text-sm text-slate-500">
                        No hay filas que coincidan con los filtros.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
            <div className="mt-4 flex flex-wrap items-center gap-2">
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
