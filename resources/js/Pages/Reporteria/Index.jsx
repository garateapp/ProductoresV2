import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { useState, useEffect } from 'react';
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

export default function Index({ auth, especies, producers, lotes, filters, sizeDistribution, averageFirmness, firmnessDistribution, solubleSolids, coverageColor, qualityDefects, conditionDefects, pestDamage, receptionDetails }) {
    const [selectedEspecie, setSelectedEspecie] = useState(filters.especie_id || '');
    const [variedades, setVariedades] = useState([]);
    const [selectedVariedad, setSelectedVariedad] = useState(filters.variedad_id || '');
    const [selectedProductor, setSelectedProductor] = useState(filters.productor_id || 'all');
    const [selectedLote, setSelectedLote] = useState(filters.lote || '');

    useEffect(() => {
        if (selectedEspecie) {
            const especie = especies.find(e => e.id === parseInt(selectedEspecie));
            setVariedades(especie ? especie.variedads : []);
        } else {
            setVariedades([]);
        }
    }, [selectedEspecie, especies]);

    const handleFilter = () => {
        router.get(route('reporteria.calidad'), {
            especie_id: selectedEspecie,
            variedad_id: selectedVariedad,
            productor_id: selectedProductor,
            lote: selectedLote,
        }, { preserveState: true, replace: true });
    };

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
    const calibreOptions = {
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
            categories: (sizeDistribution || []).map(d => d.calibre),
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
            colors: [chartColors.exportable]
        },
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " unidades"
                }
            }
        }
    };

    const calibreSeries = [{
        name: 'Cantidad',
        data: (sizeDistribution || []).map(d => d.count)
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
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " %"
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
    const coverageOptions = {
        chart: {
            type: isCherries ? 'radialBar' : 'bar',
            height: 350
        },
        plotOptions: {
            radialBar: {
                dataLabels: {
                    value: {
                        formatter: function (val) {
                            return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + "%"
                        }
                    }
                }
            },
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
        ...(isCherries ? {} : { // Conditionally include xaxis and yaxis for bar chart
            xaxis: {
                categories: (coverageColor || []).map(d => d.color),
            },
            yaxis: {
                title: {
                    text: 'Porcentaje'
                },
                labels: {
                    formatter: function (val) {
                        return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 })
                    }
                }
            },
        }),
        fill: {
            opacity: 1,
            // colors: (coverageColor || []).map(d => {
            //     console.log("d:",d);
            //     const colorMap = {
            //         'LIGHT': '#800000',
            //         'DARK': '#400000',
            //         'BLACK': '#000000'
            //     };
            //     return colorMap[d.color.toUpperCase()] || chartColors.exportable;
            // }),
            colors: isCherries ? (coverageColor || []).map(d => cherryCoverageColorsMap[d.color]) : [chartColors.exportable]
        },
        labels: isCherries ? (coverageColor || []).map(d => d.color) : [],
        tooltip: {
            y: {
                formatter: function (val) {
                    return val.toLocaleString('es-CL', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + " %"
                }
            }
        }
    };

    const coverageSeries = [{
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
        const queryParams = new URLSearchParams({
            especie_id: selectedEspecie,
            variedad_id: selectedVariedad,
            productor_id: selectedProductor,
            lote: selectedLote,
        }).toString();
        window.location.href = route('reporteria.export.consolidated') + '?' + queryParams;
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Reportería de Calidad</h2>}
        >
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div className="flex items-end space-x-4 mb-6">
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
                                                ? especies.find(especie => especie.id === parseInt(selectedEspecie))?.name
                                                : "Seleccione especie..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar especie..." />
                                            <CommandEmpty>No se encontró especie.</CommandEmpty>
                                            <CommandGroup>
                                                {(especies || []).map(especie => (
                                                    <CommandItem
                                                        key={especie.id}
                                                        value={especie.name}
                                                        onSelect={() => {
                                                            setSelectedEspecie(especie.id.toString());
                                                            setSelectedVariedad(''); // Reset variedad when especie changes
                                                            setSelectedLote(''); // Reset lote when especie changes
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedEspecie === especie.id.toString()
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {especie.name}
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
                                            disabled={!selectedEspecie}
                                        >
                                            {selectedVariedad
                                                ? variedades.find(variedad => variedad.id === parseInt(selectedVariedad))?.name
                                                : "Seleccione variedad..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar variedad..." />
                                            <CommandEmpty>No se encontró variedad.</CommandEmpty>
                                            <CommandGroup>
                                                {(variedades || []).map(variedad => (
                                                    <CommandItem
                                                        key={variedad.id}
                                                        value={variedad.name}
                                                        onSelect={() => {
                                                            setSelectedVariedad(variedad.id.toString());
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedVariedad === variedad.id.toString()
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
                                                ? producers.find(productor => productor.id === parseInt(selectedProductor))?.name
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
                                                {(producers || []).map(productor => (
                                                    <CommandItem
                                                        key={productor.id}
                                                        value={productor.name}
                                                        onSelect={() => {
                                                            setSelectedProductor(productor.id.toString());
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedProductor === productor.id.toString()
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
                                            disabled={!selectedEspecie}
                                        >
                                            {selectedLote
                                                ? lotes.find(lote => lote.numero_g_recepcion.toString() === selectedLote)?.numero_g_recepcion
                                                : "Seleccione lote..."}
                                            <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                                        </Button>
                                    </PopoverTrigger>
                                    <PopoverContent className="w-48 p-0">
                                        <Command>
                                            <CommandInput placeholder="Buscar lote..." />
                                            <CommandEmpty>No se encontró lote.</CommandEmpty>
                                            <CommandGroup>
                                                {console.log("lotes", lotes)}
                                                {(lotes || []).map(lote => (
                                                    <CommandItem
                                                        key={lote.id}
                                                        value={lote.numero_g_recepcion.toString()}
                                                        onSelect={() => {
                                                            setSelectedLote(lote.numero_g_recepcion.toString());
                                                        }}
                                                    >
                                                        <Check
                                                            className={cn(
                                                                "mr-2 h-4 w-4",
                                                                selectedLote === lote.numero_g_recepcion.toString()
                                                                    ? "opacity-100"
                                                                    : "opacity-0"
                                                            )}
                                                        />
                                                        {lote.numero_g_recepcion}
                                                    </CommandItem>
                                                ))}
                                            </CommandGroup>
                                        </Command>
                                    </PopoverContent>
                                </Popover>
                            </div>
                            <Button onClick={handleFilter}>Filtrar</Button>
                            {selectedEspecie && (
                                <Button onClick={handleExportConsolidated} className="ml-4">
                                    Exportar Consolidado
                                </Button>
                            )}
                        </div>

                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-8 mt-8">
                            <div>
                                <h3 className="text-lg font-semibold mb-4">Distribución de Calibres</h3>
                                {sizeDistribution && sizeDistribution.length > 0 ? (
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
                                {coverageColor && coverageColor.length > 0 ? (
                                    <Chart
                                        options={coverageOptions}
                                        series={isCherries ? (coverageColor || []).map(d => d.percentage) : coverageSeries}
                                        type={isCherries ? 'radialBar' : 'bar'}
                                        height={350}
                                    />
                                ) : (
                                    <div className="border-dashed border-2 border-gray-300 rounded-lg h-full flex items-center justify-center">
                                        <p className='text-center text-gray-500'>No hay datos de color de cubrimiento para la selección actual.</p>
                                    </div>
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
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
