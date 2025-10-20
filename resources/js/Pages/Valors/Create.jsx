import React from "react";
import { Link, useForm } from "@inertiajs/react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

const Create = ({ parametros, especies }) => {
  const OPTIONAL_ESPECIE_VALUE = '__NONE__';
  const { data, setData, post, processing, errors } = useForm({
    name: "",
    parametro_id: "",
    especie: "",
    variedad: "",
    informe: "",
  });

  const handleSubmit = (e) => {
    e.preventDefault();
    post(route("valores.store"));
  };

  return (
    <Card>
      <CardHeader>
        <CardTitle>Crear Valor</CardTitle>
      </CardHeader>
      <CardContent>
        <form onSubmit={handleSubmit} className="space-y-4">
          <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
              <Label htmlFor="name">Nombre</Label>
              <Input
                id="name"
                value={data.name}
                onChange={(e) => setData("name", e.target.value)}
              />
              {errors.name && (
                <p className="mt-1 text-sm text-red-600">{errors.name}</p>
              )}
            </div>
            <div>
              <Label htmlFor="parametro_id">Parámetro</Label>
              <Select
                value={data.parametro_id}
                onValueChange={(value) => setData("parametro_id", value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Seleccionar parámetro" />
                </SelectTrigger>
                <SelectContent>
                  {parametros.map((parametro) => (
                    <SelectItem
                      key={parametro.id}
                      value={String(parametro.id)}
                    >
                      {parametro.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.parametro_id && (
                <p className="mt-1 text-sm text-red-600">
                  {errors.parametro_id}
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="especie">Especie</Label>
              <Select
                value={data.especie ? data.especie : OPTIONAL_ESPECIE_VALUE}
                onValueChange={(value) =>
                  setData("especie", value === OPTIONAL_ESPECIE_VALUE ? "" : value)
                }
              >
                <SelectTrigger>
                  <SelectValue placeholder="Seleccionar especie (opcional)" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={OPTIONAL_ESPECIE_VALUE}>Sin especie</SelectItem>
                  {especies.map((especie) => (
                    <SelectItem key={especie.id} value={especie.name}>
                      {especie.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.especie && (
                <p className="mt-1 text-sm text-red-600">{errors.especie}</p>
              )}
            </div>
            <div>
              <Label htmlFor="variedad">Variedad</Label>
              <Input
                id="variedad"
                value={data.variedad ?? ""}
                onChange={(e) => setData("variedad", e.target.value)}
                placeholder="Opcional"
              />
              {errors.variedad && (
                <p className="mt-1 text-sm text-red-600">{errors.variedad}</p>
              )}
            </div>
            <div className="md:col-span-2">
              <Label htmlFor="informe">Informe</Label>
              <Input
                id="informe"
                value={data.informe ?? ""}
                onChange={(e) => setData("informe", e.target.value)}
                placeholder="Opcional"
              />
              {errors.informe && (
                <p className="mt-1 text-sm text-red-600">{errors.informe}</p>
              )}
            </div>
          </div>

          <div className="flex items-center justify-between">
            <Link href={route("valores.index")}>
              <Button type="button" variant="outline">
                Cancelar
              </Button>
            </Link>
            <Button type="submit" disabled={processing}>
              Guardar
            </Button>
          </div>
        </form>
      </CardContent>
    </Card>
  );
};

Create.layout = (page) => (
  <AuthenticatedLayout
    user={page.props.auth.user}
    header={
      <h2 className="text-xl font-semibold leading-tight text-gray-800">
        Crear Valor
      </h2>
    }
  >
    {page}
  </AuthenticatedLayout>
);

export default Create;
