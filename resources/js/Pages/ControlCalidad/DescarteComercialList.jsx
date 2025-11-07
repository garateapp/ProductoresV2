import React from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

const Pagination = ({ links }) => {
    if (! links) {
        return null;
    }

    return (
        <div className="mt-6 flex flex-wrap items-center gap-2">
            {links.map((link, index) => (
                <Link
                    key={index}
                    href={link.url ?? '#'}
                    className={`rounded-md px-3 py-1 text-sm ${
                        link.active
                            ? 'bg-greenex-dark-green text-white'
                            : link.url
                                ? 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50'
                                : 'bg-gray-100 text-gray-400'
                    }`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
};

export default function DescarteComercialList({ auth, records }) {
    const { flash } = usePage().props;

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Historial de Descartes Comerciales</h2>}
        >
            <Head title="Descarte Comercial" />

            <div className="py-12">
                <div className="max-w-6xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 space-y-6">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm text-gray-600">
                                        Consulta los descartes comerciales registrados y descarga sus reportes en PDF.
                                    </p>
                                    {flash?.success && (
                                        <div className="mt-2 rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                                            {flash.success}
                                        </div>
                                    )}
                                </div>
                                <Button asChild>
                                    <Link href={route('commercial-discards.create')}>Registrar nuevo descarte</Link>
                                </Button>
                            </div>

                            <div className="overflow-hidden rounded-lg border border-gray-200">
                                <Table>
                                    <TableHeader className="bg-gray-50">
                                        <TableRow>
                                            <TableHead>Fecha</TableHead>
                                            <TableHead>Productor</TableHead>
                                            <TableHead>Especie</TableHead>
                                            <TableHead>Variedad</TableHead>
                                            <TableHead>Línea / Turno</TableHead>
                                            <TableHead>Registrado por</TableHead>
                                            <TableHead className="text-right">Reporte</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {records.data.length === 0 && (
                                            <TableRow>
                                                <TableCell colSpan={7} className="text-center text-sm text-gray-500">
                                                    Aún no existen descartes registrados.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                        {records.data.map((record) => (
                                            <TableRow key={record.id}>
                                                <TableCell>{record.fecha}</TableCell>
                                                <TableCell>{record.productor}</TableCell>
                                                <TableCell>{record.especie}</TableCell>
                                                <TableCell>{record.variedad}</TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col text-sm">
                                                        <span className="font-semibold text-gray-800">Línea {record.linea}</span>
                                                        <span className="text-gray-500">Turno {record.turno}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{record.user ?? '—'}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button asChild variant="outline" size="sm">
                                                        <a href={record.pdf_url} target="_blank" rel="noopener noreferrer">
                                                            Ver PDF
                                                        </a>
                                                    </Button>
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                            </div>

                            <Pagination links={records.links} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
