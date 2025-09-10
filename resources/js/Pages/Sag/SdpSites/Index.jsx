import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function Index({ auth, sites, filters }) {
  const [search, setSearch] = useState(filters.search || '');

  useEffect(() => {
    setSearch(filters.search || '');
  }, [filters.search]);

  const onSearch = (e) => {
    const value = e.target.value;
    setSearch(value);
    router.get(route('sdp-sites.index'), { search: value }, { preserveState: true, replace: true });
  };

  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">SDP (Sitios de Plantación)</h2>}>
      <Head title="SDP Sites" />
      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex items-center justify-between">
              <CardTitle>Listado de SDP</CardTitle>
              <div className="flex gap-2">
                <Input placeholder="Buscar por nombre, código o CSG" value={search} onChange={onSearch} />
                <Link href={route('sdp-sites.create')}>
                  <Button>Nuevo SDP</Button>
                </Link>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>CSG</TableHead>
                    <TableHead>RUT</TableHead>
                    <TableHead>Productor</TableHead>
                    <TableHead>Código</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Dirección</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead></TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {sites.data.map(site => (
                    <TableRow key={site.id}>
                      <TableCell>{site.csg_user?.csg || '-'}</TableCell>
                      <TableCell>{site.csg_user?.rut || '-'}</TableCell>
                      <TableCell>{site.csg_user?.name || '-'}</TableCell>
                      <TableCell>{site.code || '-'}</TableCell>
                      <TableCell>{site.name}</TableCell>
                      <TableCell>{site.address || '-'}</TableCell>
                      <TableCell>{site.is_active ? 'Sí' : 'No'}</TableCell>
                      <TableCell className="text-right">
                        <Link href={route('sdp-sites.edit', site.id)}><Button variant="outline" size="sm">Editar</Button></Link>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <div className="mt-4 flex justify-center">
                {(sites.links || []).map((link, key) => (
                  <Link key={key} className={`mr-1 mb-1 px-4 py-2 text-sm border rounded ${link.active ? 'bg-gray-200' : ''}`} href={link.url || '#'} dangerouslySetInnerHTML={{ __html: link.label }} />
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

