import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage, router } from '@inertiajs/react';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import ContractForm from '@/Pages/Contracts/Partials/ContractForm';

export default function ProducerFlow({ auth, producers }) {
  const { flash } = usePage().props;
  const initialRut = typeof window !== 'undefined'
    ? new URL(window.location.href).searchParams.get('rut') || ''
    : '';
  const initialUserId = typeof window !== 'undefined'
    ? Number(new URL(window.location.href).searchParams.get('user_id') || 0)
    : 0;
  const [rut, setRut] = useState('');
  const [rutResult, setRutResult] = useState(null);
  const [rutLoading, setRutLoading] = useState(false);
  const [activateLoading, setActivateLoading] = useState(false);
  const [rutError, setRutError] = useState('');

  const [sagItems, setSagItems] = useState([]);
  const [sagLoading, setSagLoading] = useState(false);
  const [sagError, setSagError] = useState('');
  const [sagSaved, setSagSaved] = useState(null);
  const [sdpItems, setSdpItems] = useState([]);
  const [sdpLoading, setSdpLoading] = useState(false);
  const [sdpError, setSdpError] = useState('');
  const [sdpSyncLoading, setSdpSyncLoading] = useState(false);
  const [sdpSyncResult, setSdpSyncResult] = useState(null);
  const [sdpSyncError, setSdpSyncError] = useState('');
  const [sqlsrvCheck, setSqlsrvCheck] = useState(null);
  const [sqlsrvCheckLoading, setSqlsrvCheckLoading] = useState(false);
  const [sqlsrvCreateLoading, setSqlsrvCreateLoading] = useState(false);
  const [sqlsrvResult, setSqlsrvResult] = useState(null);
  const [sqlsrvError, setSqlsrvError] = useState('');
  const [confirmOpen, setConfirmOpen] = useState(false);

  const normalizeRut = (value) => String(value || '').toUpperCase().replace(/[^0-9K]/g, '');
  const formatRut = (value) => {
    const cleaned = normalizeRut(value);
    if (cleaned.length <= 1) {
      return cleaned;
    }
    const body = cleaned.slice(0, -1);
    const dv = cleaned.slice(-1);
    const withDots = body.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return `${withDots}-${dv}`;
  };

  useEffect(() => {
    if (initialRut && !rut) {
      setRut(initialRut);
    }
  }, [initialRut, rut]);

  useEffect(() => {
    if (!rutResult && initialRut && rut) {
      handleCheckRut({ preventDefault: () => {} });
    }
  }, [initialRut, rut, rutResult]);

  const handleCheckRut = async (event) => {
    event.preventDefault();
    const normalized = normalizeRut(rut);
    if (!normalized) return;
    setRutLoading(true);
    setRutError('');
    setRutResult(null);
    try {
      const response = await axios.post(route('contracts.producer-flow.check-rut'), { rut: normalized });
      setRutResult(response.data);
    } catch (error) {
      setRutError('No fue posible consultar el RUT.');
    } finally {
      setRutLoading(false);
    }
  };

  const handleValidateProspecto = (prospectoId) => {
    if (!prospectoId) return;
    router.post(route('prospectos-productores.validate', prospectoId), {}, { preserveScroll: true });
  };

  const handleActivate = async () => {
    if (!rutResult?.user?.id) return;
    setActivateLoading(true);
    setRutError('');
    try {
      const response = await axios.post(route('contracts.producer-flow.activate'), { user_id: rutResult.user.id });
      setRutResult((prev) => prev ? { ...prev, user: { ...prev.user, ...response.data.user } } : prev);
    } catch (error) {
      setRutError('No fue posible activar el productor.');
    } finally {
      setActivateLoading(false);
    }
  };

  const handleSagFetch = async (event) => {
    event.preventDefault();
    const normalized = normalizeRut(rut);
    if (!normalized) return;
    setSagLoading(true);
    setSagError('');
    setSagItems([]);
    setSagSaved(null);
    setSdpItems([]);
    setSdpError('');
    setSqlsrvResult(null);
    try {
      const response = await axios.get(route('contracts.producer-flow.sag'), {
        params: { rut: normalized },
      });
      const items = response.data.items || [];
      setSagItems(items);
      if (items.length > 0) {
        await handleSqlsrvCheck(items);
        await handleSdpFetch(normalized);
      } else {
        setSqlsrvCheck(null);
      }
    } catch (error) {
      setSagError('No fue posible consultar SAG.');
    } finally {
      setSagLoading(false);
    }
  };

  const handleSagStore = async (item) => {
    if (!rutResult?.user?.id) {
      setSagSaved({ type: 'error', message: 'Debes tener un productor activo para guardar CSG.' });
      return;
    }
    setSagSaved(null);
    try {
      const response = await axios.post(route('contracts.producer-flow.sag.store'), {
        user_id: rutResult.user.id,
        csg_code: item.csg_code || '',
        direccion: item.direccion || '',
        variedades: item.variedades || [],
      });
      const missing = response.data.missing_variedades || [];
      setSagSaved({
        type: missing.length ? 'warning' : 'success',
        message: missing.length
          ? `Guardado con advertencias. Variedades sin match: ${missing.join(', ')}`
          : 'CSG y variedades guardadas correctamente.',
      });
    } catch (error) {
      setSagSaved({ type: 'error', message: 'No fue posible guardar datos SAG.' });
    }
  };

  const handleSdpFetch = async (normalizedRut = '') => {
    const rutValue = normalizedRut || normalizeRut(rut);
    if (!rutValue) return;
    setSdpLoading(true);
    setSdpError('');
    setSdpItems([]);
    setSdpSyncResult(null);
    setSdpSyncError('');
    try {
      const response = await axios.get(route('contracts.producer-flow.sdp'), {
        params: { rut: rutValue },
      });
      setSdpItems(response.data.items || []);
    } catch (error) {
      setSdpError('No fue posible consultar SDP.');
    } finally {
      setSdpLoading(false);
    }
  };

  const handleSdpSync = async () => {
    const rutValue = normalizeRut(rut);
    if (!rutValue || sdpItems.length === 0) return;
    setSdpSyncLoading(true);
    setSdpSyncError('');
    setSdpSyncResult(null);
    try {
      const response = await axios.post(route('contracts.producer-flow.sdp.sync'), {
        rut: rutValue,
        sag_items: sagItems,
        sdp_items: sdpItems,
      });
      setSdpSyncResult(response.data);
    } catch (error) {
      setSdpSyncError('No fue posible actualizar SDP en el portal.');
    } finally {
      setSdpSyncLoading(false);
    }
  };

  const handleSqlsrvCheck = async (items = sagItems) => {
    if (!rut.trim()) return;
    setSqlsrvCheckLoading(true);
    setSqlsrvError('');
    try {
      const response = await axios.post(route('contracts.producer-flow.sqlsrv.check'), {
        rut,
        sag_items: items,
      });
      setSqlsrvCheck(response.data);
    } catch (error) {
      setSqlsrvError('No fue posible validar en FX.');
    } finally {
      setSqlsrvCheckLoading(false);
    }
  };

  const handleSqlsrvCreate = async (action = 'create') => {
    if (!rut.trim()) return;
    setSqlsrvCreateLoading(true);
    setSqlsrvError('');
    setSqlsrvResult(null);
    try {
      const response = await axios.post(route('contracts.producer-flow.sqlsrv.create'), {
        rut,
        razon_social: rutResult?.prospecto?.razon_social || rutResult?.user?.name || '',
        email: rutResult?.prospecto?.email || rutResult?.user?.email || '',
        comuna: rutResult?.prospecto?.comuna_comercial || rutResult?.prospecto?.comuna_predio || '',
        action,
        sag_items: sagItems,
        sdp_items: sdpItems,
      });
      setSqlsrvResult(response.data);
      setConfirmOpen(false);
    } catch (error) {
      setSqlsrvError('No fue posible crear el productor en FX.');
    } finally {
      setSqlsrvCreateLoading(false);
    }
  };

  const prospectoLink = rut
    ? route('prospectos-productores.create', { rut })
    : route('prospectos-productores.create');

  const userStatus = rutResult?.user?.is_active ? 'Activo' : 'Inactivo';
  const hasEmail = !!rutResult?.prospecto?.email;
  const hasDuplicateCsg = (sqlsrvCheck?.csg_exists?.length || 0) > 0;
  const canCreateSqlsrv = sagItems.length > 0 && !!rutResult?.prospecto?.validated_at && hasEmail && !hasDuplicateCsg;
  const needsConfirmation = !!sqlsrvCheck?.needs_confirmation;
  const canQuerySag = !!rutResult && !!normalizeRut(rut);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Flujo de Creación de Productores</h2>}
    >
      <Head title="Flujo de Creación de Productores" />

      <div className="py-8">
        <div className="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
          {flash?.success && (
            <div className="rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
              {flash.success}
            </div>
          )}
          <Card>
            <CardHeader>
              <CardTitle>1. Creación o revisión de ficha</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={handleCheckRut} className="flex flex-col md:flex-row md:items-end gap-4">
                <div className="flex-1">
                  <Label htmlFor="rut">RUT del productor</Label>
                  <Input
                    id="rut"
                    value={rut}
                    onChange={(event) => setRut(formatRut(event.target.value))}
                    placeholder="Ej: 77.851.659-4"
                  />
                </div>
                <Button type="submit" disabled={rutLoading}>
                  {rutLoading ? 'Consultando...' : 'Consultar'}
                </Button>
              </form>

              {rutError && <div className="text-sm text-red-600">{rutError}</div>}

              {rutResult && (
                <div className="rounded border border-gray-200 p-4 space-y-2">
                  {rutResult.exists ? (
                    <>
                      <div className="flex flex-wrap items-center gap-3">
                        <div className="font-semibold">{rutResult.user?.name || 'Productor existente'}</div>
                        <Badge variant={rutResult.user?.is_active ? 'default' : 'destructive'}>
                          {userStatus}
                        </Badge>
                      </div>
                      <div className="text-sm text-gray-600">RUT: {rutResult.user?.rut || rut}</div>
                      {rutResult.user?.email && <div className="text-sm text-gray-600">Email: {rutResult.user.email}</div>}
                      <div className="flex flex-wrap gap-2 pt-2">
                        {!rutResult.user?.is_active && (
                          <Button type="button" onClick={handleActivate} disabled={activateLoading}>
                            {activateLoading ? 'Activando...' : 'Activar productor'}
                          </Button>
                        )}
                        <Link href={prospectoLink} className="inline-flex items-center px-4 py-2 border rounded-md">
                          Ir a ficha (editar datos)
                        </Link>
                      </div>
                    </>
                  ) : (
                    <>
                      <div className="font-semibold">Productor nuevo</div>
                      <div className="text-sm text-gray-600">No se encontró el RUT en usuarios.</div>
                      {rutResult.prospecto ? (
                        <div className="rounded border border-blue-100 bg-blue-50 p-3 text-sm text-blue-700">
                          Ya existe una ficha de prospecto para este RUT.
                        </div>
                      ) : null}
                      <div className="flex flex-wrap gap-2">
                        {rutResult.prospecto ? (
                          <>
                            <Link
                              href={route('prospectos-productores.edit', rutResult.prospecto.id)}
                              className="inline-flex items-center px-4 py-2 border rounded-md"
                            >
                              Editar ficha
                            </Link>
                            {!rutResult.prospecto.validated_at && (
                              <Button type="button" onClick={() => handleValidateProspecto(rutResult.prospecto.id)}>
                                Validar prospecto
                              </Button>
                            )}
                          </>
                        ) : (
                          <Link href={prospectoLink} className="inline-flex items-center px-4 py-2 border rounded-md">
                            Crear ficha de productor
                          </Link>
                        )}
                      </div>
                    </>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>2. CSG y variedades desde SAG</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              <form onSubmit={handleSagFetch} className="flex flex-col md:flex-row md:items-end gap-4">
                <div className="flex-1">
                  <Label htmlFor="sag_rut">RUT para consulta SAG</Label>
                  <Input
                    id="sag_rut"
                    value={rut}
                    readOnly
                    disabled
                    placeholder="Consulta el RUT del productor para continuar"
                  />
                </div>
                <Button type="submit" disabled={sagLoading || !canQuerySag}>
                  {sagLoading ? 'Consultando...' : 'Consultar SAG'}
                </Button>
              </form>

              {sagError && <div className="text-sm text-red-600">{sagError}</div>}
              {sagSaved && (
                <div className={`text-sm ${sagSaved.type === 'success' ? 'text-green-700' : sagSaved.type === 'warning' ? 'text-yellow-700' : 'text-red-600'}`}>
                  {sagSaved.message}
                </div>
              )}

              {sagItems.length === 0 && !sagLoading && (
                <div className="text-sm text-gray-500">Sin resultados SAG para el RUT consultado.</div>
              )}

              {sagItems.map((item, idx) => (
                <div key={`${item.csg_code || 'csg'}-${idx}`} className="rounded border border-gray-200 p-4 space-y-2">
                  <div className="flex flex-wrap items-center gap-2">
                    <div className="font-semibold">CSG {item.csg_code || 'N/A'}</div>
                    {item.status && <Badge variant="secondary">{item.status}</Badge>}
                  </div>
                  {item.predio && <div className="text-sm text-gray-600">Predio: {item.predio}</div>}
                  {item.direccion && <div className="text-sm text-gray-600">Dirección: {item.direccion}</div>}
                  {item.especie_variedades?.length > 0 && (
                    <div className="text-sm text-gray-700">
                      Variedades:
                      <ul className="list-disc list-inside">
                        {item.especie_variedades.map((entry, index) => (
                          <li key={`${entry.raw}-${index}`}>{entry.raw}</li>
                        ))}
                      </ul>
                    </div>
                  )}
                  <div className="flex flex-wrap gap-2">
                    <Button type="button" onClick={() => handleSagStore(item)}>
                      Guardar CSG y variedades
                    </Button>
                    {!rutResult?.user?.id && (
                      <span className="text-xs text-gray-500">Debes seleccionar/activar un productor para guardar.</span>
                    )}
                  </div>
                </div>
              ))}

              <div className="pt-4 border-t border-gray-200">
                <div className="flex items-center justify-between mb-2">
                  <h4 className="text-sm font-semibold text-gray-800">SDP (Sitios de Producción)</h4>
                  <div className="flex items-center gap-2">
                    <Button type="button" variant="outline" size="sm" onClick={() => handleSdpFetch()} disabled={sdpLoading}>
                      {sdpLoading ? 'Consultando...' : 'Actualizar SDP'}
                    </Button>
                    <Button
                      type="button"
                      variant="secondary"
                      size="sm"
                      onClick={handleSdpSync}
                      disabled={sdpSyncLoading || sdpItems.length === 0}
                    >
                      {sdpSyncLoading ? 'Guardando...' : 'Guardar SDP en portal'}
                    </Button>
                  </div>
                </div>
                {sdpError && <div className="text-sm text-red-600">{sdpError}</div>}
                {sdpSyncError && <div className="text-sm text-red-600">{sdpSyncError}</div>}
                {sdpSyncResult?.message && (
                  <div className="text-sm text-yellow-700">{sdpSyncResult.message}</div>
                )}
                {sdpSyncResult && !sdpSyncResult.message && (
                  <div className="text-sm text-green-700">
                    SDP actualizado: {sdpSyncResult.updated || 0} registros, omitidos {sdpSyncResult.skipped || 0}.
                  </div>
                )}
                {sdpItems.length === 0 && !sdpLoading && (
                  <div className="text-sm text-gray-500">Sin resultados SDP para el RUT consultado.</div>
                )}
                {sdpItems.length > 0 && (
                  <div className="overflow-x-auto">
                    <table className="min-w-full text-sm">
                      <thead>
                        <tr className="text-left text-gray-500">
                          <th className="py-2 pr-4">Código SDP</th>
                          <th className="py-2 pr-4">Nombre</th>
                          <th className="py-2 pr-4">Muestreo</th>
                          <th className="py-2 pr-4">Comuna</th>
                          <th className="py-2 pr-4">Región</th>
                          <th className="py-2 pr-4">Fecha</th>
                          <th className="py-2 pr-4">Especie/Variedad</th>
                        </tr>
                      </thead>
                      <tbody className="text-gray-700">
                        {sdpItems.map((row, rowIndex) => (
                          <tr
                            key={`${row.sdp_code || 'sdp'}-${row.sdp_name || 'row'}-${rowIndex}`}
                            className="border-t border-gray-200"
                          >
                            <td className="py-2 pr-4 font-medium">{row.sdp_code}</td>
                            <td className="py-2 pr-4">{row.sdp_name || '-'}</td>
                            <td className="py-2 pr-4">{row.muestreo || '-'}</td>
                            <td className="py-2 pr-4">{row.comuna || '-'}</td>
                            <td className="py-2 pr-4">{row.region || '-'}</td>
                            <td className="py-2 pr-4">{row.fecha_registro || '-'}</td>
                            <td className="py-2 pr-4">
                              {row.variedades?.length ? (
                                <ul className="list-disc list-inside">
                                  {row.variedades.map((v, i) => (
                                    <li key={`${row.sdp_code}-${i}`}>{v}</li>
                                  ))}
                                </ul>
                              ) : (
                                '-'
                              )}
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>3. Crear productor en FX</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {!rutResult?.prospecto && (
                <div className="text-sm text-gray-600">
                  Crea o completa la ficha del productor en el paso 1 para poder crear el productor.
                </div>
              )}
              {rutResult?.prospecto && !rutResult.prospecto.validated_at && (
                <div className="text-sm text-gray-600">
                  Debes validar el prospecto antes de crear el productor.
                </div>
              )}
              {rutResult?.prospecto && !rutResult.prospecto.email && (
                <div className="text-sm text-gray-600">
                  Debes completar el email en la ficha antes de crear el productor.
                </div>
              )}
              {sqlsrvCheckLoading && (
                <div className="text-sm text-gray-500">Validando productor en FX...</div>
              )}
              {sqlsrvError && <div className="text-sm text-red-600">{sqlsrvError}</div>}
              {sqlsrvCheck?.csg_exists?.length > 0 && (
                <div className="text-sm text-yellow-700">
                  CSG ya existentes en FX: {sqlsrvCheck.csg_exists.join(', ')}.
                </div>
              )}
              {sqlsrvCheck?.records_without_csg?.length > 0 && (
                <div className="text-sm text-gray-700">
                  Hay registros sin CSG en FX para este RUT. Se requiere confirmación antes de crear.
                </div>
              )}
              <div className="flex flex-wrap gap-2">
                <Button
                  type="button"
                  onClick={() => (needsConfirmation ? setConfirmOpen(true) : handleSqlsrvCreate('create'))}
                  disabled={!canCreateSqlsrv || sqlsrvCreateLoading}
                >
                  {sqlsrvCreateLoading ? 'Creando...' : 'Crear productor FX'}
                </Button>
                {!canCreateSqlsrv && (
                  <span className="text-xs text-gray-500">
                    Para crear, necesitas ficha validada, email y CSG obtenidos sin duplicados.
                  </span>
                )}
              </div>
              {sqlsrvResult && (
                <div className="rounded border border-green-200 bg-green-50 p-3 text-sm text-green-700 space-y-1">
                  <div>Productor procesado en FX.</div>
                  {sqlsrvResult?.sqlsrv?.results?.length > 0 && (
                    <div>
                      CSG procesados: {sqlsrvResult.sqlsrv.results.map((r) => `${r.csg} (${r.status})`).join(', ')}
                    </div>
                  )}
                  {sqlsrvResult?.sqlsrv?.centros_costo?.skipped?.length > 0 && (
                    <div className="text-gray-600">
                      Centros costo existentes: {sqlsrvResult.sqlsrv.centros_costo.skipped.map((m) => `${m.csg} - ${m.variedad}`).join(', ')}
                    </div>
                  )}
                  {sqlsrvResult?.sqlsrv?.centros_costo?.missing?.length > 0 && (
                    <div className="text-yellow-700">
                      Centros costo no creados: {sqlsrvResult.sqlsrv.centros_costo.missing.map((m) => `${m.csg} - ${m.variedad}${m.error ? ` (${m.error})` : ''}`).join(', ')}
                    </div>
                  )}
                  {sqlsrvResult?.portal?.results?.length > 0 && (
                    <div>
                      Usuarios portal: {sqlsrvResult.portal.results.map((r) => `${r.csg} (${r.status})`).join(', ')}
                    </div>
                  )}
                  {sqlsrvResult?.portal?.errors?.length > 0 && (
                    <div className="text-yellow-700">
                      Errores portal: {sqlsrvResult.portal.errors.map((e) => `${e.csg}: ${e.error}`).join(' | ')}
                    </div>
                  )}
                </div>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>4. Condiciones de contrato y contrato firmado</CardTitle>
            </CardHeader>
            <CardContent>
              <ContractForm
                producers={producers}
                defaultUserId={rutResult?.user?.id || (initialUserId || '')}
                flowRedirect
                flowRut={rut}
                showBackLink={false}
                submitLabel="Guardar contrato"
              />
            </CardContent>
          </Card>
          <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
            <DialogContent className="max-w-2xl">
              <DialogHeader>
                <DialogTitle>Confirmación requerida</DialogTitle>
                <DialogDescription>
                  Se detectaron registros sin CSG en FX. Puedes actualizar registros existentes o crear nuevos.
                </DialogDescription>
              </DialogHeader>
              {sqlsrvCheck?.records_without_csg?.length > 0 && (
                <div className="text-sm text-gray-700 space-y-2">
                  {sqlsrvCheck.records_without_csg.map((record) => (
                    <div key={record.id} className="rounded border border-gray-200 p-2">
                      <div><strong>ID:</strong> {record.id} <strong>Sucursal:</strong> {record.sucursal || '-'}</div>
                      <div><strong>Dirección:</strong> {record.direccion || 'Sin dirección'}</div>
                      {record.best_match_csg && (
                        <div><strong>Mejor match:</strong> CSG {record.best_match_csg} ({record.best_match_similarity}%)</div>
                      )}
                    </div>
                  ))}
                </div>
              )}
              <DialogFooter className="gap-2">
                <Button variant="outline" onClick={() => setConfirmOpen(false)}>
                  Cancelar
                </Button>
                <Button
                  variant="secondary"
                  onClick={() => handleSqlsrvCreate('update')}
                  disabled={sqlsrvCreateLoading}
                >
                  {sqlsrvCreateLoading ? 'Procesando...' : 'Actualizar registros'}
                </Button>
                <Button
                  onClick={() => handleSqlsrvCreate('create')}
                  disabled={sqlsrvCreateLoading}
                >
                  {sqlsrvCreateLoading ? 'Procesando...' : 'Crear nuevos'}
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
