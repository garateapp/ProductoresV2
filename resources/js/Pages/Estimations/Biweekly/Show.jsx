import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

export default function Show({ auth, version, rows }) {
  const { flash } = usePage().props;
  const paginationLinks = Array.isArray(rows?.links)
    ? rows.links
    : Array.isArray(rows?.meta?.links)
      ? rows.meta.links
      : [];
  const [rowEdits, setRowEdits] = useState({});
  const [savingRowId, setSavingRowId] = useState(null);

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  useEffect(() => {
    const next = {};
    (rows?.data || []).forEach((row) => {
      next[row.id] = row.total_kilo ?? '';
    });
    setRowEdits(next);
  }, [rows?.data]);

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

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle Bisemanal</h2>}>
      <Head title="Detalle Bisemanal" />
      <Toaster richColors position="top-right" />
      <div className="container mx-auto py-8 space-y-6">
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
            <div className="overflow-auto border rounded-lg">
              <Table className="min-w-[1400px] text-sm">
                <TableHeader className="sticky top-0 bg-white z-10">
                  <TableRow>
                    <TableHead className="min-w-[240px]">Productor</TableHead>
                    <TableHead className="min-w-[140px] text-center">Sucursal</TableHead>
                    <TableHead className="min-w-[160px]">Variedad</TableHead>
                    <TableHead className="min-w-[140px] text-center">Día</TableHead>
                    <TableHead className="min-w-[120px] text-center">Semana</TableHead>
                    <TableHead className="min-w-[140px] text-center">CSG</TableHead>
                    <TableHead className="min-w-[140px] text-center">Especie</TableHead>
                    <TableHead className="min-w-[120px] text-center">Planta</TableHead>
                    <TableHead className="min-w-[120px] text-center">Tipo</TableHead>
                    <TableHead className="min-w-[120px] text-center">Acopio</TableHead>
                    <TableHead className="min-w-[120px] text-center">México</TableHead>
                    <TableHead className="min-w-[160px] text-right">Total Kilo</TableHead>
                    <TableHead className="min-w-[120px] text-center">Acción</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(rows?.data || []).map((row) => (
                    <TableRow key={row.id} className="hover:bg-gray-50/60 odd:bg-gray-50/40">
                      <TableCell className="py-3">
                        <div className="font-semibold truncate max-w-[220px]" title={row.producer || ''}>
                          {row.producer || '-'}
                        </div>
                        <div className="text-xs text-gray-500 truncate max-w-[220px]" title={row.agronomist || ''}>
                          {row.agronomist || '-'}
                        </div>
                      </TableCell>
                      <TableCell className="text-center">{row.sucursal || '-'}</TableCell>
                      <TableCell>{row.variedad || '-'}</TableCell>
                      <TableCell className="text-center">{row.dia || '-'}</TableCell>
                      <TableCell className="text-center">{row.semana || '-'}</TableCell>
                      <TableCell className="text-center">{row.csg || '-'}</TableCell>
                      <TableCell className="text-center">{row.especie || '-'}</TableCell>
                      <TableCell className="text-center">{row.planta || '-'}</TableCell>
                      <TableCell className="text-center">{row.tipo || '-'}</TableCell>
                      <TableCell className="text-center">{row.acopio ? 'Sí' : 'No'}</TableCell>
                      <TableCell className="text-center">{row.mexico === null ? '-' : (row.mexico ? 'Sí' : 'No')}</TableCell>
                      <TableCell className="text-right">
                        <Input
                          type="number"
                          step="0.01"
                          min="0"
                          className="w-32 text-right text-sm h-9"
                          value={rowEdits[row.id] ?? ''}
                          onChange={(e) => setRowEdits(prev => ({ ...prev, [row.id]: e.target.value }))}
                        />
                      </TableCell>
                      <TableCell className="text-center">
                        <Button size="sm" onClick={() => handleSave(row)} disabled={savingRowId === row.id}>Guardar</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
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
