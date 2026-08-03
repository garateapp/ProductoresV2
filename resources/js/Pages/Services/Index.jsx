import React, { useState, useEffect, useCallback } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, router } from '@inertiajs/react';
import ManageProducersModal from './ManageProducersModal';

export default function ServiceIndex({ auth, services, availableUsers, filters }) {
    const { delete: destroy, processing: processingDelete } = useForm();
    const [showModal, setShowModal] = useState(false);
    const [selectedService, setSelectedService] = useState(null);
    const [processingAttach, setProcessingAttach] = useState(false);

    const serviceList = Array.isArray(services?.data)
        ? services.data
        : (Array.isArray(services) ? services : []);

    useEffect(() => {
        if (selectedService) {
            const updatedService = serviceList.find(s => s.id === selectedService.id);
            if (updatedService) {
                setSelectedService(updatedService);
            }
        }
    }, [serviceList, services]);

    const handleDelete = (id) => {
        if (confirm('Estás seguro de que quieres eliminar este servicio?')) {
            destroy(route('services.destroy', id));
        }
    };

    const openModal = useCallback((service) => {
        setSelectedService(service);
        setShowModal(true);
    }, []);

    const closeModal = useCallback(() => {
        setSelectedService(null);
        setShowModal(false);
    }, []);

    const handleAttachUser = useCallback((userId) => {
        if (!selectedService) return;
        router.post(route('services.attachUser', selectedService.id), {
            user_id: userId,
        }, {
            preserveState: true,
            preserveScroll: true,
            onStart: () => setProcessingAttach(true),
            onFinish: () => setProcessingAttach(false),
        });
    }, [selectedService]);

    const handleDetachUser = useCallback((userId) => {
        if (!selectedService) return;
        destroy(route('services.detachUser', { service: selectedService.id, user: userId }), {
            preserveState: true,
            preserveScroll: true,
        });
    }, [destroy, selectedService]);

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Servicios</h2>}
        >
            <Head title="Servicios" />

            {selectedService && (
                <ManageProducersModal
                    service={selectedService}
                    availableUsers={availableUsers}
                    closeModal={closeModal}
                    showModal={showModal}
                    filters={filters}
                    handleAttachUser={handleAttachUser}
                    handleDetachUser={handleDetachUser}
                    processing={processingAttach || processingDelete}
                />
            )}

            <div className="py-12">
                <div className="mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <div className="flex justify-end mb-4">
                                <Link
                                    href={route('services.create')}
                                    className="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded"
                                >
                                    Crear Servicio
                                </Link>
                            </div>

                            <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nombre</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descripción</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RUT</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dueño</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teléfonos</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Emails</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {serviceList.map((service) => (
                                        <tr key={service.id}>
                                            <td className="px-6 py-4"><div className="max-w-xs truncate" title={service.name}>{service.name}</div></td>
                                            <td className="px-6 py-4"><div className="max-w-sm truncate" title={service.description}>{service.description}</div></td>
                                            <td className="px-6 py-4"><div className="max-w-xs truncate" title={service.rut}>{service.rut || '-'}</div></td>
                                            <td className="px-6 py-4"><div className="max-w-xs truncate" title={service.owner?.name}>{service.owner?.name}</div></td>
                                            <td className="px-6 py-4"><div className="max-w-sm truncate" title={service.phones.map(p => p.phone).join(', ')}>{service.phones.map(p => p.phone).join(', ')}</div></td>
                                            <td className="px-6 py-4"><div className="max-w-sm truncate" title={service.emails.map(e => e.email).join(', ')}>{service.emails.map(e => e.email).join(', ')}</div></td>
                                            <td className="px-6 py-4">
                                                <div className="flex flex-wrap gap-2">
                                                    <Link
                                                        href={route('services.show', service.id)}
                                                        className="text-indigo-600 hover:text-indigo-900"
                                                    >
                                                        Ver
                                                    </Link>
                                                    <Link
                                                        href={route('services.edit', service.id)}
                                                        className="text-green-600 hover:text-green-900"
                                                    >
                                                        Editar
                                                    </Link>
                                                    <button
                                                        onClick={() => handleDelete(service.id)}
                                                        className="text-red-600 hover:text-red-900"
                                                    >
                                                        Eliminar
                                                    </button>
                                                    <button
                                                        onClick={() => openModal(service)}
                                                        className="text-blue-600 hover:text-blue-900"
                                                    >
                                                        Administrar Productores
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
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
