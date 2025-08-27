import React, { useState, useEffect } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Toaster, toast } from 'sonner';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { FileText, Trash2, Download, User, Award, Globe, ChevronDown, MapPin } from 'lucide-react'; // Added MapPin
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/Components/ui/tabs';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/Components/ui/collapsible';

export default function ProducerShow({ auth, producer, certifyingHouses, certificateTypes, especies, markets }) {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash && flash.success) {
            toast.success(flash.success);
        }
    }, [flash]);

    const { data, setData, post, put, delete: destroy, processing, errors, reset } = useForm({
        certifying_house_id: null,
        name: '',
        certificate_type_id: null,
        certificate_code: '',
        especie_id: null,
        audit_date: '',
        expiration_date: '',
        certificate_pdf: null,
        other_documents: [],
        has_pdf_extension: false,
        existing_other_documents: [], // For editing existing documents
        user_id: producer.id, // Set user_id to the current producer's ID
    });

    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingCertification, setEditingCertification] = useState(null);
    const [viewingCertification, setViewingCertification] = useState(null);

    const handleOpenEditModal = (certification) => {
        setEditingCertification(certification);
        setData({
            certifying_house_id: String(certification.certifying_house_id),
            name: certification.name,
            certificate_type_id: String(certification.certificate_type_id),
            certificate_code: certification.certificate_code,
            especie_id: String(certification.especie_id),
            audit_date: certification.audit_date ? new Date(certification.audit_date).toISOString().split('T')[0] : '',
            expiration_date: certification.expiration_date ? new Date(certification.expiration_date).toISOString().split('T')[0] : '',
            certificate_pdf: null, // File input should be reset
            other_documents: [], // File input should be reset
            has_pdf_extension: certification.has_pdf_extension,
            existing_other_documents: certification.other_documents || [],
            user_id: certification.user_id || producer.id, // Ensure user_id is set
        });
        setIsModalOpen(true);
    };

    const handleOpenCreateModal = () => {
        setEditingCertification(null);
        reset();
        setData('user_id', producer.id);
        setIsModalOpen(true);
    };

    const handleOpenViewModal = (certification) => {
        setViewingCertification(certification);
    };

    const handleCloseViewModal = () => {
        setViewingCertification(null);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setEditingCertification(null);
        reset();
        setData('user_id', producer.id); // Reset user_id to current producer's ID
    };

    const getCertificationStatus = (expirationDate) => {
        const today = new Date();
        const expiry = new Date(expirationDate);
        const diffTime = expiry.getTime() - today.getTime();
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

        if (diffDays <= 30 && diffDays >= 0) {
            return 'Por vencer';
        } else if (diffDays < 0) {
            return 'Vencida';
        } else {
            return 'Vigente';
        }
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0'); // Los meses son 0-indexados
        const year = date.getFullYear();
        return `${day}-${month}-${year}`;
    };

    const vigentesCertifications = producer.certifications.filter(cert => getCertificationStatus(cert.expiration_date) === 'Vigente');
    const porVencerCertifications = producer.certifications.filter(cert => getCertificationStatus(cert.expiration_date) === 'Por vencer');
    const vencidasCertifications = producer.certifications.filter(cert => getCertificationStatus(cert.expiration_date) === 'Vencida');

    const groupedMarkets = markets.reduce((acc, market) => {
        const continentName = market.country.continent.name;
        const countryName = market.country.name;

        if (!acc[continentName]) {
            acc[continentName] = {};
        }
        if (!acc[continentName][countryName]) {
            acc[continentName][countryName] = [];
        }
        acc[continentName][countryName].push(market);
        return acc;
    }, {});

    const handleOtherDocumentChange = (e, index) => {
        const newOtherDocuments = [...data.other_documents];
        newOtherDocuments[index] = e.target.files[0];
        setData('other_documents', newOtherDocuments);
    };

    const addOtherDocumentInput = () => {
        setData('other_documents', [...data.other_documents, null]);
    };

    const removeOtherDocumentInput = (index) => {
        const newOtherDocuments = data.other_documents.filter((_, i) => i !== index);
        setData('other_documents', newOtherDocuments);
    };

    const removeExistingOtherDocument = (docId) => {
        setData('existing_other_documents', data.existing_other_documents.filter(doc => doc.id !== docId));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const submitData = { ...data };

        if (editingCertification) {
            post(route('producer-certifications.update', editingCertification.id), {
                ...submitData,
                _method: 'put',
            }, {
                forceFormData: true,
                onSuccess: () => handleCloseModal(),
            });
        } else {
            post(route('producer-certifications.store'), submitData, {
                forceFormData: true,
                onSuccess: () => handleCloseModal(),
            });
        }
    };

    const handleDelete = (certificationId) => {
        if (confirm('¿Estás seguro de que quieres eliminar esta certificación?')) {
            destroy(route('producer-certifications.destroy', certificationId));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Detalle del Productor: {producer.name}</h2>}
        >
            <Head title={`Detalle del Productor: ${producer.name}`} />
            <Toaster richColors position="top-right" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="flex flex-wrap -mx-4">
                        {/* Left Column (60%) */}
                        <div className="w-full lg:w-3/5 px-4 mb-8 lg:mb-0">
                            <Card className="h-full">
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="flex items-center gap-2"> <User className="h-5 w-5" /> Información del Productor</CardTitle>
                                    <Button onClick={() => handleOpenCreateModal()}>Crear Certificación</Button>
                                </CardHeader>
                                <CardContent>
                                    <p><strong>Nombre:</strong> {producer.name}</p>
                                    <p><strong>Correo:</strong> {producer.email}</p>
                                    <p>
                                        <strong>Especies:</strong>{' '}
                                        {producer.especies && producer.especies.length > 0 ? (
                                            <div className="flex flex-wrap gap-2 mt-1">
                                                {producer.especies.map((especie) => (
                                                    <Badge key={especie.id} variant="secondary">
                                                        {especie.name}
                                                    </Badge>
                                                ))}
                                            </div>
                                        ) : (
                                            'N/A'
                                        )}
                                    </p>

                                    <CardHeader className="flex flex-row items-center justify-between px-0 pt-0 pb-4">
                                        <CardTitle className="flex items-center gap-2"><Award className="h-5 w-5" /> Certificaciones</CardTitle>
                                    </CardHeader>

                                    <Tabs defaultValue="vigentes" className="w-full">
                                        <TabsList className="grid w-full grid-cols-3">
                                            <TabsTrigger value="vigentes">
                                                Vigentes <Badge className="ml-2">{vigentesCertifications.length}</Badge>
                                            </TabsTrigger>
                                            <TabsTrigger value="por-vencer">
                                                Por vencer <Badge className="ml-2">{porVencerCertifications.length}</Badge>
                                            </TabsTrigger>
                                            <TabsTrigger value="vencidas">
                                                Vencidas <Badge className="ml-2">{vencidasCertifications.length}</Badge>
                                            </TabsTrigger>
                                        </TabsList>
                                        <TabsContent value="vigentes">
                                            {vigentesCertifications.length > 0 ? (
                                                <div className="space-y-4 mt-4">
                                                    {vigentesCertifications.map(certification => (
                                                        <Card key={certification.id}>
                                                            <CardHeader>
                                                                <CardTitle>{certification.name}</CardTitle>
                                                            </CardHeader>
                                                            <CardContent>
                                                                <p><strong>Casa Certificadora:</strong> {certification.certifying_house?.name}</p>
                                                                <p><strong>Tipo Certificado:</strong> {certification.certificate_type?.name}</p>
                                                                <p><strong>Código:</strong> {certification.certificate_code}</p>
                                                                <p><strong>Fecha Auditoría:</strong> {formatDate(certification.audit_date)}</p>
                                                                <p><strong>Fecha Vencimiento:</strong> {formatDate(certification.expiration_date)}</p>
                                                                <div className="mt-4 space-x-2">
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenViewModal(certification)}>Ver</Button>
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(certification)}>Editar</Button>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-4">No hay certificaciones vigentes para este productor.</p>
                                            )}
                                        </TabsContent>
                                        <TabsContent value="por-vencer">
                                            {porVencerCertifications.length > 0 ? (
                                                <div className="space-y-4 mt-4">
                                                    {porVencerCertifications.map(certification => (
                                                        <Card key={certification.id}>
                                                            <CardHeader>
                                                                <CardTitle>{certification.name}</CardTitle>
                                                            </CardHeader>
                                                            <CardContent>
                                                                <p><strong>Casa Certificadora:</strong> {certification.certifying_house?.name}</p>
                                                                <p><strong>Tipo Certificado:</strong> {certification.certificate_type?.name}</p>
                                                                <p><strong>Código:</strong> {certification.certificate_code}</p>
                                                                <p><strong>Fecha Auditoría:</strong> {formatDate(certification.audit_date)}</p>
                                                                <p><strong>Fecha Vencimiento:</strong> {formatDate(certification.expiration_date)}</p>
                                                                <div className="mt-4 space-x-2">
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenViewModal(certification)}>Ver</Button>
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(certification)}>Editar</Button>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-4">No hay certificaciones por vencer para este productor.</p>
                                            )}
                                        </TabsContent>
                                        <TabsContent value="vencidas">
                                            {vencidasCertifications.length > 0 ? (
                                                <div className="space-y-4 mt-4">
                                                    {vencidasCertifications.map(certification => (
                                                        <Card key={certification.id}>
                                                            <CardHeader>
                                                                <CardTitle>{certification.name}</CardTitle>
                                                            </CardHeader>
                                                            <CardContent>
                                                                <p><strong>Casa Certificadora:</strong> {certification.certifying_house?.name}</p>
                                                                <p><strong>Tipo Certificado:</strong> {certification.certificate_type?.name}</p>
                                                                <p><strong>Código:</strong> {certification.certificate_code}</p>
                                                                <p><strong>Fecha Auditoría:</strong> {formatDate(certification.audit_date)}</p>
                                                                <p><strong>Fecha Vencimiento:</strong> {formatDate(certification.expiration_date)}</p>
                                                                <div className="mt-4 space-x-2">
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenViewModal(certification)}>Ver</Button>
                                                                    <Button variant="outline" size="sm" onClick={() => handleOpenEditModal(certification)}>Editar</Button>
                                                                </div>
                                                            </CardContent>
                                                        </Card>
                                                    ))}
                                                </div>
                                            ) : (
                                                <p className="mt-4">No hay certificaciones vencidas para este productor.</p>
                                            )}
                                        </TabsContent>
                                    </Tabs>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right Column (40%) */}
                        <div className="w-full lg:w-2/5 px-4">
                            <Card className="h-full">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2"><Globe className="h-5 w-5" /> Acceso a Mercados</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {Object.entries(groupedMarkets).map(([continentName, countries]) => (
                                            <Collapsible key={continentName} className="mb-2 border rounded-lg shadow-sm">
                                                <CollapsibleTrigger className="w-full text-left p-3 bg-gray-50 hover:bg-gray-100 focus:outline-none flex justify-between items-center rounded-t-lg">
                                                    <div className="flex items-center gap-2">
                                                        <Globe className="h-5 w-5 text-blue-600" />
                                                        <span className="font-semibold text-lg">{continentName}</span>
                                                        <Badge variant="outline" className="ml-2">{Object.keys(countries).length} Países</Badge>
                                                    </div>
                                                    <ChevronDown className="h-5 w-5 text-gray-500 data-[state=open]:rotate-180 transition-transform duration-200" />
                                                </CollapsibleTrigger>
                                                <CollapsibleContent className="p-3 border-t bg-white rounded-b-lg">
                                                    {Object.entries(countries).map(([countryName, markets]) => (
                                                        <Collapsible key={countryName} className="mb-2 ml-4 border rounded-md shadow-sm">
                                                            <CollapsibleTrigger className="w-full text-left p-3 bg-gray-100 hover:bg-gray-200 focus:outline-none flex justify-between items-center rounded-t-md">
                                                                <div className="flex items-center gap-2">
                                                                    <MapPin className="h-4 w-4 text-green-600" />
                                                                    <span className="font-medium">{countryName}</span>
                                                                    <Badge variant="outline" className="ml-2">{markets.length} Mercados</Badge>
                                                                </div>
                                                                <ChevronDown className="h-4 w-4 text-gray-500 data-[state=open]:rotate-180 transition-transform duration-200" />
                                                            </CollapsibleTrigger>
                                                            <CollapsibleContent className="p-3 border-t bg-white rounded-b-md">
                                                                {markets.map(market => {
                                                                    const producerCertificateTypeIds = new Set(vigentesCertifications.map(c => c.certificate_type_id));
                                                                    const requiredCertificateTypeIds = new Set(market.certificate_types.map(ct => ct.id));
                                                                    const hasAllCertificates = [...requiredCertificateTypeIds].every(id => producerCertificateTypeIds.has(id));

                                                                    const producerEspecieIds = new Set(producer.especies.map(e => e.id));
                                                                    const requiredEspecieIds = new Set(market.especies.map(e => e.id));
                                                                    const hasAllEspecies = [...requiredEspecieIds].every(id => producerEspecieIds.has(id));
                                                                    console.log('hasAllEspecies', hasAllEspecies);
                                                                    console.log('hasAllCertificates', hasAllCertificates);
                                                                    const hasAccess = hasAllCertificates && hasAllEspecies;

                                                                    return (
                                                                        <div key={market.id} className={`p-4 rounded-lg ${hasAccess ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'} mb-2 last:mb-0`}>
                                                                            <div className="flex items-center justify-between">
                                                                                <h4 className="font-semibold text-lg">{market.name}</h4>
                                                                                {market.authorization_type && (
                                                                                    <p className="text-sm text-gray-600">({market.authorization_type.name})</p>
                                                                                )}
                                                                                <Badge variant={hasAccess ? 'success' : 'destructive'}>
                                                                                    {hasAccess ? 'Acceso Permitido' : 'Acceso Denegado'}
                                                                                </Badge>
                                                                            </div>
                                                                            <div className="mt-2">
                                                                                <p className="text-sm font-medium">Certificados Requeridos:</p>
                                                                                <div className="flex flex-wrap gap-2 mt-1">
                                                                                    {market.certificate_types.map(certType => (
                                                                                        <Badge key={certType.id} variant={producerCertificateTypeIds.has(certType.id) ? 'success' : 'secondary'}>
                                                                                            {certType.name}
                                                                                        </Badge>
                                                                                    ))}
                                                                                </div>
                                                                            </div>
                                                                            <div className="mt-2">
                                                                                <p className="text-sm font-medium">Especies Requeridas:</p>
                                                                                <div className="flex flex-wrap gap-2 mt-1">
                                                                                    {market.especies.map(especie => (
                                                                                        <Badge key={especie.id} variant={producerEspecieIds.has(especie.id) ? 'success' : 'secondary'}>
                                                                                            {especie.name}
                                                                                        </Badge>
                                                                                    ))}
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </CollapsibleContent>
                                                        </Collapsible>
                                                    ))}
                                                </CollapsibleContent>
                                            </Collapsible>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editingCertification ? 'Editar Certificación' : 'Crear Certificación'}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="user_id">Productor</Label>
                                <Select
                                    value={data.user_id}
                                    onValueChange={(value) => setData('user_id', value)}
                                    disabled // Disable producer selection in this view
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Seleccionar Productor" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem key={producer.id} value={producer.id}>
                                            {producer.name}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {errors.user_id && <div className="text-red-600 text-sm mt-1">{errors.user_id}</div>}
                            </div>

                            <div>
                                <Label htmlFor="certifying_house_id">Casa Certificadora</Label>
                                <Select
                                    value={data.certifying_house_id}
                                    onValueChange={(value) => setData('certifying_house_id', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Seleccionar Casa Certificadora" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {certifyingHouses.map((house) => (
                                            <SelectItem key={house.id} value={String(house.id)}>
                                                {house.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.certifying_house_id && <div className="text-red-600 text-sm mt-1">{errors.certifying_house_id}</div>}
                            </div>

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
                                <Label htmlFor="certificate_type_id">Tipo de Certificado</Label>
                                <Select
                                    value={data.certificate_type_id}
                                    onValueChange={(value) => setData('certificate_type_id', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Seleccionar Tipo de Certificado" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {certificateTypes.map((type) => (
                                            <SelectItem key={type.id} value={String(type.id)}>
                                                {type.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.certificate_type_id && <div className="text-red-600 text-sm mt-1">{errors.certificate_type_id}</div>}
                            </div>

                            <div>
                                <Label htmlFor="certificate_code">Código Certificado</Label>
                                <Input
                                    id="certificate_code"
                                    type="text"
                                    value={data.certificate_code}
                                    onChange={(e) => setData('certificate_code', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                                {errors.certificate_code && <div className="text-red-600 text-sm mt-1">{errors.certificate_code}</div>}
                            </div>

                            <div>
                                <Label htmlFor="especie_id">Especie</Label>
                                <Select
                                    value={data.especie_id}
                                    onValueChange={(value) => setData('especie_id', value)}
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Seleccionar Especie" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {especies.map((especie) => (
                                            <SelectItem key={especie.id} value={String(especie.id)}>
                                                {especie.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.especie_id && <div className="text-red-600 text-sm mt-1">{errors.especie_id}</div>}
                            </div>

                            <div>
                                <Label htmlFor="audit_date">Fecha Auditoría</Label>
                                <Input
                                    id="audit_date"
                                    type="date"
                                    value={data.audit_date}
                                    onChange={(e) => setData('audit_date', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                                {errors.audit_date && <div className="text-red-600 text-sm mt-1">{errors.audit_date}</div>}
                            </div>

                            <div>
                                <Label htmlFor="expiration_date">Fecha Vencimiento</Label>
                                <Input
                                    id="expiration_date"
                                    type="date"
                                    value={data.expiration_date}
                                    onChange={(e) => setData('expiration_date', e.target.value)}
                                    className="mt-1 block w-full"
                                    required
                                />
                                {errors.expiration_date && <div className="text-red-600 text-sm mt-1">{errors.expiration_date}</div>}
                            </div>

                            <div className="md:col-span-2"> {/* This div spans two columns */}
                                <Label htmlFor="certificate_pdf">Certificado PDF</Label>
                                <Input
                                    id="certificate_pdf"
                                    type="file"
                                    accept=".pdf"
                                    onChange={(e) => setData('certificate_pdf', e.target.files[0])}
                                    className="mt-1 block w-full"
                                />
                                {errors.certificate_pdf && <div className="text-red-600 text-sm mt-1">{errors.certificate_pdf}</div>}
                                {editingCertification?.certificate_pdf_path && (
                                    <div className="mt-2 flex items-center space-x-2">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                        <a href={`/storage/${editingCertification.certificate_pdf_path}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">Ver PDF Actual</a>
                                    </div>
                                )}
                            </div>

                            <div className="md:col-span-2"> {/* This div spans two columns */}
                                <Label>Otros Documentos</Label>
                                {data.existing_other_documents.map((doc) => (
                                    <div key={doc.id} className="flex items-center space-x-2 mt-1">
                                        <FileText className="h-5 w-5 text-blue-600" />
                                        <a href={route('producer-certifications.downloadOtherDocument', doc.id)} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">{doc.description || 'Documento'}</a>
                                        <Button type="button" variant="destructive" size="sm" onClick={() => removeExistingOtherDocument(doc.id)}><Trash2 className="h-4 w-4" /></Button>
                                    </div>
                                ))}
                                {data.other_documents.map((file, index) => (
                                    <div key={index} className="flex items-center space-x-2 mt-1">
                                        <Input
                                            type="file"
                                            onChange={(e) => handleOtherDocumentChange(e, index)}
                                            className="block w-full"
                                        />
                                        <Button type="button" variant="destructive" size="sm" onClick={() => removeOtherDocumentInput(index)}><Trash2 className="h-4 w-4" /></Button>
                                    </div>
                                ))}
                                <Button type="button" onClick={addOtherDocumentInput} className="mt-2">Añadir Otro Documento</Button>
                                {errors.other_documents && <div className="text-red-600 text-sm mt-1">{errors.other_documents}</div>}
                            </div>

                            <div className="md:col-span-2"> {/* This div spans two columns */}
                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="has_pdf_extension"
                                        checked={data.has_pdf_extension}
                                        onCheckedChange={(value) => setData('has_pdf_extension', value)}
                                    />
                                    <Label htmlFor="has_pdf_extension">Tiene Extensión PDF</Label>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button type="submit" disabled={processing}>{editingCertification ? 'Actualizar' : 'Crear'}</Button>
                            <Button type="button" variant="outline" onClick={handleCloseModal}>Cancelar</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!viewingCertification} onOpenChange={handleCloseViewModal}>
                <DialogContent className="max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>{viewingCertification?.name}</DialogTitle>
                    </DialogHeader>
                    {viewingCertification && (
                        <div className="space-y-4">
                            <p><strong>Casa Certificadora:</strong> {viewingCertification.certifying_house?.name}</p>
                            <p><strong>Tipo Certificado:</strong> {viewingCertification.certificate_type?.name}</p>
                            <p><strong>Código:</strong> {viewingCertification.certificate_code}</p>
                            <p><strong>Fecha Auditoría:</strong> {formatDate(viewingCertification.audit_date)}</p>
                            <p><strong>Fecha Vencimiento:</strong> {formatDate(viewingCertification.expiration_date)}</p>
                            {viewingCertification.certificate_pdf_path && (
                                <div className="mt-2 flex items-center space-x-2">
                                    <FileText className="h-5 w-5 text-blue-600" />
                                    <a href={`/storage/${viewingCertification.certificate_pdf_path}`} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">Ver PDF</a>
                                </div>
                            )}
                            <div>
                                <p className="font-semibold">Otros Documentos:</p>
                                {viewingCertification.other_documents?.length > 0 ? (
                                    <ul className="list-disc list-inside">
                                        {viewingCertification.other_documents?.map(doc => (
                                            <li key={doc.id}>
                                                <a href={route('producer-certifications.downloadOtherDocument', doc.id)} target="_blank" rel="noopener noreferrer" className="text-blue-600 hover:underline">{doc.description || 'Documento'}</a>
                                            </li>
                                        ))}
                                    </ul>
                                ) : (
                                    <p>No hay otros documentos.</p>
                                )}
                            </div>
                        </div>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={handleCloseViewModal}>Cerrar</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AuthenticatedLayout>
    );
}
