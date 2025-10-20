import React from "react";
import { Link, usePage, router } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/Components/ui/table";
import { Edit, Trash2 } from "lucide-react";

const Index = ({ valores }) => {
  const { flash } = usePage().props;

  const handleDelete = (id) => {
    if (confirm("¿Estás seguro de que quieres eliminar este valor?")) {
      router.delete(route("valores.destroy", id));
    }
  };

  return (
    <>
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">
            Valores para Control de Calidad
          </CardTitle>
          <Link href={route("valores.create")}>
            <Button>Crear Valor</Button>
          </Link>
        </CardHeader>
        <CardContent>
          {flash?.success && (
            <div className="mb-4 rounded-lg bg-green-100 p-4 text-sm text-green-700">
              {flash.success}
            </div>
          )}
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Nombre</TableHead>
                <TableHead>Parámetro</TableHead>
                <TableHead>Especie</TableHead>
                <TableHead>Variedad</TableHead>
                <TableHead>Informe</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {valores.data.map((valor) => (
                <TableRow key={valor.id}>
                  <TableCell>{valor.name}</TableCell>
                  <TableCell>{valor.parametro?.name ?? "N/A"}</TableCell>
                  <TableCell>{valor.especie ?? "N/A"}</TableCell>
                  <TableCell>{valor.variedad ?? "N/A"}</TableCell>
                  <TableCell>{valor.informe ?? "N/A"}</TableCell>
                  <TableCell className="text-right space-x-2">
                    <Link href={route("valores.edit", valor.id)}>
                      <Button variant="outline" size="icon">
                        <Edit className="h-4 w-4" />
                      </Button>
                    </Link>
                    <Button
                      variant="destructive"
                      size="icon"
                      onClick={() => handleDelete(valor.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>

          <div className="mt-4 flex items-center justify-between">
            <p className="text-sm text-gray-700">
              Mostrando {valores.from} a {valores.to} de {valores.total} resultados
            </p>
            <nav className="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
              {valores.links.map((link, index) => (
                <Link
                  key={`${link.url}-${index}`}
                  href={link.url || "#"}
                  className={`relative inline-flex items-center border px-4 py-2 text-sm font-medium ${
                    link.active
                      ? "z-10 border-indigo-500 bg-indigo-50 text-indigo-600"
                      : "border-gray-300 bg-white text-gray-500 hover:bg-gray-50"
                  } ${!link.url ? "cursor-not-allowed opacity-50" : ""}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </nav>
          </div>
        </CardContent>
      </Card>
    </>
  );
};

Index.layout = (page) => (
  <AuthenticatedLayout
    user={page.props.auth.user}
    header={
      <h2 className="text-xl font-semibold leading-tight text-gray-800">
        Mantenedor de Valores
      </h2>
    }
  >
    {page}
  </AuthenticatedLayout>
);

export default Index;

