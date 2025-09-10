import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import InputError from '@/Components/InputError';

export default function Edit({ auth, site, csgUsers }) {
  const [open, setOpen] = useState(false);
  const [search, setSearch] = useState('');
  const [visible, setVisible] = useState(50);
  const { data, setData, put, delete: destroy, processing, errors } = useForm({
    csg_user_id: site.csg_user_id,
    code: site.code || '',
    name: site.name || '',
    address: site.address || '',
    is_active: Boolean(site.is_active),
  });

  useEffect(() => {
    setVisible(50);
  }, [open, search]);

  const submit = (e) => {
    e.preventDefault();
    put(route('sdp-sites.update', site.id));
  };

  const onDelete = () => {
    if (confirm('¿Eliminar este SDP?')) {
      destroy(route('sdp-sites.destroy', site.id));
    }
  };

  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar SDP</h2>}>
      <Head title="Editar SDP" />
      <div className="py-12">
        <div className="max-w-3xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Editar Sitio de Plantación</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="space-y-4">
                <div>
                  <Label>CSG</Label>
                  <Popover open={open} onOpenChange={setOpen}>
                    <PopoverTrigger asChild>
                      <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className="w-full justify-between"
                      >
                        {data.csg_user_id
                          ? `${(csgUsers.find(u => u.id === Number(data.csg_user_id)) || {}).csg || ''} - ${(csgUsers.find(u => u.id === Number(data.csg_user_id)) || {}).name || ''}`
                          : 'Seleccione CSG...'}
                        <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                      </Button>
                    </PopoverTrigger>
                    <PopoverContent className="w-[400px] p-0">
                      <Command>
                        <CommandInput placeholder="Buscar CSG o Productor..." value={search} onValueChange={setSearch} />
                        <CommandEmpty>No se encontró CSG.</CommandEmpty>
                        <CommandGroup className="max-h-72 overflow-auto">
                          {(search ? csgUsers : csgUsers.slice(0, visible)).map(u => (
                            <CommandItem
                              key={u.id}
                              value={`${u.csg} - ${u.name}`}
                              onSelect={() => {
                                setData('csg_user_id', Number(u.id));
                                setOpen(false);
                              }}
                            >
                              <Check className={cn('mr-2 h-4 w-4', Number(data.csg_user_id) === Number(u.id) ? 'opacity-100' : 'opacity-0')} />
                              {u.csg} - {u.name}
                            </CommandItem>
                          ))}
                          {!search && visible < csgUsers.length && (
                            <CommandItem value="__more__" onSelect={() => setVisible(v => v + 50)}>
                              Mostrar más...
                            </CommandItem>
                          )}
                        </CommandGroup>
                      </Command>
                    </PopoverContent>
                  </Popover>
                  <InputError message={errors.csg_user_id} className="mt-2" />
                </div>
                <div>
                  <Label>Código</Label>
                  <Input value={data.code} onChange={e => setData('code', e.target.value)} />
                  <InputError message={errors.code} className="mt-2" />
                </div>
                <div>
                  <Label>Nombre</Label>
                  <Input value={data.name} onChange={e => setData('name', e.target.value)} required />
                  <InputError message={errors.name} className="mt-2" />
                </div>
                <div>
                  <Label>Dirección</Label>
                  <Input value={data.address} onChange={e => setData('address', e.target.value)} />
                  <InputError message={errors.address} className="mt-2" />
                </div>
                <div className="flex items-center gap-2">
                  <input type="checkbox" id="is_active" checked={data.is_active} onChange={e => setData('is_active', e.target.checked)} />
                  <Label htmlFor="is_active">Activo</Label>
                </div>
                <div className="flex gap-2">
                  <Button type="submit" disabled={processing}>Guardar</Button>
                  <Button type="button" variant="destructive" onClick={onDelete}>Eliminar</Button>
                  <Link href={route('sdp-sites.index')}><Button variant="outline" type="button">Volver</Button></Link>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
