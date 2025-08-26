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

export default function SagIndex({ auth, producers, filters }) {
  const [search, setSearch] = useState(filters.search || '');
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
      router.get(route('sag.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
  }, []);

  useEffect(() => {
    setSearch(filters.search || '');
  }, [filters.search]);

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
                {/* Add button for uploading certifications later */}
              </div>
            </CardHeader>
            <CardContent>
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
                            {producer.csg_records.map((csgRecord) => (
                              <div key={csgRecord.id} className="p-3 border rounded-md bg-white shadow-sm">
                                <div className="flex items-center justify-between mb-2">
                                  <h4 className="font-semibold text-md">CSG: {csgRecord.csg_code}</h4>
                                  <Badge variant="outline">
                                    {csgRecord.sag_certifications_count} Certificaciones
                                  </Badge>
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
