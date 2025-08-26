import React, { useState, useEffect, useCallback } from 'react';
import { Link, useForm, usePage, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { FileText, Trash2, UploadCloud } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/Components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Switch } from '@/Components/ui/switch';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ recepciones, especies, variedades = [], filters, isProducer, totalRecepciones, totalKilos = 0, parametros, photoTypes = [] }) {
  const { flash } = usePage().props;
  const { data: calidadData, setData: setCalidadData, post: postCalidad, processing: processingCalidad, errors: errorsCalidad, reset: resetCalidad } = useForm({
    cantidad_total_muestra: '',
    materia_vegetal: false,
    piedras: false,
    barro: false,
    pedicelo_largo: false,
    racimo: false,
    esponjas: false,
    h_esponjas: 'BUENO',
    llenado_tottes: 'CORRECTO',
    embalaje: '',
    obs_ext: '',
  });

  const { data: detalleData, setData: setDetalleData, post: postDetalle, processing: processingDetalle, errors: errorsDetalle, reset: resetDetalle, transform } = useForm({
    parametro_id: '',
    valor_id: '',
    exportable: false,
    cantidad_muestra: '',
    temperatura: '',
    valor_presion: '',
    calidad_id: null, // Keep this initial null
  });

  // Add this transform function
  transform((data) => ({
    ...data,
    calidad_id: calidadId, // Use the state variable here
  }));

  const { data: filterData, setData: setFilterData, get: getFilterData } = useForm({
    search: filters.search || '',
    especie_id: filters.especie_id || '',
    variedad_id: filters.variedad_id || '',
  });

  const { data: photoData, setData: setPhotoData, post: postPhoto, processing: processingPhoto, errors: errorsPhoto, reset: resetPhoto } = useForm({
    photo: null,
    photo_type_id: '',
  });

  const [isModalOpen, setIsModalOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('condiciones');
  const [selectedRecepcion, setSelectedRecepcion] = useState(null);
  const [valores, setValores] = useState([]);
  const [calidadId, setCalidadId] = useState(null);
  const [photos, setPhotos] = useState([]);
  const [detallesAgregados, setDetallesAgregados] = useState([]);
  const [defectosAgregados, setDefectosAgregados] = useState([]);
  const [desordenFisiologicoAgregados, setDesordenFisiologicoAgregados] = useState([]);
  const [curvaCalibreAgregados, setCurvaCalibreAgregados] = useState([]);
  const [indiceMadurezAgregados, setIndiceMadurezAgregados] = useState([]);
  const [hasFirmproDetails, setHasFirmproDetails] = useState(false);

  const fetchCalidadData = async (recepcion) => {
    try {
      const response = await fetch(route('control-calidad.get-calidad', recepcion.id));
      const existingCalidad = await response.json();
      if (existingCalidad) {
        const transformedCalidad = {
            ...existingCalidad,
            materia_vegetal: existingCalidad.materia_vegetal === 'SI',
            piedras: existingCalidad.piedras === 'SI',
            barro: existingCalidad.barro === 'SI',
            pedicelo_largo: existingCalidad.pedicelo_largo === 'SI',
            racimo: existingCalidad.racimo === 'SI',
            esponjas: existingCalidad.esponjas === 'SI',
          };
        setCalidadData(transformedCalidad);
        setCalidadId(existingCalidad.id);
        setPhotos(existingCalidad.photos || []);
      }
    } catch (error) {
      console.error('Error fetching existing calidad or detalles:', error);
    }
  };

  const handleOpenModal = (recepcion) => {
    setSelectedRecepcion(recepcion);
    resetCalidad();
    resetDetalle();
    resetPhoto();
    setCalidadId(null);
    setPhotos([]);
    setDefectosAgregados([]);
    setDesordenFisiologicoAgregados([]);
    fetchCalidadData(recepcion);
    setIsModalOpen(true);
  };

  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSelectedRecepcion(null);
  };

  const submitPhoto = async (e) => {
    e.preventDefault();
    console.log('Attempting to submit photo. Current calidadId (from state):', calidadId);
    if (!calidadId) {
        alert('Debe guardar las condiciones de llegada antes de subir una foto.');
        return;
    }
    console.log('Photo data before post:', photoData);
    console.log('processingPhoto state:', processingPhoto);

    const formData = new FormData();
    formData.append('photo', photoData.photo);
    formData.append('photo_type_id', photoData.photo_type_id);
    formData.append('calidad_id', calidadId);

    // ADD CSRF TOKEN TO FORMDATA
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    formData.append('_token', csrfToken);
    // END CSRF TOKEN ADDITION

    setPhotoData('processing', true);

    try {
        console.log('Before fetch call.');
        const response = await fetch(route('quality-control-photos.store', calidadId), {
            method: 'POST',
            body: formData,
        });
        console.log('After fetch call. Response object:', response);

        console.log('Before parsing JSON response.');
        const data = await response.json();
        console.log('After parsing JSON response. Data object:', data);

        if (response.ok) {
            console.log('submitPhoto fetch success. Response data:', data);
            resetPhoto();
            setPhotos(prevPhotos => {
                const newPhoto = data.photo;
                const newPhotos = [...prevPhotos, newPhoto];
                return newPhotos;
            });
            alert(data.message);
        } else {
            console.error('submitPhoto fetch error. Response data:', data);
            if (data.errors) {
                alert('Error: ' + Object.values(data.errors).flat().join('\n'));
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        }
    } catch (error) {
        console.error('submitPhoto fetch failed:', error);
        alert('Network error or unexpected issue.');
    } finally {
        setPhotoData('processing', false);
        console.log('submitPhoto finally block reached. processingPhoto set to false.');
    }
  };


  const handleDeletePhoto = async (photoId) => {
    if (confirm('¿Estás seguro de que quieres eliminar esta foto?')) {
        try {
            const response = await fetch(route('quality-control-photos.destroy', photoId), {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
            });

            const data = await response.json();

            if (response.ok) {
                console.log('handleDeletePhoto fetch success. Response data:', data);
                setPhotos(prevPhotos => prevPhotos.filter(photo => photo.id !== data.deleted_id));
                alert(data.message);
            } else {
                console.error('handleDeletePhoto fetch error. Response data:', data);
                if (data.errors) {
                    alert('Error: ' + Object.values(data.errors).flat().join('\n'));
                } else {
                    alert('Error: ' + (data.message || 'Unknown error'));
                }
            }
        } catch (error) {
            console.error('handleDeletePhoto fetch failed:', error);
            alert('Network error or unexpected issue.');
        }
    }
  };

  const handleSearchChange = (e) => {
    setFilterData('search', e.target.value);
    router.get(route('control-calidad.index', { ...filterData, search: e.target.value }), { preserveState: true, replace: true });
  };

  const handleEspecieFilter = (especieId) => {
    setFilterData('especie_id', especieId);
    setFilterData('variedad_id', ''); // Reset variedad filter when specie changes
    router.get(route('control-calidad.index', { ...filterData, especie_id: especieId, variedad_id: '' }), { preserveState: true, replace: true });
  };

  const handleVariedadFilter = (variedadId) => {
    setFilterData('variedad_id', variedadId);
    router.get(route('control-calidad.index', { ...filterData, variedad_id: variedadId }), { preserveState: true, replace: true });
  };

  const getValores = useCallback(async (parametroId, especie) => {
    if (!parametroId || !especie) {
      setValores([]);
      return;
    }
    try {
      const response = await fetch(route('control-calidad.get-valores', { parametro_id: parametroId, especie: especie }));
      const data = await response.json();
      setValores(data);
    } catch (error) {
      console.error('Error fetching valores:', error);
      setValores([]);
    }
  }, []);

  const fetchDetalles = useCallback(async () => {
    if (!calidadId || !selectedRecepcion) {
      return;
    }
    try {
      const response = await fetch(route('control-calidad.get-detalles', selectedRecepcion.id));
      const data = await response.json();
      setDetallesAgregados(data.detalles || []);
      setDefectosAgregados(data.defectos || []);
      setDesordenFisiologicoAgregados(data.desordenFisiologico || []);
      setCurvaCalibreAgregados(data.curvaCalibre || []);
      setIndiceMadurezAgregados(data.indiceMadurez || []);
      setHasFirmproDetails(data.hasFirmproDetails);
    } catch (error) {
      console.error('Error fetching details:', error);
    }
  }, [calidadId, selectedRecepcion]);

  const submitCalidad = (e) => {
    e.preventDefault();
    postCalidad(route('control-calidad.store-calidad'), {
      data: { ...calidadData, recepcion_id: selectedRecepcion.id },
      onSuccess: (page) => {
        // Access calidad_id directly from page.props
        const newCalidadId = page.props.flash.calidad_id;
        console.log('submitCalidad onSuccess - newCalidadId from flash:', newCalidadId);
        if (newCalidadId) {
            setCalidadId(newCalidadId);
        }
        console.log('submitCalidad onSuccess - calidadId state after set:', newCalidadId);
        // Handle success message
        if (page.props.success) {
            alert(page.props.success);
        }
        // Re-fetch data for the current reception to update modal state
        fetchCalidadData(selectedRecepcion);
      },
      onError: (errors) => {
        console.error('Error al guardar condiciones de llegada:', errors);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  const submitDetalle = (e) => {
    e.preventDefault();
    console.log('submitDetalle - calidadId at start:', calidadId);
    if (!calidadId) {
      alert('Primero debe guardar las condiciones de llegada.');
      return;
    }

    postDetalle(route('control-calidad.store-detalle'), {
      onSuccess: () => {
        fetchDetalles();
        resetDetalle();
      },
      onError: (errors) => {
        console.error('Error al guardar detalle de defecto:', errors);
      },
      preserveState: true,
      preserveScroll: true,
    });
  };

  useEffect(() => {
    console.log('useEffect [flash] - flash.calidad_id:', flash?.calidad_id);
    if (calidadId && selectedRecepcion) {
      fetchDetalles();
    }
  }, [calidadId, selectedRecepcion, fetchDetalles]);

  useEffect(() => {
    if (selectedRecepcion) {
      getValores(detalleData.parametro_id, selectedRecepcion.n_especie);
    }
  }, [detalleData.parametro_id, selectedRecepcion, getValores]);

  // REMOVED THE PROBLEMATIC USEEFFECT FOR FILTERING

  useEffect(() => {
    console.log('useEffect [flash] - flash object:', flash);
    if (flash && flash.calidad_id) {
        console.log('useEffect [flash] - Setting calidadId from flash:', flash.calidad_id);
        setCalidadId(flash.calidad_id);
    }
    if (flash && flash.success) {
        alert(flash.success);
    }
  }, [flash]);

  console.log('Render - calidadId:', calidadId);

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Control de Calidad</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="mb-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <Input
              type="text"
              placeholder="Buscar por variedad, especie o lote..."
              value={filterData.search}
              onChange={handleSearchChange}
              className="max-w-sm"
            />
            <div className="flex flex-wrap gap-2">
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

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Recepciones</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalRecepciones.toLocaleString('es-CL')}</div>
              </CardContent>
            </Card>
            <Card className="bg-green-100">
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium">Total de Kilos Recepcionados</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="text-2xl font-bold">{totalKilos.toLocaleString('es-CL')} kg</div>
              </CardContent>
            </Card>
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Lote</TableHead>
                <TableHead>Agricola</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Fecha</TableHead>
                <TableHead>Guia</TableHead>
                <TableHead>Cantidad</TableHead>
                <TableHead>Kilos</TableHead>
                <TableHead>Nota</TableHead>
                <TableHead>Informe</TableHead>
                <TableHead>Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {recepciones.data.map(recepcion => (
                <TableRow key={recepcion.id}>
                  <TableCell>{recepcion.numero_g_recepcion}</TableCell>
                  <TableCell>{recepcion.n_emisor}</TableCell>
                  <TableCell>{recepcion.n_especie}</TableCell>
                  <TableCell>{recepcion.n_variedad}</TableCell>
                  <TableCell>{new Date(recepcion.fecha_g_recepcion).toLocaleDateString('es-CL')}</TableCell>
                  <TableCell>{recepcion.numero_documento_recepcion}</TableCell>
                  <TableCell>{recepcion.cantidad.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.peso_neto.toLocaleString('es-CL')}</TableCell>
                  <TableCell>{recepcion.nota_calidad === 0 ? 'S/N' : recepcion.nota_calidad}</TableCell>
                  <TableCell>
                    {recepcion.informe ? (
                      <a href={recepcion.informe} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:text-blue-800">
                        <FileText className="h-5 w-5" />
                      </a>
                    ) : (
                      '-'
                    )}
                  </TableCell>
                  <TableCell>
                    <Button onClick={() => handleOpenModal(recepcion)}>Evaluar</Button>
                    <a href={route('control-calidad.generate-report', recepcion.id)} target="_blank" rel="noopener noreferrer">
                      <Button variant="outline" className="ml-2">Informe</Button>
                    </a>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>

      {selectedRecepcion && (
        <Dialog open={isModalOpen} onOpenChange={(open) => {
          console.log('Dialog onOpenChange triggered. New open state:', open);
          setIsModalOpen(open);
        }}>
          <DialogContent className="max-w-5xl max-h-[90vh] overflow-y-auto">
            <DialogHeader>
              <DialogTitle>Evaluación de Calidad</DialogTitle>
              <DialogDescription>
                <div className="grid grid-cols-2 gap-4 text-sm">
                  <div>
                    <p><strong>Lote:</strong> {selectedRecepcion.numero_g_recepcion} - {new Date(selectedRecepcion.fecha_g_recepcion).toLocaleDateString('es-CL')}</p>
                  </div>
                  <div>
                    <p><strong>Productor:</strong> {selectedRecepcion.n_emisor}</p>
                    <p><strong>Número Guía:</strong> {selectedRecepcion.numero_documento_recepcion}</p>
                    <p><strong>Especie:</strong> {selectedRecepcion.n_especie}</p>
                    <p><strong>Variedad:</strong> {selectedRecepcion.n_variedad}</p>
                  </div>
                </div>
              </DialogDescription>
            </DialogHeader>

            <form onSubmit={submitCalidad}>
              <div className="grid gap-4 py-4">
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <Label htmlFor="t_muestra">Cantidad Total Muestra</Label>
                    <Input id="t_muestra" type="number" value={calidadData.t_muestra} onChange={(e) => setCalidadData('cantidad_total_muestra', e.target.value)} />
                  </div>
                  <div>
                    <Label htmlFor="embalaje">Embalaje</Label>
                    <Input id="embalaje" type="number" value={calidadData.embalaje} onChange={(e) => setCalidadData('embalaje', e.target.value)} />
                  </div>
                </div>
                <Tabs defaultValue="condiciones" className="w-full" onValueChange={setActiveTab}>
                  <TabsList className="grid w-full grid-cols-6">
                    <TabsTrigger value="condiciones">Condiciones de Llegada</TabsTrigger>
                    <TabsTrigger value="defectos">Defectos</TabsTrigger>
                    <TabsTrigger value="desorden-fisiologico">Desorden Fisiológico</TabsTrigger>
                    <TabsTrigger value="curva-calibre">Curva de Calibre</TabsTrigger>
                    <TabsTrigger value="indice-madurez">Indice de Madurez</TabsTrigger>
                    <TabsTrigger value="fotos">Fotos</TabsTrigger>
                  </TabsList>

                  <TabsContent value="condiciones">
                    <div className="grid grid-cols-3 gap-4">
                      <div className="flex items-center space-x-2">
                        <Switch id="materia_vegetal" checked={calidadData.materia_vegetal} onCheckedChange={(value) => setCalidadData('materia_vegetal', value)} />
                        <Label htmlFor="materia_vegetal">Materia Vegetal</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="piedras" checked={calidadData.piedras} onCheckedChange={(value) => setCalidadData('piedras', value)} />
                        <Label htmlFor="piedras">Piedras</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="barro" checked={calidadData.barro} onCheckedChange={(value) => setCalidadData('barro', value)} />
                        <Label htmlFor="barro">Barro</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="pedicelo_largo" checked={calidadData.pedicelo_largo} onCheckedChange={(value) => setCalidadData('pedicelo_largo', value)} />
                        <Label htmlFor="pedicelo_largo">Pedicelo Largo</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="racimo" checked={calidadData.racimo} onCheckedChange={(value) => setCalidadData('racimo', value)} />
                        <Label htmlFor="racimo">Racimo</Label>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Switch id="esponjas" checked={calidadData.esponjas} onCheckedChange={(value) => setCalidadData('esponjas', value)} />
                        <Label htmlFor="esponjas">Esponjas</Label>
                      </div>
                    </div>
                    <div className="grid grid-cols-3 gap-4 mt-4">
                      <div className="flex items-center space-x-2">
                        <Label htmlFor="h_esponjas">Humedad Esponjas</Label>
                        <Select value={calidadData.h_esponjas} onValueChange={(value) => setCalidadData('h_esponjas', value)}>
                          <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Seleccionar" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="BUENO">Bueno</SelectItem>
                            <SelectItem value="REGULAR">Regular</SelectItem>
                            <SelectItem value="MALO">Malo</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                      <div className="flex items-center space-x-2">
                        <Label htmlFor="llenado_tottes">Llenado Tottes</Label>
                        <Select value={calidadData.llenado_tottes} onValueChange={(value) => setCalidadData('llenado_tottes', value)}>
                          <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Seleccionar" />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value="CORRECTO">Correcto</SelectItem>
                            <SelectItem value="INCORRECTO">Incorrecto</SelectItem>
                          </SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="mt-4">
                      <Label htmlFor="obs_ext">Observaciones Externas</Label>
                      <textarea
                        id="obs_ext"
                        value={calidadData.obs_ext}
                        onChange={(e) => setCalidadData('obs_ext', e.target.value)}
                        className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 min-h-[80px]"
                        rows={4}
                      ></textarea>
                    </div>
                  </TabsContent>

                  <TabsContent value="defectos">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="parametro_id">Parámetro</Label>
                        <Select key={detalleData.parametro_id} value={detalleData.parametro_id} onValueChange={(value) => { setDetalleData('parametro_id', value); setDetalleData('valor_id', ''); }}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Parámetro" /></SelectTrigger>
                          <SelectContent>{parametros.filter(p => [3, 4, 5].includes(p.id)).map(parametro => (<SelectItem key={parametro.id} value={String(parametro.id)}>{parametro?.name || 'N/A'}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label htmlFor="valor_id">Valor</Label>
                        <Select key={detalleData.valor_id} value={detalleData.valor_id} onValueChange={(value) => setDetalleData('valor_id', value)}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Valor" /></SelectTrigger>
                          <SelectContent>{valores.map(valor => (<SelectItem key={valor.id} value={String(valor.id)}>{valor.name}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-4">
                      <div>
                        <Label htmlFor="cantidad_muestra">Cantidad de Muestra</Label>
                        <Input id="cantidad_muestra" type="number" value={detalleData.cantidad_muestra} onChange={(e) => setDetalleData('cantidad_muestra', e.target.value)} />
                      </div>
                      {calidadData.cantidad_total_muestra > 0 && calidadData.cantidad_muestra > 0 && (<div className="flex items-end"><Label className="text-sm text-gray-600">% de la muestra: {((calidadData.cantidad_muestra / calidadData.cantidad_total_muestra) * 100).toFixed(2)}%</Label></div>)}
                    </div>
                    <div className="flex items-center space-x-2 mt-4">
                      <Switch id="exportable_defectos" checked={detalleData.exportable} onCheckedChange={(value) => setDetalleData('exportable', value)} />
                      <Label htmlFor="exportable_defectos">Exportable</Label>
                    </div>
                    <Button type="button" onClick={submitDetalle} disabled={processingDetalle || !calidadId}>Agregar Defecto</Button>
                    {defectosAgregados.length > 0 && (<div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto"><h4 className="text-md font-medium mb-2">Defectos Agregados:</h4><Table><TableHeader><TableRow><TableHead>Cantidad</TableHead><TableHead>Tipo Item</TableHead><TableHead>Detalle Item</TableHead><TableHead>% Muestra</TableHead><TableHead>Categoría</TableHead><TableHead>Acciones</TableHead></TableRow></TableHeader><TableBody>{defectosAgregados.map((detalle, index) => (<TableRow key={index}><TableCell className="max-w-xs truncate">{detalle.cantidad}</TableCell><TableCell>{detalle.tipo_item || 'N/A'}</TableCell><TableCell>{detalle.detalle_item || 'N/A'}</TableCell><TableCell className="max-w-xs truncate">{detalle.porcentaje_muestra}</TableCell><TableCell className="max-w-xs truncate text-sm">{detalle.categoria || 'N/A'}</TableCell><TableCell><Button variant="ghost" size="icon" onClick={() => console.log('Delete', detalle.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button></TableCell></TableRow>))}</TableBody></Table></div>)}
                  </TabsContent>

                  <TabsContent value="desorden-fisiologico">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="parametro_id_df">Parámetro</Label>
                        <Select key={detalleData.parametro_id} value={detalleData.parametro_id} onValueChange={(value) => { setDetalleData('parametro_id', value); setDetalleData('valor_id', ''); }}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Parámetro" /></SelectTrigger>
                          <SelectContent>{parametros.filter(p => [11, 12].includes(p.id)).map(parametro => (<SelectItem key={parametro.id} value={String(parametro.id)}>{parametro?.name || 'N/A'}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label htmlFor="valor_id_df">Valor</Label>
                        <Select key={detalleData.valor_id} value={detalleData.valor_id} onValueChange={(value) => setDetalleData('valor_id', value)}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Valor" /></SelectTrigger>
                          <SelectContent>{valores.map(valor => (<SelectItem key={valor.id} value={String(valor.id)}>{valor.name}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-4">
                      <div>
                        <Label htmlFor="temperatura">Temperatura</Label>
                        <Input id="temperatura" type="number" value={detalleData.temperatura} onChange={(e) => setDetalleData('temperatura', e.target.value)} />
                      </div>
                      <div>
                        <Label htmlFor="valor_presion">Valor Presión</Label>
                        <Input id="valor_presion" type="number" value={detalleData.valor_presion} onChange={(e) => setDetalleData('valor_presion', e.target.value)} />
                      </div>
                    </div>
                    <Button type="button" onClick={submitDetalle} disabled={processingDetalle || !calidadId}>Agregar Desorden Fisiológico</Button>
                    {desordenFisiologicoAgregados.length > 0 && (<div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto"><h4 className="text-md font-medium mb-2">Desorden Fisiológico Agregados:</h4><Table><TableHeader><TableRow><TableHead>Temperatura</TableHead><TableHead>Cantidad</TableHead><TableHead>Tipo Item</TableHead><TableHead>Detalle Item</TableHead><TableHead>Valor SS</TableHead><TableHead>Acciones</TableHead></TableRow></TableHeader><TableBody>{desordenFisiologicoAgregados.map((detalle, index) => (<TableRow key={index}><TableCell className="text-sm">{detalle.temperatura || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.cantidad || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.tipo_item || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.detalle_item || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.valor_ss || 'N/A'}</TableCell><TableCell><Button variant="ghost" size="icon" onClick={() => console.log('Delete', detalle.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button></TableCell></TableRow>))}</TableBody></Table></div>)}
                  </TabsContent>

                  <TabsContent value="curva-calibre">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="parametro_id_cc">Parámetro</Label>
                        <Select key={detalleData.parametro_id} value={detalleData.parametro_id} onValueChange={(value) => { setDetalleData('parametro_id', value); setDetalleData('valor_id', ''); }}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Parámetro" /></SelectTrigger>
                          <SelectContent>{parametros.filter(p => [1,2,6].includes(p.id)).map(parametro => (<SelectItem key={parametro.id} value={String(parametro.id)}>{parametro?.name || 'N/A'}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label htmlFor="valor_id_cc">Valor</Label>
                        <Select key={detalleData.valor_id} value={detalleData.valor_id} onValueChange={(value) => setDetalleData('valor_id', value)}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Valor" /></SelectTrigger>
                          <SelectContent>{valores.map(valor => (<SelectItem key={valor.id} value={String(valor.id)}>{valor.name}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-4">
                      <div>
                        <Label htmlFor="cantidad_muestra_cc">Cantidad de Muestra</Label>
                        <Input id="cantidad_muestra_cc" type="number" value={detalleData.cantidad_muestra} onChange={(e) => setDetalleData('cantidad_muestra', e.target.value)} />
                      </div>
                      {calidadData.cantidad_total_muestra > 0 && calidadData.cantidad_muestra > 0 && (<div className="flex items-end"><Label className="text-sm text-gray-600">% de la muestra: {((calidadData.cantidad_muestra / calidadData.cantidad_total_muestra) * 100).toFixed(2)}%</Label></div>)}
                    </div>
                    <div className="flex items-center space-x-2 mt-4">
                      <Switch id="exportable_curva_calibre" checked={detalleData.exportable} onCheckedChange={(value) => setDetalleData('exportable', value)} />
                      <Label htmlFor="exportable_curva_calibre">Exportable</Label>
                    </div>
                    <Button type="button" onClick={submitDetalle} disabled={processingDetalle || !calidadId}>Agregar Curva de Calibre</Button>
                    {curvaCalibreAgregados.length > 0 && (<div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto"><h4 className="text-md font-medium mb-2">Curva de Calibre Agregados:</h4><Table><TableHeader><TableRow><TableHead>Cantidad</TableHead><TableHead>Tipo Item</TableHead><TableHead>Detalle Item</TableHead><TableHead>% Muestra</TableHead><TableHead>Categoría</TableHead><TableHead>Acciones</TableHead></TableRow></TableHeader><TableBody>{curvaCalibreAgregados.map((detalle, index) => (<TableRow key={index}><TableCell className="max-w-xs truncate">{detalle.cantidad}</TableCell><TableCell>{detalle.tipo_item || 'N/A'}</TableCell><TableCell>{detalle.detalle_item || 'N/A'}</TableCell><TableCell className="max-w-xs truncate">{detalle.porcentaje_muestra}</TableCell><TableCell className="max-w-xs truncate text-sm">{detalle.categoria || 'N/A'}</TableCell><TableCell><Button variant="ghost" size="icon" onClick={() => console.log('Delete', detalle.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button></TableCell></TableRow>))}</TableBody></Table></div>)}
                  </TabsContent>

                  <TabsContent value="indice-madurez">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <Label htmlFor="parametro_id_im">Parámetro</Label>
                        <Select key={detalleData.parametro_id} value={detalleData.parametro_id} onValueChange={(value) => { setDetalleData('parametro_id', value); setDetalleData('valor_id', ''); }}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Parámetro" /></SelectTrigger>
                          <SelectContent>{parametros.filter(p => [7, 8, 9, 10, 13, 14, 15, 16, 17, 18].includes(p.id)).map(parametro => (<SelectItem key={parametro.id} value={String(parametro.id)}>{parametro?.name || 'N/A'}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                      <div>
                        <Label htmlFor="valor_id_im">Valor</Label>
                        <Select key={detalleData.valor_id} value={detalleData.valor_id} onValueChange={(value) => setDetalleData('valor_id', value)}>
                          <SelectTrigger className="w-full"><SelectValue placeholder="Seleccionar Valor" /></SelectTrigger>
                          <SelectContent>{valores.map(valor => (<SelectItem key={valor.id} value={String(valor.id)}>{valor.name}</SelectItem>))}</SelectContent>
                        </Select>
                      </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4 mt-4">
                      <div>
                        <Label htmlFor="temperatura_im">Temperatura</Label>
                        <Input id="temperatura_im" type="number" value={detalleData.temperatura} onChange={(e) => setDetalleData('temperatura', e.target.value)} />
                      </div>
                      <div>
                        <Label htmlFor="valor_presion_im">Valor Presión</Label>
                        <Input id="valor_presion_im" type="number" value={detalleData.valor_presion} onChange={(e) => setDetalleData('valor_presion', e.target.value)} />
                      </div>
                    </div>
                    <Button type="button" onClick={submitDetalle} disabled={processingDetalle || !calidadId}>Agregar Indice de Madurez</Button>
                    {indiceMadurezAgregados.length > 0 && (<div className="mt-4 overflow-x-auto max-h-[200px] overflow-y-auto"><h4 className="text-md font-medium mb-2">Indice de Madurez Agregados:</h4><Table><TableHeader><TableRow><TableHead>Temperatura</TableHead><TableHead>Tipo Item</TableHead><TableHead>Detalle Item</TableHead><TableHead>Valor SS</TableHead><TableHead>Acciones</TableHead></TableRow></TableHeader><TableBody>{indiceMadurezAgregados.map((detalle, index) => (<TableRow key={index}><TableCell className="text-sm">{detalle.temperatura || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.cantidad || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.tipo_item || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.detalle_item || 'N/A'}</TableCell><TableCell className="text-sm">{detalle.valor_ss || 'N/A'}</TableCell><TableCell><Button variant="ghost" size="icon" onClick={() => console.log('Delete', detalle.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button></TableCell></TableRow>))}</TableBody></Table></div>)}
                  </TabsContent>

                  <TabsContent value="fotos">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      <div className="space-y-4">
                        <h3 className="text-lg font-medium">Subir Nueva Foto</h3>
                          <div>
                            <Label htmlFor="photo_type_id">Tipo de Foto</Label>
                            <Select onValueChange={(value) => setPhotoData('photo_type_id', value)} value={photoData.photo_type_id}>
                                <SelectTrigger><SelectValue placeholder="Seleccionar tipo..." /></SelectTrigger>
                                <SelectContent>{photoTypes.map(type => (<SelectItem key={type.id} value={String(type.id)}>{type.name}</SelectItem>))}</SelectContent>
                            </Select>
                            {errorsPhoto.photo_type_id && <p className="mt-1 text-sm text-red-600">{errorsPhoto.photo_type_id}</p>}
                          </div>
                          <div>
                            <Label htmlFor="photo">Archivo de Imagen</Label>
                            <Input id="photo" type="file" onChange={(e) => setPhotoData('photo', e.target.files[0])} />
                            {errorsPhoto.photo && <p className="mt-1 text-sm text-red-600">{errorsPhoto.photo}</p>}
                          </div>
                          <Button type="button" onClick={submitPhoto} disabled={processingPhoto || !calidadId}><UploadCloud className="mr-2 h-4 w-4" />{processingPhoto ? 'Subiendo...' : 'Subir Foto'}</Button>
                      </div>
                      <div className="space-y-4">
                        <h3 className="text-lg font-medium">Galería de Fotos</h3>
                        {photos.length > 0 ? (
                          <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                            {photos.map(photo => (
                              <div key={photo.id} className="relative group">
                                <img src={photo.url} alt={photo.photo_type.name} className="rounded-lg object-cover w-full h-32" />
                                <div className="absolute bottom-0 left-0 right-0 bg-black bg-opacity-50 text-white text-xs p-1 rounded-b-lg">{photo.photo_type.name}</div>
                                <Button variant="destructive" size="icon" className="absolute top-1 right-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity" onClick={() => handleDeletePhoto(photo.id)}><Trash2 className="h-4 w-4 text-red-500" /></Button>
                              </div>
                            ))}
                          </div>
                        ) : (
                          <p className="text-sm text-gray-500">No hay fotos para este control de calidad.</p>
                        )}
                      </div>
                    </div>
                  </TabsContent>
                </Tabs>
              </div>
              <DialogFooter>
                <Button type="submit" disabled={processingCalidad}>Guardar Condiciones</Button>
                <Button type="button" onClick={handleCloseModal}>Cerrar</Button>
              </DialogFooter>
            </form>
          </DialogContent>
        </Dialog>
      )}
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout user={page.props.auth.user} children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Control de Calidad</h2>} />;
