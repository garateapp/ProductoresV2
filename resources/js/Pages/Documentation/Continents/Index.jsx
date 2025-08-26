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

export default function ContinentsIndex({ auth, continents }) {
  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    name: '',
  });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingContinent, setEditingContinent] = useState(null);

  const handleOpenCreateModal = () => {
    setEditingContinent(null);
    reset();
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (continent) => {
    setEditingContinent(continent);
    setData({ name: continent.name });
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingContinent(null);
    reset();
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (editingContinent) {
      put(route('continents.update', editingContinent.id), {
        onSuccess: () => handleCloseModal(),
      });
    } else {
      post(route('continents.store'), {
        onSuccess: () => handleCloseModal(),
      });
    }
  };

  const handleDelete = (continentId) => {
    if (confirm('¿Estás seguro de que quieres eliminar este continente?')) {
      destroy(route('continents.destroy', continentId));
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Continentes</h2>}
    >
      <Head title="Continentes" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Continentes</CardTitle>
              <Button onClick={handleOpenCreateModal}>Crear Continente</Button>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>ID</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {continents.map((continent) => (
                    <TableRow key={continent.id}>
                      <TableCell>{continent.id}</TableCell>
                      <TableCell>{continent.name}</TableCell>
                      <TableCell>
                        <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(continent)} className="mr-2">Editar</Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(continent.id)}>Eliminar</Button>
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
            <DialogTitle>{editingContinent ? 'Editar Continente' : 'Crear Continente'}</DialogTitle>
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
            <DialogFooter>
              <Button type="submit" disabled={processing}>{editingContinent ? 'Actualizar' : 'Crear'}</Button>
              <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
