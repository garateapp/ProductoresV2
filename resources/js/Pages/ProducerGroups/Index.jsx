import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ auth, groups, producers }) {
  const { data, setData, post, processing, errors, reset } = useForm({ name: '', description: '' });

  const submit = (e) => {
    e.preventDefault();
    post(route('producer-groups.store'), { onSuccess: () => reset() });
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Grupos de Productores</h2>}>
      <Head title="Grupos de Productores" />
      <div className="max-w-5xl mx-auto py-8">
        <div className="bg-white p-4 rounded shadow mb-6">
          <h3 className="font-semibold mb-3">Crear Grupo</h3>
          <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <input className="border rounded px-2 py-2" placeholder="Nombre del grupo" value={data.name} onChange={(e)=>setData('name', e.target.value)} />
            <input className="border rounded px-2 py-2 md:col-span-2" placeholder="Descripción (opcional)" value={data.description} onChange={(e)=>setData('description', e.target.value)} />
            {errors.name && <div className="text-red-600 text-sm">{errors.name}</div>}
            <div className="md:col-span-3">
              <button type="submit" className="bg-indigo-600 text-white px-4 py-2 rounded" disabled={processing}>Guardar</button>
            </div>
          </form>
        </div>

        <div className="bg-white p-4 rounded shadow">
          <table className="w-full text-left">
            <thead>
              <tr className="border-b">
                <th className="py-2">Grupo</th>
                <th className="py-2">Productores asignados</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              {groups.map(g => (
                <tr key={g.id} className="border-b">
                  <td className="py-2 font-medium">{g.name}</td>
                  <td className="py-2">{g.producers.map(p => p.name).join(', ') || '-'}</td>
                  <td className="py-2 text-right">
                    <Link href={route('producer-groups.edit', g.id)} className="text-indigo-600 hover:underline">Editar</Link>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}

