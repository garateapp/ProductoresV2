import React, { useState } from 'react';
import { useForm, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Switch } from '@/Components/ui/switch';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import TelefonoManager from '@/Components/TelefonoManager';
import AgronomistManager from '@/Components/AgronomistManager';

export default function Edit({ producer }) {
  const { data, setData, put, errors } = useForm({
    name: producer.name || '',
    email: producer.email || '',
    rut: producer.rut || '',
    user: producer.user || '',
    idprod: producer.idprod || '',
    csg: producer.csg || '',
    emnotification: producer.emnotification || false,
    is_active: typeof producer.is_active === 'boolean' ? producer.is_active : true,
    kilos_netos: producer.kilos_netos || '',
    comercial: producer.comercial || '',
    desecho: producer.desecho || '',
    merma: producer.merma || '',
    exp: producer.exp || '',
    predio: producer.predio || '',
    comuna: producer.comuna || '',
    provincia: producer.provincia || '',
    direccion: producer.direccion || '',
    antiguedad: producer.antiguedad || '',
    fitosanitario: producer.fitosanitario || '',
    certificaciones: producer.certificaciones || '',
    status: producer.status || '',
    enviomasivo: producer.enviomasivo || false,
  });

  function submit(e) {
    e.preventDefault();
    put(route('producers.update', producer.id));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Editar Productor</CardTitle>
        </CardHeader>
        <CardContent>
          <Tabs defaultValue="general">
            <TabsList>
              <TabsTrigger value="general">Información General</TabsTrigger>
              <TabsTrigger value="telefonos">Teléfonos</TabsTrigger>
              <TabsTrigger value="agronomists">Agrónomos</TabsTrigger>
            </TabsList>
            <TabsContent value="general">
              <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                  <Label htmlFor="name">Nombre</Label>
                  <Input
                    id="name"
                    type="text"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                  />
                  {errors.name && <div className="text-red-500 text-sm">{errors.name}</div>}
                </div>
                <div>
                  <Label htmlFor="email">Correo electrónico</Label>
                  <Input
                    id="email"
                    type="email"
                    value={data.email}
                    onChange={(e) => setData('email', e.target.value)}
                  />
                  {errors.email && <div className="text-red-500 text-sm">{errors.email}</div>}
                </div>
                <div>
                  <Label htmlFor="rut">RUT</Label>
                  <Input
                    id="rut"
                    type="text"
                    value={data.rut}
                    onChange={(e) => setData('rut', e.target.value)}
                  />
                  {errors.rut && <div className="text-red-500 text-sm">{errors.rut}</div>}
                </div>
                <div>
                  <Label htmlFor="user">Usuario</Label>
                  <Input
                    id="user"
                    type="text"
                    value={data.user}
                    onChange={(e) => setData('user', e.target.value)}
                  />
                  {errors.user && <div className="text-red-500 text-sm">{errors.user}</div>}
                </div>
                <div>
                  <Label htmlFor="idprod">ID Productor</Label>
                  <Input
                    id="idprod"
                    type="text"
                    value={data.idprod}
                    onChange={(e) => setData('idprod', e.target.value)}
                  />
                  {errors.idprod && <div className="text-red-500 text-sm">{errors.idprod}</div>}
                </div>
                <div>
                  <Label htmlFor="csg">CSG</Label>
                  <Input
                    id="csg"
                    type="text"
                    value={data.csg}
                    onChange={(e) => setData('csg', e.target.value)}
                  />
                  {errors.csg && <div className="text-red-500 text-sm">{errors.csg}</div>}
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="emnotification" checked={data.emnotification} onCheckedChange={(v) => setData('emnotification', !!v)} />
                  <Label htmlFor="emnotification">Notificación por email</Label>
                  {errors.emnotification && <div className="text-red-500 text-sm">{errors.emnotification}</div>}
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', !!v)} />
                  <Label htmlFor="is_active">Activo</Label>
                  {errors.is_active && <div className="text-red-500 text-sm">{errors.is_active}</div>}
                </div>
                <div className="flex items-center space-x-2">
                  <Input
                    id="is_active"
                    type="checkbox"
                    checked={data.is_active}
                    onChange={(e) => setData('is_active', e.target.checked)}
                    className="h-4 w-4"
                  />
                  <Label htmlFor="is_active">Activo</Label>
                  {errors.is_active && <div className="text-red-500 text-sm">{errors.is_active}</div>}
                </div>
                <div>
                  <Label htmlFor="kilos_netos">Kilos Netos</Label>
                  <Input
                    id="kilos_netos"
                    type="number"
                    value={data.kilos_netos}
                    onChange={(e) => setData('kilos_netos', e.target.value)}
                  />
                  {errors.kilos_netos && <div className="text-red-500 text-sm">{errors.kilos_netos}</div>}
                </div>
                <div>
                  <Label htmlFor="comercial">Comercial</Label>
                  <Input
                    id="comercial"
                    type="number"
                    value={data.comercial}
                    onChange={(e) => setData('comercial', e.target.value)}
                  />
                  {errors.comercial && <div className="text-red-500 text-sm">{errors.comercial}</div>}
                </div>
                <div>
                  <Label htmlFor="desecho">Desecho</Label>
                  <Input
                    id="desecho"
                    type="number"
                    value={data.desecho}
                    onChange={(e) => setData('desecho', e.target.value)}
                  />
                  {errors.desecho && <div className="text-red-500 text-sm">{errors.desecho}</div>}
                </div>
                <div>
                  <Label htmlFor="merma">Merma</Label>
                  <Input
                    id="merma"
                    type="number"
                    value={data.merma}
                    onChange={(e) => setData('merma', e.target.value)}
                  />
                  {errors.merma && <div className="text-red-500 text-sm">{errors.merma}</div>}
                </div>
                <div>
                  <Label htmlFor="exp">Exp</Label>
                  <Input
                    id="exp"
                    type="number"
                    value={data.exp}
                    onChange={(e) => setData('exp', e.target.value)}
                  />
                  {errors.exp && <div className="text-red-500 text-sm">{errors.exp}</div>}
                </div>
                <div>
                  <Label htmlFor="predio">Predio</Label>
                  <Input
                    id="predio"
                    type="text"
                    value={data.predio}
                    onChange={(e) => setData('predio', e.target.value)}
                  />
                  {errors.predio && <div className="text-red-500 text-sm">{errors.predio}</div>}
                </div>
                <div>
                  <Label htmlFor="comuna">Comuna</Label>
                  <Input
                    id="comuna"
                    type="text"
                    value={data.comuna}
                    onChange={(e) => setData('comuna', e.target.value)}
                  />
                  {errors.comuna && <div className="text-red-500 text-sm">{errors.comuna}</div>}
                </div>
                <div>
                  <Label htmlFor="provincia">Provincia</Label>
                  <Input
                    id="provincia"
                    type="text"
                    value={data.provincia}
                    onChange={(e) => setData('provincia', e.target.value)}
                  />
                  {errors.provincia && <div className="text-red-500 text-sm">{errors.provincia}</div>}
                </div>
                <div>
                  <Label htmlFor="direccion">Dirección</Label>
                  <Input
                    id="direccion"
                    type="text"
                    value={data.direccion}
                    onChange={(e) => setData('direccion', e.target.value)}
                  />
                  {errors.direccion && <div className="text-red-500 text-sm">{errors.direccion}</div>}
                </div>
                <div>
                  <Label htmlFor="antiguedad">Antigüedad</Label>
                  <Input
                    id="antiguedad"
                    type="number"
                    value={data.antiguedad}
                    onChange={(e) => setData('antiguedad', e.target.value)}
                  />
                  {errors.antiguedad && <div className="text-red-500 text-sm">{errors.antiguedad}</div>}
                </div>
                <div>
                  <Label htmlFor="fitosanitario">Fitosanitario</Label>
                  <Input
                    id="fitosanitario"
                    type="text"
                    value={data.fitosanitario}
                    onChange={(e) => setData('fitosanitario', e.target.value)}
                  />
                  {errors.fitosanitario && <div className="text-red-500 text-sm">{errors.fitosanitario}</div>}
                </div>
                <div>
                  <Label htmlFor="certificaciones">Certificaciones</Label>
                  <Input
                    id="certificaciones"
                    type="text"
                    value={data.certificaciones}
                    onChange={(e) => setData('certificaciones', e.target.value)}
                  />
                  {errors.certificaciones && <div className="text-red-500 text-sm">{errors.certificaciones}</div>}
                </div>
                <div>
                  <Label htmlFor="status">Estado</Label>
                  <Input
                    id="status"
                    type="text"
                    value={data.status}
                    onChange={(e) => setData('status', e.target.value)}
                  />
                  {errors.status && <div className="text-red-500 text-sm">{errors.status}</div>}
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="enviomasivo" checked={data.enviomasivo} onCheckedChange={(v) => setData('enviomasivo', !!v)} />
                  <Label htmlFor="enviomasivo">Envío masivo</Label>
                  {errors.enviomasivo && <div className="text-red-500 text-sm">{errors.enviomasivo}</div>}
                </div>
                <div className="md:col-span-2 sticky bottom-0 bg-white/80 backdrop-blur border-t py-3 flex justify-between px-2">
                  <Link href={route('producers.index')}>
                    <Button type="button" variant="outline">Volver</Button>
                  </Link>
                  <Button type="submit">Actualizar</Button>
                </div>
              </form>
            </TabsContent>
            <TabsContent value="telefonos">
              <TelefonoManager producer={producer} />
            </TabsContent>
            <TabsContent value="agronomists">
              <AgronomistManager producer={producer} />
            </TabsContent>
          </Tabs>
        </CardContent>
      </Card>
    </div>
  );
}

Edit.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Productor</h2>} />;
