import React, { useState, useCallback, useRef, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

import { Inertia } from '@inertiajs/inertia';
import { Head, useForm, Link, router } from '@inertiajs/react';
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
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
  PaginationNext,
  PaginationPrevious,
} from '@/Components/ui/pagination';
import { Badge } from '@/Components/ui/badge';
import { Skeleton } from '@/Components/ui/skeleton'; // Assuming a Skeleton component exists or will be created





export default function MarketsIndex({ auth, markets, countries, certificateTypes, authorizationTypes, especies, filters }) {
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

  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    country_id: '',
    other_requirements: '',
    is_active: true,
    certificate_type_ids: [],
    authorization_type_id: '',
    especie_ids: [],
  });

  const handleSearch = useCallback((value) => {
    if (searchRef.current) {
      clearTimeout(searchRef.current);
    }
    searchRef.current = setTimeout(() => {
      Inertia.get(route('markets.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
  }, []);

  useEffect(() => {
    setSearch(filters.search || '');
  }, [filters.search]);

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingMarket, setEditingMarket] = useState(null);
  // Track selected especie for future variety-related features (safe placeholder)
  const [selectedEspecieId, setSelectedEspecieId] = useState(null);

  const handleOpenCreateModal = () => {
    setEditingMarket(null);
    reset();
    setData({ ...data, certificate_type_ids: [], authorization_type_id: '', especie_ids: [] });
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (market) => {
    setEditingMarket(market);
    setData({
      country_id: String(market.country_id),
      other_requirements: market.other_requirements,
      is_active: market.is_active,
      certificate_type_ids: market.certificate_types.map(type => type.id), // Set existing certificate types
      authorization_type_id: market.authorization_type_id ? String(market.authorization_type_id) : '',
      especie_ids: market.especies.map(especie => especie.id), // Set existing especies
    });
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingMarket(null);
    reset();
    setSelectedEspecieId(null); // Clear selected especie on modal close
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (editingMarket) {
      put(route('markets.update', editingMarket.id), {
        onSuccess: () => handleCloseModal(),
      });
    } else {
      post(route('markets.store'), {
        onSuccess: () => handleCloseModal(),
      });
    }
  };

  const handleDelete = (marketId) => {
    if (confirm('¿Estás seguro de que quieres eliminar este mercado?')) {
      destroy(route('markets.destroy', marketId));
    }
  };



  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Mercados</h2>}
    >
      <Head title="Mercados" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Mercados</CardTitle>
              <div className="flex items-center space-x-2">
                <Input
                  type="text"
                  placeholder="Buscar mercados..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    handleSearch(e.target.value);
                  }}
                  className="max-w-sm"
                />
                <Button onClick={handleOpenCreateModal}>Crear Mercado</Button>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>ID</TableHead>
                    <TableHead>País</TableHead>
                    <TableHead>Tipo Autorización</TableHead>
                    <TableHead>Especies</TableHead>
                    {/* Variedades removed */}
                    <TableHead>Certificaciones Exigidas</TableHead>
                    <TableHead>Observaciones</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead>Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {loading ? (
                    // Skeleton Loader
                    Array.from({ length: 5 }).map((_, index) => ( // Render 5 skeleton rows
                      <TableRow key={index}>
                        <TableCell><Skeleton className="h-4 w-12" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-20" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                        {/* Variedades removed skeleton */}
                        <TableCell><Skeleton className="h-4 w-48" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-32" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-16" /></TableCell>
                        <TableCell><Skeleton className="h-4 w-24" /></TableCell>
                      </TableRow>
                    ))
                  ) : (
                    // Actual Data
                    markets.data.map((market) => (
                      <TableRow key={market.id}>
                        <TableCell>{market.id}</TableCell>
                        <TableCell>{market.country?.name}</TableCell>
                        <TableCell>{market.authorization_type?.name || 'N/A'}</TableCell>
                        <TableCell>
                          {market.especies && market.especies.length > 0 ? (
                            <div className="flex flex-wrap gap-1">
                              {market.especies.map(especie => (
                                <Badge key={especie.id} variant="secondary">{especie.name}</Badge>
                              ))}
                            </div>
                          ) : '-'}
                        </TableCell>
                        <TableCell>
                          {market.certificate_types && market.certificate_types.length > 0 ? (
                            market.certificate_types.map(type => type.name).join(', ')
                          ) : '-'}
                        </TableCell>
                        <TableCell className="max-w-[220px] truncate">{market.other_requirements}</TableCell>
                        <TableCell>{market.is_active ? 'Sí' : 'No'}</TableCell>
                        <TableCell>
                          <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(market)} className="mr-2">Editar</Button>
                          <Button variant="destructive" size="sm" onClick={() => handleDelete(market.id)}>Eliminar</Button>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
              <div className="mt-4 flex justify-center">
                <Pagination>
                  <PaginationContent>
                    {markets.links.map((link, index) => (
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

      <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
        <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>{editingMarket ? 'Editar Mercado' : 'Crear Mercado'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="country_id">País</Label>
              <Select
                value={data.country_id}
                onValueChange={(value) => setData('country_id', value.toString())}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccionar País" />
                </SelectTrigger>
                <SelectContent>
                  {countries.map((country) => (
                    <SelectItem key={country.id} value={country.id.toString()}>
                      {country.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.country_id && <div className="text-red-600 text-sm mt-1">{errors.country_id}</div>}
            </div>

            <div>
              <Label htmlFor="authorization_type_id">Tipo de Autorización</Label>
              <Select
                value={data.authorization_type_id}
                onValueChange={(value) => setData('authorization_type_id', value)}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccionar Tipo de Autorización" />
                </SelectTrigger>
                <SelectContent>
                  {authorizationTypes.map((type) => (
                    <SelectItem key={type.id} value={String(type.id)}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.authorization_type_id && <div className="text-red-600 text-sm mt-1">{errors.authorization_type_id}</div>}
            </div>

            <div>
              <Label htmlFor="especie_ids">Especies</Label>
              <Select
                onValueChange={(value) => {
                  const id = parseInt(value);
                  setData(prevData => {
                    const currentIds = prevData.especie_ids;
                    if (!currentIds.includes(id)) {
                      // When adding a new especie, set it as the selectedEspecieId
                      setSelectedEspecieId(id);
                      return { ...prevData, especie_ids: [...currentIds, id] };
                    }
                    return prevData;
                  });
                }}
                value="" // This is important to allow re-selection of the same item
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccionar Especies" />
                </SelectTrigger>
                <SelectContent>
                  {especies.map((especie) => (
                    <SelectItem key={especie.id} value={especie.id.toString()}>
                      {especie.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <div className="mt-2 flex flex-wrap gap-2">
                {data.especie_ids.map(selectedId => {
                  const selectedEspecie = especies.find(especie => especie.id === selectedId);
                  return selectedEspecie ? (
                    <Badge key={selectedId} variant="secondary" className="flex items-center gap-1">
                      {selectedEspecie.name}
                      <button
                        type="button"
                        onClick={() => {
                          setData(prevData => ({
                            ...prevData,
                            especie_ids: prevData.especie_ids.filter(item => item !== selectedId),
                            variedad_ids: [], // Clear variedades when especie is removed
                          }));
                          setSelectedEspecieId(null); // Clear selected especie
                        }}
                        className="ml-1 text-xs text-gray-500 hover:text-gray-700 focus:outline-none"
                      >
                        x
                      </button>
                    </Badge>
                  ) : null;
                })}
              </div>
              {errors.especie_ids && <div className="text-red-600 text-sm mt-1">{errors.especie_ids}</div>}
            </div>

            {/* Variedades removed */}

            <div>
              <Label htmlFor="certificate_type_ids">Certificaciones Exigidas</Label>
              <Select
                onValueChange={(value) => {
                  const id = parseInt(value);
                  setData(prevData => {
                    const currentIds = prevData.certificate_type_ids;
                    if (!currentIds.includes(id)) { // Only add if not already present
                      return { ...prevData, certificate_type_ids: [...currentIds, id] };
                    }
                    return prevData; // Do nothing if already present
                  });
                }}
                value="" // This is important to allow re-selection of the same item
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccionar Certificaciones" />
                </SelectTrigger>
                <SelectContent>
                  {certificateTypes.map((type) => (
                    <SelectItem key={type.id} value={type.id.toString()}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <div className="mt-2 flex flex-wrap gap-2">
                {data.certificate_type_ids.map(selectedId => {
                  const selectedType = certificateTypes.find(type => type.id === selectedId);
                  return selectedType ? (
                    <Badge key={selectedId} variant="secondary" className="flex items-center gap-1">
                      {selectedType.name}
                      <button
                        type="button"
                        onClick={() => setData(prevData => ({
                          ...prevData,
                          certificate_type_ids: prevData.certificate_type_ids.filter(item => item !== selectedId)
                        }))}
                        className="ml-1 text-xs text-gray-500 hover:text-gray-700 focus:outline-none"
                      >
                        x
                      </button>
                    </Badge>
                  ) : null;
                })}
              </div>
              {errors.certificate_type_ids && <div className="text-red-600 text-sm mt-1">{errors.certificate_type_ids}</div>}
            </div>

            <div>
              <Label htmlFor="other_requirements">Observaciones</Label>
              <textarea
                id="other_requirements"
                value={data.other_requirements}
                onChange={(e) => setData('other_requirements', e.target.value)}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
              ></textarea>
              {errors.other_requirements && <div className="text-red-600 text-sm mt-1">{errors.other_requirements}</div>}
            </div>

            <div className="flex items-center space-x-2">
              <Switch
                id="is_active"
                checked={data.is_active}
                onCheckedChange={(value) => setData('is_active', value)}
              />
              <Label htmlFor="is_active">Activo</Label>
            </div>

            <DialogFooter>
              <Button type="submit" disabled={processing}>{editingMarket ? 'Actualizar' : 'Crear'}</Button>
              <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
