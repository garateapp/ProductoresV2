import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { MapPin, Save, Mic, Square } from 'lucide-react';

export default function FieldVisitsIndex({ auth, visits, filters }) {
  // Búsqueda
  const { data: searchData, setData: setSearchData, get } = useForm({
    search: filters?.search || '',
  });

  // Subida
  const { data, setData, post, processing, reset } = useForm({
    audio: null,
    visited_at: new Date().toISOString().slice(0, 16),
    latitude: '',
    longitude: '',
  });

  const [geo, setGeo] = useState({ lat: null, lng: null });
  const [error, setError] = useState('');
  const [recording, setRecording] = useState(false);
  const [recordingError, setRecordingError] = useState('');
  const mediaRecorderRef = useRef(null);
  const chunksRef = useRef([]);

  useEffect(() => {
    const debounce = setTimeout(() => {
      get(route('field-visits.index'), {
        preserveScroll: true,
        replace: true,
        data: { search: searchData.search.trim() },
      });
    }, 300);
    return () => clearTimeout(debounce);
  }, [searchData.search]);

  const captureLocation = () => {
    if (!navigator.geolocation) {
      setError('Geolocalización no soportada en este dispositivo.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => {
        setGeo({ lat: pos.coords.latitude, lng: pos.coords.longitude });
        setData('latitude', pos.coords.latitude);
        setData('longitude', pos.coords.longitude);
        setError('');
      },
      () => setError('No se pudo obtener la ubicación.')
    );
  };

  const submit = (e) => {
    e.preventDefault();
    setError('');
    if (!data.audio) {
      setError('Selecciona o graba un archivo de audio.');
      return;
    }
    post(route('field-visits.store'), {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        reset('audio');
      },
      onError: (errs) => {
        const first = Object.values(errs)[0];
        setError(first || 'No se pudo guardar la visita.');
      },
    });
  };

  const pickMimeType = () => {
    const candidates = [
      'audio/webm;codecs=opus',
      'audio/webm',
      'audio/ogg;codecs=opus',
      'audio/mp4',
      'audio/mpeg',
    ];
    return candidates.find((t) => typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(t)) || '';
  };

  const startRecording = async () => {
    setRecordingError('');
    if (typeof navigator === 'undefined' || !navigator.mediaDevices?.getUserMedia) {
      setRecordingError('El navegador no soporta grabación de audio.');
      return;
    }
    if (typeof MediaRecorder === 'undefined') {
      setRecordingError('MediaRecorder no está disponible en este navegador.');
      return;
    }
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      const mimeType = pickMimeType();
      const recorder = mimeType ? new MediaRecorder(stream, { mimeType }) : new MediaRecorder(stream);
      chunksRef.current = [];
      recorder.onstart = () => setRecording(true);
      recorder.ondataavailable = (event) => {
        if (event.data.size > 0) {
          chunksRef.current.push(event.data);
        }
      };
      recorder.onstop = () => {
        const blobType = mimeType || 'audio/webm';
        const blob = new Blob(chunksRef.current, { type: blobType });
        const ext = blobType.includes('mp4') ? 'm4a' : blobType.includes('ogg') ? 'ogg' : blobType.includes('mpeg') ? 'mp3' : 'webm';
        const file = new File([blob], `visita-${Date.now()}.${ext}`, { type: blobType });
        setData('audio', file);
        stream.getTracks().forEach((t) => t.stop());
        setRecording(false);
      };
      mediaRecorderRef.current = recorder;
      recorder.start(250);
      setRecording(true);
    } catch (err) {
      console.error('recording error', err);
      setRecordingError(err?.message || 'No se pudo acceder al micrófono o MediaRecorder no está soportado.');
      setRecording(false);
    }
  };

  const stopRecording = () => {
    if (mediaRecorderRef.current && recording) {
      mediaRecorderRef.current.stop();
    }
    setRecording(false);
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Visitas de campo</h2>}
    >
      <Head title="Visitas de campo" />
      <div className="py-8">
        <div className="mx-auto max-w-6xl space-y-6">
          <Card>
            <CardHeader>
              <CardTitle>Subir audio de visita</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {error && (
                <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                  {error}
                </div>
              )}
              {recordingError && (
                <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                  {recordingError}
                </div>
              )}
              <form className="space-y-4" onSubmit={submit}>
                <div className="grid gap-4 md:grid-cols-2">
                  <div className="space-y-2">
                    <label className="text-sm font-medium text-gray-700">Archivo de audio</label>
                    <Input
                      type="file"
                      accept="audio/*"
                      onChange={(e) => setData('audio', e.target.files?.[0] || null)}
                    />
                    <p className="text-xs text-gray-500">Formatos: mp3, wav, m4a, webm, ogg. Máx 20MB.</p>
                    <div className="flex flex-wrap gap-2">
                      <Button type="button" variant={recording ? 'destructive' : 'outline'} onClick={recording ? stopRecording : startRecording}>
                        {recording ? (
                          <>
                            <Square className="h-4 w-4 mr-2" /> Detener grabación
                          </>
                        ) : (
                          <>
                            <Mic className="h-4 w-4 mr-2" /> Grabar audio
                          </>
                        )}
                      </Button>
                      {recording && <span className="text-sm text-red-600">Grabando...</span>}
                      {data.audio && (
                        <Badge variant="secondary" className="mt-1">
                          Seleccionado: {data.audio.name}
                        </Badge>
                      )}
                    </div>
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-medium text-gray-700">Fecha y hora de visita</label>
                    <Input
                      type="datetime-local"
                      value={data.visited_at}
                      onChange={(e) => setData('visited_at', e.target.value)}
                    />
                  </div>
                </div>
                <div className="flex flex-wrap gap-3 items-center">
                  <Button type="button" variant="outline" onClick={captureLocation}>
                    <MapPin className="h-4 w-4 mr-2" /> Obtener ubicación
                  </Button>
                  {geo.lat && geo.lng ? (
                    <Badge variant="secondary">
                      Ubicación: {geo.lat.toFixed(5)}, {geo.lng.toFixed(5)}
                    </Badge>
                  ) : (
                    <span className="text-sm text-gray-500">Ubicación no capturada (opcional)</span>
                  )}
                </div>
                <div>
                  <Button type="submit" disabled={processing}>
                    <Save className="h-4 w-4 mr-2" /> {processing ? 'Procesando...' : 'Subir y transcribir'}
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Historial de visitas</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="mb-4">
                <Input
                  placeholder="Buscar por texto o nombre del agrónomo..."
                  value={searchData.search}
                  onChange={(e) => setSearchData('search', e.target.value)}
                  className="max-w-md"
                />
              </div>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Fecha</TableHead>
                    <TableHead>Agrónomo</TableHead>
                    <TableHead>Ubicación</TableHead>
                    <TableHead>Transcripción</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {visits.data.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={4} className="text-center text-gray-500">
                        No hay visitas registradas.
                      </TableCell>
                    </TableRow>
                  ) : (
                    visits.data.map((visit) => (
                      <TableRow key={visit.id}>
                        <TableCell>{visit.visited_at || '-'}</TableCell>
                        <TableCell>
                          <div className="font-semibold text-gray-800">{visit.user.name}</div>
                          <div className="text-xs text-gray-500">{visit.user.email}</div>
                        </TableCell>
                        <TableCell>
                          {visit.latitude && visit.longitude ? (
                            <div className="text-sm text-gray-700">
                              {visit.latitude.toFixed(5)}, {visit.longitude.toFixed(5)}
                            </div>
                          ) : (
                            <span className="text-gray-400">N/D</span>
                          )}
                        </TableCell>
                        <TableCell className="max-w-xs">
                          <p className="line-clamp-3 text-gray-700">{visit.transcript}</p>
                        </TableCell>
                      </TableRow>
                    ))
                  )}
                </TableBody>
              </Table>
              <div className="mt-4 flex justify-end gap-2">
                {visits.links.map((link, idx) => (
                  <Button
                    key={`${link.url}-${idx}`}
                    variant={link.active ? 'default' : 'outline'}
                    size="sm"
                    disabled={!link.url}
                    onClick={() => link.url && router.get(link.url, {}, { preserveScroll: true, preserveState: true })}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
