import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import Combobox from '@/Components/ui/combobox';

export default function Edit({ auth, estimate, especies, producers }) {
  const [agronomists, setAgronomists] = useState([]);
  const { data, setData, put, processing, errors } = useForm({
    user_id: estimate.user_id,
    agronomist_id: estimate.agronomist_id || '',
    especie_id: estimate.especie_id,
    variedad_id: estimate.variedad_id || '',
    season_code: estimate.season_code,
    iso_year: estimate.iso_year,
    iso_week: estimate.iso_week,
    predio: estimate.predio || '',
    block: estimate.block || '',
    estimated_kilos: estimate.estimated_kilos,
    estimated_bins: estimate.estimated_bins || '',
    estimated_boxes: estimate.estimated_boxes || '',
    confidence_pct: estimate.confidence_pct || '',
    status: estimate.status,
    source: estimate.source || 'manual',
    notes: estimate.notes || '',
    acopio: estimate.acopio ? '1' : (estimate.acopio === false ? '0' : ''),
    radio_mosca: estimate.radio_mosca ? '1' : (estimate.radio_mosca === false ? '0' : ''),
    corea_greenex: estimate.corea_greenex ? '1' : (estimate.corea_greenex === false ? '0' : ''),
    tipo_cereza: estimate.tipo_cereza || '',
  });

  const variedades = especies.find(e => e.id == data.especie_id)?.variedads || [];

  useEffect(() => {
    if (data.user_id) {
      axios.get(route('api.producer-agronomists', data.user_id))
        .then(res => setAgronomists(res.data))
        .catch(() => setAgronomists([]));
    }
  }, [data.user_id]);

  const submit = (e) => {
    e.preventDefault();
    put(route('weekly-harvest-estimates.update', estimate.id));
  }

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Estimación</h2>}>
      <Head title="Editar Estimación" />
      <div className="py-8 max-w-4xl mx-auto">
        <form onSubmit={submit} className="bg-white p-6 rounded shadow">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
              <label className="block text-sm font-medium mb-1">Productor</label>
              <Combobox
                value={data.user_id}
                onChange={(val)=>setData('user_id', val)}
                options={producers.map(p=>({value:String(p.id), label:p.name}))}
                placeholder="Seleccione productor..."
                searchPlaceholder="Buscar productor..."
                className="w-full"
              />
              {errors.user_id && <p className="text-red-600 text-sm">{errors.user_id}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Agrónomo</label>
              <Combobox
                value={data.agronomist_id}
                onChange={(val)=>setData('agronomist_id', val)}
                options={agronomists.map(a=>({value:String(a.id), label:a.name}))}
                placeholder="(Opcional) Seleccione agrónomo..."
                searchPlaceholder="Buscar agrónomo..."
                className="w-full"
                disabled={!agronomists.length}
              />
              {errors.agronomist_id && <p className="text-red-600 text-sm">{errors.agronomist_id}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Especie</label>
              <Combobox
                value={data.especie_id}
                onChange={(val)=>{ setData('especie_id', val); setData('variedad_id',''); }}
                options={especies.map(e=>({value:String(e.id), label:e.name}))}
                placeholder="Seleccione especie..."
                searchPlaceholder="Buscar especie..."
                className="w-full"
              />
              {errors.especie_id && <p className="text-red-600 text-sm">{errors.especie_id}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Variedad</label>
              <Combobox
                value={data.variedad_id}
                onChange={(val)=>setData('variedad_id', val)}
                options={variedades.map(v=>({value:String(v.id), label:v.name}))}
                placeholder="(Opcional) Seleccione variedad..."
                searchPlaceholder="Buscar variedad..."
                className="w-full"
                disabled={!data.especie_id}
              />
              {errors.variedad_id && <p className="text-red-600 text-sm">{errors.variedad_id}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Acopio</label>
              <Combobox
                value={data.acopio}
                onChange={(val)=>setData('acopio', val)}
                options={[{value:'1', label:'Sí'},{value:'0', label:'No'}]}
                placeholder="Seleccione..."
                className="w-full"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Radio Mosca</label>
              <Combobox
                value={data.radio_mosca}
                onChange={(val)=>setData('radio_mosca', val)}
                options={[{value:'1', label:'Sí'},{value:'0', label:'No'}]}
                placeholder="Seleccione..."
                className="w-full"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Corea Greenex</label>
              <Combobox
                value={data.corea_greenex}
                onChange={(val)=>setData('corea_greenex', val)}
                options={[{value:'1', label:'Sí'},{value:'0', label:'No'}]}
                placeholder="Seleccione..."
                className="w-full"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Tipo Cereza</label>
              <input className="border rounded w-full px-2 py-2" value={data.tipo_cereza} onChange={(e)=>setData('tipo_cereza', e.target.value)} placeholder="ROJA / ..." />
            </div>
            <div>
              <label className="block text-sm font-medium">Temporada</label>
              <input className="border rounded w-full px-2 py-2" value={data.season_code} onChange={e=>setData('season_code', e.target.value)} />
              {errors.season_code && <p className="text-red-600 text-sm">{errors.season_code}</p>}
            </div>
            <div className="grid grid-cols-2 gap-2">
              <div>
                <label className="block text-sm font-medium">Año ISO</label>
                <input type="number" className="border rounded w-full px-2 py-2" value={data.iso_year} onChange={e=>setData('iso_year', e.target.value)} />
                {errors.iso_year && <p className="text-red-600 text-sm">{errors.iso_year}</p>}
              </div>
              <div>
                <label className="block text-sm font-medium">Semana ISO</label>
                <input type="number" className="border rounded w-full px-2 py-2" value={data.iso_week} onChange={e=>setData('iso_week', e.target.value)} />
                {errors.iso_week && <p className="text-red-600 text-sm">{errors.iso_week}</p>}
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium">Predio</label>
              <input className="border rounded w-full px-2 py-2" value={data.predio} onChange={e=>setData('predio', e.target.value)} />
              {errors.predio && <p className="text-red-600 text-sm">{errors.predio}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Cuartel/Block</label>
              <input className="border rounded w-full px-2 py-2" value={data.block} onChange={e=>setData('block', e.target.value)} />
              {errors.block && <p className="text-red-600 text-sm">{errors.block}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Kilos Estimados</label>
              <input type="number" step="0.01" className="border rounded w-full px-2 py-2" value={data.estimated_kilos} onChange={e=>setData('estimated_kilos', e.target.value)} />
              {errors.estimated_kilos && <p className="text-red-600 text-sm">{errors.estimated_kilos}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Bins Estimados</label>
              <input type="number" step="0.01" className="border rounded w-full px-2 py-2" value={data.estimated_bins} onChange={e=>setData('estimated_bins', e.target.value)} />
              {errors.estimated_bins && <p className="text-red-600 text-sm">{errors.estimated_bins}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Cajas Estimadas</label>
              <input type="number" className="border rounded w-full px-2 py-2" value={data.estimated_boxes} onChange={e=>setData('estimated_boxes', e.target.value)} />
              {errors.estimated_boxes && <p className="text-red-600 text-sm">{errors.estimated_boxes}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Confianza (%)</label>
              <input type="number" className="border rounded w-full px-2 py-2" value={data.confidence_pct} onChange={e=>setData('confidence_pct', e.target.value)} />
              {errors.confidence_pct && <p className="text-red-600 text-sm">{errors.confidence_pct}</p>}
            </div>
            <div>
              <label className="block text-sm font-medium">Estado</label>
              <select className="border rounded w-full px-2 py-2" value={data.status} onChange={e=>setData('status', e.target.value)}>
                <option value="draft">Borrador</option>
                <option value="confirmed">Confirmado</option>
                <option value="final">Final</option>
              </select>
              {errors.status && <p className="text-red-600 text-sm">{errors.status}</p>}
            </div>
            <div className="md:col-span-2">
              <label className="block text-sm font-medium">Notas</label>
              <textarea className="border rounded w-full px-2 py-2" rows="3" value={data.notes} onChange={e=>setData('notes', e.target.value)} />
              {errors.notes && <p className="text-red-600 text-sm">{errors.notes}</p>}
            </div>
          </div>
          <div className="mt-6 flex gap-2">
            <button type="submit" disabled={processing} className="bg-indigo-600 text-white px-4 py-2 rounded">Actualizar</button>
          </div>
        </form>
      </div>
    </AuthenticatedLayout>
  )
}
