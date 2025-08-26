import React, { useState, useEffect } from 'react';
import { Link, useForm } from '@inertiajs/react';
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
import { ChevronUp, ChevronDown } from 'lucide-react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ producers, filters }) {
  const { data, setData, get } = useForm({
    search: filters.search || '',
    sort_by: filters.sort_by || 'name',
    sort_order: filters.sort_order || 'asc',
  });

  const { delete: destroy } = useForm();

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleSort = (column) => {
    if (data.sort_by === column) {
      setData('sort_order', data.sort_order === 'asc' ? 'desc' : 'asc');
    } else {
      setData('sort_by', column);
      setData('sort_order', 'asc');
    }
  };

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      get(route('producers.index'), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.sort_by, data.sort_order]);

  function handleDelete(e, producer) {
    e.preventDefault();
    if (confirm('Are you sure you want to delete this producer?')) {
      destroy(route('producers.destroy', producer.id));
    }
  }

  const renderSortIcon = (column) => {
    if (data.sort_by === column) {
      return data.sort_order === 'asc' ? <ChevronUp className="ml-1 h-4 w-4" /> : <ChevronDown className="ml-1 h-4 w-4" />;
    }
    return null;
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Productores</CardTitle>
          <Link href={route('producers.create')}>
            <Button>Create Producer</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex justify-between items-center">
            <Input
              type="text"
              placeholder="Search producers..."
              value={data.search}
              onChange={handleSearchChange}
              className="max-w-sm"
            />
          </div>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead onClick={() => handleSort('name')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Name {renderSortIcon('name')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('email')} className="cursor-pointer min-w-[250px]">
                  <div className="flex items-center">
                    Email {renderSortIcon('email')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('rut')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    RUT {renderSortIcon('rut')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('idprod')} className="cursor-pointer min-w-[100px]">
                  <div className="flex items-center whitespace-nowrap">
                    ID Prod {renderSortIcon('idprod')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('csg')} className="cursor-pointer min-w-[90px]">
                  <div className="flex items-center whitespace-nowrap">
                    CSG {renderSortIcon('csg')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('predio')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Predio {renderSortIcon('predio')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('comuna')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Comuna {renderSortIcon('comuna')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('provincia')} className="cursor-pointer min-w-[120px]">
                  <div className="flex items-center whitespace-nowrap">
                    Provincia {renderSortIcon('provincia')}
                  </div>
                </TableHead>
                <TableHead onClick={() => handleSort('direccion')} className="cursor-pointer min-w-[150px]">
                  <div className="flex items-center whitespace-nowrap">
                    Dirección {renderSortIcon('direccion')}
                  </div>
                </TableHead>
                <TableHead className="min-w-[120px]">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {producers.data.map(producer => (
                <TableRow key={producer.id}>
                  <TableCell>{producer.name}</TableCell>
                  <TableCell>{producer.email}</TableCell>
                  <TableCell>{producer.rut}</TableCell>
                  <TableCell>{producer.idprod}</TableCell>
                  <TableCell>{producer.csg}</TableCell>
                  <TableCell>{producer.predio}</TableCell>
                  <TableCell>{producer.comuna}</TableCell>
                  <TableCell>{producer.provincia}</TableCell>
                  <TableCell>{producer.direccion}</TableCell>
                  <TableCell>
                    <Link href={route('producers.edit', producer.id)} className="mr-2">
                      <Button variant="outline">Edit</Button>
                    </Link>
                    <Button variant="destructive" onClick={(e) => handleDelete(e, producer)}>Delete</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <div className="flex items-center justify-between mt-4">
            <div className="flex-1 flex justify-between sm:hidden">
              {producers.prev_page_url && (
                <Link
                  href={producers.prev_page_url}
                  className="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Previous
                </Link>
              )}
              {producers.next_page_url && (
                <Link
                  href={producers.next_page_url}
                  className="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50"
                >
                  Next
                </Link>
              )}
            </div>
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Showing <span className="font-medium">{producers.from}</span> to <span className="font-medium">{producers.to}</span> of{' '}
                  <span className="font-medium">{producers.total}</span> results
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {producers.links.map((link, index) => (
                    link.url ? (
                      <Link
                        key={index}
                        href={link.url}
                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                          ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                          : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    ) : (
                      <span
                        key={index}
                        className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                          ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600'
                          : 'bg-white border-gray-300 text-gray-500'
                        } cursor-not-allowed`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                      />
                    )
                  ))}
                </nav>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Productores</h2>} />;