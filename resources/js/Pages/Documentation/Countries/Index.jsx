import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
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

export default function CountriesIndex({ auth, countries, continents }) {
  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    name: '',
    continent_id: '',
  });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCountry, setEditingCountry] = useState(null);

  const handleOpenCreateModal = () => {
    setEditingCountry(null);
    reset();
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (country) => {
    setEditingCountry(country);
    setData({ name: country.name, continent_id: country.continent_id });
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingCountry(null);
    reset();
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (editingCountry) {
      put(route('countries.update', editingCountry.id), {
        onSuccess: () => handleCloseModal(),
      });
    } else {
      post(route('countries.store'), {
        onSuccess: () => handleCloseModal(),
      });
    }
  };

  const handleDelete = (countryId) => {
    if (confirm('¿Estás seguro de que quieres eliminar este país?')) {
      destroy(route('countries.destroy', countryId));
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Países</h2>}
    >
      <Head title="Países" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Países</CardTitle>
              <Button onClick={handleOpenCreateModal}>Crear País</Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>ID</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Continente</TableHead>
                    <TableHead>Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {countries.map((country) => (
                    <TableRow key={country.id}>
                      <TableCell>{country.id}</TableCell>
                      <TableCell>{country.name}</TableCell>
                      <TableCell>{country.continent.name}</TableCell>
                      <TableCell>
                        <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(country)} className="mr-2">Editar</Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(country.id)}>Eliminar</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingCountry ? 'Editar País' : 'Crear País'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="name">Nombre</Label>
              <Input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
                className="mt-1 block w-full"
                required
              />
              {errors.name && <div className="text-red-600 text-sm mt-1">{errors.name}</div>}
            </div>
            <div>
              <Label htmlFor="continent_id">Continente</Label>
              <Select
                value={data.continent_id}
                onValueChange={(value) => setData('continent_id', Number(value))}
              >
                <SelectTrigger className="w-full">
                  <SelectValue placeholder="Seleccionar Continente" />
                </SelectTrigger>
                <SelectContent>
                  {continents.map((continent) => (
                    <SelectItem key={continent.id} value={continent.id}>
                      {continent.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.continent_id && <div className="text-red-600 text-sm mt-1">{errors.continent_id}</div>}
            </div>
            <DialogFooter>
              <Button type="submit" disabled={processing}>{editingCountry ? 'Actualizar' : 'Crear'}</Button>
              <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
