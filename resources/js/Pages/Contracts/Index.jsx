import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Switch } from '@/Components/ui/switch';
import { FileText } from 'lucide-react';

export default function ContractsIndex({ auth, contracts }) {
    const [search, setSearch] = useState('');
    const [sortBy, setSortBy] = useState('vencimiento');
    const [sortOrder, setSortOrder] = useState('asc');
    const [page, setPage] = useState(1);
    const pageSize = 10;

    const [filter60, setFilter60] = useState(false);
    const [filter90, setFilter90] = useState(false);
    const [filterExpired, setFilterExpired] = useState(false);

    const parseDate = (s) => {
        if (!s) return null;
        const d = new Date(s);
        return isNaN(d.getTime()) ? null : d;
    };
    const daysUntil = (dateStr) => {
        const d = parseDate(dateStr);
        if (!d) return null;
        const today = new Date();
        const a = new Date(today.getFullYear(), today.getMonth(), today.getDate());
        const b = new Date(d.getFullYear(), d.getMonth(), d.getDate());
        const msPerDay = 24 * 60 * 60 * 1000;
        return Math.ceil((b - a) / msPerDay);
    };
    const getStatus = (c) => {
        const du = daysUntil(c.vencimiento);
        if (du === null) return { label: 'N/A', color: 'bg-gray-300 text-gray-800' };
        if (du < 0) return { label: 'Vencido', color: 'bg-red-600 text-white' };
        if (du <= 60) return { label: `Vence en ${du} días`, color: 'bg-yellow-500 text-white' };
        if (du <= 90) return { label: `Vence en ${du} días`, color: 'bg-orange-500 text-white' };
        return { label: `Vence en ${du} días`, color: 'bg-green-600 text-white' };
    };

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        let arr = Array.isArray(contracts) ? contracts : [];
        if (term) {
            arr = arr.filter(c =>
                (c.user?.name || '').toLowerCase().includes(term) ||
                (c.comparativa || '').toLowerCase().includes(term)
            );
        }
        if (filter60 || filter90 || filterExpired) {
            arr = arr.filter(c => {
                const du = daysUntil(c.vencimiento);
                if (du === null) return false;
                return (
                    (filterExpired && du < 0) ||
                    (filter60 && du >= 0 && du <= 60) ||
                    (filter90 && du > 60 && du <= 90)
                );
            });
        }
        return arr;
    }, [contracts, search, filter60, filter90, filterExpired]);

    const sorted = useMemo(() => {
        const arr = [...filtered];
        arr.sort((a, b) => {
            const dir = sortOrder === 'asc' ? 1 : -1;
            if (sortBy === 'user') {
                const an = (a.user?.name || '').toLowerCase();
                const bn = (b.user?.name || '').toLowerCase();
                return an.localeCompare(bn) * dir;
            }
            if (sortBy === 'vencimiento' || sortBy === 'fecha_contrato') {
                const ad = parseDate(a[sortBy])?.getTime() || 0;
                const bd = parseDate(b[sortBy])?.getTime() || 0;
                return (ad - bd) * dir;
            }
            const av = a[sortBy];
            const bv = b[sortBy];
            if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
            return String(av ?? '').toLowerCase().localeCompare(String(bv ?? '').toLowerCase()) * dir;
        });
        return arr;
    }, [filtered, sortBy, sortOrder]);

    const totalPages = Math.max(1, Math.ceil(sorted.length / pageSize));
    const currentPage = Math.min(page, totalPages);
    const pageItems = useMemo(() => {
        const start = (currentPage - 1) * pageSize;
        return sorted.slice(start, start + pageSize);
    }, [sorted, currentPage]);

    const toggleSort = (key) => {
        if (sortBy === key) setSortOrder(prev => prev === 'asc' ? 'desc' : 'asc');
        else { setSortBy(key); setSortOrder('asc'); }
    };

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
                            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
                                <div className="flex items-center gap-3">
                                    <Input
                                        placeholder="Buscar por productor o comparativa..."
                                        value={search}
                                        onChange={(e) => { setSearch(e.target.value); setPage(1); }}
                                        className="w-72"
                                    />
                                    <div className="flex items-center gap-4 text-sm">
                                        <div className="flex items-center gap-2">
                                            <Switch id="f-expired" checked={filterExpired} onCheckedChange={setFilterExpired} />
                                            <label htmlFor="f-expired">Vencidos</label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Switch id="f-60" checked={filter60} onCheckedChange={setFilter60} />
                                            <label htmlFor="f-60">Vencen ≤ 60 días</label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Switch id="f-90" checked={filter90} onCheckedChange={setFilter90} />
                                            <label htmlFor="f-90">Vencen 61–90 días</label>
                                        </div>
                                    </div>
                                </div>
                                <Link href={route('contracts.create')} className="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                    Agregar Contrato
                                </Link>
                            </div>
                            <h3 className="text-lg font-medium text-gray-900 mb-2">Listado de Contratos</h3>

                            <div className="overflow-x-auto">
                                <table className="min-w-full divide-y divide-gray-200">
                                    <thead className="bg-gray-50">
                                        <tr>
                                            <th onClick={() => toggleSort('user')} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">Productor {sortBy==='user' ? (sortOrder==='asc'?'▲':'▼') : ''}</th>
                                            <th onClick={() => toggleSort('fecha_contrato')} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">Fecha Contrato {sortBy==='fecha_contrato' ? (sortOrder==='asc'?'▲':'▼') : ''}</th>
                                            <th onClick={() => toggleSort('vencimiento')} className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer">Vencimiento {sortBy==='vencimiento' ? (sortOrder==='asc'?'▲':'▼') : ''}</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comisión</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Flete a Huerto</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rebate</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bonificación</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tarifa Premium</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Comparativa</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descuento Fruta Comercial</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Archivo de Contrato</th>
                                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody className="bg-white divide-y divide-gray-200">
                                        {pageItems.length > 0 ? (
                                            pageItems.map((contract) => (
                                                <tr key={contract.id}>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.user ? contract.user.name : 'N/A'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.fecha_contrato}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.vencimiento}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{(() => { const st = getStatus(contract); return (<Badge className={st.color}>{st.label}</Badge>); })()}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.comision}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.flete_a_huerto}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.rebate ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.bonificacion ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.tarifa_premium ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.comparativa}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">{contract.descuento_fruta_comercial ? 'SI' : 'NO'}</td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        {contract.contract_file_path ? (
                                                            <a
                                                                href={`/storage/${contract.contract_file_path}`}
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                aria-label="Ver archivo de contrato"
                                                                className="inline-flex items-center justify-center"
                                                            >
                                                                <FileText className="h-5 w-5 text-blue-600 hover:text-blue-800" />
                                                            </a>
                                                        ) : (
                                                            <span className="text-gray-400">—</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-4 whitespace-nowrap">
                                                        <Link href={route('contracts.edit', contract.id)}>
                                                            <Button size="sm" variant="outline">Editar</Button>
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))
                                        ) : (
                                            <tr>
                                                <td colSpan="12" className="px-6 py-4 whitespace-nowrap text-center text-gray-500">No hay contratos registrados.</td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                            {/* Pagination */}
                            <div className="mt-4 flex items-center justify-between">
                                <div className="text-sm text-gray-600">Página {currentPage} de {totalPages} — {sorted.length} resultados</div>
                                <div className="flex items-center gap-2">
                                    <Button variant="outline" size="sm" disabled={currentPage<=1} onClick={() => setPage(p => Math.max(1, p-1))}>Anterior</Button>
                                    {Array.from({ length: totalPages }).slice(0, 7).map((_, i) => {
                                        const num = i + 1;
                                        return (
                                            <Button key={num} variant={num===currentPage? 'default':'outline'} size="sm" onClick={() => setPage(num)}>{num}</Button>
                                        );
                                    })}
                                    <Button variant="outline" size="sm" disabled={currentPage>=totalPages} onClick={() => setPage(p => Math.min(totalPages, p+1))}>Siguiente</Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
