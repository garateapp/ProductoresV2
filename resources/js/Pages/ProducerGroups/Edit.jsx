import React, { useMemo } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ auth, group, producers }) {
  const currentIds = useMemo(() => new Set(group.producers.map(p => p.id)), [group]);
  const { data, setData, put, processing, errors } = useForm({
    name: group.name,
    description: group.description || '',
    producer_ids: group.producers.map(p => p.id),
  });

  const toggleProducer = (id) => {
    const set = new Set(data.producer_ids);
    if (set.has(id)) set.delete(id); else set.add(id);
    setData('producer_ids', Array.from(set));
  };

  const submit = (e) => {
    e.preventDefault();
    put(route('producer-groups.update', group.id));
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Grupo</h2>}>
      <Head title="Editar Grupo" />
      <div className="max-w-5xl mx-auto py-8">
        <form onSubmit={submit} className="bg-white p-4 rounded shadow mb-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium">Nombre</label>
              <input className="border rounded w-full px-2 py-2" value={data.name} onChange={(e)=>setData('name', e.target.value)} />
              {errors.name && <div className="text-red-600 text-sm">{errors.name}</div>}
            </div>
            <div>
              <label className="block text-sm font-medium">Descripción</label>
              <input className="border rounded w-full px-2 py-2" value={data.description} onChange={(e)=>setData('description', e.target.value)} />
            </div>
          </div>
          <div className="mt-4">
            <h3 className="font-semibold mb-2">Asignar Productores</h3>
            <div className="max-h-64 overflow-auto border rounded p-2 grid grid-cols-1 md:grid-cols-2 gap-x-6">
              {producers.map(p => (
                <label key={p.id} className="flex items-center gap-2 py-1">
                  <input type="checkbox" checked={data.producer_ids.includes(p.id)} onChange={()=>toggleProducer(p.id)} />
                  <span>{p.name}</span>
                </label>
              ))}
            </div>
            {errors.producer_ids && <div className="text-red-600 text-sm">{errors.producer_ids}</div>}
          </div>
          <div className="mt-4 flex gap-2">
            <button type="submit" disabled={processing} className="bg-indigo-600 text-white px-4 py-2 rounded">Guardar</button>
            <Link href={route('producer-groups.index')} className="px-4 py-2 border rounded">Volver</Link>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  );
}

