import React, { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Switch } from '@/Components/ui/switch';
import { Button } from '@/Components/ui/button';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { FileText, Repeat, Send, MessageCircle, RefreshCw } from 'lucide-react';

export default function Preview({ recepcionId, numero, htmlUrl, approveUrl, approved: approvedProp, generateUrl, informeUrl, resendUrl, sendPreviewUrl, sendPreviewWhatsappUrl }) {
  const [approved, setApproved] = useState(!!approvedProp);
  const [approving, setApproving] = useState(false);
  const [resending, setResending] = useState(false);
  const [sendingPreview, setSendingPreview] = useState(false);
  const [sendingWhatsapp, setSendingWhatsapp] = useState(false);
  const [previewVersion, setPreviewVersion] = useState(0);
  const { auth } = usePage().props;
  const userRoles = auth?.user?.roles ?? [];
  const isAdmin = userRoles.some((role) => ['Administrador', 'Admin','Calidad'].includes(role.name));
  // Siempre generamos el PDF vía backend; si no está aprobado se entrega temporal y no se guarda en BD
  const viewHref = generateUrl;
  const viewLabel = approved ? 'Ver informe' : 'Ver previsualización (PDF)';
  const iframeSrc = `${htmlUrl}${htmlUrl.includes('?') ? '&' : '?'}v=${previewVersion}`;
  const refreshPreview = () => setPreviewVersion((value) => value + 1);

  const handleApprove = async (value) => {
    if (!value) return; // one-way approve only
    try {
      setApproving(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(approveUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      });

      let data = null;
      try {
        data = await res.json();
      } catch (err) {
        // ignore JSON parse errors to surface a generic message
      }

      console.info('approve response', { status: res.status, ok: res.ok, data });

      if (res.ok && data?.status === 'approved') {
        setApproved(true);
        refreshPreview();
        // Disparar reenvío automático una vez aprobado
        // const resendOk = await handleResend();
        // if (!resendOk) {
        //   alert('El informe se aprobó, pero el reenvío falló. Revisa los registros.');
        // }
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
    if (!resendUrl){
        console.error('No se pudo reenviar el informe, no se encuentra la URL');
        return false;
    }
    try {
      setResending(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(resendUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({}),
      });
      let data = null;
      try {
        data = await res.json();
      } catch (err) {
        // ignore JSON parse errors to surface a generic message
      }

      console.info('resend response', { status: res.status, ok: res.ok, data });

      if (!res.ok || data?.status !== 'resent') {
        alert(data?.message || 'No se pudo reenviar el informe');
        return false;
      }

      refreshPreview();
      alert('Informe reenviado correctamente');
      return true;
    } catch (e) {
      console.error('Error reenviando informe', e);
      alert('Error al reenviar el informe');
      return false;
    } finally {
      setResending(false);
    }
  };

  const handleSendPreview = async () => {
    //if (!isAdmin || !sendPreviewUrl) return;
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
        alert(data?.message || 'No se pudo enviar el reporte de previsualización.');
      } else {
        refreshPreview();
        alert('Reporte de previsualización enviado correctamente.');
      }
    } catch (e) {
      alert('Error al enviar el reporte de previsualización.');
    } finally {
      setSendingPreview(false);
    }
  };

  const handleSendPreviewWhatsapp = async () => {
    //if (!isAdmin || !sendPreviewWhatsappUrl) return;
    try {
      setSendingWhatsapp(true);
      const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
      const res = await fetch(sendPreviewWhatsappUrl, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': token,
          'Accept': 'application/json',
        },
      });
      const data = await res.json();
      if (!res.ok || data?.status !== 'sent') {
        alert(data?.message || 'No se pudo enviar el reporte por WhatsApp.');
      } else {
        refreshPreview();
        alert('Reporte enviado por WhatsApp correctamente.');
      }
    } catch (e) {
      alert('Error al enviar el reporte por WhatsApp.');
    } finally {
      setSendingWhatsapp(false);
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
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleSendPreview}
                //   disabled={!isAdmin || !sendPreviewUrl || approving || sendingPreview}
                >
                  <Send className="h-4 w-4 mr-2" /> {sendingPreview ? "Enviando..." : "Enviar previsualización"}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={handleSendPreviewWhatsapp}
                //   disabled={!isAdmin || !sendPreviewWhatsappUrl || approving || sendingWhatsapp}
                >
                  <MessageCircle className="h-4 w-4 mr-2" /> {sendingWhatsapp ? "Enviando..." : "Enviar por WhatsApp"}
                </Button>
                <Button
                  type="button"
                  variant="outline"
                  onClick={refreshPreview}
                  disabled={approving || resending || sendingPreview || sendingWhatsapp}
                >
                  <RefreshCw className="h-4 w-4 mr-2" /> Actualizar vista
                </Button>
              </div>
              <span className="text-sm">Aprobar reporte para descarga</span>
              <Switch checked={approved} disabled={approving || approved} onCheckedChange={handleApprove} />
              {approving && (
                <span className="text-xs text-gray-500 animate-pulse">Generando reporte...</span>
              )}
            </div>
            <div className="flex items-center gap-2">
              <a href={ viewHref } target="_blank" rel="noopener noreferrer">
                <Button variant="outline">
                  <FileText className="h-4 w-4 mr-2" /> {approving ? 'Generando...' : viewLabel}
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
            <iframe key={iframeSrc} title="reporte" src={iframeSrc} className="w-full h-full border-0" />
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
