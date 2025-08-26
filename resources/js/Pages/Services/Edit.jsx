import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

export default function ServiceEdit({ auth, service, users }) {
    const { data, setData, put, processing, errors } = useForm({
        name: service.name || '',
        description: service.description || '',
        owner_id: service.owner_id || '',
        phones: service.phones.map(p => p.phone) || [''],
        emails: service.emails.map(e => e.email) || [''],
    });

    const handlePhoneChange = (index, value) => {
        const newPhones = [...data.phones];
        newPhones[index] = value;
        setData('phones', newPhones);
    };

    const addPhoneInput = () => {
        setData('phones', [...data.phones, '']);
    };

    const removePhoneInput = (index) => {
        const newPhones = data.phones.filter((_, i) => i !== index);
        setData('phones', newPhones);
    };

    const handleEmailChange = (index, value) => {
        const newEmails = [...data.emails];
        newEmails[index] = value;
        setData('emails', newEmails);
    };

    const addEmailInput = () => {
        setData('emails', [...data.emails, '']);
    };

    const removeEmailInput = (index) => {
        const newEmails = data.emails.filter((_, i) => i !== index);
        setData('emails', newEmails);
    };

    const submit = (e) => {
        e.preventDefault();
        put(route('services.update', service.id));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Editar Servicio</h2>}
        >
            <Head title="Editar Servicio" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            <form onSubmit={submit} className="space-y-6">
                                <div>
                                    <Label htmlFor="name">Nombre</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        className="mt-1 block w-full"
                                        required
                                    />
                                    {errors.name && <div className="text-red-600 text-sm mt-1">{errors.name}</div>}
                                </div>

                                <div>
                                    <Label htmlFor="description">Descripción</Label>
                                    <textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    ></textarea>
                                    {errors.description && <div className="text-red-600 text-sm mt-1">{errors.description}</div>}
                                </div>

                                <div>
                                    <Label htmlFor="owner_id">Dueño del Servicio</Label>
                                    <select
                                        id="owner_id"
                                        value={data.owner_id}
                                        onChange={(e) => setData('owner_id', e.target.value)}
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                        required
                                    >
                                        {users.map(user => (
                                            <option key={user.id} value={user.id}>{user.name}</option>
                                        ))}
                                    </select>
                                    {errors.owner_id && <div className="text-red-600 text-sm mt-1">{errors.owner_id}</div>}
                                </div>

                                <div>
                                    <Label>Teléfonos</Label>
                                    {data.phones.map((phone, index) => (
                                        <div key={index} className="flex items-center space-x-2 mt-1">
                                            <Input
                                                type="text"
                                                value={phone}
                                                onChange={(e) => handlePhoneChange(index, e.target.value)}
                                                className="block w-full"
                                            />
                                            <Button type="button" variant="destructive" onClick={() => removePhoneInput(index)}>Quitar</Button>
                                        </div>
                                    ))}
                                    <Button type="button" onClick={addPhoneInput} className="mt-2">Añadir Teléfono</Button>
                                    {errors.phones && <div className="text-red-600 text-sm mt-1">{errors.phones}</div>}
                                </div>

                                <div>
                                    <Label>Emails</Label>
                                    {data.emails.map((email, index) => (
                                        <div key={index} className="flex items-center space-x-2 mt-1">
                                            <Input
                                                type="email"
                                                value={email}
                                                onChange={(e) => handleEmailChange(index, e.target.value)}
                                                className="block w-full"
                                            />
                                            <Button type="button" variant="destructive" onClick={() => removeEmailInput(index)}>Quitar</Button>
                                        </div>
                                    ))}
                                    <Button type="button" onClick={addEmailInput} className="mt-2">Añadir Email</Button>
                                    {errors.emails && <div className="text-red-600 text-sm mt-1">{errors.emails}</div>}
                                </div>

                                <div className="flex items-center justify-end">
                                    <Button type="submit" disabled={processing}>
                                        Actualizar Servicio
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}