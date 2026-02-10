import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';

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

export default function SdpAssignmentsShow({ auth, producer, assignments }) {
  const [sdpItems, setSdpItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [saveLoading, setSaveLoading] = useState(false);
  const [saveError, setSaveError] = useState('');
  const [saveResult, setSaveResult] = useState(null);
  const [selectedCsg, setSelectedCsg] = useState({});

  const csgOptions = useMemo(() => (producer?.csgs || []).map((csg) => csg.csg).filter(Boolean), [producer]);

  const existingByKey = useMemo(() => {
    const map = {};
    (assignments || []).forEach((row) => {
      if (!row.variedad) return;
      const key = `${row.sdp || ''}|${row.variedad}`;
      map[key] = row;
    });
    return map;
  }, [assignments]);

  const existingByVariedad = useMemo(() => {
    const map = {};
    (assignments || []).forEach((row) => {
      if (!row.variedad) return;
      if (map[row.variedad] && map[row.variedad] !== row) {
        map[row.variedad] = null;
      } else if (!map[row.variedad]) {
        map[row.variedad] = row;
      }
    });
    return map;
  }, [assignments]);

  const rows = useMemo(() => {
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
  }, [sdpItems]);

  useEffect(() => {
    if (!producer?.rut) return;
    handleFetchSdp();
  }, [producer?.rut]);

  useEffect(() => {
    if (!rows.length) return;
    setSelectedCsg((prev) => {
      const next = { ...prev };
      rows.forEach((row) => {
        if (next[row.key]) return;
        const existing = existingByKey[row.key] || existingByVariedad[row.variedad];
        if (existing?.csg_code) {
          next[row.key] = existing.csg_code;
        }
      });
      return next;
    });
  }, [rows, existingByKey, existingByVariedad]);

  const handleFetchSdp = async () => {
    const rutValue = normalizeRut(producer?.rut);
    if (!rutValue) return;
    setLoading(true);
    setError('');
    setSdpItems([]);
    try {
      const response = await axios.get(route('sag.sdp-assignments.sdp'), {
        params: { rut: rutValue },
      });
      setSdpItems(response.data.items || []);
    } catch (err) {
      setError('No fue posible consultar SDP.');
    } finally {
      setLoading(false);
    }
  };

  const handleSave = async () => {
    setSaveError('');
    setSaveResult(null);

    const missing = rows.filter((row) => !selectedCsg[row.key]);
    if (missing.length > 0) {
      setSaveError('Debes asignar un CSG a todas las variedades antes de guardar.');
      return;
    }

    const payload = rows.map((row) => ({
      csg_code: selectedCsg[row.key],
      sdp: row.sdp_code,
      variedad: row.variedad,
      especie: row.especie,
      clasificacion: row.clasificacion,
    }));

    setSaveLoading(true);
    try {
      const response = await axios.post(route('sag.sdp-assignments.store', producer?.rut), {
        assignments: payload,
      });
      setSaveResult(response.data);
      router.reload({ only: ['assignments'], preserveScroll: true });
    } catch (err) {
      setSaveError('No fue posible guardar las asignaciones.');
    } finally {
      setSaveLoading(false);
    }
  };

  const getExisting = (row) => existingByKey[row.key] || existingByVariedad[row.variedad];

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Asignación SDP</h2>}
    >
      <Head title={`Asignación SDP - ${producer?.name || ''}`} />

      <div className="py-8">
        <div className="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
          <Card>
            <CardHeader className="flex flex-col gap-2">
              <CardTitle>Productor</CardTitle>
              <div className="flex flex-wrap items-center gap-2 text-sm text-gray-700">
                <span className="font-semibold">{producer?.name || 'Sin nombre'}</span>
                <Badge variant="outline">RUT: {producer?.rut}</Badge>
              </div>
              <div className="flex flex-wrap gap-2">
                {producer?.csgs?.map((csg) => (
                  <Badge key={csg.id} variant="secondary">CSG {csg.csg}</Badge>
                ))}
              </div>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="flex flex-wrap items-center gap-2">
                <Button variant="outline" onClick={handleFetchSdp} disabled={loading}>
                  {loading ? 'Consultando SDP...' : 'Consultar SDP'}
                </Button>
                <Link href={route('sag.sdp-assignments.index')}>
                  <Button variant="ghost">Volver</Button>
                </Link>
              </div>
              {error && <div className="text-sm text-red-600">{error}</div>}
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between">
              <CardTitle>Asignar SDP a CSG por variedad</CardTitle>
              <Button onClick={handleSave} disabled={saveLoading || rows.length === 0}>
                {saveLoading ? 'Guardando...' : 'Guardar asignaciones'}
              </Button>
            </CardHeader>
            <CardContent>
              {saveError && <div className="mb-2 text-sm text-red-600">{saveError}</div>}
              {saveResult && (
                <div className="mb-2 text-sm text-green-700">
                  Guardado: {saveResult.created || 0} creados, {saveResult.updated || 0} actualizados, {saveResult.skipped || 0} omitidos.
                </div>
              )}

              {rows.length === 0 && !loading && (
                <div className="text-sm text-gray-500">Sin variedades SDP para asignar.</div>
              )}

              {rows.length > 0 && (
                <div className="overflow-x-auto">
                  <table className="min-w-full text-sm">
                    <thead>
                      <tr className="text-left text-gray-500">
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
                          <tr key={row.key} className="border-t border-gray-200">
                            <td className="py-2 pr-4 font-medium">{row.sdp_code || '-'}</td>
                            <td className="py-2 pr-4">{row.especie || '-'}</td>
                            <td className="py-2 pr-4">{row.variedad}</td>
                            <td className="py-2 pr-4">{row.clasificacion || '-'}</td>
                            <td className="py-2 pr-4">
                              <select
                                className="rounded border px-2 py-1 text-sm"
                                value={selectedCsg[row.key] || ''}
                                onChange={(e) => setSelectedCsg((prev) => ({ ...prev, [row.key]: e.target.value }))}
                              >
                                <option value="">Selecciona CSG</option>
                                {csgOptions.map((csg) => (
                                  <option key={csg} value={csg}>{csg}</option>
                                ))}
                              </select>
                            </td>
                            <td className="py-2 pr-4">
                              {existing?.sdp_validado_at ? (
                                <span className="text-xs text-green-700">{existing.sdp_validado_at}</span>
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
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
