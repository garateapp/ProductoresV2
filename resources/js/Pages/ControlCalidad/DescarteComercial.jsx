import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Badge } from '@/Components/ui/badge';
import { Plus, Trash2, Sparkles, Info, Pencil } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
    TableFooter,
} from '@/Components/ui/table';

const normalizeText = (value) => (value ?? '').toString().trim().toLowerCase();

const SignatureCanvas = ({ value, onChange, error }) => {
    const canvasRef = useRef(null);
    const drawing = useRef(false);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (! canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const resize = () => {
            const ratio = window.devicePixelRatio ?? 1;
            const width = canvas.offsetWidth;
            const height = 180;
            canvas.width = width * ratio;
            canvas.height = height * ratio;
            canvas.style.width = `${width}px`;
            canvas.style.height = `${height}px`;
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.strokeStyle = '#111827';
            ctx.clearRect(0, 0, width, height);
            if (value) {
                const img = new Image();
                img.onload = () => ctx.drawImage(img, 0, 0, width, height);
                img.src = value;
            }
        };

        resize();
        window.addEventListener('resize', resize);
        return () => window.removeEventListener('resize', resize);
    }, [value]);

    useEffect(() => {
        const canvas = canvasRef.current;
        if (! canvas) {
            return;
        }

        const ctx = canvas.getContext('2d');
        const getPoint = (event) => {
            const rect = canvas.getBoundingClientRect();
            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
            };
        };

        const start = (event) => {
            event.preventDefault();
            drawing.current = true;
            const { x, y } = getPoint(event);
            ctx.beginPath();
            ctx.moveTo(x, y);
        };

        const draw = (event) => {
            if (! drawing.current) {
                return;
            }
            event.preventDefault();
            const { x, y } = getPoint(event);
            ctx.lineTo(x, y);
            ctx.stroke();
        };

        const end = (event) => {
            if (! drawing.current) {
                return;
            }
            event.preventDefault();
            drawing.current = false;
            onChange?.(canvas.toDataURL('image/png'));
        };

        canvas.addEventListener('pointerdown', start);
        canvas.addEventListener('pointermove', draw);
        window.addEventListener('pointerup', end);

        return () => {
            canvas.removeEventListener('pointerdown', start);
            canvas.removeEventListener('pointermove', draw);
            window.removeEventListener('pointerup', end);
        };
    }, [onChange]);

    const handleClear = () => {
        const canvas = canvasRef.current;
        if (! canvas) {
            return;
        }
        const ctx = canvas.getContext('2d');
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        onChange?.('');
    };

    return (
        <div>
            <canvas
                ref={canvasRef}
                className={`w-full border ${error ? 'border-red-500' : 'border-gray-300'} rounded-md bg-white`}
            />
            <div className="mt-2 flex justify-end">
                <Button type="button" variant="secondary" onClick={handleClear}>
                    Limpiar firma
                </Button>
            </div>
            {error && <p className="mt-1 text-sm text-red-600">{error}</p>}
        </div>
    );
};

export default function DescarteComercial({ auth, parametros, defaultDate }) {
    const { flash } = usePage().props;

    const defaultParametroId = parametros[0]?.id ? String(parametros[0].id) : '';

    const createEmptyDefect = useCallback(
        () => ({
            parametro_id: defaultParametroId,
            valor_id: '',
            comercial: '',
            desecho: '',
        }),
        [defaultParametroId]
    );

    const buildInitialForm = useCallback(
        () => ({
            fecha: defaultDate,
            linea: '',
            turno: '',
            proceso: '',
            productor: '',
            especie: '',
            variedad: '',
            lote: '',
            frutos: '',
            observaciones: '',
            signature_data: '',
            defects: [createEmptyDefect()],
        }),
        [defaultDate, createEmptyDefect]
    );

    const { data, setData, post, processing, errors, reset } = useForm(buildInitialForm());
    const [lookupStatus, setLookupStatus] = useState(null);
    const [lookupMessage, setLookupMessage] = useState('');
    const [defectModalOpen, setDefectModalOpen] = useState(false);
    const [activeDefectIndex, setActiveDefectIndex] = useState(null);
    const [modalError, setModalError] = useState('');
    const [defectForm, setDefectForm] = useState(createEmptyDefect());

    useEffect(() => {
        if (flash?.pdf_url) {
            window.open(flash.pdf_url, '_blank', 'noopener');
        }
    }, [flash?.pdf_url]);

    const getValoresForParametro = useCallback(
        (parametroId, especie) => {
            const parametro = parametros.find((item) => String(item.id) === String(parametroId));
            if (! parametro) {
                return [];
            }
            const normalized = normalizeText(especie);
            return parametro.valors.filter((valor) => {
                if (! valor.especie) {
                    return true;
                }
                return normalizeText(valor.especie) === normalized;
            });
        },
        [parametros]
    );

    useEffect(() => {
        setData((current) => {
            const updated = current.defects.map((defect) => {
                const valores = getValoresForParametro(defect.parametro_id, current.especie);
                if (! valores.some((valor) => String(valor.id) === String(defect.valor_id))) {
                    return { ...defect, valor_id: '' };
                }
                return defect;
            });
            const changed = updated.some((defect, idx) => defect.valor_id !== current.defects[idx].valor_id);
            return changed ? { ...current, defects: updated } : current;
        });
    }, [data.especie, getValoresForParametro, setData]);

    useEffect(() => {
        if (! defectModalOpen) {
            return;
        }
        setDefectForm((current) => {
            const valores = getValoresForParametro(current.parametro_id, data.especie);
            if (! valores.some((valor) => String(valor.id) === String(current.valor_id))) {
                return { ...current, valor_id: '' };
            }
            return current;
        });
    }, [defectModalOpen, defectForm.parametro_id, data.especie, getValoresForParametro]);

    const modalValores = getValoresForParametro(defectForm.parametro_id, data.especie);

    const openCreateDefectModal = () => {
        setActiveDefectIndex(null);
        setDefectForm(createEmptyDefect());
        setModalError('');
        setDefectModalOpen(true);
    };

    const openEditDefectModal = (index) => {
        setActiveDefectIndex(index);
        setDefectForm({ ...data.defects[index] });
        setModalError('');
        setDefectModalOpen(true);
    };

    const closeDefectModal = () => {
        setDefectModalOpen(false);
        setModalError('');
    };

    const handleDefectFormChange = (field, value) => {
        setDefectForm((prev) => ({ ...prev, [field]: value }));
    };

    const saveDefect = () => {
        if (
            ! defectForm.parametro_id ||
            ! defectForm.valor_id ||
            defectForm.comercial === '' ||
            defectForm.desecho === ''
        ) {
            setModalError('Completa todos los campos antes de guardar.');
            return;
        }

        const payload = {
            parametro_id: defectForm.parametro_id,
            valor_id: defectForm.valor_id,
            comercial: String(defectForm.comercial),
            desecho: String(defectForm.desecho),
        };

        if (activeDefectIndex === null) {
            setData('defects', [...data.defects, payload]);
        } else {
            setData(
                'defects',
                data.defects.map((defect, idx) => (idx === activeDefectIndex ? payload : defect))
            );
        }

        closeDefectModal();
    };

    const removeDefect = (index) => {
        setData(
            'defects',
            data.defects.filter((_, idx) => idx !== index)
        );
    };

    const handleLookup = async () => {
        if (! data.proceso) {
            setLookupStatus('error');
            setLookupMessage('Ingresa un número de proceso.');
            return;
        }

        try {
            setLookupStatus('loading');
            setLookupMessage('Buscando proceso…');
            const response = await fetch(
                route('commercial-discards.lookup-process', { n_proceso: data.proceso }),
                {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                }
            );

            if (! response.ok) {
                throw new Error();
            }

            const payload = await response.json();
            setData({
                ...data,
                productor: payload.productor ?? data.productor,
                especie: payload.especie ?? data.especie,
                variedad: payload.variedad ?? data.variedad,
                lote: payload.lote ?? data.lote,
                frutos: payload.frutos ?? data.frutos,
            });
            setLookupStatus('success');
            setLookupMessage('Proceso encontrado y datos prellenados.');
        } catch (error) {
            setLookupStatus('error');
            setLookupMessage('No fue posible encontrar el proceso.');
        }
    };

    const parametroById = useCallback(
        (id) => parametros.find((parametro) => String(parametro.id) === String(id)),
        [parametros]
    );

    const valorName = useCallback(
        (parametroId, valorId) => {
            const parametro = parametroById(parametroId);
            if (! parametro) {
                return '';
            }
            return parametro.valors.find((valor) => String(valor.id) === String(valorId))?.name ?? '';
        },
        [parametroById]
    );

    const totals = useMemo(() => {
        return data.defects.reduce(
            (acc, defect) => {
                acc.comercial += Number(defect.comercial || 0);
                acc.desecho += Number(defect.desecho || 0);
                return acc;
            },
            { comercial: 0, desecho: 0 }
        );
    }, [data.defects]);

    const remainingFrutos = useMemo(() => {
        const totalRegistrado = totals.comercial + totals.desecho;
        const totalFrutos = Number(data.frutos || 0);
        return Math.max(totalFrutos - totalRegistrado, 0);
    }, [data.frutos, totals]);

    const canSubmit =
        data.especie.trim() !== '' &&
        data.defects.length > 0 &&
        data.defects.every(
            (defect) =>
                defect.parametro_id &&
                defect.valor_id &&
                defect.comercial !== '' &&
                defect.desecho !== ''
        );

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('commercial-discards.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset(buildInitialForm());
                setLookupStatus(null);
                setLookupMessage('');
            },
        });
    };

    return (
        <>
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Descarte Comercial</h2>}
        >
            <Head title="Descarte Comercial" />

            <div className="py-12">
                <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900 space-y-6">
                            <p className="text-sm text-gray-600">
                                Registra los descartes comerciales y genera automáticamente el reporte PDF “Descarte Comercial
                                Cerezas”.
                            </p>

                            {flash?.success && (
                                <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                                    {flash.success}
                                </div>
                            )}

                            {flash?.error && (
                                <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                                    {flash.error}
                                </div>
                            )}

                            <form onSubmit={handleSubmit} className="space-y-6">
                                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                                    <div>
                                        <Label htmlFor="fecha">Fecha y hora</Label>
                                        <Input
                                            id="fecha"
                                            type="datetime-local"
                                            value={data.fecha}
                                            onChange={(event) => setData('fecha', event.target.value)}
                                            required
                                        />
                                        {errors.fecha && <p className="mt-1 text-sm text-red-600">{errors.fecha}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="linea">N° de línea</Label>
                                        <Input
                                            id="linea"
                                            type="text"
                                            value={data.linea}
                                            onChange={(event) => setData('linea', event.target.value)}
                                            required
                                        />
                                        {errors.linea && <p className="mt-1 text-sm text-red-600">{errors.linea}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="turno">Turno</Label>
                                        <Input
                                            id="turno"
                                            type="text"
                                            value={data.turno}
                                            onChange={(event) => setData('turno', event.target.value)}
                                            required
                                        />
                                        {errors.turno && <p className="mt-1 text-sm text-red-600">{errors.turno}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="proceso">N° de proceso</Label>
                                        <div className="flex gap-2">
                                            <Input
                                                id="proceso"
                                                type="text"
                                                value={data.proceso}
                                                onChange={(event) => setData('proceso', event.target.value)}
                                                required
                                            />
                                            <Button type="button" variant="outline" onClick={handleLookup}>
                                                Prefill
                                            </Button>
                                        </div>
                                        {errors.proceso && <p className="mt-1 text-sm text-red-600">{errors.proceso}</p>}
                                        {lookupMessage && (
                                            <p
                                                className={`mt-1 text-xs ${
                                                    lookupStatus === 'success'
                                                        ? 'text-green-600'
                                                        : lookupStatus === 'error'
                                                          ? 'text-red-600'
                                                          : 'text-gray-500'
                                                }`}
                                            >
                                                {lookupMessage}
                                            </p>
                                        )}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="productor">Productor</Label>
                                        <Input
                                            id="productor"
                                            type="text"
                                            value={data.productor}
                                            onChange={(event) => setData('productor', event.target.value)}
                                            required
                                        />
                                        {errors.productor && <p className="mt-1 text-sm text-red-600">{errors.productor}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="especie">Especie</Label>
                                        <Input
                                            id="especie"
                                            type="text"
                                            value={data.especie}
                                            onChange={(event) => setData('especie', event.target.value)}
                                            required
                                        />
                                        {errors.especie && <p className="mt-1 text-sm text-red-600">{errors.especie}</p>}
                                    </div>
                                </div>

                                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                    <div>
                                        <Label htmlFor="variedad">Variedad</Label>
                                        <Input
                                            id="variedad"
                                            type="text"
                                            value={data.variedad}
                                            onChange={(event) => setData('variedad', event.target.value)}
                                            required
                                        />
                                        {errors.variedad && <p className="mt-1 text-sm text-red-600">{errors.variedad}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="lote">N° de lote</Label>
                                        <Input
                                            id="lote"
                                            type="text"
                                            value={data.lote}
                                            onChange={(event) => setData('lote', event.target.value)}
                                            required
                                        />
                                        {errors.lote && <p className="mt-1 text-sm text-red-600">{errors.lote}</p>}
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="frutos">N° de frutos</Label>
                                    <Input
                                        id="frutos"
                                        type="number"
                                        min="0"
                                        value={data.frutos}
                                        onChange={(event) => setData('frutos', event.target.value)}
                                        required
                                    />
                                    {errors.frutos && <p className="mt-1 text-sm text-red-600">{errors.frutos}</p>}
                                </div>

                                <div className="space-y-5">
                                    <div className="grid gap-4 md:grid-cols-3">
                                        <div className="rounded-2xl border border-slate-200 bg-gradient-to-br from-white to-slate-50 p-4 shadow-sm">
                                            <p className="text-xs uppercase tracking-wider text-slate-500">Defectos registrados</p>
                                            <div className="mt-2 flex items-end gap-2">
                                                <span className="text-3xl font-semibold text-slate-900">{data.defects.length}</span>
                                                <Badge variant="secondary" className="bg-slate-100 text-slate-700">
                                                    activos
                                                </Badge>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50/70 to-white p-4 shadow-sm">
                                            <p className="text-xs uppercase tracking-wider text-amber-600">Kilos registrados</p>
                                            <div className="mt-2 flex items-center justify-between">
                                                <div>
                                                    <p className="text-sm text-amber-700">Comercial</p>
                                                    <p className="text-xl font-semibold text-amber-900">{totals.comercial}</p>
                                                </div>
                                                <div>
                                                    <p className="text-sm text-rose-700">Desecho</p>
                                                    <p className="text-xl font-semibold text-rose-900">{totals.desecho}</p>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="rounded-2xl border border-emerald-200 bg-gradient-to-br from-emerald-50/70 to-white p-4 shadow-sm">
                                            <p className="text-xs uppercase tracking-wider text-emerald-600">Frutos restantes</p>
                                            <div className="mt-2 flex items-center gap-2">
                                                <span className="text-3xl font-semibold text-emerald-900">{remainingFrutos}</span>
                                                <Sparkles className="h-5 w-5 text-emerald-500" />
                                            </div>
                                            <p className="text-xs text-emerald-700">
                                                Calculado contra el total ingresado en “N° de frutos”.
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-dashed border-gray-300 bg-gray-50/70 px-4 py-3">
                                        <div className="flex items-center gap-2 text-sm text-gray-600">
                                            <Info className="h-4 w-4" />
                                            <span>Los defectos se gestionan desde este panel. Usa la tabla para editar o eliminar.</span>
                                        </div>
                                        <Button type="button" variant="outline" onClick={openCreateDefectModal} className="gap-2">
                                            <Plus className="h-4 w-4" />
                                            Añadir defecto
                                        </Button>
                                    </div>

                                    {errors.defects && <p className="text-sm text-red-600">{errors.defects}</p>}

                                    <div className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
                                    <Table>
                                            <TableHeader className="bg-gray-50">
                                                <TableRow>
                                                    <TableHead>Tipo</TableHead>
                                                    <TableHead>Valor</TableHead>
                                                    <TableHead>Comercial</TableHead>
                                                    <TableHead>Desecho</TableHead>
                                                    <TableHead className="text-right">Acciones</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {data.defects.length === 0 && (
                                                    <TableRow>
                                                        <TableCell colSpan={5} className="text-center text-sm text-gray-500">
                                                            Aún no hay defectos registrados.
                                                        </TableCell>
                                                    </TableRow>
                                                )}
                                                {data.defects.map((defect, index) => {
                                                    const parametro = parametroById(defect.parametro_id);
                                                    const valor = valorName(defect.parametro_id, defect.valor_id);
                                                    return (
                                                        <TableRow key={`defecto-row-${index}`}>
                                                            <TableCell>
                                                                <Badge variant="secondary" className="bg-slate-100 text-slate-700">
                                                                    {parametro?.name ?? 'N/D'}
                                                                </Badge>
                                                            </TableCell>
                                                            <TableCell>{valor || 'N/D'}</TableCell>
                                                            <TableCell>{defect.comercial || 0}</TableCell>
                                                            <TableCell>{defect.desecho || 0}</TableCell>
                                                            <TableCell className="flex justify-end gap-2">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-gray-600 hover:text-greenex-dark-green"
                                                                    onClick={() => openEditDefectModal(index)}
                                                                >
                                                                    <Pencil className="h-4 w-4" />
                                                                </Button>
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    className="text-red-500 hover:text-red-700"
                                                                    onClick={() => removeDefect(index)}
                                                                >
                                                                    <Trash2 className="h-4 w-4" />
                                                                </Button>
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                            </TableBody>
                                            <TableFooter>
                                                <TableRow>
                                                    <TableCell colSpan={2}>Totales</TableCell>
                                                    <TableCell className="font-semibold text-amber-900">{totals.comercial}</TableCell>
                                                    <TableCell className="font-semibold text-rose-900">{totals.desecho}</TableCell>
                                                    <TableCell />
                                                </TableRow>
                                            </TableFooter>
                                        </Table>
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="observaciones">Observaciones</Label>
                                    <Textarea
                                        id="observaciones"
                                        rows={3}
                                        value={data.observaciones}
                                        onChange={(event) => setData('observaciones', event.target.value)}
                                    />
                                    {errors.observaciones && <p className="mt-1 text-sm text-red-600">{errors.observaciones}</p>}
                                </div>

                                <div>
                                    <Label>Firma</Label>
                                    <SignatureCanvas
                                        value={data.signature_data}
                                        onChange={(value) => setData('signature_data', value)}
                                        error={errors.signature_data}
                                    />
                                </div>

                                <div className="flex items-center justify-end">
                                    <Button type="submit" disabled={processing || ! canSubmit}>
                                        {processing ? 'Guardando…' : 'Generar reporte'}
                                    </Button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>

        <Dialog
            open={defectModalOpen}
            onOpenChange={(open) => {
                if (! open) {
                    closeDefectModal();
                }
            }}
        >
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{activeDefectIndex === null ? 'Agregar defecto' : 'Editar defecto'}</DialogTitle>
                    <DialogDescription>
                        Define el tipo, valor y pesos asociados al defecto. Se filtrarán según la especie ingresada.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4">
                    <div>
                        <Label>Tipo de defecto</Label>
                        <select
                            className="mt-1 block w-full rounded-md border-gray-300 focus:border-greenex-dark-green focus:ring-greenex-dark-green"
                            value={defectForm.parametro_id ?? ''}
                            onChange={(event) => handleDefectFormChange('parametro_id', event.target.value)}
                        >
                            {parametros.map((parametro) => (
                                <option key={parametro.id} value={parametro.id}>
                                    {parametro.name}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div>
                        <Label>Valor</Label>
                        <select
                            className="mt-1 block w-full rounded-md border-gray-300 focus:border-greenex-dark-green focus:ring-greenex-dark-green"
                            value={defectForm.valor_id ?? ''}
                            onChange={(event) => handleDefectFormChange('valor_id', event.target.value)}
                        >
                            <option value="">Seleccione…</option>
                            {modalValores.map((valor) => (
                                <option key={valor.id} value={valor.id}>
                                    {valor.name}
                                </option>
                            ))}
                        </select>
                        {modalValores.length === 0 && (
                            <p className="mt-1 text-xs text-amber-600">
                                No hay valores disponibles para la especie ingresada.
                            </p>
                        )}
                    </div>

                    <div className="grid gap-4 md:grid-cols-2">
                        <div>
                            <Label>Comercial</Label>
                            <Input
                                type="number"
                                min="0"
                                value={defectForm.comercial}
                                onChange={(event) => handleDefectFormChange('comercial', event.target.value)}
                            />
                        </div>
                        <div>
                            <Label>Desecho</Label>
                            <Input
                                type="number"
                                min="0"
                                value={defectForm.desecho}
                                onChange={(event) => handleDefectFormChange('desecho', event.target.value)}
                            />
                        </div>
                    </div>

                    {modalError && <p className="text-sm text-red-600">{modalError}</p>}
                </div>

                <DialogFooter className="gap-2">
                    <Button type="button" variant="ghost" onClick={closeDefectModal}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={saveDefect}>
                        Guardar defecto
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
        </>
    );
}
