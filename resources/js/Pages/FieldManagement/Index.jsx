import React, { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';

export default function FieldManagementIndex({
  auth,
  contractors = [],
  crews = [],
  workers,
  credentials = [],
  fields = [],
  blocks = [],
  fruitConfigs = [],
  producerOptions = [],
  workerOptions = [],
  especies = [],
  variedades = [],
  filters = {},
}) {
  const contractorForm = useForm({ name: '' });
  const crewForm = useForm({ name: '', contractor_id: '' });
  const workerForm = useForm({
    national_id: '',
    full_name: '',
    role: '',
    status: 'active',
    contractor_id: '',
    crew_id: '',
  });
  const credentialForm = useForm({ qr_uid: '', status: 'available' });
  const assignForm = useForm({ worker_id: '', credential_id: '' });
  const fieldForm = useForm({ name: '', producer_id: '' });
  const blockForm = useForm({ name: '', field_id: '' });
  const fruitForm = useForm({ species: '', variety: '', tottes_per_bin: 0, status: 'active' });

  const [editingContractorId, setEditingContractorId] = useState(null);
  const [editingCrewId, setEditingCrewId] = useState(null);
  const [editingWorkerId, setEditingWorkerId] = useState(null);
  const [editingCredentialId, setEditingCredentialId] = useState(null);
  const [editingFieldId, setEditingFieldId] = useState(null);
  const [editingBlockId, setEditingBlockId] = useState(null);
  const [editingFruitId, setEditingFruitId] = useState(null);
  const [workerSearch, setWorkerSearch] = useState(filters.worker_search || '');

  const resetContractorForm = () => {
    contractorForm.reset();
    setEditingContractorId(null);
  };

  const resetCrewForm = () => {
    crewForm.reset();
    setEditingCrewId(null);
  };

  const resetWorkerForm = () => {
    workerForm.reset();
    workerForm.setData('status', 'active');
    setEditingWorkerId(null);
  };

  const resetCredentialForm = () => {
    credentialForm.reset();
    credentialForm.setData('status', 'available');
    setEditingCredentialId(null);
  };

  const resetFieldForm = () => {
    fieldForm.reset();
    setEditingFieldId(null);
  };

  const resetBlockForm = () => {
    blockForm.reset();
    setEditingBlockId(null);
  };

  const resetFruitForm = () => {
    fruitForm.reset();
    fruitForm.setData('status', 'active');
    setEditingFruitId(null);
  };

  const submitContractor = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetContractorForm };
    if (editingContractorId) {
      contractorForm.put(route('field-management.contractors.update', editingContractorId), options);
    } else {
      contractorForm.post(route('field-management.contractors.store'), options);
    }
  };

  const submitCrew = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetCrewForm };
    if (editingCrewId) {
      crewForm.put(route('field-management.crews.update', editingCrewId), options);
    } else {
      crewForm.post(route('field-management.crews.store'), options);
    }
  };

  const submitWorker = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetWorkerForm };
    if (editingWorkerId) {
      workerForm.put(route('field-management.workers.update', editingWorkerId), options);
    } else {
      workerForm.post(route('field-management.workers.store'), options);
    }
  };

  const submitCredential = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetCredentialForm };
    if (editingCredentialId) {
      credentialForm.put(route('field-management.credentials.update', editingCredentialId), options);
    } else {
      credentialForm.post(route('field-management.credentials.store'), options);
    }
  };

  const submitAssign = (e) => {
    e.preventDefault();
    assignForm.post(route('field-management.credentials.assign'), {
      preserveScroll: true,
      onSuccess: () => assignForm.reset(),
    });
  };

  const submitField = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetFieldForm };
    if (editingFieldId) {
      fieldForm.put(route('field-management.fields.update', editingFieldId), options);
    } else {
      fieldForm.post(route('field-management.fields.store'), options);
    }
  };

  const submitBlock = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetBlockForm };
    if (editingBlockId) {
      blockForm.put(route('field-management.blocks.update', editingBlockId), options);
    } else {
      blockForm.post(route('field-management.blocks.store'), options);
    }
  };

  const submitFruit = (e) => {
    e.preventDefault();
    const options = { preserveScroll: true, onSuccess: resetFruitForm };
    if (editingFruitId) {
      fruitForm.put(route('field-management.fruit-configs.update', editingFruitId), options);
    } else {
      fruitForm.post(route('field-management.fruit-configs.store'), options);
    }
  };

  const handleDelete = (url) => {
    if (confirm('Seguro que deseas eliminar este registro?')) {
      router.delete(url, { preserveScroll: true });
    }
  };

  const handleWorkerSearch = (e) => {
    e.preventDefault();
    router.get(
      route('field-management.index'),
      { worker_search: workerSearch },
      { preserveState: true, replace: true }
    );
  };

  const workerLinks = workers?.links || [];
  const workerData = workers?.data || [];

  return (
    <AuthenticatedLayout
      user={auth?.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Gestión de Campo</h2>}
    >
      <Head title="Gestión de Campo" />
      <div className="max-w-7xl mx-auto py-8 space-y-6">
        <div className="grid md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Contratistas</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitContractor} className="flex gap-3 flex-wrap">
                <Input
                  placeholder="Nombre del contratista"
                  value={contractorForm.data.name}
                  onChange={(e) => contractorForm.setData('name', e.target.value)}
                  className="flex-1 min-w-[180px]"
                />
                <Button type="submit" disabled={contractorForm.processing}>
                  {editingContractorId ? 'Actualizar' : 'Crear'}
                </Button>
                {editingContractorId && (
                  <Button type="button" variant="outline" onClick={resetContractorForm}>
                    Cancelar
                  </Button>
                )}
              </form>
              <div className="space-y-2">
                {contractors.map((c) => (
                  <div key={c.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{c.name}</div>
                      <div className="text-xs text-gray-500">ID: {c.id}</div>
                    </div>
                    <div className="flex gap-2">
                      <Button size="sm" variant="outline" onClick={() => { contractorForm.setData('name', c.name); setEditingContractorId(c.id); }}>
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.contractors.destroy', c.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Cuadrillas</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitCrew} className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div className="md:col-span-1">
                  <Input
                    placeholder="Nombre de cuadrilla"
                    value={crewForm.data.name}
                    onChange={(e) => crewForm.setData('name', e.target.value)}
                  />
                </div>
                <div className="md:col-span-1">
                  <select
                    className="w-full border rounded px-3 py-2"
                    value={crewForm.data.contractor_id}
                    onChange={(e) => crewForm.setData('contractor_id', e.target.value)}
                  >
                    <option value="">Seleccione contratista</option>
                    {contractors.map((c) => (
                      <option key={c.id} value={c.id}>{c.name}</option>
                    ))}
                  </select>
                </div>
                <div className="md:col-span-2 flex gap-2">
                  <Button type="submit" disabled={crewForm.processing}>
                    {editingCrewId ? 'Actualizar' : 'Crear'}
                  </Button>
                  {editingCrewId && (
                    <Button type="button" variant="outline" onClick={resetCrewForm}>
                      Cancelar
                    </Button>
                  )}
                </div>
              </form>
              <div className="space-y-2">
                {crews.map((crew) => (
                  <div key={crew.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{crew.name}</div>
                      <div className="text-xs text-gray-500">
                        {crew.contractor ? `Contratista: ${crew.contractor.name}` : 'Sin contratista'}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          crewForm.setData((data) => ({
                            ...data,
                            name: crew.name,
                            contractor_id: crew.contractor?.id || '',
                          }));
                          setEditingCrewId(crew.id);
                        }}
                      >
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.crews.destroy', crew.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Trabajadores</CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <form onSubmit={handleWorkerSearch} className="flex flex-wrap gap-3 items-center">
              <Input
                placeholder="Buscar por nombre o documento"
                value={workerSearch}
                onChange={(e) => setWorkerSearch(e.target.value)}
                className="min-w-[220px]"
              />
              <Button type="submit" variant="outline">Buscar</Button>
            </form>

            <form onSubmit={submitWorker} className="grid grid-cols-1 md:grid-cols-3 gap-3">
              <Input
                placeholder="Nombre completo"
                value={workerForm.data.full_name}
                onChange={(e) => workerForm.setData('full_name', e.target.value)}
              />
              <Input
                placeholder="Documento/Número nacional"
                value={workerForm.data.national_id}
                onChange={(e) => workerForm.setData('national_id', e.target.value)}
              />
              <Input
                placeholder="Rol"
                value={workerForm.data.role}
                onChange={(e) => workerForm.setData('role', e.target.value)}
              />
              <select
                className="w-full border rounded px-3 py-2"
                value={workerForm.data.status}
                onChange={(e) => workerForm.setData('status', e.target.value)}
              >
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
              </select>
              <select
                className="w-full border rounded px-3 py-2"
                value={workerForm.data.contractor_id}
                onChange={(e) => workerForm.setData('contractor_id', e.target.value)}
              >
                <option value="">Contratista</option>
                {contractors.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
              <select
                className="w-full border rounded px-3 py-2"
                value={workerForm.data.crew_id}
                onChange={(e) => workerForm.setData('crew_id', e.target.value)}
              >
                <option value="">Cuadrilla</option>
                {crews.map((crew) => (
                  <option key={crew.id} value={crew.id}>{crew.name}</option>
                ))}
              </select>
              <div className="md:col-span-3 flex gap-2">
                <Button type="submit" disabled={workerForm.processing}>
                  {editingWorkerId ? 'Actualizar' : 'Crear'}
                </Button>
                {editingWorkerId && (
                  <Button type="button" variant="outline" onClick={resetWorkerForm}>
                    Cancelar
                  </Button>
                )}
              </div>
            </form>

            <div className="overflow-x-auto">
              <table className="min-w-full text-sm border">
                <thead className="bg-gray-50">
                  <tr>
                    <th className="px-3 py-2 text-left">Nombre</th>
                    <th className="px-3 py-2 text-left">Documento</th>
                    <th className="px-3 py-2 text-left">Rol</th>
                    <th className="px-3 py-2 text-left">Contratista / Cuadrilla</th>
                    <th className="px-3 py-2 text-left">Credencial</th>
                    <th className="px-3 py-2 text-left">Estado</th>
                    <th className="px-3 py-2 text-left">Acciones</th>
                  </tr>
                </thead>
                <tbody>
                  {workerData.map((worker) => (
                    <tr key={worker.id} className="border-t">
                      <td className="px-3 py-2 font-medium">{worker.full_name}</td>
                      <td className="px-3 py-2">{worker.national_id || '-'}</td>
                      <td className="px-3 py-2">{worker.role || '-'}</td>
                      <td className="px-3 py-2">
                        <div className="text-sm">{worker.contractor?.name || '-'}</div>
                        <div className="text-xs text-gray-500">{worker.crew?.name || ''}</div>
                      </td>
                      <td className="px-3 py-2">{worker.credential?.qr_uid || '-'}</td>
                      <td className="px-3 py-2 capitalize">{worker.status}</td>
                      <td className="px-3 py-2">
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant="outline"
                            onClick={() => {
                              workerForm.setData((data) => ({
                                ...data,
                                national_id: worker.national_id || '',
                                full_name: worker.full_name || '',
                                role: worker.role || '',
                                status: worker.status || 'active',
                                contractor_id: worker.contractor?.id || '',
                                crew_id: worker.crew?.id || '',
                              }));
                              setEditingWorkerId(worker.id);
                            }}
                          >
                            Editar
                          </Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => handleDelete(route('field-management.workers.destroy', worker.id))}
                          >
                            Eliminar
                          </Button>
                        </div>
                      </td>
                    </tr>
                  ))}
                  {workerData.length === 0 && (
                    <tr>
                      <td className="px-3 py-4 text-center text-gray-500" colSpan={7}>
                        No hay trabajadores registrados.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
            <div className="flex flex-wrap gap-2">
              {workerLinks.map((link, idx) => (
                <Button
                  key={idx}
                  variant={link.active ? 'default' : 'outline'}
                  disabled={!link.url}
                  onClick={() => link.url && router.get(link.url, {}, { preserveState: true, preserveScroll: true })}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </CardContent>
        </Card>

        <div className="grid md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Credenciales y asignación</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitCredential} className="grid grid-cols-1 md:grid-cols-3 gap-3">
                <Input
                  placeholder="UID/QR"
                  value={credentialForm.data.qr_uid}
                  onChange={(e) => credentialForm.setData('qr_uid', e.target.value)}
                />
                <select
                  className="w-full border rounded px-3 py-2"
                  value={credentialForm.data.status}
                  onChange={(e) => credentialForm.setData('status', e.target.value)}
                >
                  <option value="available">Disponible</option>
                  <option value="assigned">Asignada</option>
                  <option value="inactive">Inactiva</option>
                </select>
                <div className="flex gap-2">
                  <Button type="submit" disabled={credentialForm.processing}>
                    {editingCredentialId ? 'Actualizar' : 'Crear'}
                  </Button>
                  {editingCredentialId && (
                    <Button type="button" variant="outline" onClick={resetCredentialForm}>
                      Cancelar
                    </Button>
                  )}
                </div>
              </form>

              <form onSubmit={submitAssign} className="grid grid-cols-1 md:grid-cols-3 gap-3 border rounded p-3 bg-gray-50">
                <select
                  className="w-full border rounded px-3 py-2"
                  value={assignForm.data.worker_id}
                  onChange={(e) => assignForm.setData('worker_id', e.target.value)}
                >
                  <option value="">Selecciona trabajador</option>
                  {workerOptions.map((w) => (
                    <option key={w.id} value={w.id}>{w.full_name}</option>
                  ))}
                </select>
                <select
                  className="w-full border rounded px-3 py-2"
                  value={assignForm.data.credential_id}
                  onChange={(e) => assignForm.setData('credential_id', e.target.value)}
                >
                  <option value="">Selecciona credencial</option>
                  {credentials.map((c) => (
                    <option key={c.id} value={c.id}>
                      {c.qr_uid} {c.assigned_worker ? `(Asignada a ${c.assigned_worker.full_name})` : ''}
                    </option>
                  ))}
                </select>
                <Button type="submit" disabled={assignForm.processing}>Asignar</Button>
              </form>

              <div className="space-y-2">
                {credentials.map((c) => (
                  <div key={c.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{c.qr_uid}</div>
                      <div className="text-xs text-gray-500">
                        Estado: {c.status} {c.assigned_worker ? `· ${c.assigned_worker.full_name}` : ''}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          credentialForm.setData((data) => ({
                            ...data,
                            qr_uid: c.qr_uid,
                            status: c.status,
                          }));
                          setEditingCredentialId(c.id);
                        }}
                      >
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.credentials.destroy', c.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Configuración de fruta</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitFruit} className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <select
                  className="w-full border rounded px-3 py-2"
                  value={fruitForm.data.species}
                  onChange={(e) => {
                    fruitForm.setData((data) => ({
                      ...data,
                      species: e.target.value,
                      variety: '', // Reset variety when species changes
                    }));
                  }}
                >
                  <option value="">Seleccione Especie</option>
                  {especies.map((specie) => (
                    <option key={specie.id} value={specie.name}>
                      {specie.name}
                    </option>
                  ))}
                </select>

                <select
                  className="w-full border rounded px-3 py-2"
                  value={fruitForm.data.variety}
                  onChange={(e) => fruitForm.setData('variety', e.target.value)}
                  disabled={!fruitForm.data.species}
                >
                  <option value="">Seleccione Variedad</option>
                  {variedades
                    .filter((v) => {
                      const selectedSpecie = especies.find(e => e.name === fruitForm.data.species);
                      return selectedSpecie && v.especie_id === selectedSpecie.id;
                    })
                    .map((variety) => (
                      <option key={variety.id} value={variety.name}>
                        {variety.name}
                      </option>
                    ))}
                </select>
                <Input
                  type="number"
                  placeholder="Tottes por bin"
                  value={fruitForm.data.tottes_per_bin}
                  onChange={(e) => fruitForm.setData('tottes_per_bin', Number(e.target.value || 0))}
                />
                <select
                  className="w-full border rounded px-3 py-2"
                  value={fruitForm.data.status}
                  onChange={(e) => fruitForm.setData('status', e.target.value)}
                >
                  <option value="active">Activa</option>
                  <option value="inactive">Inactiva</option>
                </select>
                <div className="md:col-span-2 flex gap-2">
                  <Button type="submit" disabled={fruitForm.processing}>
                    {editingFruitId ? 'Actualizar' : 'Crear'}
                  </Button>
                  {editingFruitId && (
                    <Button type="button" variant="outline" onClick={resetFruitForm}>
                      Cancelar
                    </Button>
                  )}
                </div>
              </form>

              <div className="space-y-2">
                {fruitConfigs.map((fc) => (
                  <div key={fc.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{fc.species} {fc.variety ? `- ${fc.variety}` : ''}</div>
                      <div className="text-xs text-gray-500">Tottes/bin: {fc.tottes_per_bin} · Estado: {fc.status}</div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          fruitForm.setData((data) => ({
                            ...data,
                            species: fc.species,
                            variety: fc.variety || '',
                            tottes_per_bin: fc.tottes_per_bin,
                            status: fc.status,
                          }));
                          setEditingFruitId(fc.id);
                        }}
                      >
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.fruit-configs.destroy', fc.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>

        <div className="grid md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Campos</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitField} className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Input
                  placeholder="Nombre del campo"
                  value={fieldForm.data.name}
                  onChange={(e) => fieldForm.setData('name', e.target.value)}
                />
                <select
                  className="w-full border rounded px-3 py-2"
                  value={fieldForm.data.producer_id}
                  onChange={(e) => fieldForm.setData('producer_id', e.target.value)}
                >
                  <option value="">Productor asociado</option>
                  {producerOptions.map((p) => (
                    <option key={p.id} value={p.id}>{p.name}</option>
                  ))}
                </select>
                <div className="md:col-span-2 flex gap-2">
                  <Button type="submit" disabled={fieldForm.processing}>
                    {editingFieldId ? 'Actualizar' : 'Crear'}
                  </Button>
                  {editingFieldId && (
                    <Button type="button" variant="outline" onClick={resetFieldForm}>
                      Cancelar
                    </Button>
                  )}
                </div>
              </form>

              <div className="space-y-2">
                {fields.map((field) => (
                  <div key={field.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{field.name}</div>
                      <div className="text-xs text-gray-500">
                        Productor: {field.producer?.name || 'No asignado'} · Cuarteles: {field.blocks_count}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          fieldForm.setData((data) => ({
                            ...data,
                            name: field.name,
                            producer_id: field.producer?.id || '',
                          }));
                          setEditingFieldId(field.id);
                        }}
                      >
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.fields.destroy', field.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Cuarteles</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={submitBlock} className="grid grid-cols-1 md:grid-cols-2 gap-3">
                <Input
                  placeholder="Nombre del cuartel"
                  value={blockForm.data.name}
                  onChange={(e) => blockForm.setData('name', e.target.value)}
                />
                <select
                  className="w-full border rounded px-3 py-2"
                  value={blockForm.data.field_id}
                  onChange={(e) => blockForm.setData('field_id', e.target.value)}
                >
                  <option value="">Selecciona campo</option>
                  {fields.map((f) => (
                    <option key={f.id} value={f.id}>{f.name}</option>
                  ))}
                </select>
                <div className="md:col-span-2 flex gap-2">
                  <Button type="submit" disabled={blockForm.processing}>
                    {editingBlockId ? 'Actualizar' : 'Crear'}
                  </Button>
                  {editingBlockId && (
                    <Button type="button" variant="outline" onClick={resetBlockForm}>
                      Cancelar
                    </Button>
                  )}
                </div>
              </form>

              <div className="space-y-2">
                {blocks.map((block) => (
                  <div key={block.id} className="flex items-center justify-between border rounded px-3 py-2">
                    <div>
                      <div className="font-semibold">{block.name}</div>
                      <div className="text-xs text-gray-500">
                        Campo: {block.field?.name || 'N/A'} {block.field?.producer ? `· Productor: ${block.field.producer.name}` : ''}
                      </div>
                    </div>
                    <div className="flex gap-2">
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => {
                          blockForm.setData((data) => ({
                            ...data,
                            name: block.name,
                            field_id: block.field?.id || '',
                          }));
                          setEditingBlockId(block.id);
                        }}
                      >
                        Editar
                      </Button>
                      <Button
                        size="sm"
                        variant="destructive"
                        onClick={() => handleDelete(route('field-management.blocks.destroy', block.id))}
                      >
                        Eliminar
                      </Button>
                    </div>
                  </div>
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
