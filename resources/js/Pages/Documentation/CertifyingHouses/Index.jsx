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

export default function CertifyingHousesIndex({ auth, certifyingHouses }) {
  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    name: '',
  });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingCertifyingHouse, setEditingCertifyingHouse] = useState(null);

  const handleOpenCreateModal = () => {
    setEditingCertifyingHouse(null);
    reset();
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (house) => {
    setEditingCertifyingHouse(house);
    setData({ name: house.name });
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingCertifyingHouse(null);
    reset();
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (editingCertifyingHouse) {
      put(route('certifying-houses.update', editingCertifyingHouse.id), {
        onSuccess: () => handleCloseModal(),
      });
    } else {
      post(route('certifying-houses.store'), {
        onSuccess: () => handleCloseModal(),
      });
    }
  };

  const handleDelete = (houseId) => {
    if (confirm('¿Estás seguro de que quieres eliminar esta casa certificadora?')) {
      destroy(route('certifying-houses.destroy', houseId));
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Casas Certificadoras</h2>}
    >
      <Head title="Casas Certificadoras" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Casas Certificadoras</CardTitle>
              <Button onClick={handleOpenCreateModal}>Crear Casa Certificadora</Button>
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
                  {certifyingHouses.map((house) => (
                    <TableRow key={house.id}>
                      <TableCell>{house.id}</TableCell>
                      <TableCell>{house.name}</TableCell>
                      <TableCell>
                        <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(house)} className="mr-2">Editar</Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(house.id)}>Eliminar</Button>
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
            <DialogTitle>{editingCertifyingHouse ? 'Editar Casa Certificadora' : 'Crear Casa Certificadora'}</DialogTitle>
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
              <Button type="submit" disabled={processing}>{editingCertifyingHouse ? 'Actualizar' : 'Crear'}</Button>
              <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
