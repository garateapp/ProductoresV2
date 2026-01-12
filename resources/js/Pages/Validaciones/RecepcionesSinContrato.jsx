import React, { useEffect, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

export default function RecepcionesSinContrato({ producers, filters, excludedServices }) {
  const { data, setData, get } = useForm({
    search: filters.search || '',
  });
  const [ready, setReady] = useState(false);

  useEffect(() => {
    setReady(true);
  }, []);

  useEffect(() => {
    if (!ready) {
      return;
    }
    const timer = setTimeout(() => {
      get(route('validaciones.recepciones-sin-contrato'), {
        preserveState: true,
        replace: true,
      });
    }, 300);

    return () => clearTimeout(timer);
  }, [data.search, get, ready]);

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle className="text-2xl font-bold">Validaciones</CardTitle>
            <p className="text-sm text-gray-600">
              Productores con recepciones y sin contrato registrado.
            </p>
          </div>
          <div className="flex flex-col gap-2 text-sm text-gray-600">
            <span>Servicios excluidos: {excludedServices.join(', ')}</span>
            <span>Total encontrados: {producers.total}</span>
          </div>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <Input
              type="text"
              placeholder="Buscar por nombre, rut, codigo o email"
              value={data.search}
              onChange={(e) => setData('search', e.target.value)}
              className="max-w-sm"
            />
          </div>

          {producers.data.length ? (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Productor</TableHead>
                  <TableHead>RUT</TableHead>
                  <TableHead>Codigo</TableHead>
                  <TableHead>Recepciones</TableHead>
                  <TableHead>Ultima recepcion</TableHead>
                  <TableHead>Servicios</TableHead>
                  <TableHead>Accion</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {producers.data.map((producer) => (
                  <TableRow key={producer.id}>
                    <TableCell>
                      <div className="font-medium text-gray-800">{producer.name || 'Sin nombre'}</div>
                      <div className="text-xs text-gray-500">{producer.email || 'Sin email'}</div>
                    </TableCell>
                    <TableCell>{producer.rut || 'N/A'}</TableCell>
                    <TableCell>{producer.idprod || 'N/A'}</TableCell>
                    <TableCell>{producer.recepciones_count}</TableCell>
                    <TableCell>{producer.last_reception_date || 'N/A'}</TableCell>
                    <TableCell>
                      {producer.services.length ? (
                        <div className="flex flex-wrap gap-1">
                          {producer.services.map((service) => (
                            <Badge key={service.id} variant="secondary" className="text-xs">
                              {service.name}
                            </Badge>
                          ))}
                        </div>
                      ) : (
                        <span className="text-xs text-gray-500">Sin servicios</span>
                      )}
                    </TableCell>
                    <TableCell>
                      <Link href={route('producers.dashboard', producer.id)}>
                        <Button variant="outline" size="sm">Ver productor</Button>
                      </Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          ) : (
            <div className="rounded border border-dashed border-gray-200 py-12 text-center text-gray-500">
              No hay productores que cumplan la validacion actual.
            </div>
          )}

          <div className="flex items-center justify-between">
            <div className="text-sm text-gray-700">
              Mostrando {producers.from || 0} a {producers.to || 0} de {producers.total} resultados
            </div>
            <div className="flex items-center gap-1">
              {producers.links.map((link, index) => (
                link.url ? (
                  <Link
                    key={index}
                    href={link.url}
                    className={`px-3 py-1 text-sm border rounded ${link.active
                      ? 'border-green-500 bg-green-50 text-green-700'
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
        </CardContent>
      </Card>
    </div>
  );
}

RecepcionesSinContrato.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Validaciones</h2>}
  />
);
