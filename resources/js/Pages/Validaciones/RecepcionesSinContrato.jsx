import React, { useEffect, useMemo, useState } from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion';
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
    email_filter: filters.email_filter || 'all',
    phone_filter: filters.phone_filter || 'all',
    contract_filter: filters.contract_filter || 'without',
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
  }, [data.search, data.email_filter, data.phone_filter, data.contract_filter, get, ready]);

  const groupedProducers = useMemo(() => {
    const map = new Map();

    (producers?.data || []).forEach((record) => {
      const key = record.rut || `producer-${record.id}`;
      if (!map.has(key)) {
        map.set(key, {
          key,
          rut: record.rut,
          name: record.name,
          records: [],
          emails: new Set(),
          phones: new Set(),
        });
      }
      const entry = map.get(key);
      entry.records.push(record);
      if (record.email) {
        entry.emails.add(record.email);
      }
      (record.telefonos || []).forEach((phone) => {
        if (phone) {
          entry.phones.add(phone);
        }
      });
    });

    return Array.from(map.values()).map((group) => ({
      ...group,
      emails: Array.from(group.emails).sort((a, b) => String(a).localeCompare(String(b))),
      phones: Array.from(group.phones).sort((a, b) => String(a).localeCompare(String(b))),
      hasEmail: group.records.some((record) => record.has_email),
      hasPhone: group.records.some((record) => record.has_phone),
      hasContract: group.records.some((record) => record.has_contract),
    }));
  }, [producers?.data]);

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
          <div>
            <CardTitle className="text-2xl font-bold">Validaciones</CardTitle>
            <p className="text-sm text-gray-600">
              Productores con recepciones sin contrato.
            </p>
          </div>
          <div className="flex flex-col gap-2 text-sm text-gray-600">
            {/* <span>Servicios excluidos: {excludedServices.join(', ')}</span> */}
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
            <div className="flex flex-wrap items-center gap-2 text-sm text-gray-600">
              <label className="flex items-center gap-2">
                Correo
                <select
                  value={data.email_filter}
                  onChange={(e) => setData('email_filter', e.target.value)}
                  className="rounded border px-2 py-1 text-sm"
                >
                  <option value="all">Todos</option>
                  <option value="with">Con correo</option>
                  <option value="without">Sin correo</option>
                </select>
              </label>
              <label className="flex items-center gap-2">
                Telefono
                <select
                  value={data.phone_filter}
                  onChange={(e) => setData('phone_filter', e.target.value)}
                  className="rounded border px-2 py-1 text-sm"
                >
                  <option value="all">Todos</option>
                  <option value="with">Con telefono</option>
                  <option value="without">Sin telefono</option>
                </select>
              </label>
              <label className="flex items-center gap-2">
                Contrato
                <select
                  value={data.contract_filter}
                  onChange={(e) => setData('contract_filter', e.target.value)}
                  className="rounded border px-2 py-1 text-sm"
                >
                  <option value="all">Todos</option>
                  <option value="with">Con contrato</option>
                  <option value="without" selected>Sin contrato</option>
                </select>
              </label>
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
                        </p>
                      </div>
                      <div className="flex flex-wrap items-center gap-2 text-xs text-gray-600">
                        <Badge variant="outline">Registros: {group.records.length}</Badge>
                        <Badge
                          variant="outline"
                          className={group.hasEmail ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"}
                        >
                          {group.hasEmail ? 'Con correo' : 'Sin correo'}
                        </Badge>
                        <Badge
                          variant="outline"
                          className={group.hasPhone ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"}
                        >
                          {group.hasPhone ? 'Con telefono' : 'Sin telefono'}
                        </Badge>
                        <Badge
                          variant="outline"
                          className={group.hasContract ? "border-green-200 bg-green-50 text-green-700" : "border-red-200 bg-red-50 text-red-700"}
                        >
                          {group.hasContract ? 'Con contrato' : 'Sin contrato'}
                        </Badge>
                      </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                      {group.emails.length ? (
                        group.emails.map((email) => (
                          <Badge key={email} variant="secondary" className="text-xs">
                            {email}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-xs text-gray-500">Sin emails registrados</span>
                      )}
                      {group.phones.length ? (
                        group.phones.map((phone) => (
                          <Badge key={phone} variant="outline" className="text-xs">
                            {phone}
                          </Badge>
                        ))
                      ) : (
                        <span className="text-xs text-gray-500">Sin telefonos</span>
                      )}
                    </div>
                  </AccordionTrigger>
                  <AccordionContent className="px-4 pb-4">
                    <Table>
                      <TableHeader>
                        <TableRow>
                          <TableHead>Productor</TableHead>
                          <TableHead>Email</TableHead>
                          <TableHead>Telefono</TableHead>
                          <TableHead>Contrato</TableHead>
                          <TableHead>Codigo</TableHead>
                          <TableHead>Recepciones</TableHead>
                          <TableHead>Ultima recepcion</TableHead>
                          <TableHead>Servicios</TableHead>
                          <TableHead>Accion</TableHead>
                        </TableRow>
                      </TableHeader>
                      <TableBody>
                        {group.records.map((producer) => (
                          <TableRow key={producer.id}>
                            <TableCell className="font-medium text-gray-800">
                              {producer.name || 'Sin nombre'}
                            </TableCell>
                            <TableCell>
                              {producer.email || 'Sin email'}
                            </TableCell>
                            <TableCell>
                              {producer.telefonos?.length ? (
                                <div className="flex flex-wrap gap-1">
                                  {producer.telefonos.map((phone) => (
                                    <Badge key={phone} variant="outline" className="text-xs">
                                      {phone}
                                    </Badge>
                                  ))}
                                </div>
                              ) : (
                                <span className="text-xs text-gray-500">Sin telefono</span>
                              )}
                            </TableCell>
                            <TableCell>
                              {producer.has_contract ? 'Con contrato' : 'Sin contrato'}
                            </TableCell>
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
                  </AccordionContent>
                </AccordionItem>
              ))}
            </Accordion>
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
                    preserveState={true}
                    preserveScroll={true}
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
