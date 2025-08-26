import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function ServiceShow({ auth, service, availableUsers }) {
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