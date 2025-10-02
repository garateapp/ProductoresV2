import React, { useState, useEffect } from 'react';
import { Link, useForm, router, usePage } from '@inertiajs/react';
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
import { ChevronUp, ChevronDown } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ producers, filters }) {
  const { props } = usePage();
  const { data, setData, get } = useForm({
    search: filters.search || '',
    sort_by: filters.sort_by || 'name',
    sort_order: filters.sort_order || 'asc',
    show_inactive: !!filters.show_inactive,
  });

  const { delete: destroy } = useForm();

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleSort = (column) => {
    if (data.sort_by === column) {
      setData('sort_order', data.sort_order === 'asc' ? 'desc' : 'asc');
    } else {
      setData('sort_by', column);
      setData('sort_order', 'asc');
    }
  };

  useEffect(() => {
    // Si venimos de una sincronización (dry-run o real) y hay resumen en flash,
    // evitamos hacer el GET inmediato que borraría los mensajes flash.
    if (props?.flash?.sync_output || props?.flash?.success || props?.flash?.error) {
      return;
    }
    const delayDebounceFn = setTimeout(() => {
      get(route('producers.index'), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.sort_by, data.sort_order, data.show_inactive, props?.flash?.sync_output, props?.flash?.success, props?.flash?.error]);

  function handleDelete(e, producer) {
    e.preventDefault();
    if (confirm('¿Estás seguro de eliminar este productor?')) {
      destroy(route('producers.destroy', producer.id));
    }
  }

  const renderSortIcon = (column) => {
    if (data.sort_by === column) {
      return data.sort_order === 'asc' ? <ChevronUp className="ml-1 h-4 w-4" /> : <ChevronDown className="ml-1 h-4 w-4" />;
    }
    return null;
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Productores</CardTitle>
          <div className="flex items-center gap-2">
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
        <CardContent>
          {props?.flash?.success && (
            <div className="mb-3 rounded border border-green-200 bg-green-50 text-green-800 px-3 py-2 text-sm">
              {props.flash.success}
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
          <div className="mb-4 flex justify-between items-center">
            <Input
              type="text"
              placeholder="Buscar productores..."
              value={data.search}
              onChange={handleSearchChange}
              className="max-w-sm"
            />
            <div className="flex items-center gap-2 text-sm">
              <Switch
                id="show-inactive-switch"
                checked={!!data.show_inactive}
                onCheckedChange={(val) => setData('show_inactive', !!val)}
              />
              <label htmlFor="show-inactive-switch">Mostrar inactivos</label>
            </div>
          </div>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead onClick={() => handleSort('name')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Nombre {renderSortIcon('name')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('email')} className="cursor-pointer min-w-[250px]">
                  <div className="flex items-center">
                    Correo {renderSortIcon('email')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('rut')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    RUT {renderSortIcon('rut')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('idprod')} className="cursor-pointer min-w-[100px]">
                  <div className="flex items-center whitespace-nowrap">
                    ID Prod {renderSortIcon('idprod')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('csg')} className="cursor-pointer min-w-[90px]">
                  <div className="flex items-center whitespace-nowrap">
                    CSG {renderSortIcon('csg')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('predio')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Predio {renderSortIcon('predio')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('comuna')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Comuna {renderSortIcon('comuna')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('provincia')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Provincia {renderSortIcon('provincia')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('direccion')} className="cursor-pointer min-w-[150px]">
                  <div className="flex items-center whitespace-nowrap">
                    Dirección {renderSortIcon('direccion')}
                  </div>
                </TableHead>
                <TableHead className="min-w-[100px]">Estado</TableHead>
                <TableHead className="min-w-[120px]">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {producers.data.map(producer => (
                <TableRow key={producer.id}>
                  <TableCell>{producer.name}</TableCell>
                  <TableCell>{producer.email}</TableCell>
                  <TableCell>{producer.rut}</TableCell>
                  <TableCell>{producer.idprod}</TableCell>
                  <TableCell>{producer.csg}</TableCell>
                  <TableCell>{producer.predio}</TableCell>
                  <TableCell>{producer.comuna}</TableCell>
                  <TableCell>{producer.provincia}</TableCell>
                  <TableCell>{producer.direccion}</TableCell>
                  <TableCell>
                    {!producer.is_active && (
                      <Badge variant="destructive">Inactivo</Badge>
                    )}
                  </TableCell>
                  <TableCell>
                    <Link href={route('producers.edit', producer.id)} className="mr-2">
                      <Button variant="outline">Editar</Button>
                    </Link>
                    <Button variant="destructive" onClick={(e) => handleDelete(e, producer)}>Eliminar</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <div className="flex items-center justify-between mt-4">
            <div className="flex-1 flex justify-between sm:hidden">
              {producers.prev_page_url && (
                <Link
                  href={producers.prev_page_url}
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Anterior
                </Link>
              )}
              {producers.next_page_url && (
                <Link
                  href={producers.next_page_url}
                  className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Siguiente
                </Link>
              )}
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Mostrando <span className="font-medium">{producers.from}</span> a <span className="font-medium">{producers.to}</span> de{' '}
                  <span className="font-medium">{producers.total}</span> resultados
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {producers.links.map((link, index) => (
                    link.url ? (
                      <Link
                        key={index}
                        href={link.url}
                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                          ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ) : (
                      <span
                        key={index}
                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                          ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                          : 'bg-white border-gray-300 text-gray-500'
                        } cursor-not-allowed`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    )
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

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Productores</h2>} />;
