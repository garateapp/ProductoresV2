import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Switch } from '@/Components/ui/switch';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { FileText, Repeat, Send } from 'lucide-react';

export default function Preview({ recepcionId, numero, htmlUrl, approveUrl, approved: approvedProp, generateUrl, informeUrl, resendUrl, sendPreviewUrl }) {
  const [approved, setApproved] = useState(!!approvedProp);
  const [approving, setApproving] = useState(false);
  const [resending, setResending] = useState(false);
  const [sendingPreview, setSendingPreview] = useState(false);
  const { auth } = usePage().props;

  const handleApprove = async (value) => {
    if (!value) return; // one-way approve only
    try {
      setApproving(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(approveUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
      const data = await res.json();
      if (res.ok && data?.status === 'approved') {
        setApproved(true);
      } else {
        setApproved(false);
        alert(data?.message || 'No se pudo aprobar el reporte');
      }
    } catch (e) {
      setApproved(false);
      alert('Error al aprobar el reporte');
    } finally {
      setApproving(false);
    }
  };

  const handleResend = async () => {
    if (!resendUrl) return;
    try {
      setResending(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(resendUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
      const data = await res.json();
      if (!res.ok || data?.status !== 'resent') {
        alert(data?.message || 'No se pudo reenviar el informe');
      } else {
        alert('Informe reenviado correctamente');
      }
    } catch (e) {
      alert('Error al reenviar el informe');
    } finally {
      setResending(false);
    }
  };

  const handleSendPreview = async () => {
    if (!sendPreviewUrl) return;
    try {
      setSendingPreview(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(sendPreviewUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
      const data = await res.json();
      if (!res.ok || data?.status !== 'sent') {
        alert(data?.message || 'No se pudo enviar el reporte de previsualizacion.');
      } else {
        alert('Reporte de previsualizacion enviado correctamente.');
      }
    } catch (e) {
      alert('Error al enviar el reporte de previsualizacion.');
    } finally {
      setSendingPreview(false);
    }
  };



  return (
    <div className="container mx-auto py-6 space-y-4">
      <Head title={`Previsualización Informe #${numero || recepcionId}`} />
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-xl font-semibold">Previsualización Informe Recepción #{numero || recepcionId}</CardTitle>
          <div className="flex items-center gap-4">
            <div className="flex items-center gap-3">
              <Button
                type="button"
                variant="outline"
                onClick={handleSendPreview}
                disabled={!sendPreviewUrl || approved || approving || sendingPreview}
              >
                <Send className="h-4 w-4 mr-2" /> {sendingPreview ? "Enviando..." : "Enviar previsualizacion"}
              </Button>
              <span className="text-sm">Aprobar reporte para descarga</span>
              <Switch checked={approved} disabled={approving || approved} onCheckedChange={handleApprove} />
              {approving && (
                <span className="text-xs text-gray-500 animate-pulse">Generando reporte...</span>
              )}
            </div>
            <div className="flex items-center gap-2">
              <a href={approved ? generateUrl : undefined} target="_blank" rel="noopener noreferrer">
                <Button disabled={!approved || approving} variant="outline">
                  <FileText className="h-4 w-4 mr-2" /> {approving ? 'Generando...' : 'Ver informe'}
                </Button>
              </a>
              <Button
                type="button"
                variant="outline"
                onClick={handleResend}
                disabled={!approved || resending || approving}
              >
                <Repeat className="h-4 w-4 mr-2" /> {resending ? 'Reenviando...' : 'Reenviar'}
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="w-full h-[80vh] border rounded">
            <iframe title="reporte" src={htmlUrl} className="w-full h-full border-0" />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

Preview.layout = page => (
  <AuthenticatedLayout user={page.props.auth.user} children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Previsualización Informe</h2>} />
);
