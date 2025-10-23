import React, { useState, useEffect, useCallback } from "react";
import { useForm, usePage, router, Link } from "@inertiajs/react";
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/Components/ui/table";
import { Input } from "@/Components/ui/input";
import { FileText, Trash2, UploadCloud, Eye, Send } from "lucide-react";
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from "@/Components/ui/dialog";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Switch } from "@/Components/ui/switch";
import { Label } from "@/Components/ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select";
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from "@/Components/ui/collapsible";
import { Badge } from "@/Components/ui/badge"; // Import Badge
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import {
    Package,
    Apple,
    Grape,
    Calendar,
    Weight,
    ChevronDown,
    Cherry,
} from "lucide-react"; // Import new icons
//import { toast ,Toaster} from '@/Components/ui/sonner'; // Import toast
import { Toaster, toast } from "sonner";

export default function Index({
    procesos,
    filters,
    parametros,
    photoTypes = [],
    especies = [],
    variedades = [],
}) {
    console.log(parametros);
    const { flash } = usePage().props;

    const [openCollapsibles, setOpenCollapsibles] = useState({}); // State to manage collapsible open/close

    const toggleCollapsible = (procesoId) => {
        setOpenCollapsibles((prev) => ({
            ...prev,
            [procesoId]: !prev[procesoId],
        }));
    };

    const [photoTypesState, setPhotoTypesState] = useState(photoTypes || []);
    const { data: filterData, setData: setFilterData } = useForm({
        search: filters.search || '',
        especie_id: filters.especie_id || '',
        variedad_id: filters.variedad_id || '',
    });

    const handleSearchChange = (e) => {
        const value = e.target.value;
        setFilterData('search', value);
        router.get(route('processed-fruit-quality.index', { ...filterData, search: value }), { preserveState: true, replace: true });
    };

    const handleEspecieFilter = (especieId) => {
        setFilterData('especie_id', especieId);
        setFilterData('variedad_id', '');
        router.get(route('processed-fruit-quality.index', { ...filterData, especie_id: especieId, variedad_id: '' }), { preserveState: true, replace: true });
    };

    const handleVariedadFilter = (variedadId) => {
        setFilterData('variedad_id', variedadId);
        router.get(route('processed-fruit-quality.index', { ...filterData, variedad_id: variedadId }), { preserveState: true, replace: true });
    };

    useEffect(() => {
        if (!photoTypes || photoTypes.length === 0) {
            fetch(route('photo-types.all'))
                .then(r => r.json())
                .then(data => setPhotoTypesState(data || []))
                .catch(() => setPhotoTypesState([]));
        }
    }, []);

    const qualityFormState = useForm({
        proceso_id: null,
        numero_de_caja: "",
        numero_embaladora_mano: "",
        peso_exacto_caja: "",
        codigo_embalaje: "",
        categoria: "",
        tolerance_label: '1S',
        estado: 'Aprobada',
        destino: "",
        calibre: "",
        color_cubrimiento: "",
        color_fondo: "",
        t_muestra: 100,
        observaciones: "",
        responsable: "",
        // Add fields from original Calidad model
        materia_vegetal: false,
        piedras: false,
        barro: false,
        pedicelo_largo: false,
        racimo: false,
        esponjas: false,
        h_esponjas: "BUENO",
        llenado_tottes: "CORRECTO",
        embalaje: "",
        obs_ext: "",
    });

    const qualityData = qualityFormState.data;
    const setQualityData = qualityFormState.setData;
    const postQuality = qualityFormState.post;
    const putQuality = qualityFormState.put;
    const processingQuality = qualityFormState.processing;
    const errorsQuality = qualityFormState.errors;
    const resetQuality = qualityFormState.reset;

    const isCherries = useCallback((proceso) => {
        const name = String(proceso?.especie || '').toLowerCase();
        const id = proceso?.especie_id ?? proceso?.id_especie;
        return name.includes('cherr') || id === 7;
    }, []);

    // --- Tolerancias por defecto (por categoría) ---
    const [toleranceLabel, setToleranceLabel] = useState('1S'); // Etiqueta de evaluación: 1S | 1-2 | 3 | 4
    const CATEGORY_MAP = {
        'CAT 1': '1S',
        'CAT 2': '1-2',
        'COMERCIAL': '3',
        'SUPERMERCADO': '3',
        'VEGA': '3',
        'DESECHO': '4',
    };

    const TOLERANCES = {
        CONDICION: {
            // nombre canonico -> { '1S': n, '1-2': n, '3': n, '4': n }
            'PUDRICIONES PARDAS': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'PUDRICIONES NEGRAS': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'DANO PAJARO': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'PEDICELOS DESHIDRATADOS': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'HERIDAS ABIERTAS': { '1S': 0, '1-2': 1, '3': 1, '4': 1 },
            'SUTURA ABIERTA': { '1S': 2, '1-2': 2, '3': 4, '4': 6 },
            'FRUTA BLANDA': { '1S': 0, '1-2': 4, '3': 6, '4': 10 },
            'PITTING': { '1S': 4, '1-2': 6, '3': 6, '4': 12 },
            'MACHUCONES': { '1S': 2, '1-2': 5, '3': 7, '4': 10 },
            'HOMBROS BLANDOS': { '1S': 4, '1-2': 6, '3': 6, '4': 12 },
        },
        CALIDAD: {
            'DESHIDRATACION DE FRUTO': { '1S': 0, '1-2': 0, '3': 2, '4': 4 },
            'RESIDUO QUIMICO': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'POLVO / TIERRA': { '1S': 0, '1-2': 0, '3': 0, '4': 0 },
            'FRUTO DEFORME / DOBLE': { '1S': 2, '1-2': 4, '3': 6, '4': 8 },
            'MANCHAS / RUSSET': { '1S': 4, '1-2': 4, '3': 6, '4': 8 },
            'DANO TRIPS (MANCHA DORADA)': { '1S': 4, '1-2': 4, '3': 6, '4': 8 },
            'PARTIDURA / MEDIA LUNA': { '1S': 4, '1-2': 8, '3': 10, '4': 15 },
            'HERIDAS CICATRIZADAS': { '1S': 4, '1-2': 4, '3': 6, '4': 8 },
        },
        TOTAL: {
            CONDICION: { '1S': 4, '1-2': 6, '3': 7, '4': 12 },
            CALIDAD: { '1S': 8, '1-2': 10, '3': 10, '4': 15 },
        }
    };

    const normalizeText = (s = '') => {
        let t = String(s).toUpperCase();
        const map = { 'Á':'A','É':'E','Í':'I','Ó':'O','Ú':'U','Ü':'U','Ñ':'N' };
        t = t.replace(/[ÁÉÍÓÚÜÑ]/g, c => map[c] || c);
        t = t.replace(/\s+\(.*?\)/g, ''); // remove parentheses like (<65 UD) or (B*)
        t = t.replace(/\s+/g, ' ').trim();
        return t;
    };

    const mapToCanonicalDefect = (raw = '') => {
        const t = normalizeText(raw);
        // coarse contains mappings
        if (t.includes('PUDRICION') && t.includes('PARDA')) return 'PUDRICIONES PARDAS';
        if (t.includes('PUDRICION') && t.includes('NEGRA')) return 'PUDRICIONES NEGRAS';
        if (t.includes('PAJAR')) return 'DANO PAJARO';
        if (t.includes('PEDICEL') && t.includes('DESHIDRA')) return 'PEDICELOS DESHIDRATADOS';
        if (t.includes('HERIDA') && t.includes('ABIERTA')) return 'HERIDAS ABIERTAS';
        if (t.includes('SUTURA') && t.includes('ABIERTA')) return 'SUTURA ABIERTA';
        if (t.includes('FRUTA BLANDA')) return 'FRUTA BLANDA';
        if (t.includes('PITTING')) return 'PITTING';
        if (t.includes('MACHU')) return 'MACHUCONES';
        if (t.includes('HOMBRO') && t.includes('BLANDO')) return 'HOMBROS BLANDOS';
        if (t.includes('DESHIDRAT') && t.includes('FRUTO')) return 'DESHIDRATACION DE FRUTO';
        if (t.includes('RESIDUO') && t.includes('QUIM')) return 'RESIDUO QUIMICO';
        if ((t.includes('POLVO') || t.includes('TIERRA'))) return 'POLVO / TIERRA';
        if ((t.includes('DEFORME') || t.includes('DOBLE'))) return 'FRUTO DEFORME / DOBLE';
        if (t.includes('RUSSET') || t.includes('MANCHA') && !t.includes('TRIPS')) return 'MANCHAS / RUSSET';
        if (t.includes('TRIPS') || t.includes('DORADA')) return 'DANO TRIPS (MANCHA DORADA)';
        if (t.includes('PARTIDURA') || t.includes('MEDIA LUNA')) return 'PARTIDURA / MEDIA LUNA';
        if (t.includes('CICATRIZ')) return 'HERIDAS CICATRIZADAS';
        return t; // fallback
    };

    // Auto-aprobar o rechazar según tolerancias acumuladas (movido más abajo para evitar TDZ)

    const {
        data: detailData,
        setData: setDetailData,
        post: postDetail,
        processing: processingDetail,
        errors: errorsDetail,
        reset: resetDetail,
    } = useForm({
        processed_fruit_quality_id: null,
        parametro_id: "",
        valor_id: "",
        valor_text: "",
        cantidad_muestra: "",
        exportable: false,
        temperatura: "",
        valor_presion: "",
    });

    const {
        data: photoData,
        setData: setPhotoData,
        post: postPhoto,
        processing: processingPhoto,
        errors: errorsPhoto,
        reset: resetPhoto,
    } = useForm({
        photo: null,
        photo_type_id: "",
        observations: "",
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [activeTab, setActiveTab] = useState("general");
    const [selectedProceso, setSelectedProceso] = useState(null);
    const [valores, setValores] = useState([]);
    const [qualityId, setQualityId] = useState(null);
    const [detallesAgregados, setDetallesAgregados] = useState([]);
    const [defectosAgregados, setDefectosAgregados] = useState([]);
    // Removed Desorden Fisiológico and Curva de Calibre states
    const [indiceMadurezAgregados, setIndiceMadurezAgregados] = useState([]);
    const [photos, setPhotos] = useState([]);

    // Auto-aprobar o rechazar según tolerancias acumuladas
    useEffect(() => {
        try {
            const catKey = (toleranceLabel || qualityData.tolerance_label) || (CATEGORY_MAP[normalizeText(qualityData.categoria || '')] || '1S');
            const byDefect = { CONDICION: {}, CALIDAD: {} };
            let sumCond = 0; let sumCal = 0;
            (defectosAgregados || []).forEach(d => {
                const tipo = normalizeText(d.tipo_item || '');
                const det = mapToCanonicalDefect(d.detalle_item || '');
                const val = Number(d.porcentaje_muestra) || 0;
                const group = tipo.includes('CONDIC') ? 'CONDICION' : (tipo.includes('CALID') ? 'CALIDAD' : null);
                if (!group) return;
                byDefect[group][det] = (byDefect[group][det] || 0) + val;
                if (group === 'CONDICION') sumCond += val; else sumCal += val;
            });
            const totAllowedCond = TOLERANCES.TOTAL.CONDICION[catKey];
            const totAllowedCal = TOLERANCES.TOTAL.CALIDAD[catKey];
            const overTotalCond = sumCond > totAllowedCond;
            const overTotalCal = sumCal > totAllowedCal;
            let anyOver = overTotalCond || overTotalCal;
            if (!anyOver) {
                for (const [name, val] of Object.entries(byDefect.CONDICION)) {
                    const tol = TOLERANCES.CONDICION[name]?.[catKey];
                    if (tol != null && val > tol) { anyOver = true; break; }
                }
            }
            if (!anyOver) {
                for (const [name, val] of Object.entries(byDefect.CALIDAD)) {
                    const tol = TOLERANCES.CALIDAD[name]?.[catKey];
                    if (tol != null && val > tol) { anyOver = true; break; }
                }
            }
            const computed = anyOver ? 'Rechazada' : 'Aprobada';
            if (qualityData.estado !== computed) {
                setQualityData('estado', computed);
                if (qualityId) {
                    fetch(route('processed-fruit-quality.updateStatus', qualityId), {
                        method: 'PATCH',
                        headers: {
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ estado: computed })
                    }).then((r) => { if (r.ok) router.reload({ only: ['procesos'] }); }).catch(() => {});
                }
            }
        } catch (e) { /* noop */ }
    }, [defectosAgregados, toleranceLabel, qualityId, qualityData.tolerance_label, qualityData.categoria]);

    const fetchQualityData = useCallback(
        async (proceso, qualityIdToFetch = null) => {
            if (!proceso) return;
            try {
                // Always ask backend for current/last quality
                const response = await fetch(
                    route('processed-fruit-quality.getQuality', {
                        proceso: proceso.id,
                        quality_id: qualityIdToFetch || '',
                    })
                );
                const existingQuality = await response.json();

                if (existingQuality) {
                    const transformedQuality = {
                        ...existingQuality,
                        materia_vegetal: existingQuality.materia_vegetal === 'SI',
                        piedras: existingQuality.piedras === 'SI',
                        barro: existingQuality.barro === 'SI',
                        pedicelo_largo: existingQuality.pedicelo_largo === 'SI',
                        racimo: existingQuality.racimo === 'SI',
                        esponjas: existingQuality.esponjas === 'SI',
                    };
                    setQualityData(transformedQuality);
                    setQualityId(existingQuality.id);
                    setPhotos(existingQuality.photos || []);
                    if (existingQuality.tolerance_label) {
                        setToleranceLabel(existingQuality.tolerance_label);
                    }
                }

                // Fetch details too
                const detailsResponse = await fetch(
                    route('processed-fruit-quality.getDetails', {
                        proceso: proceso.id,
                        quality_id: (existingQuality && existingQuality.id) || qualityIdToFetch || '',
                    })
                );
                const data = await detailsResponse.json();
                setDetallesAgregados(data.detalles || []);
                setDefectosAgregados(data.defectos || []);
                setIndiceMadurezAgregados(data.indiceMadurez || []);
            } catch (error) {
                console.error('Error fetching existing quality data:', error);
            }
        },
        []
    );

    const handleOpenModal = async (proceso, qualityIdToEdit = null) => {
        setSelectedProceso(proceso);
        resetQuality();
        resetDetail();
        resetPhoto();
        setPhotos([]);
        setDetallesAgregados([]);
        setDefectosAgregados([]);
        // removed: desorden fisiologico and curva calibre resets
        setIndiceMadurezAgregados([]);

        // Establece el proceso y opcionalmente el ID a editar
        setQualityData("proceso_id", proceso.id);
        setQualityId(qualityIdToEdit);

        // Si estamos editando, precargar; si es nuevo, no precargar
        if (qualityIdToEdit) {
            await fetchQualityData(proceso, qualityIdToEdit);
        }

        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setSelectedProceso(null);
    };

    const submitQuality = (e) => {
        e.preventDefault();
        if (!selectedProceso) {
            return;
        }

        const payload = {
            data: { ...qualityData, proceso_id: selectedProceso.id },
            onSuccess: () => {
                toast.success('Operaci�n exitosa.');
                fetchQualityData(selectedProceso, qualityId || undefined);
                router.reload({ only: ['procesos'] });
            },
            onError: (errors) => {
                console.error(
                    "Error al guardar la calidad del proceso:",
                    errors
                );
                toast.error("Error al guardar la calidad del proceso.");
            },
            preserveState: true,
            preserveScroll: true,
        };

        if (qualityId) {
            putQuality(
                route('processed-fruit-quality.updateQuality', qualityId),
                payload
            );
        } else {
            postQuality(route('processed-fruit-quality.storeQuality'), payload);
        }
    };

    // Mantener modal con datos actualizados tras guardar Info General
    useEffect(() => {
        const id = qualityId || flash?.quality_id;
        if (isModalOpen && selectedProceso && id) {
            fetchQualityData(selectedProceso, id);
        }
    }, [isModalOpen, selectedProceso, qualityId, flash?.quality_id]);

  const submitDetail = (e) => {
  e.preventDefault();

  console.log('qualityId actual:', qualityId); // Debe ser 4
  console.log('detailData actual:', detailData); // Revisa los valores

  if (!qualityId) {
    toast.error('Primero debe guardar la información general de calidad.');
    return;
  }

  const payload = {
    ...detailData,
    processed_fruit_quality_id: qualityId,
  };

  console.log('Enviando payload:', payload); // 👈 Este es el que se manda

  router.post(
    route('processed-fruit-quality.storeDetail'),
    payload,
    {
      onSuccess: (page) => {
        toast.success(page.props.flash?.success || 'Detalle guardado.');
        fetchQualityData(selectedProceso, qualityId); // Refresca
        resetDetail();
      },
      onError: (errors) => {
        console.error('Errores:', errors);
        const firstError = Object.values(errors)[0] || 'Error desconocido';
        toast.error('Error: ' + (Array.isArray(firstError) ? firstError.join(', ') : firstError));
      },
      preserveState: true,
      preserveScroll: true,
    }
  );
};

    const submitPhoto = async (e) => {
        e.preventDefault();
        if (!qualityId) {
            toast.error(
                "Debe guardar la información general de calidad antes de subir una foto."
            );
            return;
        }

        const formData = new FormData();
        formData.append("photo", photoData.photo);
        formData.append("photo_type_id", photoData.photo_type_id);
        formData.append("processed_fruit_quality_id", qualityId);
        if (photoData.observations) {
            formData.append("observations", photoData.observations);
        }

        const csrfToken = document
            .querySelector('meta[name="csrf-token"]')
            .getAttribute("content");
        formData.append("_token", csrfToken);

        setPhotoData("processing", true);

        try {
            const response = await fetch(
                route("quality-control-photos.store"),
                {
                    method: "POST",
                    body: formData,
                }
            );
            const data = await response.json();

            if (response.ok) {
                resetPhoto();
                setPhotos((prevPhotos) => {
                    const newPhoto = data.photo;
                    const newPhotos = [...prevPhotos, newPhoto];
                    return newPhotos;
                });
                toast.success(data.message);
            } else {
                if (data.errors) {
                    toast.error(
                        "Error: " + Object.values(data.errors).flat().join("\n")
                    );
                } else {
                    toast.error("Error: " + (data.message || "Unknown error"));
                }
            }
        } catch (error) {
            toast.error("Network error or unexpected issue.");
        } finally {
            setPhotoData("processing", false);
        }
    };

    const handleDeletePhoto = async (photoId) => {
        if (
            window.confirm("¿Estás seguro de que quieres eliminar esta foto?")
        ) {
            try {
                const response = await fetch(
                    route("quality-control-photos.destroy", photoId),
                    {
                        method: "DELETE",
                        headers: {
                            "X-CSRF-TOKEN": document
                                .querySelector('meta[name="csrf-token"]')
                                .getAttribute("content"),
                            "Content-Type": "application/json",
                            Accept: "application/json",
                        },
                    }
                );

                const data = await response.json();

                if (response.ok) {
                    setPhotos((prevPhotos) =>
                        prevPhotos.filter(
                            (photo) => photo.id !== data.deleted_id
                        )
                    );
                    toast.success(data.message);
                } else {
                    if (data.errors) {
                        toast.error(
                            "Error: " +
                                Object.values(data.errors).flat().join("\n")
                        );
                    } else {
                        toast.error(
                            "Error: " + (data.message || "Unknown error")
                        );
                    }
                }
            } catch (error) {
                toast.error("Network error or unexpected issue.");
            }
        }
    };

    const getValores = useCallback(async (parametroId) => {
        if (!parametroId) {
            setValores([]);
            return;
        }
        try {
            // Assuming 'especie' is not directly available for processed fruit,
            // or it needs to be derived from the selectedProceso
            // For now, we'll just fetch all values for the parameter
            const response = await fetch(
                route("control-calidad.get-valores", {
                    parametro_id: parametroId,
                })
            );
            const data = await response.json();
            setValores(data);
        } catch (error) {
            console.error("Error fetching valores:", error);
            setValores([]);
        }
    }, []);

    useEffect(() => {
        if (detailData.parametro_id) {
            getValores(detailData.parametro_id);
        }
    }, [detailData.parametro_id, getValores]);

    return (
        <AuthenticatedLayout
            user={usePage().props.auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Control de Calidad de Fruta Procesada
                </h2>
            }
        >
            <div className="container mx-auto py-10">
                <Toaster richColors position="top-right" />
                <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
                    <Input
                        type="text"
                        placeholder="Buscar por N° Proceso, Especie o Variedad..."
                        value={filterData.search}
                        onChange={handleSearchChange}
                        className="max-w-sm"
                    />
                    <div className="flex flex-wrap gap-2 items-center">
                        <Button
                            variant={filterData.especie_id === '' ? 'default' : 'outline'}
                            onClick={() => handleEspecieFilter('')}
                        >
                            Todas las Especies
                        </Button>
                        {especies.map((especie) => (
                            <Button
                                key={especie.id}
                                variant={filterData.especie_id === String(especie.id) ? 'default' : 'outline'}
                                onClick={() => handleEspecieFilter(especie.id)}
                            >
                                {especie.name}
                            </Button>
                        ))}
                    </div>
                </div>

                {filterData.especie_id && variedades.length > 0 && (
                    <div className="mb-4 flex flex-wrap gap-2">
                        <Button
                            variant={filterData.variedad_id === '' ? 'default' : 'outline'}
                            onClick={() => handleVariedadFilter('')}
                        >
                            Todas las Variedades
                        </Button>
                        {variedades.map((variedad) => (
                            <Button
                                key={variedad.id}
                                variant={filterData.variedad_id === String(variedad.id) ? 'default' : 'outline'}
                                onClick={() => handleVariedadFilter(variedad.id)}
                            >
                                {variedad.name}
                            </Button>
                        ))}
                    </div>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl font-bold">
                            Producto Terminado
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        {procesos.data.map((proceso) => (
                            <Collapsible
                                key={proceso.id}
                                open={!!openCollapsibles[proceso.id]}
                                onOpenChange={() =>
                                    toggleCollapsible(proceso.id)
                                }
                                className="mb-4 border rounded-lg shadow-sm" // Added styling for the card
                            >
                                <CollapsibleTrigger asChild>
                                    <CardHeader className="flex flex-row items-center justify-between space-x-4 p-4 cursor-pointer hover:bg-gray-50 rounded-t-lg">
                                        <div className="flex items-center space-x-4">
                                            <Package className="h-6 w-6 text-gray-600" />
                                            <CardTitle className="text-lg font-semibold">
                                                Proceso N°: {proceso.n_proceso}
                                            </CardTitle>
                                            <Badge
                                                variant="secondary"
                                                className="bg-green-100 text-green-800 flex items-center space-x-1"
                                            >
                                                {proceso.especie ===
                                                "Apples" ? (
                                                    <Apple className="h-4 w-4" />
                                                ) : (
                                                    <Grape className="h-4 w-4" />
                                                )}
                                                <span>{proceso.especie}</span>
                                            </Badge>
                                            <span className="text-sm text-gray-600">
                                                {proceso.variedad}
                                            </span>
                                            <span className="text-sm text-gray-600 flex items-center space-x-1">
                                                <Calendar className="h-4 w-4" />
                                                <span>
                                                    {new Date(
                                                        proceso.fecha
                                                    ).toLocaleDateString(
                                                        "es-CL"
                                                    )}
                                                </span>
                                            </span>
                                            <span className="text-sm text-gray-600 flex items-center space-x-1">
                                                <Weight className="h-4 w-4" />
                                                <span>
                                                    {proceso.kilos_netos} Kgs
                                                </span>
                                            </span>
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            <Button
                                                onClick={(e) => {
                                                    e.stopPropagation();
                                                    handleOpenModal(proceso);
                                                }}
                                                className="bg-blue-500 hover:bg-blue-600 text-white"
                                            >
                                                Agregar Evaluación
                                            </Button>
                                            <ChevronDown
                                                className={`h-5 w-5 transition-transform ${
                                                    openCollapsibles[proceso.id]
                                                        ? "rotate-180"
                                                        : ""
                                                }`}
                                            />
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {/* Previsualizar reporte */}
                                            <a
                                                href={route('processed-fruit-quality.preview-report', proceso.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                title="Previsualizar Informe"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <Button variant="ghost" size="icon">
                                                    <Eye className="h-4 w-4" />
                                                </Button>
                                            </a>
                                            {/* Ver Informe: si ya existe URL usarla; si no, generar */}
                                            {proceso.informe ? (
                                                <a
                                                    href={proceso.informe}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="Ver Informe"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Button variant="ghost" size="icon">
                                                        <FileText className="h-4 w-4" />
                                                    </Button>
                                                </a>
                                            ) : (
                                                <a
                                                    href={route('processed-fruit-quality.generate-report', proceso.id)}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    title="Ver Informe"
                                                    onClick={(e) => e.stopPropagation()}
                                                >
                                                    <Button variant="ghost" size="icon">
                                                        <FileText className="h-4 w-4" />
                                                    </Button>
                                                </a>
                                            )}
                                            {/* Reenviar / Regenerar */}
                                            <a
                                                href={route('processed-fruit-quality.generate-report', proceso.id)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                title="Reenviar Informe"
                                                onClick={(e) => e.stopPropagation()}
                                            >
                                                <Button variant="ghost" size="icon">
                                                    <Send className="h-4 w-4" />
                                                </Button>
                                            </a>
                                        </div>
                                    </CardHeader>
                                </CollapsibleTrigger>
                                <CollapsibleContent>
                                    <CardContent className="p-4 border-t bg-gray-50 rounded-b-lg">
                                        {proceso.processed_fruit_qualities &&
                                        proceso.processed_fruit_qualities
                                            .length > 0 ? (
                                            <div>
                                                <h4 className="text-md font-medium mb-2">
                                                    Evaluaciones Existentes:
                                                </h4>
                                                <Table className="w-full">
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead className="w-1/4">
                                                                N° Caja
                                                            </TableHead>
                                                            <TableHead className="w-1/4">
                                                                Fecha Evaluación
                                                            </TableHead>
                                                            <TableHead className="w-1/4">
                                                                Estado
                                                            </TableHead>
                                                            <TableHead className="w-1/4">
                                                                Responsable
                                                            </TableHead>
                                                            <TableHead className="w-1/4">
                                                                Acciones
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {proceso.processed_fruit_qualities.map(
                                                            (quality) => (
                                                                <TableRow
                                                                    key={
                                                                        quality.id
                                                                    }
                                                                >
                                                                    <TableCell>
                                                                        {quality.numero_de_caja ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {new Date(
                                                                            quality.created_at
                                                                        ).toLocaleDateString(
                                                                            "es-CL"
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {quality.estado ? (
                                                                            <Badge className={quality.estado === 'Aprobada' ? 'bg-green-600 text-white' : 'bg-orange-500 text-white'}>
                                                                                {quality.estado}
                                                                            </Badge>
                                                                        ) : 'N/A'}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {quality.responsable ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <Button
                                                                            variant="outline"
                                                                            size="sm"
                                                                            onClick={(
                                                                                e
                                                                            ) => {
                                                                                e.stopPropagation();
                                                                                handleOpenModal(
                                                                                    proceso,
                                                                                    quality.id
                                                                                );
                                                                            }}
                                                                        >
                                                                            Ver/Editar
                                                                        </Button>
                                                                    </TableCell>
                                                                </TableRow>
                                                            )
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-gray-500">
                                                No hay evaluaciones registradas
                                                para este proceso.
                                            </p>
                                        )}
                                    </CardContent>
                                </CollapsibleContent>
                            </Collapsible>
                        ))}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {procesos.links.length > 3 && (
                    <div className="flex justify-center mt-4 space-x-2">
                        {procesos.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url || "#"}
                                dangerouslySetInnerHTML={{ __html: link.label }}
                                className={`px-3 py-1 border rounded-md ${
                                    link.active
                                        ? "bg-blue-500 text-white"
                                        : "bg-white text-gray-700 hover:bg-gray-100"
                                } ${
                                    !link.url
                                        ? "opacity-50 cursor-not-allowed"
                                        : ""
                                }`}
                                preserveScroll
                                preserveState
                            />
                        ))}
                    </div>
                )}

                {selectedProceso && (
                    <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                        <DialogContent className="max-w-5xl max-h-[90vh] overflow-y-auto">
                            <DialogHeader>
                                <div className="flex items-start justify-between gap-4">
                                    <div>
                                        <DialogTitle>
                                            Evaluación de Calidad para Proceso N°: {selectedProceso.n_proceso}
                                        </DialogTitle>
                                        <DialogDescription>
                                            <p><strong>Variedad:</strong> {selectedProceso.variedad}</p>
                                            <p className="text-xs text-gray-500">ID evaluación: {qualityId || '—'}</p>
                                        </DialogDescription>
                                    </div>
                                    <div className="flex items-center gap-2 pt-1">
                                        <Label htmlFor="estado_switch" className="whitespace-nowrap">
                                            {qualityData.estado === 'Aprobada' ? 'Aprobada' : 'Rechazada'}
                                        </Label>
                                        <Switch
                                            id="estado_switch"
                                            checked={qualityData.estado === 'Aprobada'}
                                            onCheckedChange={async (val) => {
                                                const nuevo = val ? 'Aprobada' : 'Rechazada';
                                                setQualityData('estado', nuevo);
                                                if (qualityId) {
                                                    try {
                                                        const resp = await fetch(route('processed-fruit-quality.updateStatus', qualityId), {
                                                            method: 'PATCH',
                                                            headers: {
                                                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                                                'Accept': 'application/json',
                                                                'Content-Type': 'application/json',
                                                            },
                                                            body: JSON.stringify({ estado: nuevo }),
                                                        });
                                                        if (resp.ok) {
                                                            router.reload({ only: ['procesos'] });
                                                        }
                                                    } catch (err) {
                                                        console.error('No se pudo actualizar el estado', err);
                                                        toast.error('No se pudo actualizar el estado');
                                                    }
                                                } else {
                                                    toast.message('El estado se guardará al crear la evaluación.');
                                                }
                                            }}
                                        />
                                    </div>
                                </div>
                            </DialogHeader>

                            <Tabs
                                defaultValue="general"
                                onValueChange={setActiveTab}
                            >
                                <TabsList className="grid w-full grid-cols-4">
                                    <TabsTrigger value="general">
                                        Info General
                                    </TabsTrigger>
                                    <TabsTrigger value="defectos">
                                        Defectos
                                    </TabsTrigger>

                                    <TabsTrigger value="indice-madurez">
                                        Indice de Madurez
                                    </TabsTrigger>
                                    <TabsTrigger value="fotos">
                                        Fotos
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="general">
                                    <form
                                        onSubmit={submitQuality}
                                        className="space-y-4 mt-4"
                                    >
                                        {/* Estado movido al encabezado */}
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="numero_de_caja">
                                                    Número de Caja
                                                </Label>
                                                <Input
                                                    id="numero_de_caja"
                                                    type="text"
                                                    value={
                                                        qualityData.numero_de_caja
                                                    }
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "numero_de_caja",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                                {errorsQuality.numero_de_caja && (
                                                    <p className="mt-1 text-sm text-red-600">
                                                        {
                                                            errorsQuality.numero_de_caja
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div>
                                                <Label htmlFor="numero_embaladora_mano">
                                                    Número de Embaladora a Mano
                                                </Label>
                                                <Input
                                                    id="numero_embaladora_mano"
                                                    type="text"
                                                    value={
                                                        qualityData.numero_embaladora_mano
                                                    }
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "numero_embaladora_mano",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="peso_exacto_caja">
                                                    Peso Exacto Caja (kg)
                                                </Label>
                                                <Input
                                                    id="peso_exacto_caja"
                                                    type="number"
                                                    step="0.01"
                                                    value={
                                                        qualityData.peso_exacto_caja
                                                    }
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "peso_exacto_caja",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="codigo_embalaje">
                                                    Código Embalaje
                                                </Label>
                                                <Input
                                                    id="codigo_embalaje"
                                                    type="text"
                                                    value={
                                                        qualityData.codigo_embalaje
                                                    }
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "codigo_embalaje",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="categoria">Categoría</Label>
                                                <Select
                                                    value={qualityData.categoria}
                                                    onValueChange={(value) => setQualityData('categoria', value)}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar categoría" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {['CAT 1','CAT 2','COMERCIAL','VEGA','SUPERMERCADO','DESECHO'].map(opt => (
                                                            <SelectItem key={opt} value={opt}>{opt}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="destino">
                                                    Destino
                                                </Label>
                                                <Input
                                                    id="destino"
                                                    type="text"
                                                    value={qualityData.destino}
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "destino",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="calibre">
                                                    Calibre
                                                </Label>
                                                <Input
                                                    id="calibre"
                                                    type="text"
                                                    value={qualityData.calibre}
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "calibre",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div style={{ display: 'none' }}>
                                                <Label htmlFor="color_cubrimiento">
                                                    Color de Cubrimiento
                                                </Label>
                                                <Input
                                                    id="color_cubrimiento"
                                                    type="text"
                                                    value={
                                                        qualityData.color_cubrimiento
                                                    }
                                                    onChange={(e) =>
                                                        setQualityData(
                                                            "color_cubrimiento",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            {!isCherries(selectedProceso) && (
                                                <div>
                                                    <Label htmlFor="color_fondo">Color de Fondo</Label>
                                                    <Input
                                                        id="color_fondo"
                                                        type="text"
                                                        value={qualityData.color_fondo}
                                                        onChange={(e) => setQualityData('color_fondo', e.target.value)}
                                                    />
                                                </div>
                                            )}
                                        </div>
                                        <div>
                                            <Label htmlFor="t_muestra">
                                                Tamaño Muestra (gr)
                                            </Label>
                                            <Input
                                                id="t_muestra"
                                                type="number"
                                                value={qualityData.t_muestra}
                                                onChange={(e) =>
                                                    setQualityData(
                                                        "t_muestra",
                                                        e.target.value
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="responsable">
                                                Responsable
                                            </Label>
                                            <Input
                                                id="responsable"
                                                value={qualityData.responsable}
                                                onChange={(e) =>
                                                    setQualityData(
                                                        "responsable",
                                                        e.target.value
                                                    )
                                                }
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="observaciones">
                                                Observaciones
                                            </Label>
                                            <textarea
                                                id="observaciones"
                                                value={
                                                    qualityData.observaciones
                                                }
                                                onChange={(e) =>
                                                    setQualityData(
                                                        "observaciones",
                                                        e.target.value
                                                    )
                                                }
                                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={processingQuality}
                                        >
                                            Guardar Info General
                                        </Button>
                                    </form>
                                </TabsContent>

                                <TabsContent value="defectos">
                                    <form
                                        onSubmit={submitDetail}
                                        className="space-y-4 mt-4"
                                    >
                                        {/* Selector de etiqueta (categoría de tolerancia) */}
                                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <Label htmlFor="tolerance_label">Etiqueta de tolerancia</Label>
                                                <Select
                                                    value={toleranceLabel}
                                                    onValueChange={(value) => setToleranceLabel(value)}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar etiqueta" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {['1S','1-2','3','4'].map(opt => (
                                                            <SelectItem key={opt} value={opt}>{opt}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="parametro_id">
                                                    Parámetro
                                                </Label>
                                                <Select
                                                    onValueChange={(value) => {
                                                        setDetailData(
                                                            "parametro_id",
                                                            value
                                                        );
                                                    setDetailData("valor_id", "");
                                                    setDetailData("valor_text", "");
                                                    }}
                                                    value={
                                                        detailData.parametro_id
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar Parámetro" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {parametros
                                                            .filter((p) =>
                                                                [
                                                                    3, 4, 5,
                                                                ].includes(p.id)
                                                            )
                                                            .map(
                                                                (parametro) => (
                                                                    <SelectItem
                                                                        key={
                                                                            parametro.id
                                                                        }
                                                                        value={String(
                                                                            parametro.id
                                                                        )}
                                                                    >
                                                                        {parametro?.nombre ||
                                                                            "N/A"}
                                                                    </SelectItem>
                                                                )
                                                            )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="valor_id">
                                                    Valor
                                                </Label>
                                                <Select
                                                    onValueChange={(value) =>
                                                        setDetailData(
                                                            "valor_id",
                                                            value
                                                        )
                                                    }
                                                    value={detailData.valor_id}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar Valor" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {parametros
                                                            .find(
                                                                (p) =>
                                                                    p.id ===
                                                                    parseInt(
                                                                        detailData.parametro_id
                                                                    )
                                                            )
                                                            ?.valors.map(
                                                                (valor) => (
                                                                    <SelectItem
                                                                        key={
                                                                            valor.id
                                                                        }
                                                                        value={String(
                                                                            valor.id
                                                                        )}
                                                                    >
                                                                        {
                                                                            valor.nombre
                                                                        }
                                                                    </SelectItem>
                                                                )
                                                            )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 mt-4">
                                            <div>
                                                <Label htmlFor="cantidad_muestra">
                                                    Cantidad de Muestra
                                                </Label>
                                                <Input
                                                    id="cantidad_muestra"
                                                    type="number"
                                                    value={
                                                        detailData.cantidad_muestra
                                                    }
                                                    onChange={(e) =>
                                                        setDetailData(
                                                            "cantidad_muestra",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            {qualityData &&
                                                qualityData.t_muestra > 0 &&
                                                detailData.cantidad_muestra >
                                                    0 && (
                                                    <div className="flex items-end">
                                                        <Label className="text-sm text-gray-600">
                                                            % de la muestra:{" "}
                                                            {(
                                                                (detailData.cantidad_muestra /
                                                                    qualityData.t_muestra) *
                                                                100
                                                            ).toFixed(2)}
                                                            %
                                                        </Label>
                                                    </div>
                                                )}
                                        </div>
                                        <div className="flex items-center space-x-2 mt-4">
                                            <Switch
                                                id="exportable_defectos"
                                                checked={detailData.exportable}
                                                onCheckedChange={(value) =>
                                                    setDetailData(
                                                        "exportable",
                                                        value
                                                    )
                                                }
                                            />
                                            <Label htmlFor="exportable_defectos">
                                                Exportable
                                            </Label>
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processingDetail || !qualityId
                                            }
                                        >
                                            Agregar Defecto
                                        </Button>
                                        {/* Indicador de Tolerancias / Score */}
                                        {(() => {
                                            const catKey = toleranceLabel || (CATEGORY_MAP[normalizeText(qualityData.categoria || '')] || '1S');
                                            const byDefect = {};
                                            let sumCond = 0; let sumCal = 0;
                                            defectosAgregados.forEach(d => {
                                                const tipo = normalizeText(d.tipo_item || '');
                                                const det = mapToCanonicalDefect(d.detalle_item || '');
                                                const val = Number(d.porcentaje_muestra) || 0;
                                                const group = tipo.includes('CONDIC') ? 'CONDICION' : (tipo.includes('CALID') ? 'CALIDAD' : null);
                                                if (!group) return;
                                                byDefect[group] = byDefect[group] || {};
                                                byDefect[group][det] = (byDefect[group][det] || 0) + val;
                                                if (group === 'CONDICION') sumCond += val; else sumCal += val;
                                            });
                                            const totAllowedCond = TOLERANCES.TOTAL.CONDICION[catKey];
                                            const totAllowedCal = TOLERANCES.TOTAL.CALIDAD[catKey];
                                            const overTotalCond = sumCond > totAllowedCond;
                                            const overTotalCal = sumCal > totAllowedCal;
                                            return (
                                                <div className="mt-4 space-y-2">
                                                    <div className="text-sm text-gray-600">Categoría seleccionada: <span className="font-semibold">{catKey}</span></div>
                                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                        <div className={`border rounded p-3 ${overTotalCond ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-white'}`}>
                                                            <div className="font-semibold mb-2">Defectos de Condición</div>
                                                            <div className="text-sm mb-2">Total: {sumCond.toFixed(2)}% / Permitido: {totAllowedCond}%</div>
                                                            <div className="max-h-40 overflow-auto">
                                                                <table className="w-full text-sm">
                                                                    <thead>
                                                                        <tr className="text-gray-500"><th className="text-left">Defecto</th><th className="text-right">Acum.</th><th className="text-right">Tol.</th></tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {Object.entries(byDefect.CONDICION || {}).map(([name, val]) => {
                                                                            const tol = TOLERANCES.CONDICION[name]?.[catKey];
                                                                            const over = tol != null && val > tol;
                                                                            const near = tol != null && !over && val > 0.9 * tol;
                                                                            return (
                                                                                <tr key={`cond-${name}`} className={`${over ? 'text-red-600' : near ? 'text-amber-600' : ''}`}>
                                                                                    <td>{name}</td>
                                                                                    <td className="text-right">{val.toFixed(2)}%</td>
                                                                                    <td className="text-right">{tol != null ? `${tol}%` : '—'}</td>
                                                                                </tr>
                                                                            );
                                                                        })}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                        <div className={`border rounded p-3 ${overTotalCal ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-white'}`}>
                                                            <div className="font-semibold mb-2">Defectos de Calidad</div>
                                                            <div className="text-sm mb-2">Total: {sumCal.toFixed(2)}% / Permitido: {totAllowedCal}%</div>
                                                            <div className="max-h-40 overflow-auto">
                                                                <table className="w-full text-sm">
                                                                    <thead>
                                                                        <tr className="text-gray-500"><th className="text-left">Defecto</th><th className="text-right">Acum.</th><th className="text-right">Tol.</th></tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        {Object.entries(byDefect.CALIDAD || {}).map(([name, val]) => {
                                                                            const tol = TOLERANCES.CALIDAD[name]?.[catKey];
                                                                            const over = tol != null && val > tol;
                                                                            const near = tol != null && !over && val > 0.9 * tol;
                                                                            return (
                                                                                <tr key={`cal-${name}`} className={`${over ? 'text-red-600' : near ? 'text-amber-600' : ''}`}>
                                                                                    <td>{name}</td>
                                                                                    <td className="text-right">{val.toFixed(2)}%</td>
                                                                                    <td className="text-right">{tol != null ? `${tol}%` : '—'}</td>
                                                                                </tr>
                                                                            );
                                                                        })}
                                                                    </tbody>
                                                                </table>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            );
                                        })()}

                                        {defectosAgregados.length > 0 && (
                                            <div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto">
                                                <h4 className="text-md font-medium mb-2">
                                                    Defectos Agregados:
                                                </h4>
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Cantidad
                                                            </TableHead>
                                                            <TableHead>
                                                                Tipo Item
                                                            </TableHead>
                                                            <TableHead>
                                                                Detalle Item
                                                            </TableHead>
                                                            <TableHead>
                                                                % Muestra
                                                            </TableHead>
                                                            <TableHead>
                                                                Categoría
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {defectosAgregados.map(
                                                            (
                                                                detalle,
                                                                index
                                                            ) => (
                                                                <TableRow
                                                                    key={index}
                                                                >
                                                                    <TableCell className="max-w-xs truncate">
                                                                        {
                                                                            detalle.cantidad_muestra
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {detalle.tipo_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {detalle.detalle_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="max-w-xs truncate">
                                                                        {
                                                                            detalle.porcentaje_muestra
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="max-w-xs truncate text-sm">
                                                                        {detalle.categoria ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            )
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        )}
                                    </form>
                                </TabsContent>



                                {/* Curva de Calibre tab removed */}
                                {/* <TabsContent value="curva-calibre">
                                    <form
                                        onSubmit={submitDetail}
                                        className="space-y-4 mt-4"
                                    >
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="parametro_id_cc">
                                                    Parámetro
                                                </Label>
                                                <Select
                                                    onValueChange={(value) => {
                                                        setDetailData(
                                                            "parametro_id",
                                                            value
                                                        );
                                                        setDetailData(
                                                            "valor_id",
                                                            ""
                                                        );
                                                    }}
                                                    value={
                                                        detailData.parametro_id
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar Parámetro" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {parametros
                                                            .filter((p) =>
                                                                [
                                                                    1, 2, 6,
                                                                ].includes(p.id)
                                                            )
                                                            .map(
                                                                (parametro) => (
                                                                    <SelectItem
                                                                        key={
                                                                            parametro.id
                                                                        }
                                                                        value={String(
                                                                            parametro.id
                                                                        )}
                                                                    >
                                                                        {parametro?.nombre ||
                                                                            "N/A"}
                                                                    </SelectItem>
                                                                )
                                                            )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div>
                                                <Label htmlFor="valor_id_cc">
                                                    Valor
                                                </Label>
                                                <Select
                                                    onValueChange={(value) =>
                                                        setDetailData(
                                                            "valor_id",
                                                            value
                                                        )
                                                    }
                                                    value={detailData.valor_id}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar Valor" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {parametros
                                                            .find(
                                                                (p) =>
                                                                    p.id ===
                                                                    parseInt(
                                                                        detailData.parametro_id
                                                                    )
                                                            )
                                                            ?.valors.map(
                                                                (valor) => (
                                                                    <SelectItem
                                                                        key={
                                                                            valor.id
                                                                        }
                                                                        value={String(
                                                                            valor.id
                                                                        )}
                                                                    >
                                                                        {
                                                                            valor.nombre
                                                                        }
                                                                    </SelectItem>
                                                                )
                                                            )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 mt-4">
                                            <div>
                                                <Label htmlFor="cantidad_muestra_cc">
                                                    Cantidad de Muestra
                                                </Label>
                                                <Input
                                                    id="cantidad_muestra_cc"
                                                    type="number"
                                                    value={
                                                        detailData.cantidad_muestra
                                                    }
                                                    onChange={(e) =>
                                                        setDetailData(
                                                            "cantidad_muestra",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            {qualityData &&
                                                qualityData.t_muestra > 0 &&
                                                detailData.cantidad_muestra >
                                                    0 && (
                                                    <div className="flex items-end">
                                                        <Label className="text-sm text-gray-600">
                                                            % de la muestra:{" "}
                                                            {(
                                                                (detailData.cantidad_muestra /
                                                                    qualityData.t_muestra) *
                                                                100
                                                            ).toFixed(2)}
                                                            %
                                                        </Label>
                                                    </div>
                                                )}
                                        </div>
                                        <div className="flex items-center space-x-2 mt-4">
                                            <Switch
                                                id="exportable_curva_calibre"
                                                checked={detailData.exportable}
                                                onCheckedChange={(value) =>
                                                    setDetailData(
                                                        "exportable",
                                                        value
                                                    )
                                                }
                                            />
                                            <Label htmlFor="exportable_curva_calibre">
                                                Exportable
                                            </Label>
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processingDetail || !qualityId
                                            }
                                        >
                                            Agregar Curva de Calibre
                                        </Button>
                                        {curvaCalibreAgregados.length > 0 && (
                                            <div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto">
                                                <h4 className="text-md font-medium mb-2">
                                                    Curva de Calibre Agregados:
                                                </h4>
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Cantidad
                                                            </TableHead>
                                                            <TableHead>
                                                                Tipo Item
                                                            </TableHead>
                                                            <TableHead>
                                                                Detalle Item
                                                            </TableHead>
                                                            <TableHead>
                                                                % Muestra
                                                            </TableHead>
                                                            <TableHead>
                                                                Categoría
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {curvaCalibreAgregados.map(
                                                            (
                                                                detalle,
                                                                index
                                                            ) => (
                                                                <TableRow
                                                                    key={index}
                                                                >
                                                                    <TableCell className="max-w-xs truncate">
                                                                        {
                                                                            detalle.cantidad_muestra
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {detalle.tipo_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        {detalle.detalle_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="max-w-xs truncate">
                                                                        {
                                                                            detalle.porcentaje_muestra
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="max-w-xs truncate text-sm">
                                                                        {detalle.categoria ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            )
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        )}
                                    </form>
                                </TabsContent> */}

                                <TabsContent value="indice-madurez">
                                    <form
                                        onSubmit={submitDetail}
                                        className="space-y-4 mt-4"
                                    >
                                        <div className="grid grid-cols-2 gap-4">
                                            <div>
                                                <Label htmlFor="parametro_id_im">
                                                    Parámetro
                                                </Label>
                                                <Select
                                                    onValueChange={(value) => {
                                                        setDetailData(
                                                            "parametro_id",
                                                            value
                                                        );
                                                        setDetailData(
                                                            "valor_id",
                                                            ""
                                                        );
                                                    }}
                                                    value={
                                                        detailData.parametro_id
                                                    }
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue placeholder="Seleccionar Parámetro" />
                                                    </SelectTrigger>
                                                    <SelectContent className="max-h-60 overflow-y-auto">
                                                        {parametros
                                                            .filter((p) =>
                                                                (p.nombre || '')
                                                                    .toLowerCase()
                                                                    .includes('solidos')
                                                            )
                                                            .map((parametro) => (
                                                                <SelectItem
                                                                    key={parametro.id}
                                                                    value={String(parametro.id)}
                                                                >
                                                                    {parametro?.nombre || 'N/A'}
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div style={{ display: 'none' }}>
                                                <Label htmlFor="valor_text_im">Valor</Label>
                                                <Input
                                                    id="valor_text_im"
                                                    type="text"
                                                    value={detailData.valor_text}
                                                    onChange={(e) => setDetailData('valor_text', e.target.value)}
                                                    placeholder="Ingrese valor"
                                                />
                                            </div>
                                        </div>
                                        <div className="grid grid-cols-2 gap-4 mt-4">
                                            <div>
                                                <Label htmlFor="temperatura_im">
                                                    Temperatura
                                                </Label>
                                                <Input
                                                    id="temperatura_im"
                                                    type="number"
                                                    value={
                                                        detailData.temperatura
                                                    }
                                                    onChange={(e) =>
                                                        setDetailData(
                                                            "temperatura",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div>
                                            <Label htmlFor="valor_presion_im">Promedio Brix</Label>
                                                <Input
                                                    id="valor_presion_im"
                                                    type="number"
                                                    value={
                                                        detailData.valor_presion
                                                    }
                                                    onChange={(e) =>
                                                        setDetailData(
                                                            "valor_presion",
                                                            e.target.value
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <Button
                                            type="submit"
                                            disabled={
                                                processingDetail || !qualityId
                                            }
                                        >
                                            Agregar Indice de Madurez
                                        </Button>
                                        {indiceMadurezAgregados.length > 0 && (
                                            <div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto">
                                                <h4 className="text-md font-medium mb-2">
                                                    Indice de Madurez Agregados:
                                                </h4>
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>
                                                                Temperatura
                                                            </TableHead>
                                                            <TableHead>
                                                                Tipo Item
                                                            </TableHead>
                                                            <TableHead>
                                                                Detalle Item
                                                            </TableHead>
                                                            <TableHead>
                                                                Valor SS
                                                            </TableHead>
                                                             <TableHead>
                                                                Promedio Brix
                                                            </TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {indiceMadurezAgregados.map(
                                                            (
                                                                detalle,
                                                                index
                                                            ) => (
                                                                <TableRow
                                                                    key={index}
                                                                >
                                                                    <TableCell className="text-sm">
                                                                        {detalle.temperatura ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="text-sm">
                                                                        {detalle.cantidad_muestra ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="text-sm">
                                                                        {detalle.tipo_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="text-sm">
                                                                        {detalle.detalle_item ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                    <TableCell className="text-sm">
                                                                        {detalle.valor_ss ||
                                                                            "N/A"}
                                                                    </TableCell>
                                                                </TableRow>
                                                            )
                                                        )}
                                                    </TableBody>
                                                </Table>
                                            </div>
                                        )}
                                    </form>
                                </TabsContent>

                                <TabsContent value="fotos">
                                    <form
                                        onSubmit={submitPhoto}
                                        className="space-y-4 mt-4"
                                    >
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div className="space-y-4">
                                                <h3 className="text-lg font-medium">
                                                    Subir Nueva Foto
                                                </h3>
                                                <div>
                                                    <Label htmlFor="photo_type_id">
                                                        Tipo de Foto
                                                    </Label>
                                                    <Select
                                                        onValueChange={(
                                                            value
                                                        ) =>
                                                            setPhotoData(
                                                                "photo_type_id",
                                                                value
                                                            )
                                                        }
                                                        value={
                                                            photoData.photo_type_id
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Seleccionar tipo..." />
                                                        </SelectTrigger>
                                                        <SelectContent className="max-h-60 overflow-y-auto">
                                                            {photoTypesState.map(
                                                                (type) => (
                                                                    <SelectItem
                                                                        key={
                                                                            type.id
                                                                        }
                                                                        value={String(
                                                                            type.id
                                                                        )}
                                                                    >{type.name}</SelectItem>
                                                                )
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                    {errorsPhoto.photo_type_id && (
                                                        <p className="mt-1 text-sm text-red-600">
                                                            {
                                                                errorsPhoto.photo_type_id
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Label htmlFor="photo_observations">Observaciones</Label>
                                                    <textarea
                                                        id="photo_observations"
                                                        value={photoData.observations || ''}
                                                        onChange={(e) => setPhotoData('observations', e.target.value)}
                                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm"
                                                        rows={3}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="photo">Archivo de Imagen</Label>
                                                    <Input
                                                        id="photo"
                                                        type="file"
                                                        onChange={(e) => setPhotoData('photo', e.target.files[0])}
                                                    />
                                                    {errorsPhoto.photo && (
                                                        <p className="mt-1 text-sm text-red-600">{errorsPhoto.photo}</p>
                                                    )}
                                                </div>
                                                <Button
                                                    type="submit"
                                                    disabled={
                                                        processingPhoto ||
                                                        !qualityId
                                                    }
                                                >
                                                    <UploadCloud className="mr-2 h-4 w-4" />
                                                    {processingPhoto
                                                        ? "Subiendo..."
                                                        : "Subir Foto"}
                                                </Button>
                                            </div>
                                            <div className="space-y-4">
                                                <h3 className="text-lg font-medium">
                                                    Galería de Fotos
                                                </h3>
                                                {photos.length > 0 ? (
                                                    <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                                        {photos.map((photo) => (
                                                            <div
                                                                key={photo.id}
                                                                className="relative group"
                                                            >
                                                                <img
                                                                    src={
                                                                        photo.url
                                                                    }
                                                                    alt={
                                                                        photo
                                                                            .photo_type
                                                                            .name
                                                                    }
                                                                    className="rounded-lg object-cover w-full h-32"
                                                                />
                                                                <div className="absolute bottom-0 left-0 right-0 bg-black bg-opacity-60 text-white text-[10px] p-1 rounded-b-lg space-y-0.5">
                                                                    <div className="font-medium">{photo.photo_type.name}</div>
                                                                    {photo.observations && (
                                                                        <div className="opacity-90">{photo.observations}</div>
                                                                    )}
                                                                </div>
                                                                <Button
                                                                    variant="destructive"
                                                                    size="icon"
                                                                    className="absolute top-1 right-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
                                                                    onClick={() =>
                                                                        handleDeletePhoto(
                                                                            photo.id
                                                                        )
                                                                    }
                                                                >
                                                                    <Trash2 className="h-4 w-4 text-red-500" />
                                                                </Button>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-sm text-gray-500">
                                                        No hay fotos para este
                                                        control de calidad.
                                                    </p>
                                                )}
                                            </div>
                                        </div>
                                    </form>
                                </TabsContent>
                            </Tabs>

                            <DialogFooter className="mt-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleCloseModal}
                                >
                                    Cerrar
                                </Button>
                            </DialogFooter>
                        </DialogContent>
                    </Dialog>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
