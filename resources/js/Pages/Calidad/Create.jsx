import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Create({ auth, recepciones }) {
    const { data, setData, post, processing, errors } = useForm({
        recepcion_id: '',
        cantidad_total_muestra: '',
        materia_vegetal: '',
        piedras: '',
        barro: '',
        pedicelo_largo: '',
        racimo: '',
        esponjas: '',
        h_esponjas: '',
        llenado_tottes: '',
        embalaje: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('calidad.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Crear Calidad</h2>}
        >
            <Head title="Crear Calidad" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <form onSubmit={submit}>
                                <div className="mb-4">
                                    <label htmlFor="recepcion_id" className="block text-sm font-medium text-gray-700">Recepción</label>
                                    <select
                                        id="recepcion_id"
                                        name="recepcion_id"
                                        value={data.recepcion_id}
                                        onChange={(e) => setData('recepcion_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">Seleccione una recepción</option>
                                        {recepciones.map((recepcion) => (
                                            <option key={recepcion.id} value={recepcion.id}>
                                                {recepcion.id} - {recepcion.fecha_recepcion}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.recepcion_id && <div className="text-red-500 text-sm mt-1">{errors.recepcion_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="cantidad_total_muestra" className="block text-sm font-medium text-gray-700">Cantidad Total Muestra</label>
                                    <input
                                        type="number"
                                        id="cantidad_total_muestra"
                                        name="cantidad_total_muestra"
                                        value={data.cantidad_total_muestra}
                                        onChange={(e) => setData('cantidad_total_muestra', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.cantidad_total_muestra && <div className="text-red-500 text-sm mt-1">{errors.cantidad_total_muestra}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="materia_vegetal" className="block text-sm font-medium text-gray-700">Materia Vegetal</label>
                                    <input
                                        type="number"
                                        id="materia_vegetal"
                                        name="materia_vegetal"
                                        value={data.materia_vegetal}
                                        onChange={(e) => setData('materia_vegetal', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.materia_vegetal && <div className="text-red-500 text-sm mt-1">{errors.materia_vegetal}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="piedras" className="block text-sm font-medium text-gray-700">Piedras</label>
                                    <input
                                        type="number"
                                        id="piedras"
                                        name="piedras"
                                        value={data.piedras}
                                        onChange={(e) => setData('piedras', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.piedras && <div className="text-red-500 text-sm mt-1">{errors.piedras}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="barro" className="block text-sm font-medium text-gray-700">Barro</label>
                                    <input
                                        type="number"
                                        id="barro"
                                        name="barro"
                                        value={data.barro}
                                        onChange={(e) => setData('barro', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.barro && <div className="text-red-500 text-sm mt-1">{errors.barro}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="pedicelo_largo" className="block text-sm font-medium text-gray-700">Pedicelo Largo</label>
                                    <input
                                        type="number"
                                        id="pedicelo_largo"
                                        name="pedicelo_largo"
                                        value={data.pedicelo_largo}
                                        onChange={(e) => setData('pedicelo_largo', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.pedicelo_largo && <div className="text-red-500 text-sm mt-1">{errors.pedicelo_largo}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="racimo" className="block text-sm font-medium text-gray-700">Racimo</label>
                                    <input
                                        type="number"
                                        id="racimo"
                                        name="racimo"
                                        value={data.racimo}
                                        onChange={(e) => setData('racimo', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.racimo && <div className="text-red-500 text-sm mt-1">{errors.racimo}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="esponjas" className="block text-sm font-medium text-gray-700">Esponjas</label>
                                    <input
                                        type="number"
                                        id="esponjas"
                                        name="esponjas"
                                        value={data.esponjas}
                                        onChange={(e) => setData('esponjas', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.esponjas && <div className="text-red-500 text-sm mt-1">{errors.esponjas}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="h_esponjas" className="block text-sm font-medium text-gray-700">H. Esponjas</label>
                                    <input
                                        type="number"
                                        id="h_esponjas"
                                        name="h_esponjas"
                                        value={data.h_esponjas}
                                        onChange={(e) => setData('h_esponjas', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.h_esponjas && <div className="text-red-500 text-sm mt-1">{errors.h_esponjas}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="llenado_tottes" className="block text-sm font-medium text-gray-700">Llenado Tottes</label>
                                    <input
                                        type="number"
                                        id="llenado_tottes"
                                        name="llenado_tottes"
                                        value={data.llenado_tottes}
                                        onChange={(e) => setData('llenado_tottes', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.llenado_tottes && <div className="text-red-500 text-sm mt-1">{errors.llenado_tottes}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="embalaje" className="block text-sm font-medium text-gray-700">Embalaje</label>
                                    <input
                                        type="number"
                                        id="embalaje"
                                        name="embalaje"
                                        value={data.embalaje}
                                        onChange={(e) => setData('embalaje', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.embalaje && <div className="text-red-500 text-sm mt-1">{errors.embalaje}</div>}
                                </div>

                                <button
                                    type="submit"
                                    className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                                    disabled={processing}
                                >
                                    Guardar Calidad
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
