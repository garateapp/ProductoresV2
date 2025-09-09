import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Link, Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import Combobox from '@/Components/ui/combobox';

export default function Index({ auth, estimates, especies, producers, filters }) {
  const { data, setData, get } = useForm({
    season_code: filters.season_code || '',
    user_id: filters.user_id || '',
    especie_id: filters.especie_id || '',
    variedad_id: filters.variedad_id || '',
    iso_year: filters.iso_year || '',
    iso_week: filters.iso_week || '',
    status: filters.status || '',
  });

  const importForm = useForm({ file: null, season_code: '', especie_id: '' });

  useEffect(() => {
    const timer = setTimeout(() => {
      get(route('weekly-harvest-estimates.index'), { preserveState: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
  }, [data.season_code, data.user_id, data.especie_id, data.variedad_id, data.iso_year, data.iso_week, data.status]);

  const variedades = especies.find(e => e.id == data.especie_id)?.variedads || [];

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Estimaciones Semanales</h2>}>
      <Head title="Estimaciones Semanales" />
      <div className="container mx-auto py-10">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle className="text-2xl font-bold">Estimaciones</CardTitle>
            <div className="flex gap-2">
              <Link href={route('weekly-harvest-estimates.create')}><Button>Nueva</Button></Link>
            </div>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-6 gap-3 mb-4">
              <Input placeholder="Temporada (T25-26)" value={data.season_code} onChange={e=>setData('season_code', e.target.value)} />
              <Combobox
                value={data.user_id}
                onChange={(val)=>setData('user_id', val)}
                options={[{value:'', label:'(Todos)'}, ...producers.map(p=>({value:String(p.id), label:p.name}))]}
                placeholder="Productor"
                searchPlaceholder="Buscar productor..."
                className="w-48"
              />
              <Combobox
                value={data.especie_id}
                onChange={(val)=>{ setData('especie_id', val); setData('variedad_id',''); }}
                options={[{value:'', label:'(Todas)'}, ...especies.map(e=>({value:String(e.id), label:e.name}))]}
                placeholder="Especie"
                searchPlaceholder="Buscar especie..."
                className="w-48"
              />
              <Combobox
                value={data.variedad_id}
                onChange={(val)=>setData('variedad_id', val)}
                options={[{value:'', label:'(Todas)'}, ...variedades.map(v=>({value:String(v.id), label:v.name}))]}
                placeholder="Variedad"
                searchPlaceholder="Buscar variedad..."
                className="w-48"
                disabled={!data.especie_id}
              />
              <Input type="number" placeholder="Año ISO" value={data.iso_year} onChange={e=>setData('iso_year', e.target.value)} />
              <Input type="number" placeholder="Semana ISO" value={data.iso_week} onChange={e=>setData('iso_week', e.target.value)} />
            </div>

            <form
              onSubmit={(e)=>{e.preventDefault(); importForm.post(route('weekly-harvest-estimates.import'), { forceFormData: true });}}
              encType="multipart/form-data"
              className="mb-6 flex items-end gap-2"
            >
              <div>
                <label className="block text-sm">Excel</label>
                <input type="file" onChange={(e)=>importForm.setData('file', e.target.files[0])} className="border rounded px-2 py-1" />
                {importForm.errors.file && <div className="text-red-600 text-sm">{importForm.errors.file}</div>}
              </div>
              <div>
                <label className="block text-sm">Temporada</label>
                <input value={importForm.data.season_code} onChange={(e)=>importForm.setData('season_code', e.target.value)} className="border rounded px-2 py-1" placeholder="T25-26" />
                {importForm.errors.season_code && <div className="text-red-600 text-sm">{importForm.errors.season_code}</div>}
              </div>
              <div>
                <label className="block text-sm">Especie</label>
                <select value={importForm.data.especie_id} onChange={(e)=>importForm.setData('especie_id', e.target.value)} className="border rounded px-2 py-2">
                  <option value="">Seleccione</option>
                  {especies.map(e => <option key={e.id} value={e.id}>{e.name}</option>)}
                </select>
                {importForm.errors.especie_id && <div className="text-red-600 text-sm">{importForm.errors.especie_id}</div>}
              </div>
              <button type="submit" className="bg-green-600 text-white px-3 py-2 rounded" disabled={importForm.processing}>Importar</button>
            </form>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Productor</TableHead>
                  <TableHead>Agrónomo</TableHead>
                  <TableHead>Especie</TableHead>
                  <TableHead>Variedad</TableHead>
                  <TableHead>Tipo Cereza</TableHead>
                  <TableHead>Acopio</TableHead>
                  <TableHead>Radio Mosca</TableHead>
                  <TableHead>Corea GX</TableHead>
                  <TableHead>Temporada</TableHead>
                  <TableHead>Año-Sem</TableHead>
                  <TableHead>Kilos</TableHead>
                  <TableHead>Estado</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {estimates.data.map((e) => (
                  <TableRow key={e.id}>
                    <TableCell>{e.producer}</TableCell>
                    <TableCell>{e.agronomist || '-'}</TableCell>
                    <TableCell>{e.especie}</TableCell>
                    <TableCell>{e.variedad || '-'}</TableCell>
                    <TableCell>{e.tipo_cereza || '-'}</TableCell>
                    <TableCell>{e.acopio ? 'Sí' : '-'}</TableCell>
                    <TableCell>{e.radio_mosca ? 'Sí' : '-'}</TableCell>
                    <TableCell>{e.corea_greenex ? 'Sí' : '-'}</TableCell>
                    <TableCell>{e.season_code}</TableCell>
                    <TableCell>{e.iso_year}-{e.iso_week}</TableCell>
                    <TableCell>{e.estimated_kilos}</TableCell>
                    <TableCell>{e.status}</TableCell>
                    <TableCell>
                      <Link href={route('weekly-harvest-estimates.edit', e.id)} className="text-indigo-600 hover:underline">Editar</Link>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {/* Pagination minimal */}
            <div className="mt-4 flex gap-2">
              {estimates.links?.map((link, idx) => (
                <Link key={idx} href={link.url || '#'} className={"px-2 " + (link.active ? 'font-bold' : '')} dangerouslySetInnerHTML={{ __html: link.label }} />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
