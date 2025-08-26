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

export default function AuthorizationTypesIndex({ auth, authorizationTypes }) {
  const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
    name: '',
  });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [editingAuthorizationType, setEditingAuthorizationType] = useState(null);

  const handleOpenCreateModal = () => {
    setEditingAuthorizationType(null);
    reset();
    setIsModalOpen(true);
  };

  const handleOpenEditModal = (type) => {
    setEditingAuthorizationType(type);
    setData({ name: type.name });
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setEditingAuthorizationType(null);
    reset();
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    if (editingAuthorizationType) {
      put(route('authorization-types.update', editingAuthorizationType.id), {
        onSuccess: () => handleCloseModal(),
      });
    } else {
      post(route('authorization-types.store'), {
        onSuccess: () => handleCloseModal(),
      });
    }
  };

  const handleDelete = (typeId) => {
    if (confirm('¿Estás seguro de que quieres eliminar este tipo de autorización?')) {
      destroy(route('authorization-types.destroy', typeId));
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Tipos de Autorización</h2>}
    >
      <Head title="Tipos de Autorización" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Tipos de Autorización</CardTitle>
              <Button onClick={handleOpenCreateModal}>Crear Tipo de Autorización</Button>
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
                  {authorizationTypes.map((type) => (
                    <TableRow key={type.id}>
                      <TableCell>{type.id}</TableCell>
                      <TableCell>{type.name}</TableCell>
                      <TableCell>
                        <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(type)} className="mr-2">Editar</Button>
                        <Button variant="destructive" size="sm" onClick={() => handleDelete(type.id)}>Eliminar</Button>
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
            <DialogTitle>{editingAuthorizationType ? 'Editar Tipo de Autorización' : 'Crear Tipo de Autorización'}</DialogTitle>
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
              <Button type="submit" disabled={processing}>{editingAuthorizationType ? 'Actualizar' : 'Crear'}</Button>
              <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
