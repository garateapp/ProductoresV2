import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ profiles }) {
  const { data, setData, post, processing } = useForm({
    profile_id: '',
    payload: '[]',
  });

  function submit(e) {
    e.preventDefault();
    let payload;
    try {
      payload = JSON.parse(data.payload);
    } catch {
      return;
    }
    post(route('integrations.simulator.preview'), {
      data: { profile_id: data.profile_id, payload },
    });
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader><CardTitle>Simulador de Transformación</CardTitle></CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label>Perfil</Label>
              <Select value={data.profile_id} onValueChange={v => setData('profile_id', v)}>
                <SelectTrigger><SelectValue placeholder="Seleccionar perfil..." /></SelectTrigger>
                <SelectContent>
                  {profiles.map(p => <SelectItem key={p.id} value={String(p.id)}>{p.codigo} - {p.nombre}</SelectItem>)}
                </SelectContent>
              </Select>
            </div>
            <div>
              <Label>Payload (JSON array)</Label>
              <Textarea
                value={data.payload}
                onChange={e => setData('payload', e.target.value)}
                rows={10}
                className="font-mono text-xs"
                placeholder='[{"campo1": "valor1", "campo2": "valor2"}]'
              />
            </div>
            <Button type="submit" disabled={processing}>Probar</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Simulador</h2>} />;
