import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  FileText, Download, ChevronDown, Tag, Globe, MapPin,
  User, Mail, CreditCard, Upload, Eye
} from 'lucide-react';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from '@/Components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError';
import { Switch } from '@/Components/ui/switch';

export default function SagShow({ auth, producerRut, producerName, csgRecords, producerSagCertifications, especies = [], countries = [] }) {
  const [isCountriesModalOpen, setIsCountriesModalOpen] = useState(false);
  const [selectedCsgRecord, setSelectedCsgRecord] = useState(null);
  const [selectedEspecie, setSelectedEspecie] = useState(null);
  const [marketsForEspecie, setMarketsForEspecie] = useState([]);
  const [countryStatuses, setCountryStatuses] = useState({});

  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [filterStatus, setFilterStatus] = useState('all');
  const [isSdpCreateOpen, setIsSdpCreateOpen] = useState(false);
  const [isSdpEditOpen, setIsSdpEditOpen] = useState(false);
  const [sdpTargetCsgId, setSdpTargetCsgId] = useState(null);
  const [sdpEditing, setSdpEditing] = useState(null);
  const [openSdpCertId, setOpenSdpCertId] = useState(null);

  const producerEmail = csgRecords.length > 0 && csgRecords[0].user ? csgRecords[0].user.email : 'N/A';

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    description: '',
    issue_date: '',
    expiration_date: '',
    certification_type: 'Certificacion SAG',
    file: null,
    csg_user_id: null,
    sdp_site_ids: [],
    especie_id: '',
    country_id: '',
    is_active: true,
  });

  // SDP inline forms
  const { data: sdpData, setData: setSdpData, post: postSdp, put: putSdp, delete: deleteSdp, processing: sdpProcessing, errors: sdpErrors, reset: resetSdp } = useForm({
    csg_user_id: null,
    code: '',
    name: '',
    address: '',
    is_active: true,
  });

  useEffect(() => {
    if (selectedCsgRecord && selectedEspecie) {
      fetch(route('especies.markets', selectedEspecie.id))
        .then(response => response.json())
        .then(data => setMarketsForEspecie(data))
        .catch(error => console.error('Error fetching markets for especie:', error));

      const currentCsgEspecieStatuses = selectedCsgRecord.csg_especie_country_statuses || [];
      const initialStatuses = {};
      currentCsgEspecieStatuses.forEach(status => {
        if (status.especie_id === selectedEspecie.id) {
          initialStatuses[status.country_id] = { status: status.status, last_updated_at: status.last_updated_at };
        }
      });
      setCountryStatuses(initialStatuses);
    } else {
      setMarketsForEspecie([]);
      setCountryStatuses({});
    }
  }, [selectedCsgRecord, selectedEspecie]);

  const handleOpenCountriesModal = (csgRecord, especie) => {
    setSelectedCsgRecord(csgRecord);
    setSelectedEspecie(especie);
    setIsCountriesModalOpen(true);
  };

  const handleCloseCountriesModal = () => {
    setIsCountriesModalOpen(false);
    setSelectedCsgRecord(null);
    setSelectedEspecie(null);
  };

  const handleStatusChange = (countryId, newStatus) => {
    if (!selectedCsgRecord || !selectedEspecie) return;
    const payload = {
      user_id: selectedCsgRecord.id,
      especie_id: selectedEspecie.id,
      country_id: countryId,
      status: newStatus,
    };
    router.post(route('sag.updateCountryStatus'), payload, {
      preserveScroll: true,
      onSuccess: () => {
        setCountryStatuses(prev => ({
          ...prev,
          [countryId]: { status: newStatus, last_updated_at: new Date().toISOString().split('T')[0] },
        }));
      },
      onError: (errors) => {
        console.error('Error updating status:', errors);
        alert('Error al actualizar el estado.');
      },
    });
  };

  const handleUploadSubmit = (e) => {
    e.preventDefault();
    post(route('sag.certifications.upload'), {
      forceFormData: true,
      onSuccess: () => {
        setIsUploadModalOpen(false);
        reset();
        router.reload({ only: ['producerSagCertifications'] });
      },
    });
  };

  const handleViewFile = (filePath) => {
    if (filePath) window.open(`/storage/${filePath}`, '_blank');
    else alert('No hay archivo para visualizar.');
  };

  const handleDownloadFile = (filePath, fileName) => {
    if (filePath) {
      const link = document.createElement('a');
      link.href = `/storage/${filePath}`;
      link.download = fileName || 'documento';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      alert('No hay archivo para descargar.');
    }
  };

  const filteredCerts = (producerSagCertifications || []).filter(cert => {
    if (filterStatus === 'all') return true;
    return cert.status === filterStatus;
  });

  const handleDeleteCertification = (id) => {
    if (!confirm('¿Eliminar esta certificación? Esto eliminará también el archivo.')) return;
    router.delete(route('sag.certifications.destroy', id), {
      onSuccess: () => router.reload({ only: ['producerSagCertifications'] })
    });
  };

  const removeSdp = (sdpId) => {
    if (!confirm('¿Eliminar este SDP?')) return;
    deleteSdp(route('sdp-sites.destroy', sdpId), {
      onSuccess: () => router.reload({ only: ['csgRecords'] })
    });
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalles del Productor SAG</h2>}
    >
      <Head title={`Detalles SAG: ${producerName}`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Productor: {producerName} (RUT: {producerRut})</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 p-4 border rounded-lg bg-gray-50">
                <div className="flex flex-col items-center"><User className="h-8 w-8 text-gray-600 mb-2" /><span className="text-sm text-gray-500">Nombre Productor</span><span className="font-semibold text-lg">{producerName}</span></div>
                <div className="flex flex-col items-center"><CreditCard className="h-8 w-8 text-gray-600 mb-2" /><span className="text-sm text-gray-500">RUT</span><span className="font-semibold text-lg">{producerRut}</span></div>
                <div className="flex flex-col items-center"><Mail className="h-8 w-8 text-gray-600 mb-2" /><span className="text-sm text-gray-500">Email</span><span className="font-semibold text-lg">{producerEmail}</span></div>
              </div>

              <Card className="p-4 mb-8">
                <CardHeader>
                  <CardTitle className="text-lg flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <FileText className="h-5 w-5" />
                      Certificaciones y Aplicaciones
                    </div>
                    <Button onClick={() => {
                      if (csgRecords.length === 1) {
                        setData('csg_user_id', csgRecords[0].id);
                        setIsUploadModalOpen(true);
                      } else {
                        alert('Seleccione el CSG específico desde el bloque para subir el documento.');
                      }
                    }}>
                      <Upload className="h-4 w-4 mr-2" /> Subir Archivo
                    </Button>
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center gap-3 mb-4">
                    <Label>Filtrar por estado</Label>
                    <select className="border rounded px-2 py-1" value={filterStatus} onChange={(e) => setFilterStatus(e.target.value)}>
                      <option value="all">Todos</option>
                      <option value="Vigente">Vigente</option>
                      <option value="Por vencer">Por vencer</option>
                      <option value="Vencida">Vencida</option>
                    </select>
                  </div>
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Nombre</TableHead>
                        <TableHead>Tipo</TableHead>
                        <TableHead>Fecha Emisión</TableHead>
                        <TableHead>Fecha Vencimiento</TableHead>
                        <TableHead>Especie</TableHead>
                        <TableHead>País</TableHead>
                        <TableHead>Vigente</TableHead>
                        <TableHead>Estado</TableHead>
                        <TableHead>CSG</TableHead>
                        <TableHead>SDP</TableHead>
                        <TableHead>Acciones</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {filteredCerts && filteredCerts.length > 0 ? (
                        filteredCerts.map((cert) => (
                          <TableRow key={cert.id}>
                            <TableCell>{cert.name}</TableCell>
                            <TableCell><Badge variant={cert.certification_type === 'Application' ? 'secondary' : 'default'}>{cert.certification_type}</Badge></TableCell>
                            <TableCell>{cert.issue_date}</TableCell>
                            <TableCell>{cert.expiration_date || 'N/A'}</TableCell>
                            <TableCell>{(especies || []).find(e => e.id === cert.especie_id)?.name || '-'}</TableCell>
                            <TableCell>{(countries || []).find(c => c.id === cert.country_id)?.name || '-'}</TableCell>
                            <TableCell>
                              <Switch
                                checked={!!cert.is_active}
                                onCheckedChange={(checked) => {
                                  router.post(route('sag.certifications.setActive', cert.id), { is_active: checked }, { preserveScroll: true });
                                }}
                              />
                            </TableCell>
                            <TableCell>
                              {cert.status && cert.status !== 'N/A' ? (
                                <Badge className={
                                  cert.status === 'Vencida' ? 'bg-red-600 text-white' :
                                  cert.status === 'Por vencer' ? 'bg-yellow-500 text-black' :
                                  'bg-green-600 text-white'
                                }>
                                  {cert.status}
                                </Badge>
                              ) : 'N/A'}
                            </TableCell>
                            <TableCell>
                              {cert.csg_code || '-'}
                            </TableCell>
                            <TableCell>
                              <Popover open={openSdpCertId === cert.id} onOpenChange={(o) => setOpenSdpCertId(o ? cert.id : null)}>
                                <PopoverTrigger asChild>
                                  <Button
                                    variant="ghost"
                                    size="icon"
                                    onMouseEnter={() => setOpenSdpCertId(cert.id)}
                                    onMouseLeave={() => setOpenSdpCertId(null)}
                                    title="Ver SDPs asociados"
                                  >
                                    <MapPin className="h-4 w-4" />
                                  </Button>
                                </PopoverTrigger>
                                <PopoverContent
                                  className="w-64"
                                  onMouseEnter={() => setOpenSdpCertId(cert.id)}
                                  onMouseLeave={() => setOpenSdpCertId(null)}
                                >
                                  {cert.sdps && cert.sdps.length > 0 ? (
                                    <div className="space-y-1">
                                      {cert.sdps.map((s) => (
                                        <div key={s.id} className="text-sm">
                                          {s.code ? `${s.code} - ` : ''}{s.name}
                                        </div>
                                      ))}
                                    </div>
                                  ) : (
                                    <div className="text-sm text-gray-500">Sin SDP asociados</div>
                                  )}
                                </PopoverContent>
                              </Popover>
                            </TableCell>
                            <TableCell>
                              <Button variant="outline" size="sm" onClick={() => handleViewFile(cert.file_path)} className="mr-2"><Eye className="h-4 w-4" /></Button>
                              <Button variant="outline" size="sm" onClick={() => handleDownloadFile(cert.file_path, cert.name)} className="mr-2"><Download className="h-4 w-4" /></Button>
                              <Button variant="destructive" size="sm" onClick={() => handleDeleteCertification(cert.id)}>Eliminar</Button>
                            </TableCell>
                          </TableRow>
                        ))
                      ) : (
                        <TableRow><TableCell colSpan="6" className="text-center">No hay certificaciones o aplicaciones para este productor.</TableCell></TableRow>
                      )}
                    </TableBody>
                  </Table>
                </CardContent>
              </Card>

              <div className="space-y-6">
                {csgRecords.length > 0 ? (
                  <Accordion type="single" collapsible className="w-full">
                    {csgRecords.map((csgRecord) => (
                      <AccordionItem key={csgRecord.id} value={csgRecord.id.toString()}>
                        <AccordionTrigger className="flex items-center justify-between p-4 bg-gray-50 hover:bg-gray-100 rounded-lg shadow-sm">
                          <div className="flex items-center gap-3">
                            <Tag className="h-5 w-5 text-gray-600" />
                            <span className="font-semibold text-lg">CSG: {csgRecord.csg_code}</span>
                            <div className="flex flex-wrap gap-1 ml-4">
                              {csgRecord.especies && csgRecord.especies.map((especie) => (<Badge key={especie.id} variant="secondary" className="mr-1">{especie.name}</Badge>))}
                            </div>
                          </div>
                          <ChevronDown className="h-5 w-5 shrink-0 transition-transform duration-200" />
                        </AccordionTrigger>
                        <AccordionContent className="p-4 border-t bg-white rounded-b-lg shadow-inner">
                          <div className="flex items-center justify-between mb-3">
                            <div className="flex gap-2">
                              <Button size="sm" onClick={() => { setData('csg_user_id', csgRecord.id); setData('sdp_site_ids', []); setIsUploadModalOpen(true); }}>
                                <Upload className="h-4 w-4 mr-1" /> Subir Doc CSG
                              </Button>
                              <Button size="sm" variant="outline" onClick={() => { setSdpTargetCsgId(csgRecord.id); setSdpData('csg_user_id', csgRecord.id); setIsSdpCreateOpen(true); }}>Agregar SDP</Button>
                            </div>
                            <div className="text-sm text-gray-600">CSG: {csgRecord.csg_code}</div>
                          </div>
                          {csgRecord.sdp_sites && csgRecord.sdp_sites.length > 0 && (
                            <div className="mb-3 text-sm">
                              <span className="font-medium">SDPs:</span>{' '}
                              {csgRecord.sdp_sites.map((sdp) => (
                                <span key={sdp.id} className="inline-flex items-center gap-2 border rounded px-2 py-1 mr-2">
                                  {sdp.code ? `${sdp.code} - ` : ''}{sdp.name}
                                  <Button size="sm" variant="outline" onClick={() => { setSdpTargetCsgId(csgRecord.id); setSdpEditing(sdp); setSdpData({ csg_user_id: csgRecord.id, code: sdp.code || '', name: sdp.name || '', address: sdp.address || '', is_active: sdp.is_active ?? true }); setIsSdpEditOpen(true); }}>Editar</Button>
                                  <Button size="sm" variant="destructive" onClick={() => removeSdp(sdp.id)}>Eliminar</Button>
                                </span>
                              ))}
                            </div>
                          )}
                          <h4 className="font-semibold text-md mb-2">Autorización por Especie y País:</h4>
                          {csgRecord.especies && csgRecord.especies.length > 0 ? (
                            <div className="space-y-3">
                              {csgRecord.especies.map((especie) => {
                                const authorizedCountriesCount = csgRecord.csg_especie_country_statuses?.filter(s => s.especie_id === especie.id && s.status === 'Autorizado').length || 0;
                                return (
                                  <div key={especie.id} className="flex items-center justify-between p-3 border rounded-md bg-gray-50">
                                    <div className="flex items-center gap-2">
                                      <Tag className="h-4 w-4 text-green-600" />
                                      <span className="font-medium">{especie.name}</span>
                                      <Badge variant="outline" className="ml-2">{authorizedCountriesCount} Países Autorizados</Badge>
                                    </div>
                                    <Button variant="outline" size="sm" onClick={() => handleOpenCountriesModal(csgRecord, especie)}><Globe className="h-4 w-4 mr-1" /> Países</Button>
                                  </div>
                                );
                              })}
                            </div>
                          ) : (
                            <p>No hay especies asociadas a este CSG.</p>
                          )}
                        </AccordionContent>
                      </AccordionItem>
                    ))}
                  </Accordion>
                ) : (
                  <p className="text-center py-8 text-gray-500">No hay CSG records para este productor.</p>
                )}
              </div>
              <div className="mt-6">
                <Link href={route('sag.index')}><Button variant="outline">Volver al Listado SAG</Button></Link>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={isUploadModalOpen} onOpenChange={setIsUploadModalOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Subir Certificación o Aplicación</DialogTitle>
            <DialogDescription>Complete los detalles y seleccione el archivo para subir.</DialogDescription>
          </DialogHeader>
          <form onSubmit={handleUploadSubmit} className="space-y-4 py-4">
            <div>
              <Label>CSG</Label>
              <Select value={data.csg_user_id ? String(data.csg_user_id) : ''} onValueChange={(value) => setData('csg_user_id', Number(value))}>
                <SelectTrigger><SelectValue placeholder="Seleccione CSG" /></SelectTrigger>
                <SelectContent>
                  {csgRecords.map(csg => (
                    <SelectItem key={csg.id} value={String(csg.id)}>{csg.csg_code}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <InputError message={errors.csg_user_id} className="mt-2" />
            </div>
            <div>
              <Label htmlFor="name">Nombre del Documento</Label>
              <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
              <InputError message={errors.name} className="mt-2" />
            </div>
            <div>
              <Label htmlFor="certification_type">Tipo</Label>
              <Select value={data.certification_type} onValueChange={(value) => setData('certification_type', value)}>
                <SelectTrigger><SelectValue /></SelectTrigger>
                <SelectContent>
                  <SelectItem value="Certificacion SAG">Certificación SAG</SelectItem>
                  <SelectItem value="Application">Aplicación</SelectItem>
                </SelectContent>
              </Select>
              <InputError message={errors.certification_type} className="mt-2" />
            </div>
            <div>
              <Label>SDP (opcional)</Label>
              <div className="flex flex-wrap gap-2">
                {(csgRecords.find(c => c.id === data.csg_user_id)?.sdp_sites || []).map(sdp => {
                  const checked = (data.sdp_site_ids || []).includes(sdp.id);
                  return (
                    <label key={sdp.id} className="flex items-center gap-1 border rounded px-2 py-1">
                      <input
                        type="checkbox"
                        checked={checked}
                        onChange={(e) => {
                          const current = new Set(data.sdp_site_ids || []);
                          if (e.target.checked) current.add(sdp.id); else current.delete(sdp.id);
                          setData('sdp_site_ids', Array.from(current));
                        }}
                      />
                      <span>{sdp.code ? `${sdp.code} - ` : ''}{sdp.name}</span>
                    </label>
                  );
                })}
              </div>
              <InputError message={errors.sdp_site_ids} className="mt-2" />
            </div>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label>Especie (opcional)</Label>
                <select className="w-full border rounded px-2 py-2" value={data.especie_id} onChange={(e) => setData('especie_id', e.target.value ? Number(e.target.value) : '')}>
                  <option value="">Seleccione...</option>
                  {(especies || []).map(e => (
                    <option key={e.id} value={e.id}>{e.name}</option>
                  ))}
                </select>
                <InputError message={errors.especie_id} className="mt-2" />
              </div>
              <div>
                <Label>País (opcional)</Label>
                <select className="w-full border rounded px-2 py-2" value={data.country_id} onChange={(e) => setData('country_id', e.target.value ? Number(e.target.value) : '')}>
                  <option value="">Seleccione...</option>
                  {(countries || []).map(c => (
                    <option key={c.id} value={c.id}>{c.name}</option>
                  ))}
                </select>
                <InputError message={errors.country_id} className="mt-2" />
              </div>
            </div>
            <div className="flex items-center gap-2">
              <Switch id="is_active" checked={data.is_active} onCheckedChange={(v) => setData('is_active', v)} />
              <Label htmlFor="is_active">Documento Vigente</Label>
            </div>
            <div>
              <Label htmlFor="issue_date">Fecha de Emisión</Label>
              <Input id="issue_date" type="date" value={data.issue_date} onChange={(e) => setData('issue_date', e.target.value)} required />
              <InputError message={errors.issue_date} className="mt-2" />
            </div>
            <div>
              <Label htmlFor="expiration_date">Fecha de Vencimiento (Opcional)</Label>
              <Input id="expiration_date" type="date" value={data.expiration_date} onChange={(e) => setData('expiration_date', e.target.value)} />
              <InputError message={errors.expiration_date} className="mt-2" />
            </div>
            <div>
              <Label htmlFor="file">Archivo</Label>
              <Input id="file" type="file" onChange={(e) => setData('file', e.target.files[0])} required />
              <InputError message={errors.file} className="mt-2" />
            </div>
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsUploadModalOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>{processing ? 'Subiendo...' : 'Subir'}</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal Crear SDP */}
      <Dialog open={isSdpCreateOpen} onOpenChange={setIsSdpCreateOpen}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>Agregar SDP</DialogTitle>
          </DialogHeader>
          <form onSubmit={(e) => { e.preventDefault(); postSdp(route('sdp-sites.store'), { onSuccess: () => { setIsSdpCreateOpen(false); router.reload({ only: ['csgRecords'] }); } }); }} className="space-y-4 py-2">
            <div>
              <Label>CSG</Label>
              <Input value={csgRecords.find(c => c.id === (sdpData.csg_user_id || sdpTargetCsgId))?.csg_code || ''} readOnly />
            </div>
            <div>
              <Label>Código</Label>
              <Input value={sdpData.code} onChange={e => setSdpData('code', e.target.value)} />
            </div>
            <div>
              <Label>Nombre</Label>
              <Input value={sdpData.name} onChange={e => setSdpData('name', e.target.value)} required />
            </div>
            <div>
              <Label>Dirección</Label>
              <Input value={sdpData.address} onChange={e => setSdpData('address', e.target.value)} />
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" id="sdp_is_active" checked={sdpData.is_active} onChange={e => setSdpData('is_active', e.target.checked)} />
              <Label htmlFor="sdp_is_active">Activo</Label>
            </div>
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsSdpCreateOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={sdpProcessing}>Guardar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Modal Editar SDP */}
      <Dialog open={isSdpEditOpen} onOpenChange={setIsSdpEditOpen}>
        <DialogContent className="sm:max-w-[500px]">
          <DialogHeader>
            <DialogTitle>Editar SDP</DialogTitle>
          </DialogHeader>
          <form onSubmit={(e) => { e.preventDefault(); if (!sdpEditing) return; putSdp(route('sdp-sites.update', sdpEditing.id), { onSuccess: () => { setIsSdpEditOpen(false); setSdpEditing(null); router.reload({ only: ['csgRecords'] }); } }); }} className="space-y-4 py-2">
            <div>
              <Label>CSG</Label>
              <Input value={csgRecords.find(c => c.id === (sdpData.csg_user_id || sdpTargetCsgId))?.csg_code || ''} readOnly />
            </div>
            <div>
              <Label>Código</Label>
              <Input value={sdpData.code} onChange={e => setSdpData('code', e.target.value)} />
            </div>
            <div>
              <Label>Nombre</Label>
              <Input value={sdpData.name} onChange={e => setSdpData('name', e.target.value)} required />
            </div>
            <div>
              <Label>Dirección</Label>
              <Input value={sdpData.address} onChange={e => setSdpData('address', e.target.value)} />
            </div>
            <div className="flex items-center gap-2">
              <input type="checkbox" id="sdp_is_active_edit" checked={sdpData.is_active} onChange={e => setSdpData('is_active', e.target.checked)} />
              <Label htmlFor="sdp_is_active_edit">Activo</Label>
            </div>
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsSdpEditOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={sdpProcessing}>Guardar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
      <Dialog open={isCountriesModalOpen} onOpenChange={handleCloseCountriesModal}>
        <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Autorización de Países para {selectedEspecie?.name} (CSG: {selectedCsgRecord?.csg_code})</DialogTitle>
          </DialogHeader>
          <div className="py-4">
            {marketsForEspecie.length > 0 ? (
              <Table>
                <TableHeader><TableRow><TableHead>País</TableHead><TableHead>Estado</TableHead><TableHead>Última Actualización</TableHead></TableRow></TableHeader>
                <TableBody>
                  {marketsForEspecie.map((market) => {
                    const currentStatus = countryStatuses[market.country_id]?.status || 'No Autorizado';
                    const lastUpdated = countryStatuses[market.country_id]?.last_updated_at || 'N/A';
                    return (
                      <TableRow key={market.id}>
                        <TableCell className="flex items-center gap-2"><MapPin className="h-4 w-4 text-blue-500" /> {market.name}</TableCell>
                        <TableCell>
                          <Select value={currentStatus} onValueChange={(value) => handleStatusChange(market.country_id, value)}>
                            <SelectTrigger className="w-[180px]"><SelectValue placeholder="Seleccionar Estado" /></SelectTrigger>
                            <SelectContent>
                              <SelectItem value="Autorizado">Autorizado</SelectItem>
                              <SelectItem value="Pendiente">Pendiente</SelectItem>
                              <SelectItem value="No Autorizado">No Autorizado</SelectItem>
                            </SelectContent>
                          </Select>
                        </TableCell>
                        <TableCell>{lastUpdated}</TableCell>
                      </TableRow>
                    );
                  })}
                </TableBody>
              </Table>
            ) : (
              <p className="text-center text-gray-500">No hay mercados definidos para esta especie.</p>
            )}
          </div>
          <DialogFooter><Button variant="outline" onClick={handleCloseCountriesModal}>Cerrar</Button></DialogFooter>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  );
}
