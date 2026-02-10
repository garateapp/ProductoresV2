import React, { useEffect, useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';

const emptyPredio = {
  nombre_predio: '',
  comuna: '',
  predio: '',
  forma_dominio: '',
  rol_avaluo: '',
  fojas: '',
  numero: '',
  ano: '',
  cbr: '',
  ciudad: '',
  comuna_cbr: '',
};

const emptyProduccion = {
  especie_id: '',
  variedad_id: '',
  kilos_totales: '',
};

const sanitizeRows = (rows) =>
  (rows || []).filter((row) => Object.values(row).some((value) => String(value || '').trim() !== ''));

export default function ProspectosProductoresCreate({ especies = [], services = [] }) {
  const page = usePage();
  const { auth, flash } = page.props;
  const initialRut = useMemo(() => {
    if (typeof window === 'undefined') {
      return '';
    }
    const params = new URL(window.location.href).searchParams;
    return params.get('rut') || '';
  }, []);
  const { data, setData, post, processing, errors, transform } = useForm({
    razon_social: '',
    rut: initialRut,
    ggn: '',
    tipo_empresa: '',
    giro: '',
    service_id: '',
    direccion_comercial: '',
    comuna_comercial: '',
    fono: '',
    fax_comercial: '',
    direccion_predio: '',
    comuna_predio: '',
    email: '',
    fax_contacto: '',
    nombre_rep_legal: '',
    rut_rep_legal: '',
    direccion_rep_legal: '',
    banco: '',
    nombre_titular: '',
    cuenta_corriente: '',
    moneda: '',
    sucursal: '',
    constitucion_fecha_escritura: '',
    notario_publico: '',
    predios: [{ ...emptyPredio }],
    produccion: [{ ...emptyProduccion }],
  });
  const [variedadesOptions, setVariedadesOptions] = useState([[]]);
  const [rutLookup, setRutLookup] = useState({ loading: false, error: '', message: '' });

  const normalizeRut = (value) => String(value || '').toUpperCase().replace(/[^0-9K]/g, '');

  useEffect(() => {
    if (data.produccion.length === variedadesOptions.length) return;
    setVariedadesOptions((prev) => {
      if (data.produccion.length > prev.length) {
        return [...prev, ...Array(data.produccion.length - prev.length).fill([])];
      }
      return prev.slice(0, data.produccion.length);
    });
  }, [data.produccion.length, variedadesOptions.length]);

  useEffect(() => {
    const normalized = normalizeRut(data.rut);
    if (!normalized) {
      setRutLookup({ loading: false, error: '', message: '' });
      return;
    }

    const timeout = setTimeout(async () => {
      setRutLookup({ loading: true, error: '', message: '' });
      try {
        const response = await fetch(route('prospectos-productores.lookup-rut', normalized));
        if (!response.ok) {
          throw new Error('Error al buscar el RUT.');
        }
        const payload = await response.json();
        if (!payload.found) {
          setRutLookup({ loading: false, error: '', message: payload.message || 'Sin resultados.' });
          return;
        }

        const incoming = payload.data || {};
        const applyIfEmpty = (key, value) => {
          if (!value) return;
          if (String(data[key] || '').trim() !== '') return;
          setData(key, value);
        };

        applyIfEmpty('razon_social', incoming.razon_social);
        applyIfEmpty('direccion_predio', incoming.direccion_predio);
        applyIfEmpty('direccion_comercial', incoming.direccion_comercial);
        applyIfEmpty('comuna_predio', incoming.comuna_predio);
        applyIfEmpty('comuna_comercial', incoming.comuna_comercial);

        setRutLookup({ loading: false, error: '', message: 'Datos cargados desde SQLSRV.' });
      } catch (error) {
        setRutLookup({ loading: false, error: 'No fue posible consultar SQLSRV.', message: '' });
      }
    }, 600);

    return () => clearTimeout(timeout);
  }, [data.rut]);

  const submit = (event) => {
    event.preventDefault();
    transform((current) => ({
      ...current,
      service_id: current.service_id ? Number(current.service_id) : null,
      predios: sanitizeRows(current.predios),
      produccion: sanitizeRows(current.produccion).map((row) => ({
        ...row,
        especie_id: row.especie_id || null,
        variedad_id: row.variedad_id || null,
      })),
    }));
    post(route('prospectos-productores.store'));
  };

  const updateArray = (key, index, field, value) => {
    const updated = data[key].map((item, idx) => (idx === index ? { ...item, [field]: value } : item));
    setData(key, updated);
  };

  const addRow = (key, template) => {
    setData(key, [...data[key], { ...template }]);
    if (key === 'produccion') {
      setVariedadesOptions((prev) => [...prev, []]);
    }
  };

  const removeRow = (key, index) => {
    if (data[key].length === 1) return;
    setData(
      key,
      data[key].filter((_, idx) => idx !== index)
    );
    if (key === 'produccion') {
      setVariedadesOptions((prev) => prev.filter((_, idx) => idx !== index));
    }
  };

  const handleEspecieChange = async (index, especieId) => {
    const updated = data.produccion.map((item, idx) =>
      idx === index
        ? {
            ...item,
            especie_id: especieId,
            variedad_id: '',
          }
        : item
    );
    setData('produccion', updated);

    if (!especieId) {
      setVariedadesOptions((prev) => prev.map((list, idx) => (idx === index ? [] : list)));
      return;
    }

    try {
      const response = await fetch(route('api.variedades-by-especie', especieId));
      if (!response.ok) {
        throw new Error('No fue posible cargar variedades');
      }
      const variedades = await response.json();
      setVariedadesOptions((prev) => prev.map((list, idx) => (idx === index ? variedades : list)));
    } catch (error) {
      setVariedadesOptions((prev) => prev.map((list, idx) => (idx === index ? [] : list)));
    }
  };

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Prospecto de Productor</h2>}
    >
      <Head title="Prospecto de Productor" />

      <div className="py-8">
        <div className="max-w-7xl mx-auto space-y-6 px-4 sm:px-6 lg:px-8">
          {flash?.success && (
            <div className="rounded border border-green-200 bg-green-50 px-4 py-2 text-sm text-green-700">
              {flash.success}
            </div>
          )}

          <Card>
            <CardHeader>
              <CardTitle>Identificacion del Productor</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="md:col-span-2">
                    <Label htmlFor="razon_social">Razon Social</Label>
                    <Input
                      id="razon_social"
                      value={data.razon_social}
                      onChange={(event) => setData('razon_social', event.target.value)}
                    />
                    {errors.razon_social && <div className="text-sm text-red-600">{errors.razon_social}</div>}
                  </div>
                  <div>
                    <Label htmlFor="rut">RUT</Label>
                    <Input id="rut" value={data.rut} onChange={(event) => setData('rut', event.target.value)} />
                    {errors.rut && <div className="text-sm text-red-600">{errors.rut}</div>}
                    {rutLookup.loading && <div className="text-xs text-gray-500 mt-1">Buscando en SQLSRV...</div>}
                    {!rutLookup.loading && rutLookup.message && <div className="text-xs text-green-700 mt-1">{rutLookup.message}</div>}
                    {!rutLookup.loading && rutLookup.error && <div className="text-xs text-red-600 mt-1">{rutLookup.error}</div>}
                  </div>
                  <div>
                    <Label htmlFor="ggn">GGN</Label>
                    <Input id="ggn" value={data.ggn} onChange={(event) => setData('ggn', event.target.value)} />
                    {errors.ggn && <div className="text-sm text-red-600">{errors.ggn}</div>}
                  </div>
                  <div>
                    <Label htmlFor="tipo_empresa">Tipo de empresa</Label>
                    <Select
                      value={data.tipo_empresa || 'default'}
                      onValueChange={(value) => setData('tipo_empresa', value === 'default' ? '' : value)}
                    >
                      <SelectTrigger id="tipo_empresa">
                        <SelectValue placeholder="Seleccione" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="default">Seleccione</SelectItem>
                        <SelectItem value="SPA">SPA</SelectItem>
                        <SelectItem value="Sociedad Responsabilidad Ltda.">Sociedad Responsabilidad Ltda.</SelectItem>
                        <SelectItem value="Sociedad de Hecho">Sociedad de Hecho</SelectItem>
                        <SelectItem value="Persona Natural">Persona Natural</SelectItem>
                      </SelectContent>
                    </Select>
                    {errors.tipo_empresa && <div className="text-sm text-red-600">{errors.tipo_empresa}</div>}
                  </div>
                  <div>
                    <Label htmlFor="giro">Giro</Label>
                    <Input id="giro" value={data.giro} onChange={(event) => setData('giro', event.target.value)} />
                    {errors.giro && <div className="text-sm text-red-600">{errors.giro}</div>}
                  </div>
                  <div>
                    <Label htmlFor="service_id">Servicio</Label>
                    <Select
                      value={data.service_id ? String(data.service_id) : 'default'}
                      onValueChange={(value) => setData('service_id', value === 'default' ? '' : value)}
                    >
                      <SelectTrigger id="service_id">
                        <SelectValue placeholder="Seleccione servicio" />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="default">Seleccione servicio</SelectItem>
                        {services.map((service) => (
                          <SelectItem key={service.id} value={String(service.id)}>
                            {service.name}
                          </SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                    {errors.service_id && <div className="text-sm text-red-600">{errors.service_id}</div>}
                  </div>
                  <div>
                    <Label htmlFor="direccion_comercial">Direccion Comercial</Label>
                    <Input
                      id="direccion_comercial"
                      value={data.direccion_comercial}
                      onChange={(event) => setData('direccion_comercial', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="comuna_comercial">Comuna</Label>
                    <Input
                      id="comuna_comercial"
                      value={data.comuna_comercial}
                      onChange={(event) => setData('comuna_comercial', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="fono">Fono</Label>
                    <Input id="fono" value={data.fono} onChange={(event) => setData('fono', event.target.value)} />
                  </div>
                  <div>
                    <Label htmlFor="fax_comercial">Fax</Label>
                    <Input
                      id="fax_comercial"
                      value={data.fax_comercial}
                      onChange={(event) => setData('fax_comercial', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="direccion_predio">Direccion (Predio)</Label>
                    <Input
                      id="direccion_predio"
                      value={data.direccion_predio}
                      onChange={(event) => setData('direccion_predio', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="comuna_predio">Comuna</Label>
                    <Input
                      id="comuna_predio"
                      value={data.comuna_predio}
                      onChange={(event) => setData('comuna_predio', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="email">E-mail</Label>
                    <Input
                      id="email"
                      type="email"
                      value={data.email}
                      onChange={(event) => setData('email', event.target.value)}
                    />
                    {errors.email && <div className="text-sm text-red-600">{errors.email}</div>}
                  </div>
                  <div>
                    <Label htmlFor="fax_contacto">Fax (contacto)</Label>
                    <Input
                      id="fax_contacto"
                      value={data.fax_contacto}
                      onChange={(event) => setData('fax_contacto', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="nombre_rep_legal">Nombre Rep. Legal</Label>
                    <Input
                      id="nombre_rep_legal"
                      value={data.nombre_rep_legal}
                      onChange={(event) => setData('nombre_rep_legal', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="rut_rep_legal">RUT Rep. Legal</Label>
                    <Input
                      id="rut_rep_legal"
                      value={data.rut_rep_legal}
                      onChange={(event) => setData('rut_rep_legal', event.target.value)}
                    />
                  </div>
                  <div className="md:col-span-2">
                    <Label htmlFor="direccion_rep_legal">Direccion Rep. Legal</Label>
                    <Input
                      id="direccion_rep_legal"
                      value={data.direccion_rep_legal}
                      onChange={(event) => setData('direccion_rep_legal', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="banco">Banco</Label>
                    <Input id="banco" value={data.banco} onChange={(event) => setData('banco', event.target.value)} />
                  </div>
                  <div>
                    <Label htmlFor="nombre_titular">Nombre Titular</Label>
                    <Input
                      id="nombre_titular"
                      value={data.nombre_titular}
                      onChange={(event) => setData('nombre_titular', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="cuenta_corriente">Cta. Cte. N / Moneda</Label>
                    <Input
                      id="cuenta_corriente"
                      value={data.cuenta_corriente}
                      onChange={(event) => setData('cuenta_corriente', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="moneda">Moneda</Label>
                    <Input
                      id="moneda"
                      value={data.moneda}
                      onChange={(event) => setData('moneda', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="sucursal">Sucursal</Label>
                    <Input
                      id="sucursal"
                      value={data.sucursal}
                      onChange={(event) => setData('sucursal', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="constitucion_fecha_escritura">Constitucion (fecha)</Label>
                    <Input
                      id="constitucion_fecha_escritura"
                      type="date"
                      value={data.constitucion_fecha_escritura}
                      onChange={(event) => setData('constitucion_fecha_escritura', event.target.value)}
                    />
                  </div>
                  <div>
                    <Label htmlFor="notario_publico">Notario Publico</Label>
                    <Input
                      id="notario_publico"
                      value={data.notario_publico}
                      onChange={(event) => setData('notario_publico', event.target.value)}
                    />
                  </div>
                </div>

                <Card>
                  <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>Informacion Legal del Predio</CardTitle>
                    <Button type="button" variant="outline" onClick={() => addRow('predios', emptyPredio)}>
                      Agregar predio
                    </Button>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {data.predios.map((predio, index) => (
                      <div key={`predio-${index}`} className="rounded border border-gray-200 p-4 space-y-3">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                          <div className="md:col-span-2">
                            <Label>Nombre Predio (CBR)</Label>
                            <Input
                              value={predio.nombre_predio}
                              onChange={(event) => updateArray('predios', index, 'nombre_predio', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Comuna</Label>
                            <Input
                              value={predio.comuna}
                              onChange={(event) => updateArray('predios', index, 'comuna', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Predio</Label>
                            <Input
                              value={predio.predio}
                              onChange={(event) => updateArray('predios', index, 'predio', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Forma dominio</Label>
                            <Input
                              value={predio.forma_dominio}
                              onChange={(event) => updateArray('predios', index, 'forma_dominio', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Rol Avaluo</Label>
                            <Input
                              value={predio.rol_avaluo}
                              onChange={(event) => updateArray('predios', index, 'rol_avaluo', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Fojas</Label>
                            <Input
                              value={predio.fojas}
                              onChange={(event) => updateArray('predios', index, 'fojas', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Numero</Label>
                            <Input
                              value={predio.numero}
                              onChange={(event) => updateArray('predios', index, 'numero', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Ano</Label>
                            <Input
                              value={predio.ano}
                              onChange={(event) => updateArray('predios', index, 'ano', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>CBR</Label>
                            <Input
                              value={predio.cbr}
                              onChange={(event) => updateArray('predios', index, 'cbr', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Ciudad</Label>
                            <Input
                              value={predio.ciudad}
                              onChange={(event) => updateArray('predios', index, 'ciudad', event.target.value)}
                            />
                          </div>
                          <div>
                            <Label>Comuna CBR</Label>
                            <Input
                              value={predio.comuna_cbr}
                              onChange={(event) => updateArray('predios', index, 'comuna_cbr', event.target.value)}
                            />
                          </div>
                        </div>
                        <div className="flex justify-end">
                          <Button type="button" variant="ghost" onClick={() => removeRow('predios', index)}>
                            Quitar
                          </Button>
                        </div>
                      </div>
                    ))}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="flex flex-row items-center justify-between">
                    <CardTitle>Produccion Contratada</CardTitle>
                    <Button type="button" variant="outline" onClick={() => addRow('produccion', emptyProduccion)}>
                      Agregar linea
                    </Button>
                  </CardHeader>
                  <CardContent className="space-y-4">
                    {data.produccion.map((fila, index) => (
                      <div key={`produccion-${index}`} className="rounded border border-gray-200 p-4 space-y-3">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                          <div>
                            <Label>Especie</Label>
                            <Select
                              value={fila.especie_id ? String(fila.especie_id) : 'default'}
                              onValueChange={(value) => handleEspecieChange(index, value === 'default' ? '' : value)}
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="Seleccione especie" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="default">Seleccione especie</SelectItem>
                                {especies.map((especie) => (
                                  <SelectItem key={especie.id} value={String(especie.id)}>
                                    {especie.name}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </div>
                          <div>
                            <Label>Variedad</Label>
                            <Select
                              value={fila.variedad_id ? String(fila.variedad_id) : 'default'}
                              onValueChange={(value) =>
                                updateArray('produccion', index, 'variedad_id', value === 'default' ? '' : value)
                              }
                              disabled={!fila.especie_id}
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="Seleccione variedad" />
                              </SelectTrigger>
                              <SelectContent>
                                <SelectItem value="default">Seleccione variedad</SelectItem>
                                {(variedadesOptions[index] || []).map((variedad) => (
                                  <SelectItem key={variedad.id} value={String(variedad.id)}>
                                    {variedad.name}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </div>
                          <div>
                            <Label>Kilos totales</Label>
                            <Input
                              value={fila.kilos_totales}
                              onChange={(event) => updateArray('produccion', index, 'kilos_totales', event.target.value)}
                            />
                          </div>
                        </div>
                        <div className="flex justify-end">
                          <Button type="button" variant="ghost" onClick={() => removeRow('produccion', index)}>
                            Quitar
                          </Button>
                        </div>
                      </div>
                    ))}
                  </CardContent>
                </Card>

                <div className="flex justify-end">
                  <Button type="submit" disabled={processing}>
                    Guardar prospecto
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
