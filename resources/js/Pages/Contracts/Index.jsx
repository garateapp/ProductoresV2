import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function ContractsIndex({ auth, contracts }) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Gestión de Contratos</h2>}
        >
            <Head title="Gestión de Contratos" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <Link href={route('contracts.create')} className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 mb-4">
                                Agregar Contrato
                            </Link>
                            <h3 className="text-lg font-medium text-gray-900 mb-4">Listado de Contratos</h3>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productor</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Fecha Contrato</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimiento</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comisión</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flete a Huerto</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rebate</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bonificación</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarifa Premium</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comparativa</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento Fruta Comercial</th>
                                            <th scope="col" className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo de Contrato</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {contracts.length > 0 ? (
                                            contracts.map((contract) => (
                                                <tr key={contract.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.user ? contract.user.name : 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.fecha_contrato}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.vencimiento}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.comision}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.flete_a_huerto}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.rebate ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.bonificacion ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.tarifa_premium ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.comparativa}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.descuento_fruta_comercial ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {contract.contract_file_path ? (
                                                            <a href={`/storage/${contract.contract_file_path}`} target="_blank" className="text-blue-600 hover:text-blue-900">Ver Archivo</a>
                                                        ) : (
                                                            <span>No subido</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="11" className="px-6 py-4 whitespace-nowrap text-center text-gray-500">No hay contratos registrados.</td>
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