import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function MrlSamplesIndex({ auth, mrlSamples }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">MRL - Gestión de Muestras</h2>}
        >
            <Head title="MRL - Gestión de Muestras" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <Link href={route('mrl-samples.create')} className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 mb-4">
                                Agregar Muestra
                            </Link>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Listado de Muestras MRL</h3>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productor</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Especie</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Variedad</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">CSG</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Laboratorio</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Muestreo</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo de Resultado</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {mrlSamples.length > 0 ? (
                                            mrlSamples.map((sample) => (
                                                <tr key={sample.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.user ? sample.user.name : 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.especie ? sample.especie.name : 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.variedad ? sample.variedad.name : 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.csg}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.laboratory}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{sample.sampling_date}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {sample.result_file_path ? (
                                                            <a href={`/storage/${sample.result_file_path}`} target="_blank" className="text-blue-600 hover:text-blue-900">Ver Archivo</a>
                                                        ) : (
                                                            <span>No subido</span>
                                                        )}
                                                        {/* Placeholder for upload functionality */}
                                                        <button className="ml-2 text-indigo-600 hover:text-indigo-900 text-sm">Subir</button>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="7" className="px-6 py-4 whitespace-nowrap text-center text-gray-500">No hay muestras MRL registradas.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}