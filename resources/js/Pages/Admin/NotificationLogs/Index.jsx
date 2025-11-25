import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination';

const emptyFilters = {
  type: '',
  status: '',
  recipient: '',
  channel: '',
  n_proceso: '',
  proceso_id: '',
  producer_name: '',
  search: '',
  date_from: '',
  date_to: '',
};

const statusVariant = {
  success: 'default',
  failure: 'destructive',
};

export default function NotificationLogsIndex({ logs, filters }) {
  const { auth } = usePage().props;
  const { data, setData, get, processing, reset } = useForm({ ...emptyFilters, ...filters });

  const submit = (e) => {
    e.preventDefault();
    get(route('notification-logs.index'), { preserveScroll: true, replace: true });
  };

  const clearFilters = () => {
    reset();
    get(route('notification-logs.index'), { data: emptyFilters, preserveScroll: true, replace: true });
  };

  const renderPagination = () => {
    if (!logs?.links) return null;

    return (
      <Pagination className="mt-4">
        <PaginationContent>
          {logs.links.map((link, idx) => {
            if (idx === 0) {
              return (
                <PaginationItem key={link.label}>
                  <PaginationPrevious
                    href={link.url || '#'}
                    className={!link.url ? 'pointer-events-none opacity-50' : ''}
                  />
                </PaginationItem>
              );
            }

            if (idx === logs.links.length - 1) {
              return (
                <PaginationItem key={link.label}>
                  <PaginationNext
                    href={link.url || '#'}
                    className={!link.url ? 'pointer-events-none opacity-50' : ''}
                  />
                </PaginationItem>
              );
            }

            return (
              <PaginationItem key={link.label}>
                <PaginationLink
                  href={link.url || '#'}
                  isActive={link.active}
                  className={!link.url ? 'pointer-events-none opacity-50' : ''}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              </PaginationItem>
            );
          })}
        </PaginationContent>
      </Pagination>
    );
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Logs de notificaciones</h2>}
    >
      <Head title="Logs de notificaciones" />

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
                    <Label htmlFor="type">Tipo</Label>
                    <Select
                      value={data.type || 'all'}
                      onValueChange={(value) => setData('type', value === 'all' ? '' : value)}
                    >
                      <SelectTrigger id="type">
                        <SelectValue placeholder="Todos" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="email">Email</SelectItem>
                        <SelectItem value="whatsapp">WhatsApp</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="status">Estado</Label>
                    <Select
                      value={data.status || 'all'}
                      onValueChange={(value) => setData('status', value === 'all' ? '' : value)}
                    >
                      <SelectTrigger id="status">
                        <SelectValue placeholder="Todos" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="all">Todos</SelectItem>
                        <SelectItem value="success">Éxito</SelectItem>
                        <SelectItem value="failure">Falla</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div>
                    <Label htmlFor="recipient">Destinatario</Label>
                    <Input
                      id="recipient"
                      value={data.recipient}
                      onChange={(e) => setData('recipient', e.target.value)}
                      placeholder="correo o teléfono"
                    />
                  </div>
                  <div>
                    <Label htmlFor="channel">Canal (context)</Label>
                    <Input
                      id="channel"
                      value={data.channel}
                      onChange={(e) => setData('channel', e.target.value)}
                      placeholder="process / recepcion"
                    />
                  </div>
                  <div>
                    <Label htmlFor="n_proceso">N° proceso</Label>
                    <Input
                      id="n_proceso"
                      value={data.n_proceso}
                      onChange={(e) => setData('n_proceso', e.target.value)}
                      placeholder="Ej: 431"
                    />
                  </div>
                  <div>
                    <Label htmlFor="proceso_id">Proceso ID</Label>
                    <Input
                      id="proceso_id"
                      value={data.proceso_id}
                      onChange={(e) => setData('proceso_id', e.target.value)}
                      placeholder="Ej: 427"
                    />
                  </div>
                  <div>
                    <Label htmlFor="producer_name">Productor</Label>
                    <Input
                      id="producer_name"
                      value={data.producer_name}
                      onChange={(e) => setData('producer_name', e.target.value)}
                      placeholder="Nombre productor"
                    />
                  </div>
                  <div>
                    <Label htmlFor="search">Texto (mensaje, asunto, destinatario)</Label>
                    <Input
                      id="search"
                      value={data.search}
                      onChange={(e) => setData('search', e.target.value)}
                      placeholder="Buscar texto"
                    />
                  </div>
                  <div>
                    <Label htmlFor="date_from">Fecha desde</Label>
                    <Input
                      id="date_from"
                      type="date"
                      value={data.date_from}
                      onChange={(e) => setData('date_from', e.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="date_to">Fecha hasta</Label>
                    <Input
                      id="date_to"
                      type="date"
                      value={data.date_to}
                      onChange={(e) => setData('date_to', e.target.value)}
                    />
                  </div>
                </div>

                <div className="flex gap-3">
                  <Button type="submit" disabled={processing}>
                    Filtrar
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
              <CardTitle>Resultados</CardTitle>
            </CardHeader>
            <CardContent className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200 text-sm">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">ID</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Tipo</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Estado</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Destinatario</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Asunto / Mensaje</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Contexto</th>
                    <th className="px-3 py-2 text-left font-semibold text-gray-700">Fecha</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-gray-200 bg-white">
                  {logs?.data?.length ? (
                    logs.data.map((log) => (
                      <tr key={log.id}>
                        <td className="px-3 py-2 text-gray-700">{log.id}</td>
                        <td className="px-3 py-2 text-gray-700 capitalize">{log.type}</td>
                        <td className="px-3 py-2">
                          <Badge variant={statusVariant[log.status] || 'secondary'}>{log.status}</Badge>
                        </td>
                        <td className="px-3 py-2 text-gray-700">
                          <div className="font-medium">{log.recipient}</div>
                          {log.subject && <div className="text-xs text-gray-500">Asunto: {log.subject}</div>}
                        </td>
                        <td className="px-3 py-2 text-gray-700">
                          {log.message ? (
                            <div className="line-clamp-2">{log.message}</div>
                          ) : (
                            <span className="text-gray-400">Sin mensaje</span>
                          )}
                        </td>
                        <td className="px-3 py-2 text-gray-700">
                          <div className="text-xs text-gray-600 space-y-1">
                            {log.channel && <div><strong>Canal:</strong> {log.channel}</div>}
                            {log.n_proceso && <div><strong>N° proceso:</strong> {log.n_proceso}</div>}
                            {log.proceso_id && <div><strong>Proceso ID:</strong> {log.proceso_id}</div>}
                            {log.c_productor && <div><strong>CSG:</strong> {log.c_productor}</div>}
                            {log.producer_name && <div><strong>Productor:</strong> {log.producer_name}</div>}
                          </div>
                        </td>
                        <td className="px-3 py-2 text-gray-700 whitespace-nowrap">{log.created_at}</td>
                      </tr>
                    ))
                  ) : (
                    <tr>
                      <td className="px-3 py-4 text-center text-gray-500" colSpan={7}>
                        No hay registros para los filtros seleccionados.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>

              {renderPagination()}
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
