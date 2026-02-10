import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Boxes, Bug, BugOff, Circle } from 'lucide-react';
import {
  Sheet,
  SheetContent,
  SheetHeader,
  SheetTitle,
  SheetDescription,
  SheetFooter,
} from '@/Components/ui/sheet';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

const Trigram = ({ type, x, y, rotate }) => {
  const barW = 12;
  const barH = 2;
  const gap = 2;
  const spacing = 4.5;
  const left = -barW / 2;
  const segmentW = (barW - gap) / 2;
  const patterns = {
    geon: ['solid', 'solid', 'solid'],
    gam: ['solid', 'broken', 'solid'],
    gon: ['broken', 'broken', 'broken'],
    ri: ['broken', 'solid', 'broken'],
  };
  const rows = patterns[type] || patterns.geon;

  return (
    <g transform={`translate(${x} ${y}) rotate(${rotate})`} fill="#111827">
      {rows.map((style, idx) => {
        const yPos = (idx - 1) * spacing;
        if (style === 'solid') {
          return <rect key={idx} x={left} y={yPos} width={barW} height={barH} rx="0.6" />;
        }
        return (
          <g key={idx}>
            <rect x={left} y={yPos} width={segmentW} height={barH} rx="0.6" />
            <rect x={left + segmentW + gap} y={yPos} width={segmentW} height={barH} rx="0.6" />
          </g>
        );
      })}
    </g>
  );
};

const KoreaFlag = ({ className = '' }) => (
  <svg
    className={`h-5 w-8 ${className}`}
    viewBox="0 0 60 40"
    role="img"
    aria-label="Bandera de Corea"
  >
    <rect x="0" y="0" width="60" height="40" rx="3" fill="#ffffff" stroke="#e5e7eb" />
    <g transform="translate(30 20)">
      <path d="M 0 -10 A 10 10 0 0 1 0 10 A 5 5 0 0 0 0 0 A 5 5 0 0 1 0 -10" fill="#0047a0" />
      <path d="M 0 10 A 10 10 0 0 1 0 -10 A 5 5 0 0 0 0 0 A 5 5 0 0 1 0 10" fill="#cd2e3a" />
      <circle cx="0" cy="-5" r="2.8" fill="#cd2e3a" />
      <circle cx="0" cy="5" r="2.8" fill="#0047a0" />
    </g>
    <Trigram type="geon" x={12} y={10} rotate={-32} />
    <Trigram type="gam" x={48} y={10} rotate={32} />
    <Trigram type="gon" x={48} y={30} rotate={-32} />
    <Trigram type="ri" x={12} y={30} rotate={32} />
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

export default function Show({ auth, version, weeks, rows }) {
  const { flash } = usePage().props;
  const orderedWeeks = useMemo(() => {
    const list = [...(weeks || [])];
    list.sort((a, b) => {
      const aDate = a.start_date ? new Date(a.start_date).getTime() : null;
      const bDate = b.start_date ? new Date(b.start_date).getTime() : null;
      if (aDate !== null && bDate !== null) return aDate - bDate;
      if (aDate !== null) return -1;
      if (bDate !== null) return 1;
      return (a.week_number || 0) - (b.week_number || 0);
    });
    return list;
  }, [weeks]);
  const weekNumbers = useMemo(() => orderedWeeks.map((w) => String(w.week_number)), [orderedWeeks]);
  const paginationLinks = Array.isArray(rows?.links)
    ? rows.links
    : Array.isArray(rows?.meta?.links)
      ? rows.meta.links
      : [];
  const [rowEdits, setRowEdits] = useState({});
  const [savingRowId, setSavingRowId] = useState(null);
  const [editingRow, setEditingRow] = useState(null);

  useEffect(() => {
    if (flash?.success) toast.success(flash.success);
    if (flash?.error) toast.error(flash.error);
  }, [flash]);

  useEffect(() => {
    const next = {};
    (rows?.data || []).forEach((row) => {
      const weekValues = row.week_values || {};
      const perWeek = {};
      weekNumbers.forEach((week) => {
        const value = weekValues[week];
        perWeek[week] = value === undefined || value === null ? '' : String(value);
      });
      next[row.id] = perWeek;
    });
    setRowEdits(next);
  }, [rows?.data, weekNumbers.join('|')]);

  const updateCell = (rowId, week, value) => {
    setRowEdits((prev) => ({
      ...prev,
      [rowId]: {
        ...prev[rowId],
        [week]: value,
      },
    }));
  };

  const handleSave = (row) => {
    const edits = rowEdits[row.id] || {};
    const payload = {
      row_id: row.id,
      row: {
        grupo: row.grupo || '',
        tipo_productor: row.tipo_productor || '',
        producer_id: row.producer_id,
        sucursal: row.sucursal || '',
        agronomist_id: row.agronomist_id || null,
        status_id: row.status_id,
        variedad_id: row.variedad_id,
        acopio: !!row.acopio,
        radio_mosca: !!row.radio_mosca,
        corea_greenex: row.corea_greenex === null ? null : !!row.corea_greenex,
        tipo_cereza: row.tipo_cereza || null,
        total_kilo: row.total_kilo ?? null,
      },
      weeks: edits,
    };

    setSavingRowId(row.id);
    router.patch(route('estimations.rows.update', { estimation_version: version.id, estimation_row: row.id }), payload, {
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
    if (!value) return '';
    const [year, month, day] = value.split('-');
    if (!year || !month || !day) return value;
    return `${day}/${month}`;
  };

  const getRowTotal = (row) => {
    const edits = rowEdits[row.id] || {};
    let total = 0;
    weekNumbers.forEach((week) => {
      const value = edits[week];
      const numeric = value === '' || value === null || value === undefined ? 0 : Number(value);
      if (!Number.isNaN(numeric)) total += numeric;
    });
    if (!total) return '-';
    return total.toLocaleString('es-CL', { maximumFractionDigits: 2 });
  };

  const renderCherryType = (value) => {
    if (!value) return <span className="text-xs text-gray-400">-</span>;
    const normalized = String(value).toLowerCase();
    if (normalized.includes('bicolor')) {
      return (
        <span className="inline-flex items-center gap-1 text-xs text-gray-700" title="Bicolor">
          <Circle className="h-3.5 w-3.5 text-red-500" />
          <Circle className="h-3.5 w-3.5 text-yellow-400" />
          Bicolor
        </span>
      );
    }
    return (
      <span className="inline-flex items-center gap-1 text-xs text-gray-700" title="Rojo">
        <Circle className="h-3.5 w-3.5 text-red-500" />
        Rojo
      </span>
    );
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle de Estimación</h2>}>
      <Head title="Detalle Estimación" />
      <Toaster richColors position="top-right" />
      <div className="container mx-auto py-8 space-y-6">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between">
            <CardTitle>Versión #{version.id}</CardTitle>
            <Link href={route('estimations.index')} className="text-indigo-600 hover:underline">Volver</Link>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-3 text-sm">
              <div><strong>Temporada:</strong> {version.season?.code || '-'}</div>
              <div><strong>Tipo:</strong> {version.type}</div>
              <div><strong>Periodo:</strong> {version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</div>
              <div><strong>Estado:</strong> {version.status}</div>
              <div><strong>Origen:</strong> {version.source}</div>
              <div><strong>Usuario:</strong> {version.uploader || '-'}</div>
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Estimaciones por Semana</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="mb-4 text-sm text-gray-600">
              Selecciona una fila para ver y editar sus semanas en un panel lateral. Las semanas están ordenadas por fecha.
            </div>
            <div className="overflow-auto border rounded-xl bg-white shadow-sm">
              <Table className="min-w-[900px] text-sm">
                <TableHeader className="sticky top-0 bg-white z-20 shadow-sm">
                  <TableRow>
                    <TableHead className="min-w-[320px] px-4 py-3">Productor</TableHead>
                    <TableHead className="min-w-[160px] px-3 py-3 text-center">Sucursal</TableHead>
                    <TableHead className="min-w-[200px] px-3 py-3">Variedad</TableHead>
                    <TableHead className="min-w-[140px] px-3 py-3 text-center">Acopio</TableHead>
                    <TableHead className="min-w-[140px] px-3 py-3 text-center">Radio Mosca</TableHead>
                    <TableHead className="min-w-[140px] px-3 py-3 text-center">Corea</TableHead>
                    <TableHead className="min-w-[180px] px-3 py-3 text-center">Tipo Cereza</TableHead>
                    <TableHead className="min-w-[140px] px-3 py-3 text-center">Status</TableHead>
                    <TableHead className="min-w-[160px] px-3 py-3 text-right">Total Estimado</TableHead>
                    <TableHead className="min-w-[140px] px-3 py-3 text-center">Acción</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(rows?.data || []).map((row) => (
                    <TableRow key={row.id} className="hover:bg-gray-50/60 odd:bg-gray-50/40">
                      <TableCell className="px-4 py-4 align-top">
                        <div className="font-semibold text-sm truncate max-w-[300px]" title={row.producer || ''}>
                          {row.producer || '-'}
                        </div>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <span className="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                          {row.sucursal || '-'}
                        </span>
                      </TableCell>
                      <TableCell className="px-3 py-4">
                        <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                          {row.variedad || '-'}
                        </span>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <IconBadge active={!!row.acopio} label={row.acopio ? 'Acopio' : 'Sin acopio'}>
                          <Boxes className={`h-4 w-4 ${row.acopio ? 'text-slate-700' : 'text-gray-400'}`} />
                          {row.acopio ? 'Sí' : 'No'}
                        </IconBadge>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <IconBadge active={!!row.radio_mosca} label={row.radio_mosca ? 'Radio Mosca' : 'Sin Radio Mosca'}>
                          {row.radio_mosca ? (
                            <Bug className="h-4 w-4 text-amber-600" />
                          ) : (
                            <BugOff className="h-4 w-4 text-gray-400" />
                          )}
                          {row.radio_mosca ? 'Sí' : 'No'}
                        </IconBadge>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <IconBadge active={row.corea_greenex !== null} label={row.corea_greenex ? 'Corea Greenex' : 'No Corea'}>
                          <KoreaFlag className={row.corea_greenex ? '' : 'opacity-40'} />
                          {row.corea_greenex ? 'Sí' : 'No'}
                        </IconBadge>
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        {renderCherryType(row.tipo_cereza)}
                      </TableCell>
                      <TableCell className="px-3 py-4 text-center">{row.status || '-'}</TableCell>
                      <TableCell className="px-3 py-4 text-right font-semibold">{getRowTotal(row)}</TableCell>
                      <TableCell className="px-3 py-4 text-center">
                        <Button size="sm" className="w-24" onClick={() => setEditingRow(row)}>Editar</Button>
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

      <Sheet open={!!editingRow} onOpenChange={(open) => !open && setEditingRow(null)}>
        <SheetContent side="right" className="flex h-full w-full flex-col sm:max-w-2xl">
          <SheetHeader>
            <SheetTitle>Editar semanas</SheetTitle>
            <SheetDescription>
              {editingRow ? `${editingRow.producer || '-'} · ${editingRow.variedad || '-'} · ${editingRow.sucursal || '-'}` : ''}
            </SheetDescription>
          </SheetHeader>

          {editingRow && (
            <div className="mt-4 flex-1 overflow-y-auto pr-2">
              <div className="mb-4 flex flex-wrap gap-2">
                <IconBadge active={!!editingRow.acopio} label="Acopio">
                  <Boxes className={`h-4 w-4 ${editingRow.acopio ? 'text-slate-700' : 'text-gray-400'}`} />
                  {editingRow.acopio ? 'Acopio' : 'Sin acopio'}
                </IconBadge>
                <IconBadge active={!!editingRow.radio_mosca} label="Radio Mosca">
                  {editingRow.radio_mosca ? (
                    <Bug className="h-4 w-4 text-amber-600" />
                  ) : (
                    <BugOff className="h-4 w-4 text-gray-400" />
                  )}
                  {editingRow.radio_mosca ? 'Radio Mosca' : 'Sin Radio Mosca'}
                </IconBadge>
                <IconBadge active={editingRow.corea_greenex !== null} label="Corea Greenex">
                  <KoreaFlag className={editingRow.corea_greenex ? '' : 'opacity-40'} />
                  {editingRow.corea_greenex ? 'Corea' : 'No Corea'}
                </IconBadge>
                <span className="inline-flex items-center gap-2 rounded-full bg-slate-50 px-2 py-0.5 text-xs text-slate-700">
                  {renderCherryType(editingRow.tipo_cereza)}
                </span>
              </div>
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                {orderedWeeks.map((week) => (
                  <div key={week.week_number} className="rounded-lg border bg-white p-3 shadow-sm">
                    <div className="text-xs font-semibold text-gray-900">Semana {week.week_number}</div>
                    <div className="text-[11px] text-gray-500">
                      {formatDate(week.start_date)}{week.end_date ? `-${formatDate(week.end_date)}` : ''}
                    </div>
                    <Input
                      type="number"
                      step="0.01"
                      min="0"
                      className="mt-2 h-9 text-right text-sm"
                      value={rowEdits[editingRow.id]?.[String(week.week_number)] ?? ''}
                      onChange={(e) => updateCell(editingRow.id, String(week.week_number), e.target.value)}
                    />
                  </div>
                ))}
              </div>
            </div>
          )}

          <SheetFooter className="mt-4">
            <Button
              onClick={() => editingRow && handleSave(editingRow)}
              disabled={savingRowId === editingRow?.id}
            >
              Guardar cambios
            </Button>
          </SheetFooter>
        </SheetContent>
      </Sheet>
    </AuthenticatedLayout>
  );
}
