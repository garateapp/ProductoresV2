import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Create() {
  const { data, setData, post, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    rut: '',
    user: '',
    idprod: '',
    csg: '',
    emnotification: true,
    kilos_netos: '',
    comercial: '',
    desecho: '',
    merma: '',
    exp: '',
    predio: '',
    comuna: '',
    provincia: '',
    direccion: '',
    antiguedad: '',
    fitosanitario: '',
    certificaciones: '',
    status: '',
    enviomasivo: true,
  });

  function submit(e) {
    e.preventDefault();
    post(route('producers.store'));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Create Producer</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label htmlFor="name">Name</Label>
              <Input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
              />
              {errors.name && <div className="text-red-500 text-sm">{errors.name}</div>}
            </div>
            <div>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
              />
              {errors.email && <div className="text-red-500 text-sm">{errors.email}</div>}
            </div>
            <div>
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                value={data.password}
                onChange={(e) => setData('password', e.target.value)}
              />
              {errors.password && <div className="text-red-500 text-sm">{errors.password}</div>}
            </div>
            <div>
              <Label htmlFor="password_confirmation">Confirm Password</Label>
              <Input
                id="password_confirmation"
                type="password"
                value={data.password_confirmation}
                onChange={(e) => setData('password_confirmation', e.target.value)}
              />
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
              <Label htmlFor="user">User</Label>
              <Input
                id="user"
                type="text"
                value={data.user}
                onChange={(e) => setData('user', e.target.value)}
              />
              {errors.user && <div className="text-red-500 text-sm">{errors.user}</div>}
            </div>
            <div>
              <Label htmlFor="idprod">ID Prod</Label>
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
            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="emnotification"
                checked={data.emnotification}
                onChange={(e) => setData('emnotification', e.target.checked)}
              />
              <Label htmlFor="emnotification">Email Notification</Label>
              {errors.emnotification && <div className="text-red-500 text-sm">{errors.emnotification}</div>}
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
              <Label htmlFor="antiguedad">Antiguedad</Label>
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
              <Label htmlFor="status">Status</Label>
              <Input
                id="status"
                type="text"
                value={data.status}
                onChange={(e) => setData('status', e.target.value)}
              />
              {errors.status && <div className="text-red-500 text-sm">{errors.status}</div>}
            </div>
            <div className="flex items-center space-x-2">
              <input
                type="checkbox"
                id="enviomasivo"
                checked={data.enviomasivo}
                onChange={(e) => setData('enviomasivo', e.target.checked)}
              />
              <Label htmlFor="enviomasivo">Envio Masivo</Label>
              {errors.enviomasivo && <div className="text-red-500 text-sm">{errors.enviomasivo}</div>}
            </div>
            <Button type="submit">Create</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Create.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Create Producer</h2>} />;