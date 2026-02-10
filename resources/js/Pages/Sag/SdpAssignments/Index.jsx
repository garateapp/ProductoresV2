import React, { useCallback, useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from '@/Components/ui/accordion';
import {
  Pagination,
  PaginationContent,
  PaginationItem,
  PaginationLink,
} from '@/Components/ui/pagination';
import { User } from 'lucide-react';

const normalizeRut = (value) => String(value || '').toUpperCase().replace(/[^0-9K]/g, '');
const entryPattern = /([A-ZÁÉÍÓÚÜÑ]+\.[A-Z0-9\s\-']+?\s*-\s*Clasificac(?:i[oó]n|ion|in)\s*:\s*[ABC](?:\*)?)/giu;
const classificationPattern = /Clasificac(?:i[oó]n|ion|in)\s*:\s*([ABC](?:\*)?)/i;

const extractEntries = (line) => {
  if (!line) return [];
  const matches = Array.from(line.matchAll(entryPattern)).map((match) => match[1]?.trim()).filter(Boolean);
  return matches.length ? matches : [line.trim()];
};

const parseEntry = (entry) => {
  const classificationMatch = entry.match(classificationPattern);
  const classification = classificationMatch ? classificationMatch[1]?.toUpperCase() : null;
  const left = entry.split('-')[0]?.trim() || '';
  const [rawEspecie, rawVariedad] = left.split('.', 2);
  const especie = rawEspecie?.trim() || null;
  const variedad = (rawVariedad || rawEspecie || '').trim();

  return {
    especie,
    variedad,
    clasificacion: classification,
    raw: entry,
  };
};

export default function SdpAssignmentsIndex({ auth, producers, filters }) {
  const [search, setSearch] = useState(filters.search || '');
  const searchRef = useRef(null);
  const [loading, setLoading] = useState(false);
  const [assignmentState, setAssignmentState] = useState({});

  useEffect(() => {
    const handleStart = () => setLoading(true);
    const handleFinish = () => setLoading(false);

    const unsubscribeStart = router.on('start', handleStart);
    const unsubscribeFinish = router.on('finish', handleFinish);

    return () => {
      unsubscribeStart();
      unsubscribeFinish();
    };
  }, []);

  const handleSearch = useCallback((value) => {
    if (searchRef.current) {
      clearTimeout(searchRef.current);
    }
    searchRef.current = setTimeout(() => {
      router.get(route('sag.sdp-assignments.index'), { search: value }, { preserveState: true, replace: true });
    }, 300);
  }, []);

  const buildRows = useCallback((sdpItems) => {
    const seen = new Set();
    const result = [];
    (sdpItems || []).forEach((item) => {
      const sdpCode = item.sdp_code || '';
      (item.variedades || []).forEach((line) => {
        extractEntries(line).forEach((entry) => {
          const parsed = parseEntry(entry);
          if (!parsed.variedad) return;
          const key = `${sdpCode}|${parsed.variedad}`;
          if (seen.has(key)) return;
          seen.add(key);
          result.push({
            key,
            sdp_code: sdpCode,
            sdp_name: item.sdp_name || '',
            especie: parsed.especie,
            variedad: parsed.variedad,
            clasificacion: parsed.clasificacion,
            raw: parsed.raw,
          });
        });
      });
    });
    return result;
  }, []);

  const buildAssignmentMaps = useCallback((assignments = []) => {
    const byKey = {};
    const byVariedad = {};
    assignments.forEach((row) => {
      if (!row.variedad) return;
      const key = `${row.sdp || ''}|${row.variedad}`;
      byKey[key] = row;
      if (byVariedad[row.variedad] && byVariedad[row.variedad] !== row) {
        byVariedad[row.variedad] = null;
      } else if (!byVariedad[row.variedad]) {
        byVariedad[row.variedad] = row;
      }
    });
    return { byKey, byVariedad };
  }, []);

  const ensureState = useCallback((rut) => {
    setAssignmentState((prev) => {
      if (prev[rut]) return prev;
      return {
        ...prev,
        [rut]: {
          loading: false,
          error: '',
          sdpItems: [],
          assignments: [],
          selectedCsg: {},
          saveLoading: false,
          saveError: '',
          saveResult: null,
        },
      };
    });
  }, []);

  const handleLoad = useCallback(async (rut) => {
    const normalizedRut = normalizeRut(rut);
    if (!normalizedRut) return;
    ensureState(rut);
    setAssignmentState((prev) => ({
      ...prev,
      [rut]: { ...(prev[rut] || {}), loading: true, error: '', saveResult: null },
    }));
    try {
      const [dataResponse, sdpResponse] = await Promise.all([
        axios.get(route('sag.sdp-assignments.data', rut)),
        axios.get(route('sag.sdp-assignments.sdp'), { params: { rut: normalizedRut } }),
      ]);

      const assignments = dataResponse.data.assignments || [];
      const sdpItems = sdpResponse.data.items || [];
      const rows = buildRows(sdpItems);
      const { byKey, byVariedad } = buildAssignmentMaps(assignments);
      const selectedCsg = {};
      rows.forEach((row) => {
        const existing = byKey[row.key] || byVariedad[row.variedad];
        if (existing?.csg_code) {
          selectedCsg[row.key] = existing.csg_code;
        }
      });

      setAssignmentState((prev) => ({
        ...prev,
        [rut]: {
          ...(prev[rut] || {}),
          loading: false,
          error: '',
          assignments,
          sdpItems,
          selectedCsg: { ...(prev[rut]?.selectedCsg || {}), ...selectedCsg },
        },
      }));
    } catch (err) {
      setAssignmentState((prev) => ({
        ...prev,
        [rut]: { ...(prev[rut] || {}), loading: false, error: 'No fue posible cargar SDP/asignaciones.' },
      }));
    }
  }, [buildAssignmentMaps, buildRows, ensureState]);

  const handleSave = useCallback((rut, rows) => {
    setAssignmentState((prev) => ({
      ...prev,
      [rut]: { ...(prev[rut] || {}), saveError: '', saveResult: null },
    }));

    const state = assignmentState[rut];
    if (!state) return;
    const payload = rows
      .filter((row) => state.selectedCsg?.[row.key])
      .map((row) => ({
        csg_code: state.selectedCsg[row.key],
        sdp: row.sdp_code,
        variedad: row.variedad,
        especie: row.especie,
        clasificacion: row.clasificacion,
      }));

    if (payload.length === 0) {
      setAssignmentState((prev) => ({
        ...prev,
        [rut]: { ...(prev[rut] || {}), saveError: 'Debes seleccionar al menos un CSG para guardar.' },
      }));
      return;
    }

    setAssignmentState((prev) => ({
      ...prev,
      [rut]: { ...(prev[rut] || {}), saveLoading: true, saveError: '' },
    }));

    axios.post(route('sag.sdp-assignments.store', rut), { assignments: payload })
      .then((response) => {
        setAssignmentState((prev) => ({
          ...prev,
          [rut]: { ...(prev[rut] || {}), saveLoading: false, saveResult: response.data },
        }));
        handleLoad(rut);
      })
      .catch(() => {
        setAssignmentState((prev) => ({
          ...prev,
          [rut]: { ...(prev[rut] || {}), saveLoading: false, saveError: 'No fue posible guardar las asignaciones.' },
        }));
      });
  }, [assignmentState, handleLoad]);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Asignación SDP</h2>}
    >
      <Head title="Asignación SDP" />

      <div className="py-12 bg-gradient-to-br from-emerald-50 via-white to-lime-50">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card className="border-emerald-200/60 shadow-lg bg-white/90 backdrop-blur">
            <CardHeader className="flex flex-row items-center justify-between bg-emerald-700 text-white rounded-t-lg">
              <CardTitle className="text-white">Productores activos</CardTitle>
              <div className="flex items-center space-x-2">
                <Input
                  type="text"
                  placeholder="Buscar por RUT o Nombre..."
                  value={search}
                  onChange={(e) => {
                    setSearch(e.target.value);
                    handleSearch(e.target.value);
                  }}
                  className="max-w-sm bg-white text-gray-900 placeholder:text-gray-400"
                />
              </div>
            </CardHeader>
            <CardContent>
              {loading ? (
                <div className="text-sm text-gray-500">Cargando productores...</div>
              ) : (
                producers.data.length > 0 ? (
                  <Accordion type="single" collapsible className="w-full space-y-3">
                    {producers.data.map((producer) => {
                      const rut = producer.rut;
                      const state = assignmentState[rut];
                      const rows = state ? buildRows(state.sdpItems) : [];
                      const { byKey, byVariedad } = buildAssignmentMaps(state?.assignments || []);
                      const csgOptions = producer.csg_records?.map((csg) => csg.csg).filter(Boolean) || [];

                      const getExisting = (row) => byKey[row.key] || byVariedad[row.variedad];

                      return (
                      <AccordionItem key={producer.rut} value={producer.rut} className="border border-emerald-100 rounded-xl overflow-hidden">
                        <AccordionTrigger className="flex items-center justify-between p-4 bg-emerald-50 hover:bg-emerald-100 rounded-none">
                          <div className="flex items-center gap-3">
                            <User className="h-6 w-6 text-emerald-700" />
                            <span className="font-semibold text-lg text-emerald-900">{producer.name || 'Sin nombre'}</span>
                            <Badge variant="outline" className="ml-2 border-emerald-300 text-emerald-700">{producer.rut}</Badge>
                          </div>
                        </AccordionTrigger>
                        <AccordionContent className="p-4 border-t border-emerald-100 bg-white">
                          <div className="space-y-4">
                            <div className="flex flex-wrap items-center justify-between gap-3">
                              <div className="flex flex-wrap gap-2">
                                {producer.csg_records?.length ? (
                                  producer.csg_records.map((csg) => (
                                    <Badge key={csg.id} variant="secondary" className="bg-emerald-100 text-emerald-800 border border-emerald-200">
                                      CSG {csg.csg}
                                    </Badge>
                                  ))
                                ) : (
                                  <span className="text-sm text-gray-500">Sin CSG registrados</span>
                                )}
                              </div>
                              <div className="flex flex-wrap gap-2">
                                <Button size="sm" variant="outline" className="border-emerald-300 text-emerald-700 hover:bg-emerald-50" onClick={() => handleLoad(rut)} disabled={state?.loading}>
                                  {state?.loading ? 'Consultando...' : 'Cargar SDP'}
                                </Button>
                                <Button
                                  size="sm"
                                  className="bg-emerald-700 hover:bg-emerald-800 text-white"
                                  onClick={() => handleSave(rut, rows)}
                                  disabled={state?.saveLoading || rows.length === 0}
                                >
                                  {state?.saveLoading ? 'Guardando...' : 'Guardar asignaciones'}
                                </Button>
                              </div>
                            </div>

                            {state?.error && <div className="text-sm text-red-600">{state.error}</div>}
                            {state?.saveError && <div className="text-sm text-red-600">{state.saveError}</div>}
                            {state?.saveResult && (
                              <div className="text-sm text-green-700">
                                Guardado: {state.saveResult.created || 0} creados, {state.saveResult.updated || 0} actualizados, {state.saveResult.skipped || 0} omitidos.
                              </div>
                            )}

                            {rows.length > 0 && (
                              <div className="overflow-x-auto border border-emerald-100 rounded-lg">
                                <table className="min-w-full text-sm">
                                  <thead className="bg-emerald-50">
                                    <tr className="text-left text-emerald-700">
                                      <th className="py-2 pr-4">SDP</th>
                                      <th className="py-2 pr-4">Especie</th>
                                      <th className="py-2 pr-4">Variedad</th>
                                      <th className="py-2 pr-4">Clasificación</th>
                                      <th className="py-2 pr-4">CSG</th>
                                      <th className="py-2 pr-4">Validado</th>
                                    </tr>
                                  </thead>
                                  <tbody className="text-gray-700">
                                    {rows.map((row) => {
                                      const existing = getExisting(row);
                                      return (
                                        <tr key={row.key} className="border-t border-emerald-100 odd:bg-white even:bg-emerald-50/40 hover:bg-emerald-50">
                                          <td className="py-2 pr-4 font-medium">
                                            {row.sdp_code || '-'}
                                            {row.sdp_name ? (
                                              <span className="block text-xs text-emerald-700">{row.sdp_name}</span>
                                            ) : null}
                                          </td>
                                          <td className="py-2 pr-4">{row.especie || '-'}</td>
                                          <td className="py-2 pr-4">{row.variedad}</td>
                                          <td className="py-2 pr-4">{row.clasificacion || '-'}</td>
                                          <td className="py-2 pr-4">
                                            <select
                                              className="rounded border border-emerald-200 bg-white px-2 py-1 text-sm focus:border-emerald-400 focus:ring-emerald-200"
                                              value={state?.selectedCsg?.[row.key] || ''}
                                              onChange={(e) => {
                                                const value = e.target.value;
                                                setAssignmentState((prev) => ({
                                                  ...prev,
                                                  [rut]: {
                                                    ...(prev[rut] || {}),
                                                    selectedCsg: {
                                                      ...(prev[rut]?.selectedCsg || {}),
                                                      [row.key]: value,
                                                    },
                                                  },
                                                }));
                                              }}
                                            >
                                              <option value="">Selecciona CSG</option>
                                              {csgOptions.map((csg) => {
                                                const predioName = producer.csg_records?.find((record) => record.csg === csg)?.predio;
                                                return (
                                                  <option key={`${csg}-${row.variedad}`} value={csg}>
                                                    {csg} - {predioName || 'Sin predio'} - {row.variedad}
                                                  </option>
                                                );
                                              })}
                                            </select>
                                          </td>
                                          <td className="py-2 pr-4">
                                            {existing?.sdp_validado_at ? (
                                              <span className="text-xs text-emerald-700">{existing.sdp_validado_at}</span>
                                            ) : (
                                              <span className="text-xs text-gray-400">Sin validar</span>
                                            )}
                                          </td>
                                        </tr>
                                      );
                                    })}
                                  </tbody>
                                </table>
                              </div>
                            )}
                          </div>
                        </AccordionContent>
                      </AccordionItem>
                    )})}
                  </Accordion>
                ) : (
                  <div className="text-center py-8 text-gray-500">No hay productores activos con CSG registrados.</div>
                )
              )}
              <div className="mt-4 flex justify-center">
                <Pagination>
                  <PaginationContent>
                    {producers.links.map((link, index) => (
                      <PaginationItem key={index}>
                        <PaginationLink
                          href={link.url || '#'}
                          isActive={link.active}
                          dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                      </PaginationItem>
                    ))}
                  </PaginationContent>
                </Pagination>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
