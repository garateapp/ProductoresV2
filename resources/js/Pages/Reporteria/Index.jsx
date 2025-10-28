import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState, useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Command, CommandInput, CommandEmpty, CommandGroup, CommandItem } from '@/Components/ui/command';
import { Check, ChevronsUpDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/Components/ui/button';
import Chart from 'react-apexcharts';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Link } from '@inertiajs/react';

const Pagination = ({ links }) => (
    <div className="mt-4 flex justify-center">
        {
            (links || []).map((link, key) => (
                link.url === null ?
                    (<div key={key} className="mr-1 mb-1 px-4 py-3 text-sm leading-4 text-gray-400 border rounded" dangerouslySetInnerHTML={{ __html: link.label }} />) :
                    (<Link key={key} className="mr-1 mb-1 px-4 py-3 text-sm leading-4 border rounded hover:bg-white focus:border-indigo-500 focus:text-indigo-500" href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />)
            ))
        }
    </div>
);

function getChartColors(species) {
    switch (species.toLowerCase()) {
        case 'cherries':
            return {
                exportable: 'rgba(255, 99, 132, 0.6)', // Red tone
                defectosCalidad: 'rgba(200, 0, 0, 0.6)', // Darker red
                defectosCondicion: 'rgba(150, 0, 0, 0.6)', // Even darker red
                danosPlaga: 'rgba(100, 0, 0, 0.6)', // Darkest red
                borderColor: 'rgba(255, 255, 255, 1)'
            };
        case 'apples':
            return {
                exportable: 'rgba(75, 192, 192, 0.6)', // Green tone
                defectosCalidad: 'rgba(0, 150, 0, 0.6)', // Darker green
                defectosCondicion: 'rgba(0, 100, 0, 0.6)', // Even darker green
                danosPlaga: 'rgba(0, 50, 0, 0.6)', // Darkest green
                borderColor: 'rgba(255, 255, 255, 1)'
            };
        case 'nectarines':
            return {
                exportable: 'rgba(255, 159, 64, 0.6)', // Orange tone
                defectosCalidad: 'rgba(200, 100, 0, 0.6)', // Darker orange
                defectosCondicion: 'rgba(150, 50, 0, 0.6)', // Even darker orange
                danosPlaga: 'rgba(100, 25, 0, 0.6)', // Darkest orange
                borderColor: 'rgba(255, 255, 255, 1)'
            };
        default: // Default colors if species not matched
            return {
                exportable: 'rgba(54, 162, 235, 0.6)', // Blue
                defectosCalidad: 'rgba(255, 206, 86, 0.6)', // Yellow
                defectosCondicion: 'rgba(153, 102, 255, 0.6)', // Purple
                danosPlaga: 'rgba(255, 99, 132, 0.6)', // Red
                borderColor: 'rgba(255, 255, 255, 1)'
            };
    }
}

function formatNumber(value, { minimumFractionDigits = 0, maximumFractionDigits = 0 } = {}) {
    const numericValue = Number(value ?? 0);
    return numericValue.toLocaleString('es-CL', {
        minimumFractionDigits,
        maximumFractionDigits,
    });
}

const coerceNumber = (value) => {
    if (value === null || value === undefined) {
        return 0;
    }

    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : 0;
    }

    if (typeof value === 'string') {
        const sanitized = value.replace(/[^\d.,-]/g, '').replace(',', '.');
        const parsed = parseFloat(sanitized);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    return 0;
};

export default function Index({ auth, especies, producers, lotes, filters, filterMatrix = [], kpiSummary, kpiTrend, sizeDistribution, averageFirmness, firmnessDistribution, solubleSolids, coverageColor, qualityDefects, conditionDefects, pestDamage, receptionDetails, ready }) {
    const [selectedEspecie, setSelectedEspecie] = useState(filters.especie_id || '');
    const [selectedVariedad, setSelectedVariedad] = useState(filters.variedad_id || '');
    const [selectedProductor, setSelectedProductor] = useState(filters.productor_id || 'all');
    const [selectedLote, setSelectedLote] = useState(filters.lote || '');
    const [selectedLotes, setSelectedLotes] = useState((filters.lotes || []).map(String));
    const [fromDate, setFromDate] = useState(filters.from_date || '');
    const [toDate, setToDate] = useState(filters.to_date || '');

    const normalizedMatrix = useMemo(() => {
        return (filterMatrix || []).map((item) => {
            const speciesId = item?.especie_id ? String(item.especie_id) : '';
            const varietyId = item?.variedad_id ? String(item.variedad_id) : '';
            const lot = item?.lote ? String(item.lote) : '';

            if (!speciesId || !varietyId || !lot) {
                return null;
            }

            return {
                speciesId,
                speciesName: item?.especie_name ?? '',
                varietyId,
                varietyName: item?.variedad_name ?? '',
                producerId: item?.productor_id ? String(item.productor_id) : null,
                producerName: item?.productor_name ?? (item?.productor_id ? String(item.productor_id) : 'Sin asignar'),
                lote: lot,
            };
        }).filter(Boolean);
    }, [filterMatrix]);

    const availableSpecies = useMemo(() => {
        if (!normalizedMatrix.length) {
            return (especies || []).map((especie) => ({
                id: String(especie.id),
                name: especie.name,
            }));
        }

        const map = new Map();
        normalizedMatrix
            .filter(
                (row) =>
                    (!selectedVariedad || row.varietyId === selectedVariedad) &&
                    (selectedProductor === 'all' || !selectedProductor || (row.producerId && row.producerId === selectedProductor)) &&
                    (!selectedLote || row.lote === selectedLote),
            )
            .forEach((row) => {
                if (!map.has(row.speciesId)) {
                    map.set(row.speciesId, { id: row.speciesId, name: row.speciesName });
                }
            });

        if (map.size === 0) {
            return (especies || []).map((especie) => ({
                id: String(especie.id),
                name: especie.name,
            }));
        }

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [normalizedMatrix, selectedVariedad, selectedProductor, selectedLote, especies]);

    const availableVariedades = useMemo(() => {
        const map = new Map();
        normalizedMatrix
            .filter(
                (row) =>
                    (!selectedEspecie || row.speciesId === selectedEspecie) &&
                    (selectedProductor === 'all' || !selectedProductor || (row.producerId && row.producerId === selectedProductor)) &&
                    (!selectedLote || row.lote === selectedLote),
            )
            .forEach((row) => {
                if (!map.has(row.varietyId)) {
                    map.set(row.varietyId, { id: row.varietyId, name: row.varietyName });
                }
            });

        if (map.size === 0 && selectedEspecie) {
            const especie = especies.find((item) => String(item.id) === selectedEspecie);
            if (especie) {
                (especie.variedads || []).forEach((variedad) => {
                    map.set(String(variedad.id), { id: String(variedad.id), name: variedad.name });
                });
            }
        }

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [normalizedMatrix, selectedEspecie, selectedProductor, selectedLote, especies]);

    const availableProductores = useMemo(() => {
        const map = new Map();
        normalizedMatrix
            .filter(
                (row) =>
                    (!selectedEspecie || row.speciesId === selectedEspecie) &&
                    (!selectedVariedad || row.varietyId === selectedVariedad) &&
                    (!selectedLote || row.lote === selectedLote),
            )
            .forEach((row) => {
                if (row.producerId && !map.has(row.producerId)) {
                    map.set(row.producerId, { id: row.producerId, name: row.producerName });
                }
            });

        if (map.size === 0) {
            (producers || []).forEach((producer) => {
                const producerId = producer.idprod ?? producer.id;
                if (producerId && !map.has(String(producerId))) {
                    map.set(String(producerId), { id: String(producerId), name: producer.name });
                }
            });
        }

        return Array.from(map.values()).sort((a, b) => a.name.localeCompare(b.name));
    }, [normalizedMatrix, selectedEspecie, selectedVariedad, selectedLote, producers]);

    const availableLotes = useMemo(() => {
        const map = new Map();
        normalizedMatrix
            .filter(
                (row) =>
                    (!selectedEspecie || row.speciesId === selectedEspecie) &&
                    (!selectedVariedad || row.varietyId === selectedVariedad) &&
                    (selectedProductor === 'all' || !selectedProductor || (row.producerId && row.producerId === selectedProductor)),
            )
            .forEach((row) => {
                if (!map.has(row.lote)) {
                    map.set(row.lote, { value: row.lote, label: row.lote });
                }
            });

        if (map.size === 0) {
            (lotes || []).forEach((lote) => {
                const value = String(lote.numero_g_recepcion);
                if (!map.has(value)) {
                    map.set(value, { value, label: value });
                }
            });
        }

        return Array.from(map.values()).sort((a, b) => a.value.localeCompare(b.value));
    }, [normalizedMatrix, selectedEspecie, selectedVariedad, selectedProductor, lotes]);

    useEffect(() => {
        if (selectedEspecie && !availableSpecies.some((option) => option.id === selectedEspecie)) {
            setSelectedEspecie('');
        }
    }, [selectedEspecie, availableSpecies]);

    useEffect(() => {
        if (selectedVariedad && !availableVariedades.some((option) => option.id === selectedVariedad)) {
            setSelectedVariedad('');
        }
    }, [selectedVariedad, availableVariedades]);

    useEffect(() => {
        if (selectedProductor !== 'all' && selectedProductor && !availableProductores.some((option) => option.id === selectedProductor)) {
            setSelectedProductor('all');
        }
    }, [selectedProductor, availableProductores]);

    useEffect(() => {
        if (selectedLote && !availableLotes.some((option) => option.value === selectedLote)) {
            setSelectedLote('');
        }
    }, [selectedLote, availableLotes]);

    useEffect(() => {
        if (selectedLotes.length) {
            const filtered = selectedLotes.filter((item) => availableLotes.some((option) => option.value === item));
            if (filtered.length !== selectedLotes.length) {
                setSelectedLotes(filtered);
            }
        }
    }, [selectedLotes, availableLotes]);

    const summary = kpiSummary ?? {};
    const exportableKpi = summary.exportable ?? { kilos: 0, percentage: 0 };
    const comercialKpi = summary.comercial ?? { kilos: 0, percentage: 0 };
    const mermaKpi = summary.merma ?? { kilos: 0, percentage: 0 };
    const totalKilos = Number(summary.total_kilos ?? 0);
    const totalCantidad = Number(summary.total_cantidad ?? 0);
    const totalRecepciones = Number(summary.total_receptions ?? 0);
    const promedioCalidad = summary.promedio_calidad ?? null;
    const exportablePct = Number(exportableKpi.percentage ?? 0);
    const comercialPct = Number(comercialKpi.percentage ?? 0);
    const mermaPct = Number(mermaKpi.percentage ?? 0);
    const exportableKilos = Number(exportableKpi.kilos ?? 0);
    const comercialKilos = Number(comercialKpi.kilos ?? 0);
    const mermaKilos = Number(mermaKpi.kilos ?? 0);

    const stackedPercents = {
        exportable: Math.max(0, Math.min(100, exportablePct)),
        comercial: Math.max(0, Math.min(100, comercialPct)),
        merma: Math.max(0, Math.min(100, mermaPct)),
    };
    const hasKpiTrend = (kpiTrend?.categories?.length ?? 0) > 0;

    const kpiTrendOptions = useMemo(
        () => ({
            chart: { type: 'area', height: 280, toolbar: { show: false } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: kpiTrend?.categories ?? [] },
            yaxis: {
                labels: {
                    formatter: (value) => value.toLocaleString('es-CL', { maximumFractionDigits: 0 }),
                },
            },
            tooltip: {
                y: {
                    formatter: (value) => value.toLocaleString('es-CL', { maximumFractionDigits: 0 }) + ' kg',
                },
            },
            colors: ['#10b981', '#3b82f6', '#f97316', '#64748b'],
            fill: {
                type: 'gradient',
                gradient: { shadeIntensity: 0.3, opacityFrom: 0.7, opacityTo: 0.1, stops: [0, 90, 100] },
            },
            legend: { position: 'top' },
        }),
        [kpiTrend],
    );

    const kpiTrendSeries = useMemo(() => kpiTrend?.series ?? [], [kpiTrend]);

    const handleFilter = () => {
        const params = {
            especie_id: selectedEspecie,
            variedad_id: selectedVariedad,
            productor_id: selectedProductor,
            lote: selectedLote,
            from_date: fromDate,
            to_date: toDate,
        };
        if (selectedLotes && selectedLotes.length > 0) {
            params.lotes = selectedLotes;
        }
        router.get(route('reporteria.calidad'), params, { preserveState: true, replace: true });
    };

    const hasSelections = Boolean(selectedEspecie);
    const canExport = Boolean(selectedEspecie);

    const currentEspecie = (especies || []).find(e => e.id === parseInt(selectedEspecie));
    const chartColors = getChartColors(currentEspecie ? currentEspecie.name : 'default');

    const cherryCoverageColorsMap = {
        "ROJO": "#FF0000",
        "ROJO CAOBA": "#7f1313ff",
        "SANTINA": "#DE3163",
        "CAOBA OSCURO": "#4a1006ff",
        "NEGRO": "#000000",
        "Fuera de Color": "#808080"
    };

    const cherrySolubleSolidsColorsMap = {
        "LIGHT": "#800000",
        "DARK": "#400000",
        "BLACK": "#000000"
    };

    const isCherries = currentEspecie && currentEspecie.name === 'Cherries';

    // Chart options and series for Distribución de Calibres
    const getCherrySeriesColor = (name) => {
        const key = (name || '').toUpperCase().replace('BLACK', 'NEGRO');
        return cherryCoverageColorsMap[key] || chartColors.exportable;
    };

    const nonCherryData = Array.isArray(sizeDistribution) ? sizeDistribution : [];
    const nonCherryCategories = nonCherryData.map((item) =>
        item?.calibre ??
        item?.label ??
        item?.size ??
        item?.color ??
        item?.name ??
        item?.descripcion ??
        'N/A'
    );

    const nonCherryValueKey = useMemo(() => {
        const candidates = ['count', 'counts', 'cantidad', 'percentage', 'porcentaje', 'percent', 'value', 'valor'];
        const reference = nonCherryData.find((item) =>
            candidates.some((key) => item && item[key] !== undefined && item[key] !== null),
        );

        if (!reference) {
            return { key: null, isPercentage: false };
        }

        const matchedKey = candidates.find((key) => reference[key] !== undefined);
        const rawValue = reference[matchedKey];
        const isPercentage =
            ['percentage', 'porcentaje', 'percent'].includes(matchedKey) ||
            (typeof rawValue === 'string' && rawValue.includes('%'));

        return { key: matchedKey, isPercentage };
    }, [nonCherryData]);

    const nonCherrySeriesData = nonCherryData.map((item) =>
        coerceNumber(nonCherryValueKey.key ? item?.[nonCherryValueKey.key] : item),
    );

    const nonCherryHasData = nonCherryData.length > 0;
    const nonCherryIsPercentage = nonCherryValueKey.isPercentage;
    console.log(sizeDistribution, nonCherryCategories, nonCherrySeriesData, nonCherryHasData, nonCherryIsPercentage);
    const calibreOptions = isCherries ? {
        chart: { type: 'bar', height: 350, stacked: true },
        plotOptions: { bar: { horizontal: false, columnWidth: '75%', endingShape: 'rounded' } },
        dataLabels: {
            enabled: true,
            formatter: function (val, { seriesIndex, dataPointIndex, w }) {
                const pct = typeof val === 'number' ? val : (w?.config?.series?.[seriesIndex]?.data?.[dataPointIndex] ?? 0);
                const countsSeries = w?.config?.countsSeries || [];
                const abs = (countsSeries[seriesIndex] && countsSeries[seriesIndex].data)
                  ? countsSeries[seriesIndex].data[dataPointIndex]
                  : null;
                return abs !== null ? `${pct}% (${abs})` : `${pct}%`;
            }
        },
        stroke: { show: true, width: 1, colors: ['#fff'] },
        xaxis: { categories: (sizeDistribution && sizeDistribution.categories) ? sizeDistribution.categories : [] },
        yaxis: {
            title: { text: 'Porcentaje' },
            labels: { formatter: (val) => val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }
        },
        fill: { opacity: 1 },
        colors: (sizeDistribution && sizeDistribution.series) ? sizeDistribution.series.map(s => getCherrySeriesColor(s.name)) : [],
        legend: { position: 'top', horizontalAlign: 'left', offsetX: 40 },
        countsSeries: (sizeDistribution && sizeDistribution.countsSeries) ? sizeDistribution.countsSeries : [],
        tooltip: {
          custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const pct = series[seriesIndex][dataPointIndex] ?? 0;
            const countsSeries = w.config.countsSeries || [];
            const abs = (countsSeries[seriesIndex] && countsSeries[seriesIndex].data)
              ? countsSeries[seriesIndex].data[dataPointIndex] : null;
            const cat = (w.config.xaxis.categories || [])[dataPointIndex] || '';
            const ser = (w.config.series || [])[seriesIndex]?.name || '';
            return `<div class=\"px-2 py-1\"><strong>${ser}</strong> - ${cat}: ${pct}%` + (abs !== null ? ` (${abs})` : '') + `</div>`;
          }
        }
    } : {
        chart: { type: 'bar', height: 350 },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
        dataLabels: {
            enabled: true,
            formatter: function (val, { seriesIndex, dataPointIndex, w }) {
                const pct = typeof val === 'number' ? val : (w?.config?.series?.[seriesIndex]?.data?.[dataPointIndex] ?? 0);
                const countsSeries = w?.config?.countsSeries || [];
                const abs = (countsSeries[seriesIndex] && countsSeries[seriesIndex].data)
                  ? countsSeries[seriesIndex].data[dataPointIndex]
                  : null;
                return abs !== null ? `${pct}% (${abs})` : `${pct}%`;
            }
        },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: nonCherryCategories },
        yaxis: {
            title: { text: nonCherryIsPercentage ? 'Porcentaje' : 'Cantidad' },
            labels: {
                formatter: (val) =>
                    Number(val ?? 0).toLocaleString('es-CL', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: nonCherryIsPercentage ? 2 : 0,
                    }),
            },
        },
        fill: { opacity: 1, colors: [chartColors.exportable] },
        tooltip: {
            y: {
                formatter: (val) => {
                    const formatted = Number(val ?? 0).toLocaleString('es-CL', {
                        minimumFractionDigits: 0,
                        maximumFractionDigits: nonCherryIsPercentage ? 2 : 0,
                    });
                    return nonCherryIsPercentage ? `${formatted}%` : `${formatted} unidades`;
                },
            },
        },
    };

    const calibreSeries = isCherries
        ? (sizeDistribution && sizeDistribution.series ? sizeDistribution.series : [])
        : [{
              name: nonCherryIsPercentage ? 'Porcentaje' : 'Cantidad',
              data: nonCherrySeriesData,
          }];



    // Chart options and series for Promedio de Distribución de Firmezas
    const distFirmezaOptions = {
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: (firmnessDistribution || []).map(d => d.reading_name),
        },
        yaxis: {
            title: {
                text: 'Firmeza Promedio'
            },
            labels: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                }
            }
        },

        fill: {
            opacity: 1,
            colors: (firmnessDistribution || []).map(d => {
                const colorMap = {
                    'LIGHT': '#800000',
                    'DARK': '#400000',
                    'BLACK': '#000000'
                };
                return colorMap[d.reading_name.toUpperCase()] || chartColors.exportable;
            })
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " "
                }
            }
        }
    };

    const distFirmezaSeries = [{
        name: 'Firmeza Promedio',
        data: (firmnessDistribution || []).map(d => d.avg_firmness)
    }];

    // Chart options and series for Sólidos Solubles (°BRIX)
    const brixOptions = {
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: true
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: (solubleSolids || []).map(d => d.size),
        },
        yaxis: {
            title: {
                text: '°Brix Promedio'
            },
            labels: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                }
            }
        },
        fill: {
            opacity: 1,
            colors: isCherries ? (solubleSolids || []).map(d => cherrySolubleSolidsColorsMap[d.size]) : [chartColors.exportable]
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " °Brix"
                }
            }
        }
    };

    const brixSeries = [{
        name: '°Brix Promedio',
        data: (solubleSolids || []).map(d => d.avg_brix)
    }];

    // Chart options and series for Color de Cubrimiento
    const coverageOptions = isCherries ? {
        chart: { type: 'bar', height: 350, stacked: true },
        plotOptions: { bar: { horizontal: false, columnWidth: '75%', endingShape: 'rounded' } },
        dataLabels: {
            enabled: true,
            formatter: function (val, { seriesIndex, dataPointIndex, w }) {
                const pct = typeof val === 'number' ? val : (w?.config?.series?.[seriesIndex]?.data?.[dataPointIndex] ?? 0);
                const countsSeries = w?.config?.countsSeries || [];
                const abs = (countsSeries[seriesIndex] && countsSeries[seriesIndex].data)
                  ? countsSeries[seriesIndex].data[dataPointIndex]
                  : null;
                return abs !== null ? `${pct}% (${abs})` : `${pct}%`;
            }
        },
        stroke: { show: true, width: 1, colors: ['#fff'] },
        xaxis: { categories: (coverageColor && coverageColor.categories) ? coverageColor.categories : [] },
        yaxis: {
            title: { text: 'Porcentaje' },
            labels: { formatter: (val) => val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }
        },
        fill: { opacity: 1 },
        legend: { position: 'top', horizontalAlign: 'left', offsetX: 40 },
        countsSeries: (coverageColor && coverageColor.countsSeries) ? coverageColor.countsSeries : [],
        tooltip: {
          custom: function({ series, seriesIndex, dataPointIndex, w }) {
            const pct = series[seriesIndex][dataPointIndex] ?? 0;
            const countsSeries = w.config.countsSeries || [];
            const abs = (countsSeries[seriesIndex] && countsSeries[seriesIndex].data)
              ? countsSeries[seriesIndex].data[dataPointIndex] : null;
            const cat = (w.config.xaxis.categories || [])[dataPointIndex] || '';
            const ser = (w.config.series || [])[seriesIndex]?.name || '';
            return `<div class="px-2 py-1"><strong>${ser}</strong> - ${cat}: ${pct}%` + (abs !== null ? ` (${abs})` : '') + `</div>`;
          }
        }
    } : {
        chart: { type: 'bar', height: 350 },
        plotOptions: { bar: { horizontal: false, columnWidth: '55%', endingShape: 'rounded' } },
        dataLabels: { enabled: false },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: (coverageColor || []).map(d => d.color) },
        yaxis: {
            title: { text: 'Porcentaje' },
            labels: { formatter: (val) => val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) }
        },
        fill: { opacity: 1, colors: [chartColors.exportable] },
    };

    const coverageSeries = isCherries ? (coverageColor && coverageColor.series ? coverageColor.series : []) : [{
        name: 'Porcentaje',
        data: (coverageColor || []).map(d => d.percentage)
    }];

    // Chart options and series for Defectos de Calidad
    const qualityDefectsOptions = {
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: (qualityDefects || []).map(d => d.defect),
        },
        yaxis: {
            title: {
                text: 'Cantidad'
            },
            labels: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                }
            }
        },
        fill: {
            opacity: 1,
            colors: [chartColors.defectosCalidad]
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " unidades"
                }
            }
        }
    };

    const qualityDefectsSeries = [{
        name: 'Cantidad',
        data: (qualityDefects || []).map(d => d.count)
    }];

    // Chart options and series for Defectos de Condición
    const conditionDefectsOptions = {
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: (conditionDefects || []).map(d => d.defect),
        },
        yaxis: {
            title: {
                text: 'Cantidad'
            },
            labels: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                }
            }
        },
        fill: {
            opacity: 1,
            colors: [chartColors.defectosCondicion]
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " unidades"
                }
            }
        }
    };

    const conditionDefectsSeries = [{
        name: 'Cantidad',
        data: (conditionDefects || []).map(d => d.count)
    }];

    // Chart options and series for Daño Plaga
    const pestDamageOptions = {
        chart: {
            type: 'bar',
            height: 350
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '55%',
                endingShape: 'rounded'
            },
        },
        dataLabels: {
            enabled: false
        },
        stroke: {
            show: true,
            width: 2,
            colors: ['transparent']
        },
        xaxis: {
            categories: (pestDamage || []).map(d => d.damage_type),
        },
        yaxis: {
            title: {
                text: 'Cantidad'
            },
            labels: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                }
            }
        },
        fill: {
            opacity: 1,
            colors: [chartColors.danosPlaga]
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " unidades"
                }
            }
        }
    };

    const pestDamageSeries = [{
        name: 'Cantidad',
        data: (pestDamage || []).map(d => d.count)
    }];

    const handleExportConsolidated = () => {
        const params = new URLSearchParams({
            especie_id: selectedEspecie,
            variedad_id: selectedVariedad,
            productor_id: selectedProductor,
            lote: selectedLote,
        });
        if (fromDate) params.append('from_date', fromDate);
        if (toDate) params.append('to_date', toDate);
        (selectedLotes || []).forEach(l => params.append('lotes[]', l));
        window.location.href = route('reporteria.export.consolidated') + '?' + params.toString();
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Reportería de Calidad</h2>}
        >
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex items-end space-x-4 mb-2">
                            <div>
                                <Label htmlFor="especie">Especie</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className="w-48 justify-between"
                                        >
                                            {selectedEspecie
                                                ? (availableSpecies.find(option => option.id === selectedEspecie)?.name
                                                    ?? especies.find(especie => String(especie.id) === selectedEspecie)?.name
                                                    ?? "Seleccione especie...")
                                                : "Seleccione especie..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar especie..." />
                                            <CommandEmpty>No se encontró especie.</CommandEmpty>
                                            <CommandGroup>
                                                {availableSpecies.map((option) => (
                                                    <CommandItem
                                                        key={option.id}
                                                        value={option.name}
                                                        onSelect={() => {
                                                            setSelectedEspecie(option.id);
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedEspecie === option.id
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {option.name}
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <div>
                                <Label htmlFor="variedad">Variedad</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className="w-48 justify-between"
                                            disabled={!availableVariedades.length}
                                        >
                                            {selectedVariedad
                                                ? (availableVariedades.find(option => option.id === selectedVariedad)?.name
                                                    ?? "Seleccione variedad...")
                                                : "Seleccione variedad..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar variedad..." />
                                            <CommandEmpty>No se encontró variedad.</CommandEmpty>
                                            <CommandGroup>
                                                {availableVariedades.map((variedad) => (
                                                    <CommandItem
                                                        key={variedad.id}
                                                        value={variedad.name}
                                                        onSelect={() => {
                                                            setSelectedVariedad(variedad.id);
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedVariedad === variedad.id
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {variedad.name}
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <div>
                                <Label htmlFor="productor">Productor</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className="w-64 justify-between"
                                        >
                                            {selectedProductor !== 'all'
                                                ? (availableProductores.find(productor => productor.id === selectedProductor)?.name
                                                    ?? producers.find(productor => String(productor.idprod ?? productor.id) === selectedProductor)?.name
                                                    ?? "Seleccione productor...")
                                                : "Todos los productores"}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-64 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar productor..." />
                                            <CommandEmpty>No se encontró productor.</CommandEmpty>
                                            <CommandGroup>
                                                <CommandItem
                                                    value="all"
                                                    onSelect={() => setSelectedProductor('all')}
                                                >
                                                    <Check
                                                        className={cn(
                                                            "mr-2 h-4 w-4",
                                                            selectedProductor === 'all' ? "opacity-100" : "opacity-0"
                                                        )}
                                                    />
                                                    Todos
                                                </CommandItem>
                                                {availableProductores.map(productor => (
                                                    <CommandItem
                                                        key={productor.id}
                                                        value={productor.name}
                                                        onSelect={() => {
                                                            setSelectedProductor(productor.id);
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedProductor === productor.id
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {productor.name}
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <div>
                                <Label htmlFor="lote">Lote</Label>
                                <Popover>
                                    <PopoverTrigger asChild>
                                        <Button
                                            variant="outline"
                                            role="combobox"
                                            className="w-48 justify-between"
                                            disabled={!availableLotes.length}
                                        >
                                            {selectedLote
                                                ? (availableLotes.find(lote => lote.value === selectedLote)?.label ?? "Seleccione lote...")
                                                : "Seleccione lote..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar lote..." />
                                            <CommandEmpty>No se encontró lote.</CommandEmpty>
                                            <CommandGroup>

                                                {availableLotes.map(lote => (
                                                    <CommandItem
                                                        key={lote.value}
                                                        value={lote.label}
                                                        onSelect={() => {
                                                            setSelectedLote(lote.value);
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedLote === lote.value
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {lote.label}
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <Button onClick={handleFilter} disabled={!hasSelections}>Filtrar</Button>
                            <Button onClick={handleExportConsolidated} className="ml-4" disabled={!canExport}>
                                Exportar Consolidado
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4 mt-6">
                            <div className="rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-medium text-slate-500 uppercase">Recepciones</p>
                                <p className="mt-2 text-2xl font-semibold text-slate-900">{formatNumber(totalRecepciones)}</p>
                                <p className="text-sm text-slate-500 mt-1">
                                    Promedio calidad:{' '}
                                    {promedioCalidad !== null
                                        ? formatNumber(promedioCalidad, { minimumFractionDigits: 1, maximumFractionDigits: 1 })
                                        : 'N/A'}
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-medium text-slate-500 uppercase">Kilos recepcionados</p>
                                <p className="mt-2 text-2xl font-semibold text-slate-900">
                                    {formatNumber(totalKilos, { maximumFractionDigits: 0 })}
                                    <span className="ml-2 text-sm font-normal text-slate-500">kg</span>
                                </p>
                                <p className="text-sm text-slate-500 mt-1">Cantidad: {formatNumber(totalCantidad)}</p>
                            </div>
                            <div className="rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-medium text-slate-500 uppercase">Fruta exportable</p>
                                <p className="mt-2 text-2xl font-semibold text-emerald-600">
                                    {formatNumber(exportableKilos, { maximumFractionDigits: 0 })}
                                    <span className="ml-2 text-sm font-normal text-slate-500">kg</span>
                                </p>
                                <p className="text-sm text-emerald-600 mt-1">
                                    {formatNumber(exportablePct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}% del total
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-medium text-slate-500 uppercase">Fruta comercial</p>
                                <p className="mt-2 text-2xl font-semibold text-blue-600">
                                    {formatNumber(comercialKilos, { maximumFractionDigits: 0 })}
                                    <span className="ml-2 text-sm font-normal text-slate-500">kg</span>
                                </p>
                                <p className="text-sm text-blue-600 mt-1">
                                    {formatNumber(comercialPct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}% del total
                                </p>
                            </div>
                            <div className="rounded-lg border border-slate-200 p-4">
                                <p className="text-xs font-medium text-slate-500 uppercase">Merma</p>
                                <p className="mt-2 text-2xl font-semibold text-orange-600">
                                    {formatNumber(mermaKilos, { maximumFractionDigits: 0 })}
                                    <span className="ml-2 text-sm font-normal text-slate-500">kg</span>
                                </p>
                                <p className="text-sm text-orange-600 mt-1">
                                    {formatNumber(mermaPct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}% del total
                                </p>
                            </div>
                        </div>
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
                            <div className="rounded-lg border border-slate-200 p-4">
                                <h3 className="text-sm font-semibold text-slate-700">Distribución porcentual</h3>
                                <p className="text-xs text-slate-500">Sobre kilos recepcionados</p>
                                <div className="mt-4 h-3 w-full rounded-full bg-slate-100 overflow-hidden flex">
                                    <div
                                        className="h-full bg-emerald-500"
                                        style={{ width: `${stackedPercents.exportable}%` }}
                                    />
                                    <div
                                        className="h-full bg-blue-500"
                                        style={{ width: `${stackedPercents.comercial}%` }}
                                    />
                                    <div
                                        className="h-full bg-orange-500"
                                        style={{ width: `${stackedPercents.merma}%` }}
                                    />
                                </div>
                                <div className="mt-3 space-y-1 text-xs text-slate-600">
                                    <div className="flex items-center justify-between">
                                        <span className="flex items-center gap-2">
                                            <span className="inline-flex h-2 w-2 rounded-full bg-emerald-500" />
                                            Exportable
                                        </span>
                                        <span>{formatNumber(exportablePct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="flex items-center gap-2">
                                            <span className="inline-flex h-2 w-2 rounded-full bg-blue-500" />
                                            Comercial
                                        </span>
                                        <span>{formatNumber(comercialPct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="flex items-center gap-2">
                                            <span className="inline-flex h-2 w-2 rounded-full bg-orange-500" />
                                            Merma
                                        </span>
                                        <span>{formatNumber(mermaPct, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%</span>
                                    </div>
                                </div>
                            </div>
                            <div className="rounded-lg border border-slate-200 p-4">
                                <h3 className="text-sm font-semibold text-slate-700">Tendencia de kilos</h3>
                                <p className="text-xs text-slate-500">Últimas recepciones registradas</p>
                                <div className="mt-4">
                                    {hasKpiTrend ? (
                                        <Chart options={kpiTrendOptions} series={kpiTrendSeries} type="area" height={280} />
                                    ) : (
                                        <div className="h-[280px] flex items-center justify-center text-sm text-slate-500">
                                            No hay datos suficientes para la tendencia.
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                        {/*<div className="flex items-end space-x-4 mb-6">
                            <div className="flex flex-col">
                                <Label htmlFor="from_date">Desde</Label>
                                <input id="from_date" type="date" className="border rounded px-2 py-2" value={fromDate} onChange={e=>setFromDate(e.target.value)} />
                            </div>
                            <div className="flex flex-col">
                                <Label htmlFor="to_date">Hasta</Label>
                                <input id="to_date" type="date" className="border rounded px-2 py-2" value={toDate} onChange={e=>setToDate(e.target.value)} />
                            </div>
                             <div className="flex flex-col">
                                <Label htmlFor="lotesMulti">Lotes (multi)</Label>
                                <select id="lotesMulti" multiple className="border rounded px-2 py-2 min-w-[12rem] h-24" value={selectedLotes} onChange={(e)=>{
                                    const options = Array.from(e.target.selectedOptions).map(o=>o.value);
                                    setSelectedLotes(options);
                                }}>
                                    {(lotes || []).map(l => (
                                        <option key={l.id} value={String(l.numero_g_recepcion)}>{l.numero_g_recepcion}</option>
                                    ))}
                                </select>
                            </div>
                        </div>*/}

                        {!ready ? (
                            <div className="mt-8 p-10 border-2 border-dashed border-gray-300 rounded-lg text-center text-gray-600">
                                Selecciona una especie y presiona "Filtrar" para ver los gráficos.
                            </div>
                        ) : (
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Distribución de Calibres</h3>

                                {isCherries ? (
                                    (sizeDistribution && sizeDistribution.series && sizeDistribution.series.length > 0) ? (
                                        <Chart
                                            options={calibreOptions}
                                            series={calibreSeries}
                                            type="bar"
                                            height={350}
                                        />
                                    ) : (
                                        <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                            <p className='text-center text-gray-500'>No hay datos de calibres para la selección actual.</p>
                                        </div>
                                    )
                                ) : (
                                    (nonCherryHasData) ? (
                                        <Chart
                                            options={calibreOptions}
                                            series={calibreSeries}
                                            type="bar"
                                            height={350}
                                        />
                                    ) : (
                                        <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                            <p className='text-center text-gray-500'>No hay datos de calibres para la selección actual.</p>
                                        </div>
                                    )
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">% Distribución de Firmezas por Segregación de Color</h3>
                                {averageFirmness && averageFirmness.categories && averageFirmness.series ? (
                                    <Chart
                                        options={{
                                            chart: {
                                                type: 'bar',
                                                height: 350,
                                                stacked: true,
                                            },
                                            plotOptions: {
                                                bar: {
                                                    horizontal: false,
                                                },
                                            },
                                            stroke: {
                                                width: 1,
                                                colors: ['#fff']
                                            },

                                            xaxis: {
                                                categories: averageFirmness.categories,
                                            },
                                            yaxis: {
                                                title: {
                                                    text: 'Cantidad de Frutas'
                                                },
                                            },
                                            fill: {
                                                opacity: 1
                                            },
                                            //
                                            colors:['#dc0c15', '#71160e', '#2b1d16'],
                                            legend: {
                                                position: 'top',
                                                horizontalAlign: 'left',
                                                offsetX: 40
                                            }
                                        }}
                                        series={averageFirmness.series}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de firmeza para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Promedio de Distribución de Firmezas</h3>
                                {console.log("FD:", firmnessDistribution)}
                                {firmnessDistribution && firmnessDistribution.length > 0 ? (

                                    <Chart
                                        options={distFirmezaOptions}
                                        series={distFirmezaSeries}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de distribución de firmeza para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Promedio °BRIX</h3>
                                {console.log("SS:", solubleSolids)}
                                {solubleSolids && solubleSolids.length > 0 ? (
                                    <Chart
                                        options={brixOptions}
                                        series={brixSeries}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de sólidos solubles para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Color de Cubrimiento</h3>
                                {isCherries ? (
                                    (coverageColor && coverageColor.series && coverageColor.series.length > 0) ? (
                                        <Chart options={coverageOptions} series={coverageSeries} type="bar" height={350} />
                                    ) : (
                                        <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                            <p className='text-center text-gray-500'>No hay datos de color de cubrimiento para la selección actual.</p>
                                        </div>
                                    )
                                ) : (
                                    (coverageColor && coverageColor.length > 0) ? (
                                        <Chart options={coverageOptions} series={coverageSeries} type="bar" height={350} />
                                    ) : (
                                        <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                            <p className='text-center text-gray-500'>No hay datos de color de cubrimiento para la selección actual.</p>
                                        </div>
                                    )
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Defectos de Calidad</h3>
                                {qualityDefects && qualityDefects.length > 0 ? (
                                    <Chart
                                        options={qualityDefectsOptions}
                                        series={qualityDefectsSeries}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de defectos de calidad para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                             <div>
                                <h3 className="text-lg font-semibold mb-4">Defectos de Condición</h3>
                                {conditionDefects && conditionDefects.length > 0 ? (
                                    <Chart
                                        options={conditionDefectsOptions}
                                        series={conditionDefectsSeries}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de defectos de condición para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Daño Plaga</h3>
                                {pestDamage && pestDamage.length > 0 ? (
                                    <Chart
                                        options={pestDamageOptions}
                                        series={pestDamageSeries}
                                        type="bar"
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de daño por plaga para la selección actual.</p>
                                    </div>
                                )}
                            </div>
                        </div>
                        )}

                        {ready && (
                        <div className="mt-8">
                            <h3 className="text-lg font-semibold mb-4">Detalle de Recepciones</h3>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Productor</TableHead>
                                        <TableHead>Especie</TableHead>
                                        <TableHead>Variedad</TableHead>
                                        <TableHead>Nota de Calidad</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {receptionDetails && receptionDetails.data && receptionDetails.data.map((recepcion, index) => (
                                        <TableRow key={index}>
                                            <TableCell>{recepcion.fecha_g_recepcion}</TableCell>
                                            <TableCell>{recepcion.n_emisor}</TableCell>
                                            <TableCell>{recepcion.n_especie}</TableCell>
                                            <TableCell>{recepcion.n_variedad}</TableCell>
                                            <TableCell>{recepcion.nota_calidad}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <Pagination links={receptionDetails && receptionDetails.links} />
                        </div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
