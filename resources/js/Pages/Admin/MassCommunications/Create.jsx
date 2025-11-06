import React, { useRef } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

export default function MassCommunicationsCreate({ services }) {
    const { auth, flash } = usePage().props;
    const fileInputRef = useRef(null);
    const sentRecipients = flash?.sent_recipients ?? [];
    const failedRecipients = flash?.failed_recipients ?? [];
    const missingRecipients = flash?.missing_recipients ?? [];
    const duplicateRecipients = flash?.duplicate_recipients ?? [];
    const localOverride = flash?.local_override ?? [];
    const intendedRecipientCount = flash?.intended_recipient_count ?? null;
    const MAX_PREVIEW_COUNT = 10;

    const { data, setData, post, processing, errors, reset } = useForm({
        service_id: '',
        subject: '',
        body: '',
        attachment: null,
        manual_recipients: '',
    });

    const hasRecipientsTarget =
        (data.service_id && data.service_id !== '') ||
        (data.manual_recipients && data.manual_recipients.trim().length > 0);

    const getMissingLabel = (item) => {
        if (item?.name) {
            return item.name;
        }

        if (item?.email) {
            return item.email;
        }

        if (item?.type === 'service_email') {
            return `Correo adicional${item.id ? ` #${item.id}` : ''}`;
        }

        if (item?.type === 'owner') {
            return 'Dueño del servicio';
        }

        return item?.type ?? 'Contacto';
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        post(route('mass-communications.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                if (fileInputRef.current) {
                    fileInputRef.current.value = null;
                }
            },
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Envío Masivo de Comunicados</h2>}
        >
            <Head title="Envío Masivo" />

            <div className="py-12">
                <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 space-y-6">
                            <p className="text-sm text-gray-600">
                                Selecciona un servicio, redacta el mensaje y adjunta un archivo opcional. El comunicado se enviará
                                a todos los correos vinculados al servicio.
                            </p>

                            {intendedRecipientCount !== null && (
                                <p className="text-xs text-gray-500">
                                    Contactos detectados: {intendedRecipientCount}. Se enviará un correo individual por destinatario.
                                </p>
                            )}

                            {flash?.error && (
                                <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                    {flash.error}
                                </div>
                            )}

                            {flash?.success && (
                                <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                                    {flash.success}
                                </div>
                            )}

                            {sentRecipients.length > 0 && (
                                <div className="rounded border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                                    <p className="font-medium">Destinatarios enviados ({sentRecipients.length})</p>
                                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                                        {sentRecipients.slice(0, MAX_PREVIEW_COUNT).map((email) => (
                                            <li key={email}>{email}</li>
                                        ))}
                                    </ul>
                                    {sentRecipients.length > MAX_PREVIEW_COUNT && (
                                        <p className="mt-1 text-xs text-blue-700">
                                            y {sentRecipients.length - MAX_PREVIEW_COUNT} destinatario(s) más.
                                        </p>
                                    )}
                                </div>
                            )}

                            {failedRecipients.length > 0 && (
                                <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                    <p className="font-medium">Correos con error ({failedRecipients.length})</p>
                                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                                        {failedRecipients.slice(0, MAX_PREVIEW_COUNT).map((item, index) => (
                                            <li key={`${item.email}-${index}`}>
                                                {item.email} — {item.error}
                                            </li>
                                        ))}
                                    </ul>
                                    {failedRecipients.length > MAX_PREVIEW_COUNT && (
                                        <p className="mt-1 text-xs text-red-700">
                                            y {failedRecipients.length - MAX_PREVIEW_COUNT} destinatario(s) más con error.
                                        </p>
                                    )}
                                </div>
                            )}

                            {missingRecipients.length > 0 && (
                                <div className="rounded border border-yellow-200 bg-yellow-50 px-3 py-2 text-sm text-yellow-800">
                                    <p className="font-medium">Contactos sin correo ({missingRecipients.length})</p>
                                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                                        {missingRecipients.slice(0, MAX_PREVIEW_COUNT).map((item, index) => (
                                            <li key={`${item.type ?? 'contact'}-${item.id ?? index}`}>
                                                {getMissingLabel(item)} — {item.reason}
                                            </li>
                                        ))}
                                    </ul>
                                    {missingRecipients.length > MAX_PREVIEW_COUNT && (
                                        <p className="mt-1 text-xs text-yellow-700">
                                            y {missingRecipients.length - MAX_PREVIEW_COUNT} contacto(s) más sin correo.
                                        </p>
                                    )}
                                </div>
                            )}

                            {duplicateRecipients.length > 0 && (
                                <div className="rounded border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <p className="font-medium">Correos duplicados omitidos ({duplicateRecipients.length})</p>
                                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                                        {duplicateRecipients.slice(0, MAX_PREVIEW_COUNT).map((item, index) => (
                                            <li key={`${item.email ?? 'dup'}-${index}`}>
                                                {item.email} {item.name ? `— ${item.name}` : ''}
                                            </li>
                                        ))}
                                    </ul>
                                    {duplicateRecipients.length > MAX_PREVIEW_COUNT && (
                                        <p className="mt-1 text-xs text-gray-600">
                                            y {duplicateRecipients.length - MAX_PREVIEW_COUNT} duplicado(s) más.
                                        </p>
                                    )}
                                </div>
                            )}

                            {localOverride.length > 0 && (
                                <div className="rounded border border-purple-200 bg-purple-50 px-3 py-2 text-sm text-purple-800">
                                    <p className="font-medium">Modo local activo</p>
                                    <p className="text-xs text-purple-700">
                                        Las direcciones reales fueron reemplazadas por MASS_SEND_EMAIL:
                                    </p>
                                    <ul className="mt-1 list-inside list-disc space-y-0.5">
                                        {localOverride.map((email) => (
                                            <li key={email}>{email}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div>
                                    <Label htmlFor="service_id">Servicio</Label>
                                    <Select
                                        value={data.service_id !== '' ? data.service_id : '__none'}
                                        onValueChange={(value) => setData('service_id', value === '__none' ? '' : value)}
                                    >
                                        <SelectTrigger id="service_id" className="mt-1">
                                            <SelectValue placeholder="Selecciona un servicio" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="__none">Sin servicio</SelectItem>
                                            {services.map((service) => (
                                                <SelectItem key={service.id} value={String(service.id)}>
                                                    {service.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.service_id && (
                                        <p className="mt-1 text-sm text-red-600">{errors.service_id}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="manual_recipients">Correos específicos (opcional)</Label>
                                    <Textarea
                                        id="manual_recipients"
                                        className="mt-1 min-h-[120px]"
                                        placeholder="Ingresa correos separados por comas, punto y coma o saltos de línea"
                                        value={data.manual_recipients}
                                        onChange={(event) => setData('manual_recipients', event.target.value)}
                                    />
                                    <p className="mt-1 text-xs text-gray-500">
                                        Usa este campo para enviar el comunicado directamente a una lista de correos sin asociarlo a un
                                        servicio.
                                    </p>
                                    {errors.manual_recipients && (
                                        <p className="mt-1 text-sm text-red-600">{errors.manual_recipients}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="subject">Asunto</Label>
                                    <Input
                                        id="subject"
                                        type="text"
                                        className="mt-1"
                                        value={data.subject}
                                        onChange={(event) => setData('subject', event.target.value)}
                                        required
                                    />
                                    {errors.subject && (
                                        <p className="mt-1 text-sm text-red-600">{errors.subject}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="body">Mensaje</Label>
                                    <Textarea
                                        id="body"
                                        className="mt-1 min-h-[160px]"
                                        value={data.body}
                                        onChange={(event) => setData('body', event.target.value)}
                                        required
                                    />
                                    {errors.body && (
                                        <p className="mt-1 text-sm text-red-600">{errors.body}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="attachment">Archivo adjunto (opcional)</Label>
                                    <Input
                                        id="attachment"
                                        type="file"
                                        className="mt-1"
                                        ref={fileInputRef}
                                        onChange={(event) => setData('attachment', event.target.files?.[0] ?? null)}
                                    />
                                    {errors.attachment && (
                                        <p className="mt-1 text-sm text-red-600">{errors.attachment}</p>
                                    )}
                                </div>

                                <div className="flex items-center justify-end space-x-3">
                                    <Button
                                        type="submit"
                                        disabled={processing || !hasRecipientsTarget || !data.subject || !data.body}
                                    >
                                        {processing ? 'Enviando…' : 'Enviar comunicado'}
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
