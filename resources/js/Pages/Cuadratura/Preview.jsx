import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { FileText } from 'lucide-react';

export default function Preview({ procesoId, numero, htmlUrl, downloadUrl, reviewUrl }) {
  return (
    <div className="container mx-auto py-6 space-y-4">
      <Head title={`Previsualización Proceso #${numero || procesoId}`} />
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-xl font-semibold">
            Previsualización Informe Proceso #{numero || procesoId}
          </CardTitle>
          <div className="flex items-center gap-2">
            <Link href={reviewUrl}>
              <Button variant="secondary">Volver a revisión</Button>
            </Link>
            <a href={downloadUrl} target="_blank" rel="noopener noreferrer">
              <Button variant="outline">
                <FileText className="mr-2 h-4 w-4" /> Descargar informe PDF
              </Button>
            </a>
          </div>
        </CardHeader>
        <CardContent>
          <div className="h-[80vh] w-full overflow-hidden rounded border bg-gray-50">
            <iframe title="previsualizacion-proceso" src={htmlUrl} className="h-full w-full border-0" />
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

Preview.layout = (page) => (
  <AuthenticatedLayout
    user={page.props.auth.user}
    children={page}
    header={<h2 className="text-xl font-semibold leading-tight text-gray-800">Previsualización Informe</h2>}
  />
);
