import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ContractForm from '@/Pages/Contracts/Partials/ContractForm';

export default function Create({ auth, producers }) {
  return (
    <AuthenticatedLayout user={auth.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Crear Contrato</h2>}>
      <Head title="Crear Contrato" />
      <div className="py-12">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
            <div className="p-6 text-gray-900">
              <ContractForm producers={producers} />
            </div>
          </div>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
