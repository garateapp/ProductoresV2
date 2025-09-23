import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import Select from 'react-select';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';

export default function Create({ auth, producers }) {
  const { data, setData, post, processing, errors } = useForm({
    user_id: '',
    contract_file: null,
    fecha_contrato: '',
    vencimiento: '',
    comision: '',
    flete_a_huerto: '',
    rebate: false,
    bonificacion: false,
    tarifa_premium: false,
    comparativa: '',
    descuento_fruta_comercial: false,
  });

  const producerOptions = (producers || [])
    .filter(user => user.idprod !== null)
    .reduce((acc, user) => {
      if (!acc.some(option => option.rut === user.rut)) {
        acc.push({ value: user.id, label: user.name, rut: user.rut });
      }
      return acc;
    }, []);

  const fleteOptions = [
    { value: 'NO', label: 'NO' },
    { value: '50%', label: '50%' },
    { value: '100%', label: '100%' },
  ];

  const submit = (e) => {
    e.preventDefault();
    post(route('contracts.store'));
  };

  const inputClass = (hasError) => [
    'mt-1 block w-full rounded-md border shadow-sm focus:ring-opacity-50',
    hasError
      ? 'border-red-500 focus:border-red-500 focus:ring-red-200'
      : 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200',
  ].join(' ');

  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Crear Contrato</h2>}>
      <Head title="Crear Contrato" />
      <div className="py-12">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <Label htmlFor="user_id">Productor</Label>
                  <Select
                    id="user_id"
                    name="user_id"
                    options={producerOptions}
                    value={producerOptions.find(option => option.value === data.user_id) || null}
                    onChange={(opt) => setData('user_id', opt ? opt.value : '')}
                    classNamePrefix="react-select"
                    placeholder="Seleccione un productor"
                    isClearable
                    aria-invalid={!!errors.user_id}
                    styles={{
                      control: (base, state) => ({
                        ...base,
                        borderColor: errors.user_id ? '#ef4444' : base.borderColor,
                        boxShadow: errors.user_id ? '0 0 0 1px rgba(239,68,68,0.2)' : base.boxShadow,
                        '&:hover': { borderColor: errors.user_id ? '#ef4444' : base.borderColor },
                      }),
                    }}
                  />
                  {errors.user_id && <div className="text-red-600 text-sm mt-1">{errors.user_id}</div>}
                </div>

                <div className="md:col-span-2">
                  <Label htmlFor="contract_file">Archivo de Contrato</Label>
                  <input
                    type="file"
                    id="contract_file"
                    name="contract_file"
                    onChange={(e) => setData('contract_file', e.target.files[0])}
                    className={inputClass(!!errors.contract_file) + ' text-sm text-gray-900 cursor-pointer bg-gray-50 focus:outline-none'}
                    aria-invalid={!!errors.contract_file}
                  />
                  {errors.contract_file && <div className="text-red-600 text-sm mt-1">{errors.contract_file}</div>}
                </div>

                <div>
                  <Label htmlFor="fecha_contrato">Fecha de Contrato</Label>
                  <input
                    type="date"
                    id="fecha_contrato"
                    name="fecha_contrato"
                    value={data.fecha_contrato}
                    onChange={(e) => setData('fecha_contrato', e.target.value)}
                    className={inputClass(!!errors.fecha_contrato)}
                    aria-invalid={!!errors.fecha_contrato}
                  />
                  {errors.fecha_contrato && <div className="text-red-600 text-sm mt-1">{errors.fecha_contrato}</div>}
                </div>

                <div>
                  <Label htmlFor="vencimiento">Fecha de Vencimiento</Label>
                  <input
                    type="date"
                    id="vencimiento"
                    name="vencimiento"
                    value={data.vencimiento}
                    onChange={(e) => setData('vencimiento', e.target.value)}
                    className={inputClass(!!errors.vencimiento)}
                    aria-invalid={!!errors.vencimiento}
                  />
                  {errors.vencimiento && <div className="text-red-600 text-sm mt-1">{errors.vencimiento}</div>}
                </div>

                <div>
                  <Label htmlFor="comision">Comisión</Label>
                  <input
                    type="number"
                    id="comision"
                    name="comision"
                    value={data.comision}
                    onChange={(e) => setData('comision', e.target.value)}
                    className={inputClass(!!errors.comision)}
                    aria-invalid={!!errors.comision}
                  />
                  {errors.comision && <div className="text-red-600 text-sm mt-1">{errors.comision}</div>}
                </div>

                <div>
                  <Label htmlFor="flete_a_huerto">Flete a Huerto</Label>
                  <Select
                    id="flete_a_huerto"
                    name="flete_a_huerto"
                    options={fleteOptions}
                    value={fleteOptions.find(option => option.value === data.flete_a_huerto) || null}
                    onChange={(opt) => setData('flete_a_huerto', opt ? opt.value : '')}
                    classNamePrefix="react-select"
                    placeholder="Seleccione una opción"
                    aria-invalid={!!errors.flete_a_huerto}
                    styles={{
                      control: (base, state) => ({
                        ...base,
                        borderColor: errors.flete_a_huerto ? '#ef4444' : base.borderColor,
                        boxShadow: errors.flete_a_huerto ? '0 0 0 1px rgba(239,68,68,0.2)' : base.boxShadow,
                        '&:hover': { borderColor: errors.flete_a_huerto ? '#ef4444' : base.borderColor },
                      }),
                    }}
                  />
                  {errors.flete_a_huerto && <div className="text-red-600 text-sm mt-1">{errors.flete_a_huerto}</div>}
                </div>

                <div className="flex items-center gap-3">
                  <Switch id="rebate" checked={data.rebate} onCheckedChange={(v)=>setData('rebate', !!v)} />
                  <Label htmlFor="rebate">Rebate</Label>
                  {errors.rebate && <div className="text-red-600 text-sm mt-1">{errors.rebate}</div>}
                </div>

                <div className="flex items-center gap-3">
                  <Switch id="bonificacion" checked={data.bonificacion} onCheckedChange={(v)=>setData('bonificacion', !!v)} />
                  <Label htmlFor="bonificacion">Bonificación</Label>
                  {errors.bonificacion && <div className="text-red-600 text-sm mt-1">{errors.bonificacion}</div>}
                </div>

                <div className="flex items-center gap-3">
                  <Switch id="tarifa_premium" checked={data.tarifa_premium} onCheckedChange={(v)=>setData('tarifa_premium', !!v)} />
                  <Label htmlFor="tarifa_premium">Tarifa Premium</Label>
                  {errors.tarifa_premium && <div className="text-red-600 text-sm mt-1">{errors.tarifa_premium}</div>}
                </div>

                <div className="md:col-span-2">
                  <Label htmlFor="comparativa">Comparativa</Label>
                  <textarea
                    id="comparativa"
                    name="comparativa"
                    value={data.comparativa}
                    onChange={(e) => setData('comparativa', e.target.value)}
                    rows="3"
                    className={inputClass(!!errors.comparativa)}
                    aria-invalid={!!errors.comparativa}
                  ></textarea>
                  {errors.comparativa && <div className="text-red-600 text-sm mt-1">{errors.comparativa}</div>}
                </div>

                <div className="flex items-center gap-3">
                  <Switch id="descuento_fruta_comercial" checked={data.descuento_fruta_comercial} onCheckedChange={(v)=>setData('descuento_fruta_comercial', !!v)} />
                  <Label htmlFor="descuento_fruta_comercial">Descuento Fruta Comercial</Label>
                  {errors.descuento_fruta_comercial && <div className="text-red-600 text-sm mt-1">{errors.descuento_fruta_comercial}</div>}
                </div>

                <div className="md:col-span-2 sticky bottom-0 bg-white/80 backdrop-blur border-t py-3 flex justify-between px-2 mt-2">
                  <Link href={route('contracts.index')} className="inline-flex items-center px-4 py-2 border rounded-md">Volver</Link>
                  <button type="submit" className="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-md" disabled={processing}>Guardar Contrato</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
