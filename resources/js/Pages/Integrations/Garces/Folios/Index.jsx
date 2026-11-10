import React, { useMemo, useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import GarcesPagination from '@/Components/Garces/GarcesPagination';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

/**
 * Rutas fijas: no dependen del listado de Ziggy embebido en el HTML inicial,
 * que queda obsoleto cuando la página se cargó antes de agregar una ruta.
 */
const PREVIEW_URL = '/integrations/garces/folios/preview';
const SYNC_URL = '/integrations/garces/folios/sync';

const formatNumber = (value, digits = 2) =>
  Number(value || 0).toLocaleString('es-CL', { maximumFractionDigits: digits });

const formatDate = (value) => {
  if (!value) return '—';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleDateString('es-CL');
};

const formatDateTime = (value) => {
  if (!value) return '';
  const date = new Date(String(value).replace(' ', 'T'));
  return Number.isNaN(date.getTime()) ? String(value) : date.toLocaleString('es-CL');
};

export default function Index({ folios, filters, config = {}, sourceError = null }) {
  const { props } = usePage();
  const flash = props?.flash || {};

  const [form, setForm] = useState({
    q: filters?.q || '',
    desde: filters?.desde || '',
    hasta: filters?.hasta || '',
    estado: filters?.estado || 'pendientes',
  });
  const [selected, setSelected] = useState([]);
  const [sending, setSending] = useState(false);
  const [dryRun, setDryRun] = useState({ open: false, loading: false, error: null, result: null, generatedAt: null });

  const rows = folios?.data || [];
  const selectableRows = useMemo(() => rows.filter((row) => !row.enviado), [rows]);
  const allSelected = selectableRows.length > 0 && selectableRows.every((row) => selected.includes(row.folio));

  const applyFilters = (e) => {
    e?.preventDefault();
    setSelected([]);
    router.get(route('integrations.garces.folios.index'), form, { preserveState: true, replace: true });
  };

  const toggle = (folio) => {
    setSelected((prev) => (prev.includes(folio) ? prev.filter((f) => f !== folio) : [...prev, folio]));
  };

  const toggleAll = () => {
    setSelected(allSelected ? [] : selectableRows.map((row) => row.folio));
  };

  const send = () => {
    if (selected.length === 0) return;

    const preview = selected.slice(0, 10).join('\n');
    const ok = window.confirm(
      `¿Enviar ${selected.length} folio(s) a Garces?\n\n${preview}${selected.length > 10 ? '\n…' : ''}`
    );
    if (!ok) return;

    setSending(true);
    router.post(
      SYNC_URL,
      { folios: selected },
      {
        preserveScroll: true,
        onSuccess: () => setSelected([]),
        onFinish: () => setSending(false),
      }
    );
  };

  const runDryRun = async () => {
    if (selected.length === 0) return;

    setDryRun({ open: true, loading: true, error: null, result: null, generatedAt: null });

    try {
      const response = await window.axios.post(PREVIEW_URL, { folios: selected });
      setDryRun({
        open: true,
        loading: false,
        error: null,
        result: response.data,
        generatedAt: new Date().toLocaleString('es-CL'),
      });
    } catch (e) {
      const data = e.response?.data;
      const errors = data && typeof data === 'object' ? data.errors : null;
      const detail = errors
        ? Object.values(errors).flat().join(' ')
        : (data && typeof data === 'object' ? data.message : null) || e.message || 'Error de red';
      const status = e.response?.status ? `HTTP ${e.response.status}: ` : '';

      setDryRun({
        open: true,
        loading: false,
        error: `No se pudo generar el JSON de prueba. ${status}${detail}`,
        result: null,
        generatedAt: null,
      });
    }
  };

  const copyJson = async (value) => {
    if (!value) return;

    try {
      await navigator.clipboard.writeText(JSON.stringify(value, null, 2));
      window.alert('JSON copiado al portapapeles.');
    } catch (e) {
      window.alert('No se pudo copiar el JSON.');
    }
  };

  const exportExcel = async () => {
    if (selected.length === 0) return;

    try {
      const response = await window.axios.post(
        route('integrations.garces.folios.export-excel'),
        { folios: selected },
        { responseType: 'blob' }
      );

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = response.headers['content-disposition']?.match(/filename="(.+)"/)?.[1] || 'Folios_Garces.xlsx';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (e) {
      const data = e.response?.data;
      const detail = data && typeof data === 'object' ? (data.message || JSON.stringify(data)) : e.message;
      window.alert(`No se pudo exportar Excel: ${detail}`);
    }
  };

  const exportReporteExcel = async () => {
    if (selected.length === 0) return;

    try {
      const response = await window.axios.post(
        route('integrations.garces.folios.export-reporte-excel'),
        { folios: selected },
        { responseType: 'blob' }
      );

      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = response.headers['content-disposition']?.match(/filename="(.+)"/)?.[1] || 'Reporte_Garces.xlsx';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      window.URL.revokeObjectURL(url);
    } catch (e) {
      const data = e.response?.data;
      const detail = data && typeof data === 'object' ? (data.message || JSON.stringify(data)) : e.message;
      window.alert(`No se pudo exportar reporte Excel: ${detail}`);
    }
  };

  const selectedCajas = rows
    .filter((row) => selected.includes(row.folio))
    .reduce((total, row) => total + Number(row.cajas || 0), 0);

  const sendDisabled = sending || selected.length === 0 || !config.enabled || !config.endpoint_configured;

  return (
    <div className="container mx-auto space-y-6 py-10">
      <div>
        <h1 className="text-2xl font-bold">Envío de producción a Garces</h1>
        <p className="text-muted-foreground">
          Selecciona los folios a enviar. El envío es manual: no existe sincronización automática.
        </p>
      </div>

      {!config.enabled && (
        <div className="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
          Servicio deshabilitado (GARCES_PALLET_BOX_ENABLED=false): puedes revisar folios, pero el envío será rechazado.
        </div>
      )}

      {!config.endpoint_configured && (
        <div className="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
          Falta el endpoint de Garces: configura GARCES_PALLET_BOX_BASE_URL.
        </div>
      )}

      {sourceError && (
        <div className="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">
          No se pudo leer la vista de producción: {sourceError}
        </div>
      )}

      {flash.success && (
        <div className="rounded border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-800">{flash.success}</div>
      )}
      {flash.error && (
        <div className="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{flash.error}</div>
      )}

      <Card>
        <CardHeader>
          <CardTitle>Filtros</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={applyFilters} className="grid gap-4 md:grid-cols-5">
            <div className="space-y-1 md:col-span-2">
              <Label>Folio, proceso o productor</Label>
              <Input
                value={form.q}
                onChange={(e) => setForm({ ...form, q: e.target.value })}
                placeholder="Ej: 123456"
              />
            </div>
            <div className="space-y-1">
              <Label>Desde</Label>
              <Input type="date" value={form.desde} onChange={(e) => setForm({ ...form, desde: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label>Hasta</Label>
              <Input type="date" value={form.hasta} onChange={(e) => setForm({ ...form, hasta: e.target.value })} />
            </div>
            <div className="space-y-1">
              <Label>Estado</Label>
              <select
                className="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                value={form.estado}
                onChange={(e) => setForm({ ...form, estado: e.target.value })}
              >
                <option value="pendientes">Pendientes</option>
                <option value="enviados">Enviados</option>
                <option value="">Todos</option>
              </select>
            </div>
            <div className="md:col-span-5">
              <Button type="submit">Buscar</Button>
            </div>
          </form>
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="flex flex-row items-center justify-between gap-4">
          <CardTitle>Folios ({formatNumber(folios?.total || 0, 0)})</CardTitle>
          <div className="flex items-center gap-3">
            <span className="text-sm text-muted-foreground">
              {selected.length} seleccionado(s) · {formatNumber(selectedCajas, 0)} cajas
            </span>
            <Button
              variant="outline"
              onClick={runDryRun}
              disabled={dryRun.loading || selected.length === 0}
            >
              {dryRun.loading ? 'Generando…' : 'Dry run'}
            </Button>
            <Button onClick={send} disabled={sendDisabled}>
              {sending ? 'Enviando…' : `Enviar seleccionados${selected.length ? ` (${selected.length})` : ''}`}
            </Button>
            <Button variant="outline" onClick={exportExcel} disabled={selected.length === 0}>
              Exportar Excel
            </Button>
            <Button variant="outline" onClick={exportReporteExcel} disabled={selected.length === 0}>
              Exportar Reporte Excel
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-10">
                  <input
                    type="checkbox"
                    className="h-4 w-4"
                    checked={allSelected}
                    onChange={toggleAll}
                    disabled={selectableRows.length === 0}
                    title="Seleccionar los folios de esta página"
                  />
                </TableHead>
                <TableHead>Folio</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>N° proceso</TableHead>
                <TableHead>Productor</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Calibre</TableHead>
                <TableHead>Categoría</TableHead>
                <TableHead>Embalaje</TableHead>
                <TableHead>Envase</TableHead>
                <TableHead className="text-right">Cajas</TableHead>
                <TableHead className="text-right">Kilos</TableHead>
                <TableHead>Estado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.length === 0 && (
                <TableRow>
                  <TableCell colSpan={13} className="text-center text-muted-foreground">
                    No hay folios para los filtros seleccionados.
                  </TableCell>
                </TableRow>
              )}
              {rows.map((row) => (
                <TableRow key={row.folio}>
                  <TableCell>
                    <input
                      type="checkbox"
                      className="h-4 w-4"
                      checked={selected.includes(row.folio)}
                      onChange={() => toggle(row.folio)}
                      disabled={row.enviado}
                    />
                  </TableCell>
                  <TableCell className="font-medium">{row.folio}</TableCell>
                  <TableCell>{formatDate(row.fecha_proceso)}</TableCell>
                  <TableCell>{row.numero_proceso || '—'}</TableCell>
                  <TableCell>{row.productor || '—'}</TableCell>
                  <TableCell>{row.variedad || '—'}</TableCell>
                  <TableCell>{row.calibre || '—'}</TableCell>
                  <TableCell>{row.categoria || '—'}</TableCell>
                  <TableCell>{row.embalaje || '—'}</TableCell>
                  <TableCell>{row.envase || '—'}</TableCell>
                  <TableCell className="text-right">{formatNumber(row.cajas, 0)}</TableCell>
                  <TableCell className="text-right">{formatNumber(row.kilos)}</TableCell>
                  <TableCell>
                    {row.enviado ? (
                      <span className="rounded bg-green-100 px-2 py-0.5 text-xs text-green-800" title={formatDateTime(row.envio?.sent_at)}>
                        Enviado{row.envio?.sent_at ? ` · ${formatDate(row.envio.sent_at)}` : ''}
                      </span>
                    ) : row.envio?.status === 'failed' ? (
                      <span className="rounded bg-red-100 px-2 py-0.5 text-xs text-red-800" title={row.envio?.last_error || ''}>
                        Falló ({row.envio?.attempts})
                      </span>
                    ) : (
                      <span className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">Pendiente</span>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <GarcesPagination links={folios?.links} />
        </CardContent>
      </Card>

      <Dialog open={dryRun.open} onOpenChange={(open) => setDryRun((prev) => ({ ...prev, open }))}>
        <DialogContent className="max-h-[92vh] overflow-hidden p-0 sm:max-w-4xl">
          <DialogHeader className="border-b px-6 py-4">
            <DialogTitle>Dry run · JSON a enviar</DialogTitle>
            <DialogDescription>
              Simulación: no se envía nada a Garces. Este es el resultado y el JSON que se enviaría con los folios seleccionados.
            </DialogDescription>
          </DialogHeader>

          <div className="max-h-[calc(92vh-160px)] space-y-4 overflow-y-auto px-6 py-4">
            {dryRun.loading && <p className="text-sm text-muted-foreground">Generando JSON…</p>}

            {dryRun.error && (
              <div className="rounded border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800">{dryRun.error}</div>
            )}

            {dryRun.result && (
              <>
                <div className="grid gap-3 sm:grid-cols-4">
                  <div className="rounded border px-3 py-2 text-sm">
                    <div className="text-xs text-muted-foreground">Estado</div>
                    <div className="font-semibold">{dryRun.result.status}</div>
                  </div>
                  <div className="rounded border px-3 py-2 text-sm">
                    <div className="text-xs text-muted-foreground">Folios / pallets</div>
                    <div className="font-semibold">
                      {formatNumber(dryRun.result.folio_count ?? 0, 0)} /{' '}
                      {formatNumber(dryRun.result.would_send || 0, 0)}
                    </div>
                  </div>
                  <div className="rounded border px-3 py-2 text-sm">
                    <div className="text-xs text-muted-foreground">Cajas registradas</div>
                    <div className="font-semibold">{formatNumber(dryRun.result.records || 0, 0)}</div>
                  </div>
                  <div className="rounded border px-3 py-2 text-sm">
                    <div className="text-xs text-muted-foreground">Total cajas / kilos</div>
                    <div className="font-semibold">
                      {formatNumber(dryRun.result.cajas || 0, 0)} / {formatNumber(dryRun.result.kilos || 0)}
                    </div>
                  </div>
                </div>

                <div className="space-y-1 text-sm">
                  <p className="text-muted-foreground">{dryRun.result.message}</p>
                  <p className="text-xs text-muted-foreground">
                    Endpoint: {dryRun.result.endpoint || 'no configurado'} · Servicio{' '}
                    {dryRun.result.enabled ? 'habilitado' : 'deshabilitado'} · Generado {dryRun.generatedAt}
                  </p>
                  <p className="text-xs text-muted-foreground">Folios: {(dryRun.result.folios || []).join(', ')}</p>
                </div>

                {(dryRun.result.folios_without_boxes || []).length > 0 && (
                  <div className="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    Sin cajas en el origen (el detalle del JSON queda vacío y Garces podría rechazarlo):{' '}
                    {(dryRun.result.folios_without_boxes || []).join(', ')}
                  </div>
                )}

                {(dryRun.result.missing_folios || []).length > 0 && (
                  <div className="rounded border border-slate-300 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                    Sin datos de producción: {(dryRun.result.missing_folios || []).join(', ')}
                  </div>
                )}

                {(dryRun.result.details || []).length > 0 && (
                  <div className="overflow-auto rounded border">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Folio</TableHead>
                          <TableHead>N° proceso</TableHead>
                          <TableHead className="text-right">Pallets</TableHead>
                          <TableHead className="text-right">Cajas en detalle</TableHead>
                          <TableHead className="text-right">Cajas / Kilos</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {(dryRun.result.details || []).map((item) => (
                          <TableRow key={item.folio}>
                            <TableCell className="font-medium">{item.folio}</TableCell>
                            <TableCell>{item.numero_proceso || '—'}</TableCell>
                            <TableCell className="text-right">{formatNumber(item.pallets, 0)}</TableCell>
                            <TableCell className="text-right">{formatNumber(item.boxes, 0)}</TableCell>
                            <TableCell className="text-right">
                              {formatNumber(item.cajas, 0)} / {formatNumber(item.kilos)}
                            </TableCell>
                          </TableRow>
                        ))}
                      </TableBody>
                    </Table>
                  </div>
                )}

                <div className="space-y-2">
                  <div className="flex items-center justify-between gap-3">
                    <Label>
                      JSON a enviar · un solo payload con los folios seleccionados
                    </Label>
                    <Button size="sm" variant="outline" onClick={() => copyJson(dryRun.result.payload)}>
                      Copiar JSON
                    </Button>
                  </div>

                  {!dryRun.result.payload && <p className="text-sm text-red-700">No se generó ningún payload.</p>}

                  {dryRun.result.payload && (
                    <pre className="max-h-96 overflow-auto rounded bg-slate-950 p-3 text-xs text-slate-100">
{JSON.stringify(dryRun.result.payload, null, 2)}
                    </pre>
                  )}
                </div>
              </>
            )}
          </div>

          <DialogFooter className="border-t px-6 py-3">
            <Button variant="ghost" onClick={() => setDryRun((prev) => ({ ...prev, open: false }))}>Cerrar</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout children={page} header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Folios a Garces</h2>} />
);
