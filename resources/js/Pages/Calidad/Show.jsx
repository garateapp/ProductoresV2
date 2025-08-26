import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, Link } from '@inertiajs/react';

export default function Show({ auth, calidad, parametros, valores }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        parametro_id: '',
        valor_id: '',
        cantidad_muestra: '',
        exportable: false,
    });

    const submitDetalle = (e) => {
        e.preventDefault();
        post(route('calidad.detalles.store', calidad.id), {
            onSuccess: () => reset(),
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalles de Calidad</h2>}
        >
            <Head title="Detalles de Calidad" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Información de Calidad</h3>
                            <p><strong>ID Recepción:</strong> {calidad.recepcion.id} - {calidad.recepcion.fecha_recepcion}</p>
                            <p><strong>Cantidad Total Muestra:</strong> {calidad.cantidad_total_muestra}</p>
                            <p><strong>Materia Vegetal:</strong> {calidad.materia_vegetal}</p>
                            <p><strong>Piedras:</strong> {calidad.piedras}</p>
                            <p><strong>Barro:</strong> {calidad.barro}</p>
                            <p><strong>Pedicelo Largo:</strong> {calidad.pedicelo_largo}</p>
                            <p><strong>Racimo:</strong> {calidad.racimo}</p>
                            <p><strong>Esponjas:</strong> {calidad.esponjas}</p>
                            <p><strong>H. Esponjas:</strong> {calidad.h_esponjas}</p>
                            <p><strong>Llenado Tottes:</strong> {calidad.llenado_tottes}</p>
                            <p><strong>Embalaje:</strong> {calidad.embalaje}</p>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Agregar Nuevo Detalle</h3>
                            <form onSubmit={submitDetalle}>
                                <div className="mb-4">
                                    <label htmlFor="parametro_id" className="block text-sm font-medium text-gray-700">Parámetro</label>
                                    <select
                                        id="parametro_id"
                                        name="parametro_id"
                                        value={data.parametro_id}
                                        onChange={(e) => setData('parametro_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">Seleccione un parámetro</option>
                                        {parametros.map((parametro) => (
                                            <option key={parametro.id} value={parametro.id}>
                                                {parametro.nombre}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.parametro_id && <div className="text-red-500 text-sm mt-1">{errors.parametro_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="valor_id" className="block text-sm font-medium text-gray-700">Valor</label>
                                    <select
                                        id="valor_id"
                                        name="valor_id"
                                        value={data.valor_id}
                                        onChange={(e) => setData('valor_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">Seleccione un valor</option>
                                        {valores.map((valor) => (
                                            <option key={valor.id} value={valor.id}>
                                                {valor?.nombre || 'N/A'}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.valor_id && <div className="text-red-500 text-sm mt-1">{errors.valor_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="cantidad_muestra" className="block text-sm font-medium text-gray-700">Cantidad Muestra</label>
                                    <input
                                        type="number"
                                        id="cantidad_muestra"
                                        name="cantidad_muestra"
                                        value={data.cantidad_muestra}
                                        onChange={(e) => setData('cantidad_muestra', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.cantidad_muestra && <div className="text-red-500 text-sm mt-1">{errors.cantidad_muestra}</div>}
                                </div>

                                <div className="mb-4 flex items-center">
                                    <input
                                        type="checkbox"
                                        id="exportable"
                                        name="exportable"
                                        checked={data.exportable}
                                        onChange={(e) => setData('exportable', e.target.checked)}
                                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                    <label htmlFor="exportable" className="ml-2 block text-sm font-medium text-gray-700">Exportable</label>
                                    {errors.exportable && <div className="text-red-500 text-sm mt-1">{errors.exportable}</div>}
                                </div>

                                <button
                                    type="submit"
                                    className="bg-green-500 hover:bg-green-700 text-white font-bold py-2 px-4 rounded"
                                    disabled={processing}
                                >
                                    Agregar Detalle
                                </button>
                            </form>
                        </div>
                    </div>

                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 bg-white border-b border-gray-200">
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Detalles Agregados</h3>
                            {calidad.detalles.length > 0 ? (
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo Item</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Detalle Item</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Cantidad</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {calidad.detalles.map((detalle) => (
                                            <tr key={detalle.id}>
                                                <td className="px-6 py-4 whitespace-nowrap">{detalle.tipo_item}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">{detalle.detalle_item}</td>
                                                <td className="px-6 py-4 whitespace-nowrap">{detalle.cantidad}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            ) : (
                                <p>No hay detalles agregados para esta calidad.</p>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
