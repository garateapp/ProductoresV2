import React from 'react';
import { Link, useForm } from '@inertiajs/react';
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

export default function Show({ profile, versions }) {
  const { post } = useForm();

  function handleDuplicate() {
    if (confirm('¿Duplicar este perfil?')) {
      post(route('integrations.profiles.duplicate', profile.id));
    }
  }

  function handlePublish() {
    if (confirm('¿Publicar este perfil? Las versiones publicadas serán inmutables.')) {
      post(route('integrations.profiles.publish', profile.id));
    }
  }

  return (
    <div className="container mx-auto py-10 space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h1 className="text-2xl font-bold">{profile.nombre}</h1>
          <p className="text-muted-foreground">{profile.codigo}</p>
        </div>
        <div className="flex gap-2">
          <Link href={route('integrations.profiles.edit', profile.id)}>
            <Button>Editar</Button>
          </Link>
          <Button variant="outline" onClick={handleDuplicate}>Duplicar</Button>
          {profile.estado !== 'publicado' && (
            <Button variant="secondary" onClick={handlePublish}>Publicar</Button>
          )}
          <Link href={route('integrations.profiles.index')}>
            <Button variant="ghost">Volver</Button>
          </Link>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Estado</CardTitle></CardHeader>
          <CardContent><Badge>{profile.estado_label}</Badge></CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Cliente</CardTitle></CardHeader>
          <CardContent>{profile.cliente}</CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Dirección</CardTitle></CardHeader>
          <CardContent>{profile.direccion}</CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Versión Actual</CardTitle></CardHeader>
          <CardContent>v{profile.version_actual}</CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Tipo Salida</CardTitle></CardHeader>
          <CardContent>{profile.tipo_salida}</CardContent>
        </Card>
        <Card>
          <CardHeader className="pb-2"><CardTitle className="text-sm">Creado por</CardTitle></CardHeader>
          <CardContent className="text-sm">{profile.creador} · {profile.created_at}</CardContent>
        </Card>
      </div>

      {profile.last_run && (
        <Card>
          <CardHeader><CardTitle>Última Ejecución</CardTitle></CardHeader>
          <CardContent>
            <div className="grid grid-cols-4 gap-4 text-sm">
              <div><span className="text-muted-foreground">Estado:</span> {profile.last_run.estado}</div>
              <div><span className="text-muted-foreground">Registros:</span> {profile.last_run.total}</div>
              <div><span className="text-muted-foreground">Usuario:</span> {profile.last_run.usuario}</div>
              <div><span className="text-muted-foreground">Fecha:</span> {profile.last_run.created_at}</div>
            </div>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader><CardTitle>Versiones ({versions.length})</CardTitle></CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Versión</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead>Inmutable</TableHead>
                <TableHead>Publicado</TableHead>
                <TableHead>Creado</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {versions.map(v => (
                <TableRow key={v.id}>
                  <TableCell className="font-mono">v{v.version}</TableCell>
                  <TableCell><Badge variant={v.estado === 'publicado' ? 'default' : 'secondary'}>{v.estado}</Badge></TableCell>
                  <TableCell>{v.inmutable ? 'Sí' : 'No'}</TableCell>
                  <TableCell>{v.published_at || '—'}</TableCell>
                  <TableCell>{v.created_at}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Show.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Perfil de Integración</h2>} />;
