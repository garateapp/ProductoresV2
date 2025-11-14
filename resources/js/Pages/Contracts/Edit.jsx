import React, { useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import Select from 'react-select';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';

export default function Edit({ auth, contract, producers }) {
  const { data, setData, put, processing, errors } = useForm({
    user_id: contract.user_id || '',
    contract_file: null,
    fecha_contrato: contract.fecha_contrato || '',
    vencimiento: contract.vencimiento || '',
    comision: contract.comision || '',
    flete_a_huerto: contract.flete_a_huerto || '',
    rebate: !!contract.rebate,
    bonificacion: !!contract.bonificacion,
    tarifa_premium: !!contract.tarifa_premium,
    comparativa: contract.comparativa || '',
    descuento_fruta_comercial: !!contract.descuento_fruta_comercial,
    aplica_descuento_hidrocooler: contract.descuento_hidrocooler !== null && contract.descuento_hidrocooler !== undefined,
    descuento_hidrocooler: contract.descuento_hidrocooler ?? '',
    porcentaje_descuento_fruta_comercial: contract.porcentaje_descuento_fruta_comercial ?? '',
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
    { value: '0.014', label: '0.014' },
    { value: '0.017', label: '0.017' },
    { value: '0.018', label: '0.018' },
    { value: '0.019', label: '0.019' },
    { value: '0.025', label: '0.025' },
    { value: '0.028', label: '0.028' },
    { value: '0.049', label: '0.049' },
    { value: '0.053', label: '0.053' },
    { value: '0.058', label: '0.058' },
     { value: '0.30', label: '0.30' },
  ];

  const submit = (e) => {
    e.preventDefault();
    put(route('contracts.update', contract.id));
  };

  const inputClass = (hasError) => [
    'mt-1 block w-full rounded-md border shadow-sm focus:ring-opacity-50',
    hasError ? 'border-red-500 focus:border-red-500 focus:ring-red-200' : 'border-gray-300 focus:border-indigo-300 focus:ring focus:ring-indigo-200',
  ].join(' ');

  useEffect(() => {
    if (!data.descuento_fruta_comercial) {
      setData('porcentaje_descuento_fruta_comercial', '');
    }
  }, [data.descuento_fruta_comercial]);

  useEffect(() => {
    if (!data.aplica_descuento_hidrocooler) {
      setData('descuento_hidrocooler', '');
    }
  }, [data.aplica_descuento_hidrocooler]);

  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Contrato</h2>}>
      <Head title="Editar Contrato" />
      <div className="py-12">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <form onSubmit={submit} className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div className="md:col-span-2">
                  <Label>Productor</Label>
                  <Select
                    options={producerOptions}
                    value={producerOptions.find(o => o.value === data.user_id) || null}
                    isClearable={false}
                    isDisabled={true}
                    placeholder="No editable"
                    styles={{ control: (base)=>({ ...base, backgroundColor:'#f5f5f5' }) }}
                  />
                </div>

                <div>
                  <Label>Fecha de Contrato</Label>
                  <input type="date" value={data.fecha_contrato} onChange={(e)=>setData('fecha_contrato', e.target.value)} className={inputClass(!!errors.fecha_contrato)} aria-invalid={!!errors.fecha_contrato} />
                  {errors.fecha_contrato && <div className="text-red-600 text-sm mt-1">{errors.fecha_contrato}</div>}
                </div>
                <div>
                  <Label>Fecha de Vencimiento</Label>
                  <input type="date" value={data.vencimiento} onChange={(e)=>setData('vencimiento', e.target.value)} className={inputClass(!!errors.vencimiento)} aria-invalid={!!errors.vencimiento} />
                  {errors.vencimiento && <div className="text-red-600 text-sm mt-1">{errors.vencimiento}</div>}
                </div>

                <div>
                  <Label>Comisión</Label>
                  <input type="number" step="0.01" value={data.comision} onChange={(e)=>setData('comision', e.target.value)} className={inputClass(!!errors.comision)} aria-invalid={!!errors.comision} />
                  {errors.comision && <div className="text-red-600 text-sm mt-1">{errors.comision}</div>}
                </div>
                <div>
                  <Label>Flete a Huerto</Label>
                  <Select
                    options={fleteOptions}
                    value={fleteOptions.find(o => o.value === data.flete_a_huerto) || null}
                    onChange={(opt)=>setData('flete_a_huerto', opt ? opt.value : '')}
                    placeholder="Seleccione flete"
                    aria-invalid={!!errors.flete_a_huerto}
                    styles={{ control: (base)=>({ ...base, borderColor: errors.flete_a_huerto ? '#ef4444' : base.borderColor }) }}
                  />
                  {errors.flete_a_huerto && <div className="text-red-600 text-sm mt-1">{errors.flete_a_huerto}</div>}
                </div>

                <div className="md:col-span-2">
                  <Label>Archivo de Contrato (opcional)</Label>
                  <input type="file" onChange={(e)=>setData('contract_file', e.target.files[0])} className={inputClass(!!errors.contract_file) + ' text-sm'} aria-invalid={!!errors.contract_file} />
                  {errors.contract_file && <div className="text-red-600 text-sm mt-1">{errors.contract_file}</div>}
                </div>

                <div className="flex items-center gap-3">
                  <Switch id="rebate" checked={data.rebate} onCheckedChange={(v)=>setData('rebate', !!v)} />
                  <Label htmlFor="rebate">Rebate</Label>
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="bonificacion" checked={data.bonificacion} onCheckedChange={(v)=>setData('bonificacion', !!v)} />
                  <Label htmlFor="bonificacion">Bonificación</Label>
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="tarifa_premium" checked={data.tarifa_premium} onCheckedChange={(v)=>setData('tarifa_premium', !!v)} />
                  <Label htmlFor="tarifa_premium">Tarifa Premium</Label>
                </div>
                <div className="flex items-center gap-3">
                  <Switch id="descuento_fruta_comercial" checked={data.descuento_fruta_comercial} onCheckedChange={(v)=>setData('descuento_fruta_comercial', !!v)} />
                  <Label htmlFor="descuento_fruta_comercial">Descuento Fruta Comercial</Label>
                </div>

                <div className="flex items-center gap-3">
                  <Switch
                    id="aplica_descuento_hidrocooler"
                    checked={data.aplica_descuento_hidrocooler}
                    onCheckedChange={(v)=>setData('aplica_descuento_hidrocooler', !!v)}
                  />
                  <Label htmlFor="aplica_descuento_hidrocooler">Descuento por Hidrocooler</Label>
                </div>



                {data.descuento_fruta_comercial && (
                  <div>
                    <Label htmlFor="porcentaje_descuento_fruta_comercial">Porcentaje Descuento Fruta Comercial (%)</Label>
                    <input
                      id="porcentaje_descuento_fruta_comercial"
                      name="porcentaje_descuento_fruta_comercial"
                      type="number"
                      min="0"
                      max="100"
                      step="0.01"
                      value={data.porcentaje_descuento_fruta_comercial}
                      onChange={(e)=>setData('porcentaje_descuento_fruta_comercial', e.target.value)}
                      className={inputClass(!!errors.porcentaje_descuento_fruta_comercial)}
                      aria-invalid={!!errors.porcentaje_descuento_fruta_comercial}
                    />
                    {errors.porcentaje_descuento_fruta_comercial && <div className="text-red-600 text-sm mt-1">{errors.porcentaje_descuento_fruta_comercial}</div>}
                  </div>
                )}
                  {data.aplica_descuento_hidrocooler && (
                  <div>
                    <Label htmlFor="descuento_hidrocooler">Monto Descuento Hidrocooler</Label>
                    <input
                      id="descuento_hidrocooler"
                      name="descuento_hidrocooler"
                      type="number"
                      min="0"
                      step="0.01"
                      value={data.descuento_hidrocooler}
                      onChange={(e)=>setData('descuento_hidrocooler', e.target.value)}
                      className={inputClass(!!errors.descuento_hidrocooler)}
                      aria-invalid={!!errors.descuento_hidrocooler}
                    />
                    {errors.descuento_hidrocooler && <div className="text-red-600 text-sm mt-1">{errors.descuento_hidrocooler}</div>}
                  </div>
                )}

                <div className="md:col-span-2">
                  <Label>Comparativa</Label>
                  <textarea rows={3} value={data.comparativa} onChange={(e)=>setData('comparativa', e.target.value)} className={inputClass(!!errors.comparativa)} aria-invalid={!!errors.comparativa} />
                  {errors.comparativa && <div className="text-red-600 text-sm mt-1">{errors.comparativa}</div>}
                </div>

                <div className="md:col-span-2 sticky bottom-0 bg-white/80 backdrop-blur border-t py-3 flex justify-between px-2 mt-2">
                  <Link href={route('contracts.index')} className="inline-flex items-center px-4 py-2 border rounded-md">Volver</Link>
                  <button type="submit" disabled={processing} className="inline-flex items-center px-4 py-2 bg-gray-800 text-white rounded-md">Actualizar</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
