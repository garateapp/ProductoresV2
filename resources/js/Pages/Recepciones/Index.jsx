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
import { FileText } from 'lucide-react'; // Import FileText icon
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ recepciones, especies, variedades = [], filters, isProducer, totalRecepciones, totalKilos = 0 }) {
  const { data, setData, get } = useForm({
    search: filters.search || '',
    especie_id: filters.especie_id || '',
    variedad_id: filters.variedad_id || '',
  });

  const handleSearchChange = (e) => {
    setData('search', e.target.value);
  };

  const handleEspecieFilter = (especieId) => {
    setData('especie_id', especieId);
    setData('variedad_id', ''); // Reset variedad filter when specie changes
  };

  const handleVariedadFilter = (variedadId) => {
    setData('variedad_id', variedadId);
  };

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      get(route('recepciones.index', {
        search: data.search,
        especie_id: data.especie_id,
        variedad_id: data.variedad_id,
      }), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.especie_id, data.variedad_id]);

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Recepciones</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <Input
              type="text"
              placeholder="Buscar por variedad, especie o lote..."
              value={data.search}
              onChange={handleSearchChange}
              className="max-w-sm"
            />
            <div className="flex flex-wrap gap-2">
              <Button
                variant={data.especie_id === '' ? 'default' : 'outline'}
                onClick={() => handleEspecieFilter('')}
              >
                Todas las Especies
              </Button>
              {especies.map((especie) => (
                <Button
                  key={especie.id}
                  variant={data.especie_id === especie.id ? 'default' : 'outline'}
                  onClick={() => handleEspecieFilter(especie.id)}
                >
                  {especie.name}
                </Button>
              ))}
            </div>
          </div>

          {data.especie_id && variedades.length > 0 && (
            <div className="mb-4 flex flex-wrap gap-2">
              <Button
                variant={data.variedad_id === '' ? 'default' : 'outline'}
                onClick={() => handleVariedadFilter('')}
              >
                Todas las Variedades
              </Button>
              {variedades.map((variedad) => (
                <Button
                  key={variedad.id}
                  variant={data.variedad_id === variedad.id ? 'default' : 'outline'}
                  onClick={() => handleVariedadFilter(variedad.id)}
                >
                  {variedad.name}
                </Button>
              ))}
            </div>
          )}

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Recepciones</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalRecepciones.toLocaleString('es-CL')}</div>
              </CardContent>
            </Card>
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Kilos Recepcionados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalKilos.toLocaleString('es-CL')} kg</div>
              </CardContent>
            </Card>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Lote</TableHead>
                <TableHead>Agricola</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Guia</TableHead>
                <TableHead>Cantidad</TableHead>
                <TableHead>Kilos</TableHead>
                <TableHead>Nota</TableHead>
                <TableHead>Informe</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {recepciones.data.map(recepcion => (
                <TableRow key={recepcion.id}>
                  <TableCell>{recepcion.id_g_recepcion}</TableCell>
                  <TableCell>{recepcion.n_emisor}</TableCell>
                  <TableCell>{recepcion.n_especie}</TableCell>
                  <TableCell>{recepcion.n_variedad}</TableCell>
                  <TableCell>{new Date(recepcion.fecha_g_recepcion).toLocaleDateString('es-CL')}</TableCell>
                  <TableCell>{recepcion.numero_documento_recepcion}</TableCell>
                  <TableCell>{recepcion.cantidad.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.peso_neto.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.nota_calidad === 0 ? 'S/N' : recepcion.nota_calidad}</TableCell>
                  <TableCell>
                    {recepcion.informe ? (
                      <a href={recepcion.informe} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                        <FileText className="h-5 w-5" />
                      </a>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          {/* Paginación */}
          <div className="flex items-center justify-between mt-4">
            <div className="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
              <div>
                <p className="text-sm text-gray-700">
                  Mostrando <span className="font-medium">{recepciones.from}</span> a <span className="font-medium">{recepciones.to}</span> de{' '}
                  <span className="font-medium">{recepciones.total}</span> resultados
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {recepciones.links.map((link, index) => (
                    <Link
                      key={`${link.url}-${index}`}
                      href={link.url || '#'}
                      disabled={!link.url}
                      preserveState={true}
                      preserveScroll={true}
                      className={`relative inline-flex items-center px-4 py-2 border text-sm font-medium ${link.active
                        ? 'z-10 bg-indigo-500 border-indigo-500 text-indigo-600'
                        : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                      } ${!link.url ? 'cursor-not-allowed opacity-50' : ''}`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
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

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Control de Calidad</h2>} />;
