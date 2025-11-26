import React, { useEffect, useRef, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Mic, Square, MapPin, Save } from 'lucide-react';

const wsUrl = (host, token) =>
  `wss://${host}/v2/realtime/ws?sample_rate=16000&token=${encodeURIComponent(token)}`;

export default function FieldVisitsIndex({ auth, visits, filters, assemblyai }) {
  const { data, setData, get } = useForm({ search: filters?.search || '' });
  const [transcript, setTranscript] = useState('');
  const [partial, setPartial] = useState('');
  const [recording, setRecording] = useState(false);
  const [connecting, setConnecting] = useState(false);
  const [geo, setGeo] = useState({ lat: null, lng: null });
  const [micError, setMicError] = useState('');
  const socketRef = useRef(null);
  const mediaRecorderRef = useRef(null);

  useEffect(() => {
    const debounce = setTimeout(() => {
      get(route('field-visits.index'), {
        preserveScroll: true,
        replace: true,
        data: { search: data.search.trim() },
      });
    }, 300);
    return () => clearTimeout(debounce);
  }, [data.search]);

  const stopAndCleanup = () => {
    setRecording(false);
    setConnecting(false);
    if (mediaRecorderRef.current) {
      mediaRecorderRef.current.stop();
      mediaRecorderRef.current.stream.getTracks().forEach((t) => t.stop());
      mediaRecorderRef.current = null;
    }
    if (socketRef.current) {
      socketRef.current.close();
      socketRef.current = null;
    }
  };

  const startRecording = async () => {
    setMicError('');
    if (!assemblyai?.api_key) {
      setMicError('No hay API key de AssemblyAI configurada.');
      return;
    }
    if (typeof navigator === 'undefined' || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      setMicError('El dispositivo/navegador no soporta captura de audio.');
      return;
    }
    if (typeof MediaRecorder === 'undefined') {
      setMicError('MediaRecorder no está disponible en este navegador. Usa Chrome/Edge/Firefox en Android.');
      return;
    }

    setTranscript('');
    setPartial('');
    setConnecting(true);
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
      let mediaRecorder;
      try {
        mediaRecorder = new MediaRecorder(stream);
      } catch (err) {
        mediaRecorder = new MediaRecorder(stream);
      }
      mediaRecorderRef.current = mediaRecorder;

      const socket = new WebSocket(wsUrl(assemblyai.host, assemblyai.api_key));
      socketRef.current = socket;

      socket.onmessage = (event) => {
        const msg = JSON.parse(event.data);
        if (msg?.message_type === 'PartialTranscript') {
          setPartial(msg.text || '');
        }
        if (msg?.message_type === 'FinalTranscript') {
          setTranscript((prev) => `${prev} ${msg.text || ''}`.trim());
          setPartial('');
        }
      };

      socket.onerror = (evt) => {
        console.error('WS error', evt);
        setMicError('Error en la conexión con AssemblyAI. Revisa la API key, host y red (requiere WSS).');
        stopAndCleanup();
      };

      socket.onclose = (evt) => {
        if (!recording) {
          setMicError(`Conexión cerrada (código ${evt.code || 'desconocido'}). Verifica la API key y host (api.assemblyai.com).`);
        }
        setConnecting(false);
        setRecording(false);
      };

      socket.onopen = () => {
        setConnecting(false);
        setRecording(true);
        mediaRecorder.start(250);
      };

      mediaRecorder.addEventListener('dataavailable', async (event) => {
        if (event.data.size > 0 && socket.readyState === WebSocket.OPEN) {
          const buffer = await event.data.arrayBuffer();
          const base64 = btoa(String.fromCharCode(...new Uint8Array(buffer)));
          socket.send(JSON.stringify({ audio: base64 }));
        }
      });
    } catch (error) {
      console.error('Microphone error', error);
      setMicError(error?.message || 'No se pudo acceder al micrófono. Verifica permisos y que el sitio esté en HTTPS.');
      setConnecting(false);
      stopAndCleanup();
    }
  };

  const stopRecording = () => {
    stopAndCleanup();
  };

  const captureLocation = () => {
    if (!navigator.geolocation) {
      alert('Geolocalización no soportada en este dispositivo.');
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (pos) => setGeo({ lat: pos.coords.latitude, lng: pos.coords.longitude }),
      () => alert('No se pudo obtener la ubicación.')
    );
  };

  const saveVisit = () => {
    const text = `${transcript} ${partial}`.trim();
    if (!text) {
      alert('No hay texto para guardar.');
      return;
    }
    router.post(
      route('field-visits.store'),
      {
        transcript: text,
        visited_at: new Date().toISOString(),
        latitude: geo.lat,
        longitude: geo.lng,
      },
      {
        preserveScroll: true,
        onSuccess: () => {
          setTranscript('');
          setPartial('');
        },
      }
    );
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
              <CardTitle>Captura de visita</CardTitle>
            </CardHeader>
          <CardContent className="space-y-4">
            {micError && (
              <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                {micError}
              </div>
            )}
            <div className="flex flex-wrap gap-3">
                <Button onClick={startRecording} disabled={recording || connecting}>
                  <Mic className="h-4 w-4 mr-2" /> {connecting ? 'Conectando...' : 'Grabar'}
                </Button>
                <Button onClick={stopRecording} variant="outline" disabled={!recording}>
                  <Square className="h-4 w-4 mr-2" /> Detener
                </Button>
                <Button onClick={captureLocation} variant="outline">
                  <MapPin className="h-4 w-4 mr-2" /> Obtener ubicación
                </Button>
                <Button onClick={saveVisit} variant="secondary">
                  <Save className="h-4 w-4 mr-2" /> Guardar visita
                </Button>
              </div>

              <div className="rounded border bg-gray-50 p-3 text-sm">
                <p className="text-gray-500">Transcripción en vivo:</p>
                <p className="mt-2 min-h-[100px] whitespace-pre-wrap text-gray-800">
                  {transcript || partial ? `${transcript} ${partial}`.trim() : 'Aún sin texto...'}
                </p>
              </div>

              <div className="flex gap-3 text-sm text-gray-600">
                {geo.lat && geo.lng ? (
                  <Badge variant="secondary">
                    Ubicación: {geo.lat.toFixed(5)}, {geo.lng.toFixed(5)}
                  </Badge>
                ) : (
                  <span className="text-gray-500">Ubicación no capturada</span>
                )}
              </div>
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
                  value={data.search}
                  onChange={(e) => setData('search', e.target.value)}
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
