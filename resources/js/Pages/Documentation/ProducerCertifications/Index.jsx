import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link , router} from '@inertiajs/react';
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
import { Inertia } from '@inertiajs/inertia';
import { Users, CheckCircle, Clock, XCircle } from 'lucide-react';

export default function CertificationsIndex({ auth, producers, filters, kpis }) {
  const [search, setSearch] = useState(filters.search || '');

  const handleSearch = (e) => {
    e.preventDefault();
    const url = route('producer-certifications.index', { search });
    router.get(url);
  };

  const getCertificationStatus = (expirationDate) => {
    const today = new Date();
    const expiry = new Date(expirationDate);
    const diffTime = expiry.getTime() - today.getTime();
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (diffDays <= 30 && diffDays >= 0) {
        return 'Por vencer';
    } else if (diffDays < 0) {
        return 'Vencida';
    } else {
        return 'Vigente';
    }
  };

  const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0'); // Los meses son 0-indexados
    const year = date.getFullYear();
    return `${day}-${month}-${year}`;
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Certificaciones</h2>}
    >
      <Head title="Certificaciones" />

      <div className="py-6">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total Productores</CardTitle>
                <Users className="h-4 w-4 text-gray-800" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{kpis.totalProducers}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Productores al Día</CardTitle>
                <CheckCircle className="h-4 w-4 text-green-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-green-600">{kpis.producersUpToDate}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Por Vencer</CardTitle>
                <Clock className="h-4 w-4 text-yellow-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-yellow-600">{kpis.producersExpiringSoon}</div>
              </CardContent>
            </Card>
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Vencidas</CardTitle>
                <XCircle className="h-4 w-4 text-red-600" />
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold text-red-600">{kpis.producersExpired}</div>
              </CardContent>
            </Card>
          </div>
        </div>
      </div>

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Lista de Certificaciones</CardTitle>
              <form onSubmit={handleSearch} className="flex items-center space-x-2">
                <Input
                  type="text"
                  placeholder="Buscar por nombre, email, rut..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  className="max-w-sm"
                />
                <Button type="submit">Buscar</Button>
              </form>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Productor</TableHead>
                    <TableHead>Certificaciones</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Fecha Vencimiento</TableHead>
                    <TableHead>Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                    {console.log(producers)}
                  {Object.keys(producers.data).length > 0 ? (
                    Object.values(producers.data).map((producerGroup) => {

                      const validProducerGroup = producerGroup.filter(p => p !== null && p !== undefined);
                      if (validProducerGroup.length === 0) return null;
                      const firstProducer = validProducerGroup[0];
                      const allCertifications = validProducerGroup.map(p => p.certifications || []).flat();
                      return (
                        <TableRow key={firstProducer.rut}>
                          <TableCell>{firstProducer.name}</TableCell>
                          <TableCell>

                            {allCertifications.length > 0 ? (

                              <div className="flex flex-wrap gap-1">
                                {allCertifications.map((cert) => (
                                  <span
                                    key={cert.id}
                                    className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                                  >
                                    {cert.name}
                                  </span>
                                ))}
                              </div>
                            ) : (
                              'N/A'
                            )}
                          </TableCell>
                          <TableCell>
                            {allCertifications.length > 0 ? (
                              <div className="flex flex-col gap-1">
                                {allCertifications.map((cert) => (
                                  <span
                                    key={cert.id}
                                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${
                                      getCertificationStatus(cert.expiration_date) === 'Por vencer'
                                        ? 'bg-yellow-100 text-yellow-800'
                                        : getCertificationStatus(cert.expiration_date) === 'Vencida'
                                        ? 'bg-red-100 text-red-800'
                                        : 'bg-green-100 text-green-800'
                                    }`}
                                  >
                                    {getCertificationStatus(cert.expiration_date)}
                                  </span>
                                ))}
                              </div>
                            ) : (
                              'N/A'
                            )}
                          </TableCell>
                          <TableCell>
                            {allCertifications.length > 0 ? (
                              <div className="flex flex-col gap-1">
                                {allCertifications.map((cert) => (
                                  <span key={cert.id}>{formatDate(cert.expiration_date)}</span>
                                ))}
                              </div>
                            ) : (
                              'N/A'
                            )}
                          </TableCell>
                          <TableCell>
                            <Link href={route('producer-certifications.show', firstProducer.id)}>
                              <Button variant="outline" size="sm">
                                Ver Detalle
                              </Button>
                            </Link>
                          </TableCell>
                        </TableRow>
                      );
                    })
                  ) : (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center">
                        No se encontraron productores.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
              <div className="flex justify-between items-center mt-4">
                {producers.links.map((link, index) => (
                  link.url ? (
                    <Link
                      key={index}
                      href={link.url}
                      className={`px-3 py-2 text-sm rounded-md ${
                        link.active
                          ? 'bg-blue-500 text-white'
                          : 'bg-gray-200 text-gray-700 hover:bg-gray-300'
                      }`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ) : (
                    <span
                      key={index}
                      className={`px-3 py-2 text-sm rounded-md opacity-50 cursor-not-allowed ${
                        link.active
                          ? 'bg-blue-500 text-white'
                          : 'bg-gray-200 text-gray-700'
                      }`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  )
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
