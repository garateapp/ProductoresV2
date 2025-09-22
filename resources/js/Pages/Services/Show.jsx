import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ServiceShow({ auth, service, availableUsers, recepciones = [], procesos = [] }) {
    const { post, delete: destroy } = useForm();

    const handleAttachUser = (userId) => {
        post(route('services.attachUser', service.id), { user_id: userId });
    };

    const handleDetachUser = (userId) => {
        destroy(route('services.detachUser', { service: service.id, user: userId }));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalles del Servicio</h2>}
        >
            <Head title="Detalles del Servicio" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <h3 className="text-lg font-medium text-gray-900">Servicio: {service.name}</h3>
                            <p className="mt-1 text-sm text-gray-600">{service.description}</p>

                            <div className="mt-6">
                                <h4 className="text-md font-medium text-gray-900">Productores Asociados</h4>
                                {service.users.length > 0 ? (
                                    <ul className="mt-2 divide-y divide-gray-200">
                                        {service.users.map((user) => (
                                            <li key={user.id} className="py-2 flex justify-between items-center">
                                                <span>{user.name} ({user.email})</span>
                                                <button
                                                    onClick={() => handleDetachUser(user.id)}
                                                    className="text-red-600 hover:text-red-900 text-sm"
                                                >
                                                    Quitar
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-gray-600">No hay productores asociados a este servicio.</p>
                                )}
                            </div>

                            <div className="mt-6">
                                <h4 className="text-md font-medium text-gray-900">Productores Disponibles para Asociar</h4>
                                {availableUsers.length > 0 ? (
                                    <ul className="mt-2 divide-y divide-gray-200">
                                        {availableUsers.map((user) => (
                                            <li key={user.id} className="py-2 flex justify-between items-center">
                                                <span>{user.name} ({user.email})</span>
                                                <button
                                                    onClick={() => handleAttachUser(user.id)}
                                                    className="text-blue-600 hover:text-blue-900 text-sm"
                                                >
                                                    Asociar
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p className="mt-2 text-sm text-gray-600">No hay más productores disponibles para asociar.</p>
                                )}
                            </div>

                            {/* Recepciones del Servicio */}
                            <div className="mt-8">
                                <h4 className="text-md font-medium text-gray-900">Recepciones del Servicio</h4>
                                {Array.isArray(recepciones) && recepciones.length > 0 ? (
                                    <div className="mt-2 overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Lote</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Especie</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Variedad</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Kilos</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {recepciones.map(r => (
                                                    <tr key={r.id}>
                                                        <td className="px-4 py-2 whitespace-nowrap">{r.numero_g_recepcion}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{new Date(r.fecha_g_recepcion).toLocaleDateString('es-CL')}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{r.n_especie}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{r.n_variedad}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{(r.peso_neto ?? 0).toLocaleString('es-CL')}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-gray-600">No hay recepciones asociadas a los productores de este servicio.</p>
                                )}
                            </div>

                            {/* Procesos del Servicio */}
                            <div className="mt-8">
                                <h4 className="text-md font-medium text-gray-900">Procesos del Servicio</h4>
                                {Array.isArray(procesos) && procesos.length > 0 ? (
                                    <div className="mt-2 overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Proceso</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Fecha</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Especie</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Variedad</th>
                                                    <th className="px-4 py-2 text-xs font-medium text-gray-500 uppercase">Kg Netos</th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {procesos.map(p => (
                                                    <tr key={p.id}>
                                                        <td className="px-4 py-2 whitespace-nowrap">{p.n_proceso}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{new Date(p.fecha).toLocaleDateString('es-CL')}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{p.especie}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{p.variedad}</td>
                                                        <td className="px-4 py-2 whitespace-nowrap">{(p.kilos_netos ?? 0).toLocaleString('es-CL')}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                ) : (
                                    <p className="mt-2 text-sm text-gray-600">No hay procesos asociados a los productores de este servicio.</p>
                                )}
                            </div>

                            <div className="mt-6">
                                <Link
                                    href={route('services.index')}
                                    className="text-indigo-600 hover:text-indigo-900"
                                >
                                    Volver a la Lista de Servicios
                                </Link>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
