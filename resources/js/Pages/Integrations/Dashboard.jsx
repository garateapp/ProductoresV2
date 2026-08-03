import React from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Dashboard({ stats, ultimas_ejecuciones, ultimos_errores, proximos_vencer }) {
  const statCards = [
    { label: 'Perfiles Activos', value: stats.profiles_activos, href: route('integrations.profiles.index') },
    { label: 'Ejecuciones Hoy', value: stats.ejecuciones_hoy, href: route('integrations.runs.index') },
    { label: 'Registros Procesados', value: stats.registros_procesados },
    { label: 'Exitosos', value: stats.registros_exitosos },
    { label: 'Pendientes Homologación', value: stats.pendientes_homologacion, href: route('integrations.pending-mappings.index') },
    { label: 'Fallidos', value: stats.registros_fallidos },
    { label: 'Exportaciones Hoy', value: stats.exportaciones_hoy, href: route('integrations.exports.index') },
    { label: 'Duración Promedio', value: stats.duracion_promedio ? `${stats.duracion_promedio}s` : '—' },
  ];

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-bold">Dashboard de Integraciones</h1>
        <div className="flex gap-2">
          <Link href={route('integrations.profiles.create')}>
            <Button>Nuevo Perfil</Button>
          </Link>
          <Link href={route('integrations.simulator.index')}>
            <Button variant="outline">Simulador</Button>
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
        {statCards.map((stat, i) => (
          <Card key={i}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">{stat.label}</CardTitle>
            </CardHeader>
            <CardContent>
              {stat.href ? (
                <Link href={stat.href} className="text-3xl font-bold hover:underline">{stat.value}</Link>
              ) : (
                <p className="text-3xl font-bold">{stat.value}</p>
              )}
            </CardContent>
          </Card>
        ))}
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        <Card>
          <CardHeader>
            <CardTitle>Últimas Ejecuciones</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Perfil</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead>Total</TableHead>
                  <TableHead>Usuario</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {ultimas_ejecuciones.map(run => (
                  <TableRow key={run.id}>
                    <TableCell>
                      <Link href={route('integrations.runs.show', run.id)} className="hover:underline font-medium">
                        {run.profile}
                      </Link>
                    </TableCell>
                    <TableCell>
                      <Badge>{run.estado_label}</Badge>
                    </TableCell>
                    <TableCell>{run.total}</TableCell>
                    <TableCell>{run.usuario}</TableCell>
                  </TableRow>
                ))}
                {ultimas_ejecuciones.length === 0 && (
                  <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground">Sin ejecuciones</TableCell></TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Últimos Errores</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Evento</TableHead>
                  <TableHead>Entidad</TableHead>
                  <TableHead>Fecha</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {ultimos_errores.map(err => (
                  <TableRow key={err.id}>
                    <TableCell className="max-w-[200px] truncate">{err.evento}</TableCell>
                    <TableCell>{err.entidad_nombre}</TableCell>
                    <TableCell>{err.created_at}</TableCell>
                  </TableRow>
                ))}
                {ultimos_errores.length === 0 && (
                  <TableRow><TableCell colSpan={3} className="text-center text-muted-foreground">Sin errores recientes</TableCell></TableRow>
                )}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>

      {proximos_vencer.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Conjuntos de Mapeo por Vencer</CardTitle>
          </CardHeader>
          <CardContent>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Código</TableHead>
                  <TableHead>Vencimiento</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {proximos_vencer.map(v => (
                  <TableRow key={v.id}>
                    <TableCell>{v.nombre}</TableCell>
                    <TableCell>{v.codigo}</TableCell>
                    <TableCell><Badge variant="secondary">{v.vencimiento}</Badge></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}
    </div>
  );
}

Dashboard.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Dashboard de Integraciones</h2>} />;
