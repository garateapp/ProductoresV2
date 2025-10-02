import React, { useEffect, useMemo, useState } from 'react';
import { Link, useForm, router, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion';
import { ChevronDown, ChevronUp } from 'lucide-react';

export default function Index({ producers, filters }) {
  const { props } = usePage();
  const { data, setData, get } = useForm({
    search: filters.search || '',
    sort_by: filters.sort_by || 'name',
    sort_order: filters.sort_order || 'asc',
    show_inactive: !!filters.show_inactive,
  });

  const { delete: destroy } = useForm();
  const [ready, setReady] = useState(false);

  useEffect(() => {
    setReady(true);
  }, []);

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleSortFieldChange = (e) => {
    setData('sort_by', e.target.value);
  };

  const toggleSortOrder = () => {
    setData('sort_order', data.sort_order === 'asc' ? 'desc' : 'asc');
  };

  useEffect(() => {
    if (!ready || props?.flash?.sync_output || props?.flash?.success || props?.flash?.error) {
      return;
    }
    const delayDebounceFn = setTimeout(() => {
      get(route('producers.index'), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [ready, data.search, data.sort_by, data.sort_order, data.show_inactive, props?.flash?.sync_output, props?.flash?.success, props?.flash?.error]);

  function handleDelete(e, producer) {
    e.preventDefault();
    if (confirm('¿Estás seguro de eliminar este productor?')) {
      destroy(route('producers.destroy', producer.id));
    }
  }

  const groupedProducers = useMemo(() => {
    const map = new Map();

    (producers?.data || []).forEach((record) => {
      const key = record.rut || `producer-${record.id}`;
      if (!map.has(key)) {
        map.set(key, {
          key,
          rut: record.rut,
          name: record.name,
          email: record.email,
          records: [],
          csgs: new Set(),
          activeCount: 0,
          inactiveCount: 0,
        });
      }
      const entry = map.get(key);
      entry.records.push(record);
      if (record.csg) {
        entry.csgs.add(record.csg);
      }
      if (record.is_active) {
        entry.activeCount += 1;
      } else {
        entry.inactiveCount += 1;
      }
    });

    return Array.from(map.values()).map((group) => ({
      ...group,
      csgs: Array.from(group.csgs).sort((a, b) => String(a).localeCompare(String(b))),
    }));
  }, [producers?.data]);

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-col gap-4 pb-4 md:flex-row md:items-center md:justify-between md:space-y-0">
          <CardTitle className="text-2xl font-bold">Productores</CardTitle>
          <div className="flex flex-wrap items-center gap-2">
            <Button
              variant="secondary"
              onClick={() => {
                if (!confirm('¿Ejecutar sincronización de estados de productores?')) return;
                router.post(route('producers.sync-active'), {}, { preserveScroll: true });
              }}
              title="Sincroniza estados (activos/inactivos) desde SQL Server"
            >
              Sincronizar estados
            </Button>
            <Button
              variant="outline"
              onClick={() => {
                if (!confirm('¿Ejecutar sincronización de prueba (sin aplicar cambios)?')) return;
                router.post(route('producers.sync-active'), { dry_run: true }, { preserveScroll: true });
              }}
              title="Ejecuta una simulación de la sincronización (dry-run)"
            >
              Prueba (dry-run)
            </Button>
            <Link href={route('producers.create')}>
              <Button>Crear Productor</Button>
            </Link>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          {props?.flash?.success && (
            <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
              {props.flash.success}
            </div>
          )}
          {props?.flash?.sync_output && (
            <div>
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

          <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
              <Input
                type="text"
                placeholder="Buscar productores..."
                value={data.search}
                onChange={handleSearchChange}
                className="max-w-xs"
              />
              <div className="flex items-center gap-2 text-sm">
                <Switch
                  id="show-inactive-switch"
                  checked={!!data.show_inactive}
                  onCheckedChange={(val) => setData('show_inactive', !!val)}
                />
                <label htmlFor="show-inactive-switch" className="text-gray-600">Mostrar inactivos</label>
              </div>
            </div>
            <div className="flex flex-wrap items-center gap-2 text-sm text-gray-600">
              <span>Ordenar por:</span>
              <select
                value={data.sort_by}
                onChange={handleSortFieldChange}
                className="rounded border px-2 py-1 text-sm"
              >
                <option value="name">Nombre</option>
                <option value="email">Email</option>
                <option value="rut">RUT</option>
                <option value="idprod">Código Productor</option>
                <option value="csg">CSG</option>
                <option value="predio">Predio</option>
                <option value="comuna">Comuna</option>
                <option value="provincia">Provincia</option>
                <option value="direccion">Dirección</option>
              </select>
              <Button
                variant="outline"
                size="icon"
                onClick={toggleSortOrder}
                title={`Cambiar orden (${data.sort_order === 'asc' ? 'Ascendente' : 'Descendente'})`}
              >
                {data.sort_order === 'asc' ? (
                  <ChevronUp className="h-4 w-4" />
                ) : (
                  <ChevronDown className="h-4 w-4" />
                )}
              </Button>
            </div>
          </div>

          {groupedProducers.length ? (
            <Accordion type="multiple" className="space-y-4">
              {groupedProducers.map((group) => (
                <AccordionItem key={group.key} value={group.key} className="border rounded-lg">
                  <AccordionTrigger className="flex flex-col items-start gap-2 px-4 py-3 text-left">
                    <div className="flex w-full flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="font-semibold text-gray-800">{group.name || 'Sin nombre'}</p>
                        <p className="text-xs text-gray-500">
                          RUT: {group.rut || 'N/A'}
                          {group.email ? ` · ${group.email}` : ''}
                        </p>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                        <Badge variant="outline">Registros: {group.records.length}</Badge>
                        {group.inactiveCount > 0 && (
                          <Badge variant="destructive">Inactivos: {group.inactiveCount}</Badge>
                        )}
                      </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      {group.csgs.length ? (
                        group.csgs.map((csg) => (
                          <Badge key={csg} variant="secondary" className="text-xs">
                            CSG {csg}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-xs text-gray-500">Sin CSG registrados</span>
                      )}
                    </div>
                  </AccordionTrigger>
                  <AccordionContent className="px-4 pb-4">
                    <div className="space-y-4">
                      {group.records.map((record) => (
                        <div key={record.id} className="rounded-md border border-gray-200 bg-white p-4 shadow-sm">
                          <div className="flex flex-wrap gap-4 text-sm text-gray-700">
                            <div className="flex items-center gap-2">
                              <span className="font-semibold text-gray-800">CSG:</span>
                              {record.csg ? (
                                <Badge variant="secondary">{record.csg}</Badge>
                              ) : (
                                <span className="text-gray-500">Sin CSG</span>
                              )}
                            </div>
                            <div>
                              <span className="font-semibold text-gray-800">Código productor:</span> {record.idprod || 'N/A'}
                            </div>
                            <div>
                              <span className="font-semibold text-gray-800">Predio:</span> {record.predio || 'N/A'}
                            </div>
                            <div>
                              <span className="font-semibold text-gray-800">Comuna:</span> {record.comuna || 'N/A'}
                            </div>
                            <div>
                              <span className="font-semibold text-gray-800">Provincia:</span> {record.provincia || 'N/A'}
                            </div>
                            <div className="flex-1 min-w-[200px]">
                              <span className="font-semibold text-gray-800">Dirección:</span> {record.direccion || 'N/A'}
                            </div>
                          </div>
                          <div className="mt-4 flex flex-wrap items-center gap-2">
                            {!record.is_active && <Badge variant="destructive">Inactivo</Badge>}
                            <div className="ml-auto flex flex-wrap gap-2">
                              <Link href={route('producers.edit', record.id)}>
                                <Button variant="outline" size="sm">Editar</Button>
                              </Link>
                              <Button variant="destructive" size="sm" onClick={(e) => handleDelete(e, record)}>
                                Eliminar
                              </Button>
                            </div>
                          </div>
                        </div>
                      ))}
                    </div>
                  </AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
          ) : (
            <div className="rounded border border-dashed border-gray-200 py-12 text-center text-gray-500">
              No se encontraron productores con los filtros seleccionados.
            </div>
          )}

          <div className="flex items-center justify-between pt-4">
            <div className="flex-1 flex justify-between md:hidden">
              {producers.prev_page_url ? (
                <Link
                  href={producers.prev_page_url}
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Anterior
                </Link>
              ) : (
                <span className="relative inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                  Anterior
                </span>
              )}
              {producers.next_page_url ? (
                <Link
                  href={producers.next_page_url}
                  className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Siguiente
                </Link>
              ) : (
                <span className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-200 text-sm font-medium rounded-md text-gray-300 bg-white cursor-not-allowed">
                  Siguiente
                </span>
              )}
            </div>
            <div className="hidden flex-1 items-center justify-between md:flex">
              <div className="text-sm text-gray-700">
                Mostrando <span className="font-medium">{producers.from}</span> a <span className="font-medium">{producers.to}</span> de{' '}
                <span className="font-medium">{producers.total}</span> resultados
              </div>
              <div className="flex items-center gap-1">
                {producers.links.map((link, index) => (
                  link.url ? (
                    <Link
                      key={index}
                      href={link.url}
                      className={`px-3 py-1 text-sm border rounded ${link.active
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
                        : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'
                      }`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ) : (
                    <span
                      key={index}
                      className="px-3 py-1 text-sm border border-gray-200 rounded text-gray-400"
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  )
                ))}
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = (page) => (
  <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Productores</h2>} />
);
