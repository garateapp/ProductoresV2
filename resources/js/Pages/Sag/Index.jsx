import React, { useState, useCallback, useRef, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination';
import { Skeleton } from '@/Components/ui/skeleton';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion'; // Added Accordion components
import { User, ChevronDown, Tag, FileText, Download } from 'lucide-react'; // Added Tag, FileText, Download icons

export default function SagIndex({ auth, producers, filters, kpis }) {
  const [search, setSearch] = useState(filters.search || '');
  const [status, setStatus] = useState(filters.status || 'all');
  const searchRef = useRef(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const handleStart = () => setLoading(true);
    const handleFinish = () => setLoading(false);

    const unsubscribeStart = router.on('start', handleStart);
    const unsubscribeFinish = router.on('finish', handleFinish);

    return () => {
      unsubscribeStart();
      unsubscribeFinish();
    };
  }, []);

  const handleSearch = useCallback((value) => {
    if (searchRef.current) {
      clearTimeout(searchRef.current);
    }
    searchRef.current = setTimeout(() => {
      router.get(route('sag.index'), { search: value, status }, { preserveState: true, replace: true });
    }, 300);
  }, [status]);

  useEffect(() => {
    setSearch(filters.search || '');
    setStatus(filters.status || 'all');
  }, [filters.search, filters.status]);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Módulo SAG</h2>}
    >
      <Head title="Módulo SAG" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Productores y sus CSG</CardTitle>
              <div className="flex items-center space-x-2">
                <Input
                  type="text"
                  placeholder="Buscar por RUT o Nombre..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    handleSearch(e.target.value);
                  }}
                  className="max-w-sm"
                />
                <select
                  className="border rounded px-2 py-2"
                  value={status}
                  onChange={(e) => {
                    setStatus(e.target.value);
                    router.get(route('sag.index'), { search, status: e.target.value }, { preserveState: true, replace: true });
                  }}
                >
                  <option value="all">Todos</option>
                  <option value="Vigente">Vigente</option>
                  <option value="Por vencer">Por vencer</option>
                  <option value="Vencida">Vencida</option>
                </select>
                <Button
                  variant="outline"
                  onClick={() => {
                    const params = new URLSearchParams({ search: search || '', status: status || 'all' }).toString();
                    window.location.href = route('sag.export') + '?' + params;
                  }}
                >
                  Exportar CSV
                </Button>
              </div>
            </CardHeader>
            <CardContent>
              {kpis && (
                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                  <div className="p-4 rounded-lg bg-green-50 border border-green-200">
                    <div className="text-sm text-gray-500">Vigentes</div>
                    <div className="text-2xl font-bold text-green-700">{kpis.valid}</div>
                  </div>
                  <div className="p-4 rounded-lg bg-yellow-50 border border-yellow-200">
                    <div className="text-sm text-gray-600">Por vencer (≤30 días)</div>
                    <div className="text-2xl font-bold text-yellow-700">{kpis.expiring_soon}</div>
                  </div>
                  <div className="p-4 rounded-lg bg-red-50 border border-red-200">
                    <div className="text-sm text-gray-600">Vencidas</div>
                    <div className="text-2xl font-bold text-red-700">{kpis.expired}</div>
                  </div>
                  <div className="p-4 rounded-lg bg-gray-50 border border-gray-200">
                    <div className="text-sm text-gray-600">Total</div>
                    <div className="text-2xl font-bold text-gray-800">{kpis.total}</div>
                  </div>
                </div>
              )}
              {loading ? (
                // Skeleton Loader for Accordion
                Array.from({ length: 5 }).map((_, index) => (
                  <div key={index} className="mb-4 border rounded-lg p-4">
                    <Skeleton className="h-6 w-3/4 mb-2" />
                    <Skeleton className="h-4 w-1/2" />
                  </div>
                ))
              ) : (
                producers.data.length > 0 ? (
                  <Accordion type="single" collapsible className="w-full">
                    {producers.data.map((producer) => (
                      <AccordionItem key={producer.rut} value={producer.rut}>
                        <AccordionTrigger className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg shadow-sm">
                          <div className="flex items-center gap-3">
                            <User className="h-6 w-6 text-gray-600" />
                            <span className="font-semibold text-lg">{producer.name}</span>
                            <Badge variant="outline" className="ml-2">{producer.rut}</Badge>
                            <Badge variant="default" className="ml-2 flex items-center gap-1">
                                <FileText className="h-3 w-3" />
                                {producer.sag_certifications_count} Documentos
                            </Badge>
                            <div className="flex flex-wrap gap-1 ml-4">
                              {producer.csg_records.flatMap(csg => csg.especies).filter((value, index, self) => self.findIndex(e => e.id === value.id) === index).map(especie => (
                                <Badge key={especie.id} variant="secondary" className="flex items-center gap-1">
                                  <Tag className="h-3 w-3" /> {especie.name}
                                </Badge>
                              ))}
                            </div>
                          </div>
                          <ChevronDown className="h-5 w-5 shrink-0 transition-transform duration-200" />
                        </AccordionTrigger>
                        <AccordionContent className="p-4 border-t border-gray-200 bg-white rounded-b-lg shadow-inner">
                          <div className="space-y-4">
                            {producer.csg_records
                              .filter((csgRecord) => {
                                if (status === 'all') return true;
                                const s = csgRecord.sag_certifications_status || { valid:0, expiring_soon:0, expired:0 };
                                if (status === 'Vigente') return s.valid > 0;
                                if (status === 'Por vencer') return s.expiring_soon > 0;
                                if (status === 'Vencida') return s.expired > 0;
                                return true;
                              })
                              .map((csgRecord) => (
                              <div key={csgRecord.id} className="p-3 border rounded-md bg-white shadow-sm">
                                <div className="flex items-center justify-between mb-2">
                                  <h4 className="font-semibold text-md">CSG: {csgRecord.csg_code}</h4>
                                  <div className="flex items-center gap-2">
                                    <Badge variant="outline">
                                      {csgRecord.sag_certifications_count} Certificaciones
                                    </Badge>
                                    {csgRecord.sag_certifications_status && (
                                      <div className="flex items-center gap-1 text-xs">
                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-green-600 text-white">Vigentes: {csgRecord.sag_certifications_status.valid}</span>
                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-yellow-500 text-black">Por vencer: {csgRecord.sag_certifications_status.expiring_soon}</span>
                                        <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-red-600 text-white">Vencidas: {csgRecord.sag_certifications_status.expired}</span>
                                      </div>
                                    )}
                                  </div>
                                </div>
                                <p className="text-sm text-gray-600 mb-2">
                                  Especies asociadas a este CSG: {' '}
                                  {csgRecord.especies && csgRecord.especies.length > 0 ? (
                                    csgRecord.especies.map((especie) => (
                                      <Badge key={especie.id} variant="secondary" className="mr-1">
                                        {especie.name}
                                      </Badge>
                                    ))
                                  ) : 'N/A'}
                                </p>
                                <div className="mt-3 flex justify-end">
                                  <Link href={route('sag.show', producer.rut)}> {/* Link to producer details */}
                                    <Button variant="outline" size="sm">Ver Detalles</Button>
                                  </Link>
                                </div>
                              </div>
                            ))}
                          </div>
                        </AccordionContent>
                      </AccordionItem>
                    ))}
                  </Accordion>
                ) : (
                  <div className="text-center py-8 text-gray-500">No hay productores con CSG registrados.</div>
                )
              )}
              <div className="mt-4 flex justify-center">
                <Pagination>
                  <PaginationContent>
                    {producers.links.map((link, index) => (
                      <PaginationItem key={index}>
                        <PaginationLink
                          href={link.url || '#'}
                          isActive={link.active}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      </PaginationItem>
                    ))}
                  </PaginationContent>
                </Pagination>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
