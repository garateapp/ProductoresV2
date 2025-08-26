import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import Select from 'react-select';

export default function Create({ auth, users, especies, variedades }) {
    const [filteredVariedades, setFilteredVariedades] = useState([]);
    const [filteredCsgs, setFilteredCsgs] = useState([]); // New state for filtered CSGs

    const { data, setData, post, processing, errors, reset } = useForm({
        user_id: '',
        especie_id: '',
        variedad_id: '',
        csg: '',
        laboratory: '',
        sampling_date: '',
        result_file: null, // New field for file upload
    });

    const producerOptions = users
        .filter(user => user.idprod !== null) // Only include users who are producers
        .reduce((acc, user) => {
            // Group by RUT, ensuring unique RUTs
            if (!acc.some(option => option.rut === user.rut)) {
                acc.push({
                    value: user.id, // Use the ID of one user with this RUT
                    label: user.name, // Display the name of this user
                    rut: user.rut, // Store RUT for internal check
                });
            }
            return acc;
        }, []);

    useEffect(() => {
        if (data.especie_id) {
            axios.get(route('api.variedades-by-especie', data.especie_id))
                .then(response => {
                    setFilteredVariedades(response.data);
                })
                .catch(error => {
                    console.error('Error fetching variedades:', error);
                    setFilteredVariedades([]);
                });
        } else {
            setFilteredVariedades([]);
        }
        setData('variedad_id', ''); // Reset variedad when especie changes
    }, [data.especie_id]);

    // New useEffect for fetching CSGs based on selected producer's RUT
    useEffect(() => {
        const selectedProducer = users.find(user => user.id === data.user_id);
        if (selectedProducer && selectedProducer.rut) {
            axios.get(route('api.csgs-by-rut', selectedProducer.rut))
                .then(response => {
                    const csgOptions = response.data.map(csg => ({ value: csg, label: csg }));
                    setFilteredCsgs(csgOptions);
                })
                .catch(error => {
                    console.error('Error fetching CSGs:', error);
                    setFilteredCsgs([]);
                });
        } else {
            setFilteredCsgs([]);
        }
        setData('csg', ''); // Reset csg when producer changes
    }, [data.user_id]); // Trigger when user_id changes

    const submit = (e) => {
        e.preventDefault();
        post(route('mrl-samples.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Crear Muestra MRL</h2>}
        >
            <Head title="Crear Muestra MRL" />

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
                                        onChange={(selectedOption) => {
                                            setData('user_id', selectedOption ? selectedOption.value : '');
                                            // CSG fetching is now handled by the new useEffect
                                        }}
                                        className="mt-1 block w-full rounded-md shadow-sm"
                                        classNamePrefix="react-select"
                                        placeholder="Seleccione un productor"
                                        isClearable
                                    />
                                    {errors.user_id && <div className="text-red-600 text-sm mt-1">{errors.user_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="especie_id" className="block text-sm font-medium text-gray-700">Especie</label>
                                    <select
                                        id="especie_id"
                                        name="especie_id"
                                        value={data.especie_id}
                                        onChange={(e) => setData('especie_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">Seleccione una especie</option>
                                        {especies.map((especie) => (
                                            <option key={especie.id} value={especie.id}>{especie.name}</option>
                                        ))}
                                    </select>
                                    {errors.especie_id && <div className="text-red-600 text-sm mt-1">{errors.especie_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="variedad_id" className="block text-sm font-medium text-gray-700">Variedad</label>
                                    <select
                                        id="variedad_id"
                                        name="variedad_id"
                                        value={data.variedad_id}
                                        onChange={(e) => setData('variedad_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    >
                                        <option value="">Seleccione una variedad</option>
                                        {filteredVariedades.map((variedad) => (
                                            <option key={variedad.id} value={variedad.id}>{variedad.name}</option>
                                        ))}
                                    </select>
                                    {errors.variedad_id && <div className="text-red-600 text-sm mt-1">{errors.variedad_id}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="csg" className="block text-sm font-medium text-gray-700">CSG</label>
                                    <Select
                                        id="csg"
                                        name="csg"
                                        options={filteredCsgs}
                                        value={filteredCsgs.find(option => option.value === data.csg)}
                                        onChange={(selectedOption) => setData('csg', selectedOption ? selectedOption.value : '')}
                                        className="mt-1 block w-full rounded-md shadow-sm"
                                        classNamePrefix="react-select"
                                        placeholder="Seleccione un CSG"
                                        isClearable
                                    />
                                    {errors.csg && <div className="text-red-600 text-sm mt-1">{errors.csg}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="laboratory" className="block text-sm font-medium text-gray-700">Laboratorio</label>
                                    <input
                                        type="text"
                                        id="laboratory"
                                        name="laboratory"
                                        value={data.laboratory}
                                        onChange={(e) => setData('laboratory', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.laboratory && <div className="text-red-600 text-sm mt-1">{errors.laboratory}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="result_file" className="block text-sm font-medium text-gray-700">Archivo de Resultado</label>
                                    <input
                                        type="file"
                                        id="result_file"
                                        name="result_file"
                                        onChange={(e) => setData('result_file', e.target.files[0])}
                                        className="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none"
                                    />
                                    {errors.result_file && <div className="text-red-600 text-sm mt-1">{errors.result_file}</div>}
                                </div>

                                <div className="mb-4">
                                    <label htmlFor="sampling_date" className="block text-sm font-medium text-gray-700">Fecha Muestreo</label>
                                    <input
                                        type="date"
                                        id="sampling_date"
                                        name="sampling_date"
                                        value={data.sampling_date}
                                        onChange={(e) => setData('sampling_date', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    />
                                    {errors.sampling_date && <div className="text-red-600 text-sm mt-1">{errors.sampling_date}</div>}
                                </div>

                                <div className="flex items-center justify-end mt-4">
                                    <button
                                        type="submit"
                                        className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                        disabled={processing}
                                    >
                                        Guardar Muestra
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