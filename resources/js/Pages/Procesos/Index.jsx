import React, { useState, useEffect, useRef } from 'react';
import { Link, useForm, usePage, router } from '@inertiajs/react';
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
import { FileText, RefreshCw, Send, Upload as UploadIcon, X } from 'lucide-react';
import Chart from 'react-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const formatProcessDate = (dateString) => {
  if (!dateString) {
    return '-';
  }

  const normalized = `${dateString}T00:00:00`;
  const date = new Date(normalized);

  if (Number.isNaN(date.getTime())) {
    return '-';
  }

  return date.toLocaleDateString('es-CL');
};

export default function Index({ procesos, especies, variedades = [], filters, isProducer, totalProcesos, totalKgProcesados, totalExportacion, totalComercial, totalMerma, chartData }) {
  const { props } = usePage();
  const { data, setData, get } = useForm({
    search: filters.search || '',
    especie_id: filters.especie_id || '',
    variedad_id: filters.variedad_id || '',
  });

  const [selectedFiles, setSelectedFiles] = useState([]);
  const [isDragging, setIsDragging] = useState(false);
  const [fileErrors, setFileErrors] = useState([]);
  const [uploading, setUploading] = useState(false);
  const [resendingId, setResendingId] = useState(null);
  const fileInputRef = useRef(null);

  const userRoles = props?.auth?.user?.roles ?? [];
  const isAdmin = userRoles.some((role) => ['Administrador', 'Admin'].includes(role.name));
  const csrfToken = props?.csrf_token ?? (typeof document !== 'undefined'
    ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
    : null);

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleEspecieFilter = (especieId) => {
    setData('especie_id', especieId);
    setData('variedad_id', '');
  };

  const handleVariedadFilter = (variedadId) => {
    setData('variedad_id', variedadId);
  };

  const extractProcesoId = (filename) => {
    const match = filename.match(/^(\d+)/);
    return match ? match[1] : null;
  };

  const handleFilesSelection = (fileList) => {
    if (!isAdmin || uploading) {
      return;
    }
    setFileErrors([]);
    const incoming = Array.from(fileList || []);
    if (!incoming.length) return;
    const errors = [];

    setSelectedFiles((prev) => {
      const existingNames = new Set(prev.map((file) => file.name));
      const merged = [...prev];

      incoming.forEach((file) => {
        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
        if (!isPdf) {
          errors.push(`${file.name} (formato no permitido)`);
          return;
        }
        if (existingNames.has(file.name)) {
          errors.push(`${file.name} (duplicado)`);
          return;
        }
        merged.push(file);
        existingNames.add(file.name);
      });

      return merged;
    });

    if (errors.length) {
      setFileErrors((prev) => [...prev, ...errors]);
    }
  };

  const handleFileInputChange = (event) => {
    handleFilesSelection(event.target.files);
    event.target.value = '';
  };

  const handleDrop = (event) => {
    event.preventDefault();
    event.stopPropagation();
    setIsDragging(false);
    if (!isAdmin || uploading) {
      return;
    }
    handleFilesSelection(event.dataTransfer.files);
  };

  const handleResendReport = async (procesoId) => {
    if (!isAdmin) {
      return;
    }
    if (!csrfToken) {
      alert('No se pudo obtener el token de seguridad. Actualiza la página e inténtalo nuevamente.');
      return;
    }
    if (!confirm('¿Deseas reenviar el informe de este proceso al productor por correo y WhatsApp?')) {
      return;
    }
    setResendingId(procesoId);
    try {
      const response = await fetch(route('procesos.resend-report', procesoId), {
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
        throw new Error(payload?.message || 'No se pudo reenviar el informe.');
      }
      alert(payload?.message || 'Informe reenviado correctamente.');
    } catch (error) {
      alert(error?.message || 'Ocurrió un error al intentar reenviar el informe.');
    } finally {
      setResendingId(null);
    }
  };

  const handleDragOver = (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (!isAdmin || uploading) {
      return;
    }
    if (!isDragging) {
      setIsDragging(true);
    }
  };

  const handleDragLeave = (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (event.currentTarget && event.currentTarget.contains(event.relatedTarget)) {
      return;
    }
    setIsDragging(false);
  };

  const handleOpenFileDialog = () => {
    if (!isAdmin || uploading) {
      return;
    }
    if (fileInputRef.current) {
      fileInputRef.current.click();
    }
  };

  const removeSelectedFile = (index) => {
    setSelectedFiles((prev) => prev.filter((_, idx) => idx !== index));
  };

  const clearFileErrors = () => setFileErrors([]);

  const handleUpload = () => {
    if (!isAdmin) {
      return;
    }
    if (!selectedFiles.length) {
      setFileErrors((prev) => [...prev, 'Selecciona al menos un archivo antes de subir.']);
      return;
    }

    setFileErrors([]);
    const formData = new FormData();
    selectedFiles.forEach((file) => formData.append('files[]', file));

    setUploading(true);
    router.post(route('procesos.informes.upload'), formData, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        setSelectedFiles([]);
      },
      onError: () => {
        setFileErrors((prev) => [...prev, 'Ocurrio un error al subir los archivos.']);
      },
      onFinish: () => setUploading(false),
    });
  };

  useEffect(() => {
    if (props?.flash?.sync_output || props?.flash?.success || props?.flash?.error) {
      return;
    }
    const delayDebounceFn = setTimeout(() => {
      get(route('procesos.index', {
        search: data.search,
        especie_id: data.especie_id,
        variedad_id: data.variedad_id,
      }), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.especie_id, data.variedad_id, props?.flash?.sync_output, props?.flash?.success, props?.flash?.error]);

  const calculatePercentage = (value, total) => {
    if (total === 0) return '0.00%';
    return ((value / total) * 100).toFixed(2) + '%';
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Procesos</CardTitle>
          <SyncButton />
        </CardHeader>
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">{props.flash.success}</div>
          )}
          {props?.flash?.error && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 text-red-800 px-3 py-2 text-sm">{props.flash.error}</div>
          )}
          {props?.flash?.sync_output && (
            <div className="mb-4">
              <details className="rounded border bg-gray-50">
                <summary className="cursor-pointer select-none px-3 py-2 text-sm font-medium text-gray-800">Ver detalle de la sincronización</summary>
                <pre className="max-h-64 overflow-auto whitespace-pre-wrap p-3 text-xs text-gray-800">{props.flash.sync_output}</pre>
              </details>
            </div>
          )}
          {isAdmin && props?.flash?.upload_report && (
            <div className="mb-3 rounded border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
              <p>Archivos procesados: {props.flash.upload_report.processed ?? 0}. Informes actualizados: {props.flash.upload_report.updated ?? 0}.</p>
              {props.flash.upload_report.not_found?.length > 0 && (
                <p className="mt-1"><span className="font-medium">Sin coincidencias</span>: {props.flash.upload_report.not_found.slice(0, 5).join(', ')}{props.flash.upload_report.not_found.length > 5 ? '...' : ''}</p>
              )}
              {props.flash.upload_report.invalid_name?.length > 0 && (
                <p className="mt-1"><span className="font-medium">Nombres no validos</span>: {props.flash.upload_report.invalid_name.slice(0, 5).join(', ')}{props.flash.upload_report.invalid_name.length > 5 ? '...' : ''}</p>
              )}
            </div>
          )}
          {isAdmin && fileErrors.length > 0 && (
            <div className="mb-3 rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
              <div className="flex items-center justify-between">
                <span>Revisa los archivos seleccionados:</span>
                <button type="button" onClick={clearFileErrors} className="text-red-600 hover:text-red-800">Cerrar</button>
              </div>
              <ul className="mt-2 list-disc space-y-1 pl-5">
                {fileErrors.map((error, index) => (
                  <li key={`${error}-${index}`}>{error}</li>
                ))}
              </ul>
            </div>
          )}
          {isAdmin && (
            <div
              className={`mb-6 flex flex-col items-center justify-center rounded-lg border-2 border-dashed px-6 py-8 text-center transition ${isDragging ? 'border-indigo-500 bg-indigo-50' : 'border-gray-300 bg-white'}`}
              onDragEnter={handleDragOver}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
              onDrop={handleDrop}
              role="button"
              tabIndex={0}
              onClick={handleOpenFileDialog}
              onKeyDown={(event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                  event.preventDefault();
                  event.stopPropagation();
                  handleOpenFileDialog();
                }
              }}
              >
              <input
                ref={fileInputRef}
                type="file"
                accept=".pdf"
                multiple
                className="hidden"
                onChange={handleFileInputChange}
              />
              <UploadIcon className="h-8 w-8 text-gray-500" />
              <p className="mt-3 text-sm text-gray-700">Arrastra y suelta los informes en PDF o usa el boton para seleccionar archivos.</p>
              <Button
                type="button"
                variant="outline"
                className="mt-4"
                disabled={uploading}
                onClick={(event) => {
                  event.stopPropagation();
                  handleOpenFileDialog();
                }}
              >
                Seleccionar archivos
              </Button>
              <p className="mt-2 text-xs text-gray-500">Formato esperado: IDPROCESO-*.pdf (ej. 12-3103225.pdf).</p>
            </div>
          )}
          {isAdmin && selectedFiles.length > 0 && (
            <div className="mb-6 rounded border border-gray-200 bg-gray-50 p-4">
              <div className="flex items-center justify-between">
                <h4 className="text-sm font-semibold text-gray-700">Archivos listos para subir ({selectedFiles.length})</h4>
                <Button type="button" variant="ghost" onClick={() => setSelectedFiles([])} disabled={!isAdmin || uploading}>Limpiar</Button>
              </div>
              <ul className="mt-3 space-y-2 text-sm text-gray-700">
                {selectedFiles.map((file, index) => (
                  <li key={`${file.name}-${index}`} className="flex items-center justify-between rounded border border-gray-200 bg-white px-3 py-2">
                    <div>
                      <span className="font-medium">{file.name}</span>
                      {extractProcesoId(file.name) && (
                        <span className="ml-2 text-xs text-gray-500">Proceso: {extractProcesoId(file.name)}</span>
                      )}
                    </div>
                    <button type="button" onClick={() => removeSelectedFile(index)} className="text-gray-500 hover:text-red-600" aria-label="Eliminar archivo">
                      <X className="h-4 w-4" />
                    </button>
                  </li>
                ))}
              </ul>
              <div className="mt-4 flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={handleOpenFileDialog} disabled={!isAdmin || uploading}>Anadir mas</Button>
                <Button type="button" onClick={handleUpload} disabled={!isAdmin || uploading}>{uploading ? 'Subiendo...' : 'Subir archivos'}</Button>
              </div>
            </div>
          )}
          <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <Input
              type="text"
              placeholder="Buscar por especie o variedad..."
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

          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Procesos</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalProcesos.toLocaleString('es-CL')}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Kg Procesados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalKgProcesados.toLocaleString('es-CL')} kg</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Exportación</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalExportacion, totalKgProcesados)}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Comercial</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalComercial, totalKgProcesados)}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Merma</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalMerma, totalKgProcesados)}</div>
              </CardContent>
            </Card>
          </div>

          <div className="mb-4 h-80">
            <Chart
              options={{
                chart: {
                  type: 'bar',
                  stacked: true,
                  toolbar: { show: false },
                },
                plotOptions: {
                  bar: { horizontal: false, dataLabels: { position: 'top' } },
                },
                dataLabels: {
                  enabled: true,
                  formatter: function (val) {
                    return val.toLocaleString('es-CL');
                  },
                  offsetY: -20,
                  style: {
                    fontSize: '12px',
                    colors: ['#304758']
                  }
                },
                xaxis: {
                  categories: chartData.map(data => data.especie),
                },
                yaxis: {
                  title: { text: 'Kilos' },
                  labels: {
                    formatter: function (val) {
                      return val.toLocaleString('es-CL');
                    }
                  }
                },
                fill: { opacity: 1 },
                tooltip: {
                  y: {
                    formatter: function (val) {
                      return val.toLocaleString('es-CL') + " Kilos"
                    }
                  }
                },
                legend: {
                  position: 'top',
                  horizontalAlign: 'left',
                  offsetX: 40
                },
                colors: ['#8884d8', '#82ca9d', '#ffc658', '#ff7300'],
              }}
              series={[
                { name: 'Exportación', data: chartData.map(data => data.exportacion) },
                { name: 'Comercial', data: chartData.map(data => data.comercial) },
                { name: 'Desecho', data: chartData.map(data => data.desecho) },
                { name: 'Merma', data: chartData.map(data => data.merma) },
              ]}
              type="bar"
              height={320}
            />
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Agricola</TableHead>
                <TableHead>N° Proceso</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>

                <TableHead>Fecha</TableHead>
                <TableHead>Kg Procesados</TableHead>
                <TableHead>Exportación</TableHead>
                <TableHead>Comercial</TableHead>
                <TableHead>Desecho</TableHead>
                <TableHead>Merma</TableHead>
                <TableHead>Informe</TableHead>
                <TableHead>Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {procesos.data.map(proceso => (
                <TableRow key={proceso.id}>
                  <TableCell>{proceso.agricola}</TableCell>
                  <TableCell>{proceso.n_proceso}</TableCell>
                  <TableCell>{proceso.especie}</TableCell>
                  <TableCell>{proceso.variedad}</TableCell>

                  <TableCell>{formatProcessDate(proceso.fecha)}</TableCell>
                  <TableCell>{(proceso.kilos_netos ?? 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell>{calculatePercentage(proceso.exp ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.comercial ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.desecho ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.merma ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>
                    {proceso.informe ? (
                      <a href={"storage/" + proceso.informe} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                        <FileText className="h-5 w-5" />
                      </a>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    {isAdmin && proceso.informe ? (
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => handleResendReport(proceso.id)}
                        disabled={resendingId === proceso.id}
                      >
                        <Send className="h-4 w-4 mr-1" />
                        {resendingId === proceso.id ? 'Enviando...' : 'Reenviar'}
                      </Button>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {/* Paginación */}
          <div className="flex items-center justify-between mt-4">
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Mostrando <span className="font-medium">{procesos.from}</span> a <span className="font-medium">{procesos.to}</span> de{' '}
                  <span className="font-medium">{procesos.total}</span> resultados
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {procesos.links.map((link, index) => (
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
    if (!dry && !confirm('¿Ejecutar sincronización de procesos?')) return;
    if (dry && !confirm('¿Ejecutar sincronización de prueba (sin aplicar cambios)?')) return;
    setSyncing(true);
    router.post(route('procesos.sync'), dry ? { dry_run: true } : {}, {
      preserveScroll: true,
      onFinish: () => setSyncing(false),
    });
  };
  return (
    <div className="flex items-center gap-2">
      <Button variant="outline" onClick={() => doSync(false)} disabled={syncing} title="Sincronizar procesos">
        <RefreshCw className={`h-4 w-4 mr-2 ${syncing ? 'animate-spin' : ''}`} /> {syncing ? 'Sincronizando...' : 'Sincronizar'}
      </Button>
      <Button variant="secondary" onClick={() => doSync(true)} disabled={syncing} title="Simular sincronización (dry-run)">Prueba (dry-run)</Button>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Procesos</h2>} />;
