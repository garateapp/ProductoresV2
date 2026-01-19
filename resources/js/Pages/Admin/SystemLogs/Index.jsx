import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Textarea } from '@/Components/ui/textarea';

const defaultFilters = {
  file: '',
  lines: '500',
  search: '',
};

const formatBytes = (bytes) => {
  if (!bytes && bytes !== 0) return '-';
  const units = ['B', 'KB', 'MB', 'GB'];
  let size = bytes;
  let idx = 0;

  while (size >= 1024 && idx < units.length - 1) {
    size /= 1024;
    idx += 1;
  }

  return `${size.toFixed(idx === 0 ? 0 : 1)} ${units[idx]}`;
};

export default function SystemLogsIndex({ files, selectedFile, filters, log }) {
  const { auth } = usePage().props;
  const { data, setData, get, processing, reset } = useForm({ ...defaultFilters, ...filters });

  const submit = (event) => {
    event.preventDefault();
    get(route('system-logs.index'), { preserveScroll: true, replace: true });
  };

  const clearFilters = () => {
    reset();
    get(route('system-logs.index'), { data: defaultFilters, preserveScroll: true, replace: true });
  };

  const hasFiles = (files || []).length > 0;
  const fileInfo = selectedFile
    ? `${selectedFile} | ${formatBytes(log?.file_size)} | ${log?.modified_at || '-'}`
    : 'No hay logs disponibles';

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Logs del sistema</h2>}
    >
      <Head title="Logs del sistema" />

      <div className="py-8">
        <div className="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Filtros</CardTitle>
            </CardHeader>
            <CardContent>
              <form className="space-y-4" onSubmit={submit}>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <Label htmlFor="file">Archivo</Label>
                    <Select
                      value={data.file || 'default'}
                      onValueChange={(value) => setData('file', value === 'default' ? '' : value)}
                      disabled={!hasFiles}
                    >
                      <SelectTrigger id="file">
                        <SelectValue placeholder="Seleccione un log" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="default">Ultimo log</SelectItem>
                        {(files || []).map((file) => (
                          <SelectItem key={file.name} value={file.name}>
                            {file.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="lines">Lineas</Label>
                    <Input
                      id="lines"
                      type="number"
                      min="50"
                      max="2000"
                      value={data.lines}
                      onChange={(event) => setData('lines', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="search">Buscar texto</Label>
                    <Input
                      id="search"
                      value={data.search}
                      onChange={(event) => setData('search', event.target.value)}
                      placeholder="Error, Exception, etc."
                    />
                  </div>
                </div>

                <div className="flex gap-3">
                  <Button type="submit" disabled={processing}>
                    Actualizar
                  </Button>
                  <Button type="button" variant="outline" onClick={clearFilters} disabled={processing}>
                    Limpiar
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Contenido</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div className="text-sm text-gray-600">{fileInfo}</div>
              <div className="text-xs text-gray-500">
                Lineas mostradas: {log?.line_count || 0}
                {data.search ? ' (filtrado)' : ''}
              </div>

              <Textarea
                value={log?.content || ''}
                readOnly
                className="h-[520px] font-mono text-xs"
                placeholder={hasFiles ? 'Sin contenido para los filtros seleccionados.' : 'No hay logs.'}
              />
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
