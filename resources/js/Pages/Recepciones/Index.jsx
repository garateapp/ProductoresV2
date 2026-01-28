import React, { useState, useEffect } from 'react';
import { Link, useForm, router } from '@inertiajs/react';
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
import { Input } from '@/Components/ui/input';
import { FileText, RefreshCw, Mail, MessageCircle, Send } from 'lucide-react'; // Import FileText icon
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { usePage } from '@inertiajs/react';

export default function Index({ recepciones, especies, variedades = [], exportadoras = [], filters, isProducer, totalRecepciones, totalKilos = 0 }) {
  const { props } = usePage();
  const { data, setData, get } = useForm({
    search: filters.search || '',
    especie_id: filters.especie_id || '',
    variedad_id: filters.variedad_id || '',
    exportadora: filters.exportadora || '',
  });
  const userRoles = props?.auth?.user?.roles ?? [];
  const isAdmin = userRoles.some((role) => ['Administrador', 'Admin', 'Calidad'].includes(role.name));
  const canSeeNotifications = userRoles.some((role) => ['Administrador', 'Admin', 'Calidad'].includes(role.name));
  const canFilterExportadora = userRoles.some((role) => ['Administrador', 'Admin'].includes(role.name));
  const canManage = isAdmin && !isProducer;
  const isCarlos = (props?.auth?.user?.email || '').toLowerCase() === 'carlos.alvarez@greenex.cl';
  const [sendingWhatsappId, setSendingWhatsappId] = useState(null);
  const csrfToken = props?.csrf_token ?? (typeof document !== 'undefined'
    ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    : null);

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleEspecieFilter = (especieId) => {
    setData('especie_id', especieId);
    setData('variedad_id', ''); // Reset variedad filter when specie changes
  };

  const handleVariedadFilter = (variedadId) => {
    setData('variedad_id', variedadId);
  };

  const handleExportadoraFilter = (event) => {
    setData('exportadora', event.target.value);
  };

  useEffect(() => {
    if (props?.flash?.sync_output || props?.flash?.success || props?.flash?.error) {
      return;
    }
    const delayDebounceFn = setTimeout(() => {
      const searchTerm = (data.search || '').trim();
      get(
        route('recepciones.index', {
          search: searchTerm,
          especie_id: data.especie_id,
          variedad_id: data.variedad_id,
          exportadora: data.exportadora,
        }),
        { preserveState: true, replace: true }
      );
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.especie_id, data.variedad_id, data.exportadora, props?.flash?.sync_output, props?.flash?.success, props?.flash?.error]);

  const handleSendWhatsappTest = async (recepcionId) => {
    if (!isCarlos) {
      return;
    }
    if (!csrfToken) {
      alert('No se pudo obtener el token de seguridad. Actualiza la pagina e intentalo nuevamente.');
      return;
    }
    setSendingWhatsappId(recepcionId);
    try {
      const response = await fetch(route('recepciones.send-whatsapp-test', recepcionId), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          Accept: 'application/json',
        },
        body: JSON.stringify({}),
      });
      const payload = await response.json().catch(() => ({}));
      if (!response.ok) {
        throw new Error(payload?.message || 'No se pudo enviar el WhatsApp.');
      }
      alert(payload?.message || 'WhatsApp enviado.');
    } catch (error) {
      alert(error?.message || 'Ocurrio un error al enviar el WhatsApp.');
    } finally {
      setSendingWhatsappId(null);
    }
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Recepciones</CardTitle>
          {canManage && <SyncButton />}
        </CardHeader>
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
            </div>
          )}
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">
              {props.flash.error}
            </div>
          )}
          {props?.flash?.sync_output && (
            <div className="mb-4">
              <details className="rounded border bg-gray-50">
                <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-800">
                  Ver detalle de la sincronización
                </summary>
                <pre className="max-h-64 overflow-auto whitespace-pre-wrap p-3 text-xs text-gray-800">
{props.flash.sync_output}
                </pre>
              </details>
            </div>
          )}
          <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <Input
              type="text"
              placeholder="Buscar por lote, agrícola, especie, variedad o Guía de Recepción..."
              value={data.search}
              onChange={handleSearchChange}
              className="max-w-sm"
            />
            <div className="flex flex-wrap gap-2">
              <Button
                variant={data.especie_id === '' ? 'default' : 'outline'}
                onClick={() => handleEspecieFilter('')}
              >
                Todas las Especies
              </Button>
              {especies.map((especie) => (
                <Button
                  key={especie.id}
                  variant={data.especie_id === especie.id ? 'default' : 'outline'}
                  onClick={() => handleEspecieFilter(especie.id)}
                >
                  {especie.name}
                </Button>
              ))}
            </div>
          </div>

          {data.especie_id && variedades.length > 0 && (
            <div className="mb-4 flex flex-wrap gap-2">
              <Button
                variant={data.variedad_id === '' ? 'default' : 'outline'}
                onClick={() => handleVariedadFilter('')}
              >
                Todas las Variedades
              </Button>
              {variedades.map((variedad) => (
                <Button
                  key={variedad.id}
                  variant={data.variedad_id === variedad.id ? 'default' : 'outline'}
                  onClick={() => handleVariedadFilter(variedad.id)}
                >
                  {variedad.name}
                </Button>
              ))}
            </div>
          )}

          {canFilterExportadora && exportadoras.length > 0 && (
            <div className="mb-4">
              <label className="block text-sm text-gray-600 mb-1">Exportadora</label>
              <select
                className="border rounded px-2 py-2 text-sm max-w-xs"
                value={data.exportadora}
                onChange={handleExportadoraFilter}
              >
                <option value="">Todas</option>
                {exportadoras.map((exportadora) => (
                  <option key={exportadora} value={exportadora}>{exportadora}</option>
                ))}
              </select>
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Recepciones</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalRecepciones.toLocaleString('es-CL')}</div>
              </CardContent>
            </Card>
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Kilos Recepcionados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalKilos.toLocaleString('es-CL')} kg</div>
              </CardContent>
            </Card>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Lote</TableHead>
                <TableHead>Agrícola</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Guía</TableHead>
                <TableHead>Cantidad</TableHead>
                <TableHead>Kilos</TableHead>
                <TableHead>Nota</TableHead>
                <TableHead>Informe</TableHead>
                {canSeeNotifications && <TableHead>Notificaciones</TableHead>}
                {isCarlos && <TableHead>Acciones</TableHead>}
              </TableRow>
            </TableHeader>
            <TableBody>
              {recepciones.data.map(recepcion => {
                const emailSent = recepcion.notifications?.email_sent;
                const whatsappSent = recepcion.notifications?.whatsapp_sent;

                return (
                  <TableRow key={recepcion.id}>
                  <TableCell>{recepcion.numero_g_recepcion}</TableCell>
                  <TableCell>{recepcion.n_productor_rotulado}</TableCell>
                  <TableCell>{recepcion.n_especie}</TableCell>
                  <TableCell>{recepcion.n_variedad}</TableCell>
                  <TableCell>{new Date(recepcion.fecha_g_recepcion).toLocaleDateString('es-CL')}</TableCell>
                  <TableCell>{recepcion.numero_documento_recepcion}</TableCell>
                  <TableCell>{recepcion.cantidad.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.peso_neto.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.nota_calidad === 0 ? 'S/N' : recepcion.nota_calidad}</TableCell>
                  <TableCell>
                    {recepcion.informe ? (
                      <a href={recepcion.informe} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                        <FileText className="h-5 w-5" />
                      </a>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  {canSeeNotifications && (
                    <TableCell>
                      <div className="flex items-center gap-2">
                        <Mail
                          className={`h-4 w-4 ${emailSent ? 'text-green-600' : 'text-red-500'}`}
                          title={emailSent ? 'Informe enviado por email' : 'Email no enviado'}
                        />
                        <MessageCircle
                          className={`h-4 w-4 ${whatsappSent ? 'text-green-600' : 'text-red-500'}`}
                          title={whatsappSent ? 'Informe enviado por WhatsApp' : 'WhatsApp no enviado'}
                        />
                      </div>
                    </TableCell>
                  )}
                  {isCarlos && (
                    <TableCell>
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleSendWhatsappTest(recepcion.id)}
                        disabled={sendingWhatsappId === recepcion.id}
                        title="Enviar WhatsApp a +56966291494"
                      >
                        <Send className="h-4 w-4 mr-1" />
                        {sendingWhatsappId === recepcion.id ? 'Enviando...' : 'WhatsApp'}
                      </Button>
                    </TableCell>
                  )}
                </TableRow>
              )})}
            </TableBody>
          </Table>
          {/* Paginación */}
          <div className="flex items-center justify-between mt-4">
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Mostrando <span className="font-medium">{recepciones.from}</span> a <span className="font-medium">{recepciones.to}</span> de{' '}
                  <span className="font-medium">{recepciones.total}</span> resultados
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {recepciones.links.map((link, index) => (
                    <Link
                      key={`${link.url}-${index}`}
                      href={link.url || '#'}
                      disabled={!link.url}
                      preserveState={true}
                      preserveScroll={true}
                      className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                        ? 'z-10 bg-indigo-500 border-indigo-500 text-indigo-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                      } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ))}
                </nav>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function SyncButton() {
  const [syncing, setSyncing] = useState(false);
  const doSync = (dry = false) => {
    if (!dry && !confirm('¿Ejecutar sincronización de recepciones?')) return;
    if (dry && !confirm('¿Ejecutar sincronización de prueba (sin aplicar cambios)?')) return;
    setSyncing(true);
    router.post(route('recepciones.sync'), dry ? { dry_run: true } : {}, {
      preserveScroll: true,
      onFinish: () => setSyncing(false),
    });
  };
  return (
    <div className="flex items-center gap-2">
      <Button variant="outline" onClick={() => doSync(false)} disabled={syncing} title="Sincronizar recepciones">
        <RefreshCw className={`h-4 w-4 mr-2 ${syncing ? 'animate-spin' : ''}`} /> {syncing ? 'Sincronizando...' : 'Sincronizar'}
      </Button>
      <Button
        variant="secondary"
        onClick={() => doSync(true)}
        title="Simular sincronización (dry-run)"
      >
        Prueba (dry-run)
      </Button>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Control de Calidad</h2>} />;
