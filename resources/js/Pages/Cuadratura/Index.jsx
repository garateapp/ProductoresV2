import React, { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { ArrowDown, ArrowRight, ArrowUp, Eye } from 'lucide-react';

const statusClassMap = {
  pendiente_cuadratura: 'bg-slate-100 text-slate-800',
  enviado_jefe: 'bg-blue-100 text-blue-800',
  rechazado_jefe: 'bg-red-100 text-red-800',
  aprobado_jefe: 'bg-green-100 text-green-800',
};

const formatDateTime = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('es-CL');
};

const formatNumber = (value, decimals = 0) => {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return '-';
  return numericValue.toLocaleString('es-CL', {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  });
};

const formatPercent = (value) => {
  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) return '-';
  return `${formatNumber(numericValue, 2)}%`;
};

const formatLotes = (item) => {
  if (Array.isArray(item?.lotes) && item.lotes.length > 0) {
    return item.lotes.join(', ');
  }

  return item?.lote_recepcion || '-';
};

const renderExportacionCalidad = (item) => {
  const porcentajesPorLote = Array.isArray(item?.exportacion_calidad_por_lote)
    ? item.exportacion_calidad_por_lote
    : [];

  if (porcentajesPorLote.length === 0) {
    return formatPercent(item?.exportacion_calidad_percent);
  }

  if (porcentajesPorLote.length === 1) {
    return formatPercent(porcentajesPorLote[0]?.porcentaje_exportable);
  }

  return (
    <div className="space-y-1 text-right">
      {porcentajesPorLote.map((entry, index) => (
        <div key={`lote-${entry?.lote || 'na'}-${index}`}>
          {entry?.lote || '-'}: {formatPercent(entry?.porcentaje_exportable)}
        </div>
      ))}
    </div>
  );
};

const renderExportacion = (item) => {
  const exportacion = Number(item?.exportacion_percent);
  const exportacionCalidad = Number(item?.exportacion_calidad_percent);

  const canCompare = Number.isFinite(exportacion) && Number.isFinite(exportacionCalidad);
  const isHigher = canCompare && exportacion > exportacionCalidad;
  const isLower = canCompare && exportacion < exportacionCalidad;

  return (
    <div className="flex items-center justify-end gap-1">
      {isHigher ? <ArrowUp className="h-4 w-4 text-green-600" /> : null}
      {isLower ? <ArrowDown className="h-4 w-4 text-red-600" /> : null}
      <span>{formatPercent(item?.exportacion_percent)}</span>
    </div>
  );
};

export default function Index({ procesos, filters, statusOptions = [] }) {
  const { props } = usePage();
  const [selected, setSelected] = useState([]);
  const [sending, setSending] = useState(false);

  const { data, setData, get } = useForm({
    search: filters?.search || '',
    status: filters?.status || '',
  });

  const processIds = useMemo(() => (procesos?.data || []).map((item) => item.id), [procesos?.data]);
  const canSelectAny = useMemo(() => (procesos?.data || []).some((item) => item.workflow_status !== 'aprobado_jefe'), [procesos?.data]);

  useEffect(() => {
    setSelected((prev) => prev.filter((id) => processIds.includes(id)));
  }, [processIds]);

  useEffect(() => {
    const timeout = setTimeout(() => {
      get(route('cuadratura.index'), {
        preserveState: true,
        replace: true,
        preserveScroll: true,
      });
    }, 300);

    return () => clearTimeout(timeout);
  }, [data.search, data.status]);

  const toggleAll = () => {
    if (selected.length > 0) {
      setSelected([]);
      return;
    }

    const allSelectable = (procesos?.data || [])
      .filter((item) => item.workflow_status !== 'aprobado_jefe')
      .map((item) => item.id);
    setSelected(allSelectable);
  };

  const toggleRow = (id) => {
    setSelected((prev) => (
      prev.includes(id)
        ? prev.filter((item) => item !== id)
        : [...prev, id]
    ));
  };

  const sendForApproval = () => {
    if (!selected.length || sending) return;
    if (!confirm(`¿Enviar ${selected.length} proceso(s) al Jefe de Planta?`)) return;

    setSending(true);
    router.post(route('cuadratura.send-for-approval'), { proceso_ids: selected }, {
      preserveScroll: true,
      onSuccess: () => setSelected([]),
      onFinish: () => setSending(false),
    });
  };

  return (
    <>
      <Head title="Cuadratura" />
      <div className="w-full px-4 py-10 sm:px-6 lg:px-8">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle className="text-2xl font-bold">Cuadratura</CardTitle>
            <div className="flex items-center gap-2">
              <Button
                variant="secondary"
                onClick={toggleAll}
                disabled={!canSelectAny || sending}
              >
                {selected.length > 0 ? 'Quitar selección' : 'Seleccionar pendientes'}
              </Button>
              <Button onClick={sendForApproval} disabled={!selected.length || sending}>
                {sending ? 'Enviando...' : `Enviar a Jefe (${selected.length})`}
              </Button>
            </div>
          </CardHeader>
          <CardContent>
            {props?.flash?.success && (
              <div className="mb-4 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                {props.flash.success}
              </div>
            )}
            {props?.flash?.error && (
              <div className="mb-4 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {props.flash.error}
              </div>
            )}

            <div className="mb-4 grid gap-3 md:grid-cols-2">
              <Input
                type="text"
                placeholder="Buscar por proceso, productor, especie, variedad..."
                value={data.search}
                onChange={(event) => setData('search', event.target.value)}
              />
              <select
                className="rounded-md border border-gray-300 px-3 py-2 text-sm"
                value={data.status}
                onChange={(event) => setData('status', event.target.value)}
              >
                <option value="">Todos los estados</option>
                {statusOptions.map((option) => (
                  <option key={option.value} value={option.value}>{option.label}</option>
                ))}
              </select>
            </div>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-10"></TableHead>
                  <TableHead>N° Proceso</TableHead>
                  <TableHead>Productor</TableHead>
                  <TableHead>Especie</TableHead>
                  <TableHead>Variedad</TableHead>
                  <TableHead>Lote(s)</TableHead>
                  <TableHead className="text-right">Peso Neto</TableHead>
                  <TableHead className="text-right">% Exportación</TableHead>
                  <TableHead className="text-right">% Exp. Calidad</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Último hito</TableHead>
                  {/* <TableHead>Informe</TableHead> */}
                  <TableHead>Acción</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {(procesos?.data || []).map((item) => {
                  const isApproved = item.workflow_status === 'aprobado_jefe';
                  const statusClass = statusClassMap[item.workflow_status] || statusClassMap.pendiente_cuadratura;
                  const lastMilestone = item.aprobado_jefe_at || item.rechazado_jefe_at || item.enviado_jefe_at;

                  return (
                    <TableRow key={item.id}>
                      <TableCell>
                        <input
                          type="checkbox"
                          checked={selected.includes(item.id)}
                          disabled={isApproved}
                          onChange={() => toggleRow(item.id)}
                        />
                      </TableCell>
                      <TableCell>{item.n_proceso}</TableCell>
                      <TableCell>{item.agricola || '-'}</TableCell>
                      <TableCell>{item.especie || '-'}</TableCell>
                      <TableCell>{item.variedad || '-'}</TableCell>
                      <TableCell className="max-w-[100px] whitespace-normal break-words">{formatLotes(item)}</TableCell>
                      <TableCell className="text-right">{formatNumber(item.peso_neto, 2)}</TableCell>
                      <TableCell className="text-right">{renderExportacion(item)}</TableCell>
                      <TableCell className="text-right">{renderExportacionCalidad(item)}</TableCell>
                      <TableCell>
                        <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${statusClass}`}>
                          {item.workflow_status_label}
                        </span>
                        {item.comentario_rechazo ? (
                          <p className="mt-1 max-w-xs text-xs text-red-700">{item.comentario_rechazo}</p>
                        ) : null}
                      </TableCell>
                      <TableCell>{formatDateTime(lastMilestone)}</TableCell>
                      {/* <TableCell>
                        {item.informe_url ? (
                          <a href={item.informe_url} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                            Abrir
                          </a>
                        ) : '-'}
                      </TableCell> */}
                      <TableCell>
                        <div className="flex items-center gap-3">
                          {item.preview_url ? (
                            <Button
                              type="button"
                              variant="outline"
                              size="sm"
                              onClick={() => window.open(item.preview_url, '_blank', 'noopener,noreferrer')}
                              title="Vista previa del informe">
                              <Eye className="h-4 w-4" />

                            </Button>
                          ) : null}
                          <Button asChild variant="secondary" size="sm" title="Revisar proceso">
                            <Link href={item.review_url} className="inline-flex items-center gap-1" alt="Revisar proceso">
                              <ArrowRight className="h-4 w-4" />

                            </Link>
                          </Button>
                        </div>
                      </TableCell>
                    </TableRow>
                  );
                })}
              </TableBody>
            </Table>

            <div className="mt-4 flex items-center justify-between">
              <p className="text-sm text-gray-700">
                Mostrando <span className="font-medium">{procesos.from || 0}</span> a <span className="font-medium">{procesos.to || 0}</span> de <span className="font-medium">{procesos.total || 0}</span> resultados
              </p>
              <div className="flex items-center gap-1">
                {(procesos.links || []).map((link, index) => (
                  <Link
                    key={`${link.url}-${index}`}
                    href={link.url || '#'}
                    preserveState
                    preserveScroll
                    className={`rounded border px-3 py-1 text-sm ${link.active ? 'border-indigo-600 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-600'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Cuadratura</h2>}
  />
);
