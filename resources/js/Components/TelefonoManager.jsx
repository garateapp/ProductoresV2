import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';

export default function TelefonoManager({ producer }) {
  const [telefonos, setTelefonos] = useState(producer.telefonos || []);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const { data, setData, post, put, delete: destroy, errors, reset } = useForm({
    id: null,
    numero: '',
    user_id: producer.id,
    sync_same_rut: false,
  });

  const openModal = (telefono = null) => {
    if (telefono) {
      setData({
        id: telefono.id,
        numero: telefono.numero,
        user_id: producer.id,
        sync_same_rut: false,
      });
    } else {
      reset();
      setData({
        id: null,
        numero: '',
        user_id: producer.id,
        sync_same_rut: false,
      });
    }
    setIsModalOpen(true);
  };

  const closeModal = () => {
    setIsModalOpen(false);
    reset();
    setData({
      id: null,
      numero: '',
      user_id: producer.id,
      sync_same_rut: false,
    });
  };

  const handleSubmit = (e) => {
    e.preventDefault();
    const url = data.id ? route('telefonos.update', data.id) : route('telefonos.store');
    const method = data.id ? 'put' : 'post';

    (method === 'put' ? put : post)(url, {
      ...data,
      onSuccess: () => closeModal(),
    });
  };

  const handleDelete = (id) => {
    destroy(route('telefonos.destroy', id));
  };

  return (
    <div>
      <div className="flex justify-end mb-4">
        <Button onClick={() => openModal()}>Add Telefono</Button>
      </div>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Numero</TableHead>
            <TableHead>Actions</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {telefonos.map((telefono) => (
            <TableRow key={telefono.id}>
              <TableCell>{telefono.numero}</TableCell>
              <TableCell>
                <Button variant="outline" size="sm" onClick={() => openModal(telefono)}>
                  Edit
                </Button>
                <Button variant="destructive" size="sm" className="ml-2" onClick={() => handleDelete(telefono.id)}>
                  Delete
                </Button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

      <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{data.id ? 'Edit' : 'Add'} Telefono</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Input
                type="text"
                value={data.numero}
                onChange={(e) => setData('numero', e.target.value)}
                placeholder="Numero de Telefono"
              />
              {errors.numero && <div className="text-red-500 text-sm">{errors.numero}</div>}
            </div>
            <div className="flex items-center gap-2">
              <Switch
                id="sync_same_rut"
                checked={!!data.sync_same_rut}
                onCheckedChange={(value) => setData('sync_same_rut', value)}
              />
              <Label htmlFor="sync_same_rut" className="cursor-pointer">
                Actualizar el resto de los CSG con el mismo RUT
              </Label>
            </div>
            <Button type="submit">{data.id ? 'Update' : 'Create'}</Button>
          </form>
        </DialogContent>
      </Dialog>
    </div>
  );
}
