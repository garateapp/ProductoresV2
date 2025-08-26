import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

const Create = () => {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    description: '',
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    post(route('photo-types.store'));
  };

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle>Crear Nuevo Tipo de Foto</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit}>
            <div className="mb-4">
              <Label htmlFor="name">Nombre</Label>
              <Input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
              />
              {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
            </div>

            <div className="mb-4">
              <Label htmlFor="description">Descripción</Label>
              <textarea
                id="description"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 min-h-[80px]"
                rows={4}
              ></textarea>
              {errors.description && <p className="mt-1 text-sm text-red-600">{errors.description}</p>}
            </div>

            <div className="flex items-center justify-end space-x-2">
              <Link href={route('photo-types.index')}>
                <Button variant="outline">Cancelar</Button>
              </Link>
              <Button type="submit" disabled={processing}>
                {processing ? 'Guardando...' : 'Guardar'}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </>
  );
};

Create.layout = (page) => (
  <AuthenticatedLayout
    user={page.props.auth.user}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Crear Tipo de Foto</h2>}
  >
    {page}
  </AuthenticatedLayout>
);

export default Create;