import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import Select from 'react-select';

export default function Create({ auth, producers }) {
    const { data, setData, post, processing, errors, reset } = useForm({
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

    const producerOptions = producers
        .filter(user => user.idprod !== null) // Ensure only producers are listed
        .reduce((acc, user) => {
            if (!acc.some(option => option.rut === user.rut)) {
                acc.push({
                    value: user.id,
                    label: user.name,
                    rut: user.rut,
                });
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

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Crear Contrato</h2>}
        >
            <Head title="Crear Contrato" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <form onSubmit={submit}>
                                <div className="mb-4">
                                    <label htmlFor="user_id" className="block text-sm font-medium text-gray-700">Productor</label>
                                    <Select
                                        id="user_id"
                                        name="user_id"
                                        options={producerOptions}
                                        value={producerOptions.find(option => option.value === data.user_id)}
                                        onChange={(selectedOption) => setData('user_id', selectedOption ? selectedOption.value : '')}
                                        className="mt-1 block w-full rounded-md shadow-sm"
                                        classNamePrefix="react-select"
                                        placeholder="Seleccione un productor"
                                        isClearable
                                    />
                                    {errors.user_id && <div className="text-red-600 text-sm mt-1">{errors.user_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="contract_file" className="block text-sm font-medium text-gray-700">Archivo de Contrato</label>
                                    <input
                                        type="file"
                                        id="contract_file"
                                        name="contract_file"
                                        onChange={(e) => setData('contract_file', e.target.files[0])}
                                        className="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none"
                                    />
                                    {errors.contract_file && <div className="text-red-600 text-sm mt-1">{errors.contract_file}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="fecha_contrato" className="block text-sm font-medium text-gray-700">Fecha de Contrato</label>
                                    <input
                                        type="date"
                                        id="fecha_contrato"
                                        name="fecha_contrato"
                                        value={data.fecha_contrato}
                                        onChange={(e) => setData('fecha_contrato', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.fecha_contrato && <div className="text-red-600 text-sm mt-1">{errors.fecha_contrato}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="vencimiento" className="block text-sm font-medium text-gray-700">Fecha de Vencimiento</label>
                                    <input
                                        type="date"
                                        id="vencimiento"
                                        name="vencimiento"
                                        value={data.vencimiento}
                                        onChange={(e) => setData('vencimiento', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.vencimiento && <div className="text-red-600 text-sm mt-1">{errors.vencimiento}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="comision" className="block text-sm font-medium text-gray-700">Comisión (%)</label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        id="comision"
                                        name="comision"
                                        value={data.comision}
                                        onChange={(e) => setData('comision', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.comision && <div className="text-red-600 text-sm mt-1">{errors.comision}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="flete_a_huerto" className="block text-sm font-medium text-gray-700">Flete a Huerto</label>
                                    <Select
                                        id="flete_a_huerto"
                                        name="flete_a_huerto"
                                        options={fleteOptions}
                                        value={fleteOptions.find(option => option.value === data.flete_a_huerto)}
                                        onChange={(selectedOption) => setData('flete_a_huerto', selectedOption ? selectedOption.value : '')}
                                        className="mt-1 block w-full rounded-md shadow-sm"
                                        classNamePrefix="react-select"
                                        placeholder="Seleccione una opción"
                                        isClearable
                                    />
                                    {errors.flete_a_huerto && <div className="text-red-600 text-sm mt-1">{errors.flete_a_huerto}</div>}
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="rebate"
                                        name="rebate"
                                        checked={data.rebate}
                                        onChange={(e) => setData('rebate', e.target.checked)}
                                        className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                    />
                                    <label htmlFor="rebate" className="ml-2 block text-sm font-medium text-gray-700">Rebate</label>
                                    {errors.rebate && <div className="text-red-600 text-sm mt-1">{errors.rebate}</div>}
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="bonificacion"
                                        name="bonificacion"
                                        checked={data.bonificacion}
                                        onChange={(e) => setData('bonificacion', e.target.checked)}
                                        className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                    />
                                    <label htmlFor="bonificacion" className="ml-2 block text-sm font-medium text-gray-700">Bonificación</label>
                                    {errors.bonificacion && <div className="text-red-600 text-sm mt-1">{errors.bonificacion}</div>}
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="tarifa_premium"
                                        name="tarifa_premium"
                                        checked={data.tarifa_premium}
                                        onChange={(e) => setData('tarifa_premium', e.target.checked)}
                                        className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                    />
                                    <label htmlFor="tarifa_premium" className="ml-2 block text-sm font-medium text-gray-700">Tarifa Premium</label>
                                    {errors.tarifa_premium && <div className="text-red-600 text-sm mt-1">{errors.tarifa_premium}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="comparativa" className="block text-sm font-medium text-gray-700">Comparativa</label>
                                    <textarea
                                        id="comparativa"
                                        name="comparativa"
                                        value={data.comparativa}
                                        onChange={(e) => setData('comparativa', e.target.value)}
                                        rows="3"
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    ></textarea>
                                    {errors.comparativa && <div className="text-red-600 text-sm mt-1">{errors.comparativa}</div>}
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="descuento_fruta_comercial"
                                        name="descuento_fruta_comercial"
                                        checked={data.descuento_fruta_comercial}
                                        onChange={(e) => setData('descuento_fruta_comercial', e.target.checked)}
                                        className="h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500"
                                    />
                                    <label htmlFor="descuento_fruta_comercial" className="ml-2 block text-sm font-medium text-gray-700">Descuento Fruta Comercial</label>
                                    {errors.descuento_fruta_comercial && <div className="text-red-600 text-sm mt-1">{errors.descuento_fruta_comercial}</div>}
                                </div>

                                <div className="flex items-center justify-end mt-4">
                                    <button
                                        type="submit"
                                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        disabled={processing}
                                    >
                                        Guardar Contrato
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}