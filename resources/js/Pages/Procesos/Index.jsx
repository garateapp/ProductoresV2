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
import { FileText } from 'lucide-react';
import Chart from 'react-apexcharts';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ procesos, especies, variedades = [], filters, isProducer, totalProcesos, totalKgProcesados, totalExportacion, totalComercial, totalMerma, chartData }) {
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
    setData('variedad_id', '');
  };

  const handleVariedadFilter = (variedadId) => {
    setData('variedad_id', variedadId);
  };

  useEffect(() => {
    const delayDebounceFn = setTimeout(() => {
      get(route('procesos.index', {
        search: data.search,
        especie_id: data.especie_id,
        variedad_id: data.variedad_id,
      }), { preserveState: true, replace: true });
    }, 300);

    return () => clearTimeout(delayDebounceFn);
  }, [data.search, data.especie_id, data.variedad_id]);

  const calculatePercentage = (value, total) => {
    if (total === 0) return '0.00%';
    return ((value / total) * 100).toFixed(2) + '%';
  };

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Procesos</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <Input
              type="text"
              placeholder="Buscar por especie o variedad..."
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

          <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-4">
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Procesos</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalProcesos.toLocaleString('es-CL')}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Kg Procesados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalKgProcesados.toLocaleString('es-CL')} kg</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Exportación</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalExportacion, totalKgProcesados)}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Comercial</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalComercial, totalKgProcesados)}</div>
              </CardContent>
            </Card>
            <Card className="bg-blue-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Merma</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{calculatePercentage(totalMerma, totalKgProcesados)}</div>
              </CardContent>
            </Card>
          </div>

          <div className="mb-4 h-80">
            <Chart
              options={{
                chart: {
                  type: 'bar',
                  stacked: true,
                  toolbar: { show: false },
                },
                plotOptions: {
                  bar: { horizontal: false, dataLabels: { position: 'top' } },
                },
                dataLabels: {
                  enabled: true,
                  formatter: function (val) {
                    return val.toLocaleString('es-CL');
                  },
                  offsetY: -20,
                  style: {
                    fontSize: '12px',
                    colors: ['#304758']
                  }
                },
                xaxis: {
                  categories: chartData.map(data => data.especie),
                },
                yaxis: {
                  title: { text: 'Kilos' },
                  labels: {
                    formatter: function (val) {
                      return val.toLocaleString('es-CL');
                    }
                  }
                },
                fill: { opacity: 1 },
                tooltip: {
                  y: {
                    formatter: function (val) {
                      return val.toLocaleString('es-CL') + " Kilos"
                    }
                  }
                },
                legend: {
                  position: 'top',
                  horizontalAlign: 'left',
                  offsetX: 40
                },
                colors: ['#8884d8', '#82ca9d', '#ffc658', '#ff7300'],
              }}
              series={[
                { name: 'Exportación', data: chartData.map(data => data.exportacion) },
                { name: 'Comercial', data: chartData.map(data => data.comercial) },
                { name: 'Desecho', data: chartData.map(data => data.desecho) },
                { name: 'Merma', data: chartData.map(data => data.merma) },
              ]}
              type="bar"
              height={320}
            />
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Agricola</TableHead>
                <TableHead>N° Proceso</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Kg Procesados</TableHead>
                <TableHead>Exportación</TableHead>
                <TableHead>Comercial</TableHead>
                <TableHead>Desecho</TableHead>
                <TableHead>Merma</TableHead>
                <TableHead>Informe</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {procesos.data.map(proceso => (
                <TableRow key={proceso.id}>
                  <TableCell>{proceso.agricola}</TableCell>
                  <TableCell>{proceso.n_proceso}</TableCell>
                  <TableCell>{proceso.especie}</TableCell>
                  <TableCell>{proceso.variedad}</TableCell>
                  <TableCell>{new Date(proceso.fecha).toLocaleDateString('es-CL')}</TableCell>
                  <TableCell>{(proceso.kilos_netos ?? 0).toLocaleString('es-CL')}</TableCell>
                  <TableCell>{calculatePercentage(proceso.exp ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.comercial ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.desecho ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>{calculatePercentage(proceso.merma ?? 0, proceso.kilos_netos ?? 0)}</TableCell>
                  <TableCell>
                    {proceso.informe ? (
                      <a href={proceso.informe} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
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
                  Mostrando <span className="font-medium">{procesos.from}</span> a <span className="font-medium">{procesos.to}</span> de{' '}
                  <span className="font-medium">{procesos.total}</span> resultados
                </p>
              </div>
              <div>
                <nav className="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                  {procesos.links.map((link, index) => (
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

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Procesos</h2>} />;