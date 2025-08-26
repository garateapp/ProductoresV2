import React from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Edit, Trash2 } from 'lucide-react';

const Index = ({ photoTypes }) => {
  const { flash } = usePage().props;

  const handleDelete = (id) => {
    if (confirm('¿Estás seguro de que quieres eliminar este tipo de foto?')) {
      router.delete(route('photo-types.destroy', id));
    }
  };

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Tipos de Foto para Control de Calidad</CardTitle>
          <Link href={route('photo-types.create')}>
            <Button>Crear Tipo de Foto</Button>
          </Link>
        </CardHeader>
        <CardContent>
          {flash && flash.success && (
            <div className="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">
              {flash.success}
            </div>
          )}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Descripción</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {photoTypes.data.map((type) => (
                <TableRow key={type.id}>
                  <TableCell>{type.name}</TableCell>
                  <TableCell>{type.description}</TableCell>
                  <TableCell className="text-right">
                    <Link href={route('photo-types.edit', type.id)} className="mr-2">
                      <Button variant="outline" size="icon">
                        <Edit className="h-4 w-4" />
                      </Button>
                    </Link>
                    <Button
                      variant="destructive"
                      size="icon"
                      onClick={() => handleDelete(type.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          {/* Paginación */}
          <div className="mt-4 flex items-center justify-between">
            <p className="text-sm text-gray-700">
              Mostrando {photoTypes.from} a {photoTypes.to} de {photoTypes.total} resultados
            </p>
            <nav className="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
              {photoTypes.links.map((link, index) => (
                <Link
                  key={`${link.url}-${index}`}
                  href={link.url || '#'}
                  disabled={!link.url}
                  className={`relative inline-flex items-center border px-4 py-2 text-sm font-medium ${
                    link.active
                      ? 'z-10 border-indigo-500 bg-indigo-50 text-indigo-600'
                      : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50'
                  } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </nav>
          </div>
        </CardContent>
      </Card>
    </>
  );
};

Index.layout = (page) => (
  <AuthenticatedLayout
    user={page.props.auth.user}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Gestión de Tipos de Foto</h2>}
  >
    {page}
  </AuthenticatedLayout>
);

export default Index;
