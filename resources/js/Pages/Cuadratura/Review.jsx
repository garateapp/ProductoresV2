import React from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Textarea } from '@/Components/ui/textarea';

const statusClassMap = {
  pendiente_cuadratura: 'bg-slate-100 text-slate-800',
  enviado_jefe: 'bg-blue-100 text-blue-800',
  rechazado_jefe: 'bg-red-100 text-red-800',
  aprobado_jefe: 'bg-green-100 text-green-800',
};

const formatDate = (dateString) => {
  if (!dateString) return '-';
  const date = new Date(dateString);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('es-CL');
};

function KeyValue({ label, value }) {
  return (
    <div className="rounded border bg-white p-3">
      <p className="text-xs uppercase tracking-wide text-gray-500">{label}</p>
      <p className="text-sm font-medium text-gray-800">{value ?? '-'}</p>
    </div>
  );
}

export default function Review({ proceso, workflow, cabecera, ingresos = [], salidas = [], sqlError = null, eventos = [], canSendToChief, canResolveAsChief }) {
  const { props } = usePage();
  const rejectForm = useForm({
    comentario: '',
  });

  const sendSingle = () => {
    if (!canSendToChief) return;
    if (!confirm('¿Enviar este proceso a aprobación de Jefe de Planta?')) return;

    router.post(route('cuadratura.send-for-approval'), {
      proceso_ids: [proceso.id],
    }, {
      preserveScroll: true,
    });
  };

  const approve = () => {
    if (!canResolveAsChief) return;
    if (!confirm('¿Aprobar este proceso?')) return;

    router.post(route('cuadratura.approve', proceso.id), {}, {
      preserveScroll: true,
    });
  };

  const reject = (event) => {
    event.preventDefault();
    if (!canResolveAsChief) return;

    rejectForm.post(route('cuadratura.reject', proceso.id), {
      preserveScroll: true,
      onSuccess: () => rejectForm.setData('comentario', ''),
    });
  };

  const isSent = workflow.estado === 'enviado_jefe';
  const isRejected = workflow.estado === 'rechazado_jefe';
  const isApproved = workflow.estado === 'aprobado_jefe';

  return (
    <>
      <Head title={`Cuadratura Proceso ${proceso.n_proceso}`} />
      <div className="container mx-auto py-10">
        <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
          <h1 className="text-2xl font-bold">Proceso {proceso.n_proceso}</h1>
          <div className="flex items-center gap-2">
            <span className={`inline-flex rounded-full px-2 py-1 text-xs font-medium ${statusClassMap[workflow.estado] || statusClassMap.pendiente_cuadratura}`}>
              {workflow.estado_label}
            </span>
            <Link href={route('cuadratura.index')} className="text-sm text-indigo-700 hover:text-indigo-900">
              Volver a cuadratura
            </Link>
          </div>
        </div>

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
        {sqlError && (
          <div className="mb-4 rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
            {sqlError}
          </div>
        )}

        <Card className="mb-6">
          <CardHeader className="flex flex-row items-center justify-between space-y-0">
            <CardTitle>Acciones de Workflow</CardTitle>
            <div className="flex items-center gap-2">
              {canSendToChief && (
                <Button variant="secondary" onClick={sendSingle}>
                  {isRejected ? 'Reenviar a Jefe de Planta' : 'Enviar a Jefe de Planta'}
                </Button>
              )}
              {canResolveAsChief && (
                <Button onClick={approve} disabled={isApproved}>
                  {isApproved ? 'Aprobado' : 'Aprobar'}
                </Button>
              )}
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 md:grid-cols-3">
              <KeyValue label="Ciclo" value={workflow.ciclo} />
              <KeyValue label="Enviado a jefe" value={formatDate(workflow.enviado_jefe_at)} />
              <KeyValue label="Aprobado en" value={formatDate(workflow.aprobado_jefe_at)} />
            </div>
            {workflow.comentario_rechazo ? (
              <div className="mt-4 rounded border border-red-200 bg-red-50 p-3">
                <p className="text-xs font-semibold uppercase text-red-700">Motivo de rechazo</p>
                <p className="mt-1 text-sm text-red-800">{workflow.comentario_rechazo}</p>
              </div>
            ) : null}

            {canResolveAsChief && (isSent || isRejected) && !isApproved && (
              <form className="mt-4 space-y-3" onSubmit={reject}>
                <label className="block text-sm font-medium text-gray-700" htmlFor="comentario-rechazo">
                  Comentario de rechazo (obligatorio para rechazar)
                </label>
                <Textarea
                  id="comentario-rechazo"
                  value={rejectForm.data.comentario}
                  onChange={(event) => rejectForm.setData('comentario', event.target.value)}
                  rows={4}
                  placeholder="Indica el motivo del rechazo..."
                />
                {rejectForm.errors.comentario ? (
                  <p className="text-sm text-red-600">{rejectForm.errors.comentario}</p>
                ) : null}
                <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                  {rejectForm.processing ? 'Rechazando...' : 'Rechazar'}
                </Button>
              </form>
            )}
          </CardContent>
        </Card>

        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Resumen del Proceso</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid gap-3 md:grid-cols-3">
              <KeyValue label="Productor" value={proceso.agricola} />
              <KeyValue label="Especie" value={proceso.especie} />
              <KeyValue label="Variedad" value={proceso.variedad} />
              <KeyValue label="Fecha" value={proceso.fecha} />
              <KeyValue label="Lote recepción" value={proceso.lote_recepcion} />
              <KeyValue label="ID Empresa" value={proceso.id_empresa} />
            </div>
            <div className="mt-4">
              {proceso.informe_url ? (
                <a href={proceso.informe_url} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-700 hover:text-blue-900">
                  Abrir informe del proceso
                </a>
              ) : (
                <p className="text-sm text-gray-600">El proceso no tiene informe cargado.</p>
              )}
            </div>
          </CardContent>
        </Card>

        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Cabecera (SQLSRV)</CardTitle>
          </CardHeader>
          <CardContent>
            {cabecera ? (
              <div className="grid gap-3 md:grid-cols-4">
                <KeyValue label="Productor" value={cabecera.n_productor} />
                <KeyValue label="Especie" value={cabecera.n_especie} />
                <KeyValue label="Variedad" value={cabecera.n_variedad} />
                <KeyValue label="Línea proceso" value={cabecera.n_linea_proceso} />
                <KeyValue label="Centro costo" value={cabecera.n_centrocosto} />
                <KeyValue label="N° producción" value={cabecera.numero_g_produccion} />
                <KeyValue label="Fecha producción" value={cabecera.fecha_g_produccion} />
                <KeyValue label="Turno" value={cabecera.n_turno} />
                <KeyValue label="Tipo proceso" value={cabecera.n_tipo_proceso} />
                <KeyValue label="Categoría" value={cabecera.t_categoria} />
                <KeyValue label="Embalaje" value={cabecera.c_embalaje} />
                <KeyValue label="Calibre / Etiqueta" value={`${cabecera.n_calibre || '-'} / ${cabecera.n_etiqueta || '-'}`} />
              </div>
            ) : (
              <p className="text-sm text-gray-600">No se encontró cabecera para este proceso en SQLSRV.</p>
            )}
          </CardContent>
        </Card>

        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Ingresos a Proceso</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Productor</TableHead>
                  <TableHead>N° Guía/Lote</TableHead>
                  <TableHead>Especie</TableHead>
                  <TableHead>Variedad</TableHead>
                  <TableHead>Embalaje</TableHead>
                  <TableHead>Categoría</TableHead>
                  <TableHead>Cantidad</TableHead>
                  <TableHead>Peso</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {ingresos.length ? ingresos.map((row, index) => (
                  <TableRow key={`${row.guia_lote || 'ing'}-${index}`}>
                    <TableCell>{row.n_productor || '-'}</TableCell>
                    <TableCell>{row.guia_lote || '-'}</TableCell>
                    <TableCell>{row.n_especie || '-'}</TableCell>
                    <TableCell>{row.n_variedad || '-'}</TableCell>
                    <TableCell>{row.n_embalaje || '-'}</TableCell>
                    <TableCell>{row.t_categoria || '-'}</TableCell>
                    <TableCell>{Number(row.cantidad || 0).toLocaleString('es-CL')}</TableCell>
                    <TableCell>{Number(row.peso || 0).toLocaleString('es-CL')}</TableCell>
                  </TableRow>
                )) : (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center text-sm text-gray-500">Sin registros de ingresos.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card className="mb-6">
          <CardHeader>
            <CardTitle>Salidas de Proceso</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Productor</TableHead>
                  <TableHead>Especie</TableHead>
                  <TableHead>Variedad</TableHead>
                  <TableHead>Embalaje</TableHead>
                  <TableHead>Categoría</TableHead>
                  <TableHead>Calibre</TableHead>
                  <TableHead>Cantidad</TableHead>
                  <TableHead>Peso Neto</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {salidas.length ? salidas.map((row, index) => (
                  <TableRow key={`${row.c_embalaje || 'sal'}-${index}`}>
                    <TableCell>{row.n_productor || '-'}</TableCell>
                    <TableCell>{row.n_especie || '-'}</TableCell>
                    <TableCell>{row.n_variedad || '-'}</TableCell>
                    <TableCell>{row.n_embalaje || row.c_embalaje || '-'}</TableCell>
                    <TableCell>{row.n_categoria || '-'}</TableCell>
                    <TableCell>{row.n_calibre || '-'}</TableCell>
                    <TableCell>{Number(row.cantidad || 0).toLocaleString('es-CL')}</TableCell>
                    <TableCell>{Number(row.peso_neto || 0).toLocaleString('es-CL')}</TableCell>
                  </TableRow>
                )) : (
                  <TableRow>
                    <TableCell colSpan={8} className="text-center text-sm text-gray-500">Sin registros de salidas.</TableCell>
                  </TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Historial de workflow</CardTitle>
          </CardHeader>
          <CardContent>
            {eventos.length ? (
              <ul className="space-y-2">
                {eventos.map((event) => (
                  <li key={event.id} className="rounded border px-3 py-2">
                    <p className="text-sm font-medium text-gray-800">
                      {event.accion} · {event.created_at}
                    </p>
                    <p className="text-xs text-gray-600">
                      {event.actor_nombre || '-'} ({event.actor_email || '-'})
                    </p>
                    {event.detalle ? <p className="mt-1 text-sm text-gray-700">{event.detalle}</p> : null}
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-sm text-gray-600">Sin historial registrado aún.</p>
            )}
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Review.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Revisión de Cuadratura</h2>}
  />
);
