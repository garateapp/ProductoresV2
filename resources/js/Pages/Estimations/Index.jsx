import React, { useEffect, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import Combobox from '@/Components/ui/combobox';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

const normalizeSpeciesGroup = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (normalized === 'cherries') return 'cherries';
  if (['carozos', 'plums', 'nectarines', 'peaches'].includes(normalized)) return 'carozos';
  return '';
};

const speciesLabel = (value) => {
  const normalized = normalizeSpeciesGroup(value);
  if (normalized === 'cherries') return 'Cherries';
  if (normalized === 'carozos') return 'Carozos';
  return '-';
};

const speciesBadgeClass = (value) => {
  const normalized = normalizeSpeciesGroup(value);
  if (normalized === 'cherries') return 'bg-rose-50 text-rose-700 border-rose-200';
  if (normalized === 'carozos') return 'bg-amber-50 text-amber-700 border-amber-200';
  return 'bg-slate-100 text-slate-600 border-slate-200';
};

const versionStatusLabel = (value) => {
  const normalized = String(value || '').trim().toLowerCase();
  if (normalized === 'active') return 'Activa';
  if (normalized === 'superseded') return 'superseded';
  if (normalized === 'rejected') return 'Rechazada';
  return value || '-';
};

export default function Index({ auth, versions, seasons, statuses, types, filters }) {
  const { import_errors: importErrors, import_feedback: importFeedback, flash } = usePage().props;
  const typeRows = Array.isArray(types) ? types : [];
  const activeTypeRows = typeRows.filter((type) => !!type.is_active);
  const fallbackType = activeTypeRows[0]?.code || '';
  const versionRows = Array.isArray(versions?.data) ? versions.data : (Array.isArray(versions) ? versions : []);
  const paginationLinks = Array.isArray(versions?.links)
    ? versions.links
    : Array.isArray(versions?.meta?.links)
      ? versions.meta.links
      : [];
  const { data, setData, get } = useForm({
    season_id: filters.season_id || '',
    type: filters.type || '',
    species: filters.species || '',
    status: filters.status || '',
  });

  const importForm = useForm({
    file: null,
    season_id: '',
    type: fallbackType,
    species: '',
    period_start_week: '',
    period_end_week: '',
    notes: '',
  });
  const [isPollingImportFeedback, setIsPollingImportFeedback] = useState(false);

  useEffect(() => {
    const timer = setTimeout(() => {
      get(route('estimations.index'), { preserveState: true, replace: true });
    }, 300);
    return () => clearTimeout(timer);
  }, [data.season_id, data.type, data.species, data.status]);

  useEffect(() => {
    if (!isPollingImportFeedback) return undefined;

    let ticks = 0;
    const interval = setInterval(() => {
      ticks += 1;

      router.reload({
        only: ['import_feedback', 'import_errors'],
        preserveState: true,
        preserveScroll: true,
      });

      if (ticks >= 90) {
        setIsPollingImportFeedback(false);
      }
    }, 4000);

    return () => clearInterval(interval);
  }, [isPollingImportFeedback]);

  useEffect(() => {
    const payload = importFeedback ?? importErrors;
    if (!payload) return;

    const list = Array.isArray(payload) ? payload : [payload];
    let hasSuccess = false;

    list.forEach((item) => {
      const label = item?.label ? ` (${item.label})` : '';
      const file = item?.file ? ` [${item.file}]` : '';

      if (item?.status === 'success') {
        hasSuccess = true;
        toast.success(`Importación finalizada${label}${file}: ${item?.message || 'Completada.'}`);
        return;
      }

      toast.error(`Error de importación${label}${file}: ${item?.message || 'Ocurrió un error.'}`);
    });

    setIsPollingImportFeedback(false);

    if (hasSuccess) {
      router.reload({
        only: ['versions'],
        preserveState: true,
        preserveScroll: true,
      });
    }
  }, [importFeedback, importErrors]);

  useEffect(() => {
    if (!flash?.error) return;
    toast.error(flash.error);
  }, [flash?.error]);

  useEffect(() => {
    if (!importForm.data.type && fallbackType) {
      importForm.setData('type', fallbackType);
    }
  }, [fallbackType]);


  const seasonOptions = [{ value: '', label: '(Todas)' }, ...seasons.map(s => ({ value: String(s.id), label: s.code }))];
  const statusOptions = [
    { value: '', label: '(Todos)' },
    ...statuses.map((s) => {
      const statusName = String(s.name || '');
      return { value: statusName, label: versionStatusLabel(statusName) };
    }),
  ];
  const typeOptions = [{ value: '', label: '(Todos)' }, ...typeRows.map((type) => ({ value: type.code, label: type.name }))];
  const speciesOptions = [
    { value: 'cherries', label: 'Cherries' },
    { value: 'carozos', label: 'Carozos' },
  ];
  const filterSpeciesOptions = [{ value: '', label: '(Todas)' }, ...speciesOptions];
  const importTypeOptions = activeTypeRows.map((type) => ({ value: type.code, label: type.name }));
  const typeLabelByCode = new Map(typeRows.map((type) => [type.code, type.name]));

  const selectedSeasonId = importForm.data.season_id || data.season_id;
  const selectedType = importForm.data.type || data.type || fallbackType;
  const [templateSpecies, setTemplateSpecies] = useState('');

  const handleDownload = () => {
    if (!selectedSeasonId || !selectedType || !templateSpecies) return;
    const speciesValue = templateSpecies === 'carozos' ? 'plums' : templateSpecies;
    const params = {
      season_id: selectedSeasonId,
      type: selectedType,
      species: speciesValue,
      period_start_week: importForm.data.period_start_week || undefined,
      period_end_week: importForm.data.period_end_week || undefined,
    };
    window.location.href = route('estimations.template', params);
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Estimaciones</h2>}>
      <Head title="Estimaciones" />
      <Toaster richColors position="top-right" />
      <div className="mx-20 w-full max-w-[1800px] space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <Card className="border-slate-200 shadow-sm">
          <CardHeader className="space-y-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div className="space-y-1">
                <CardTitle className="text-2xl font-bold">Versiones de Estimaciones</CardTitle>
                <p className="text-sm text-slate-600">
                  Historial por temporada, tipo y especie. Puedes descargar cada versión para editarla en Excel y volver a subirla.
                </p>
              </div>
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                <select
                  value={templateSpecies}
                  onChange={(e) => setTemplateSpecies(e.target.value)}
                  className="h-10 rounded-md border border-slate-300 bg-white px-3 text-sm"
                >
                  <option value="">Especie plantilla</option>
                  {speciesOptions.map((species) => (
                    <option key={species.value} value={species.value}>{species.label}</option>
                  ))}
                </select>
                <Button
                  type="button"
                  variant="secondary"
                  onClick={handleDownload}
                  disabled={!selectedSeasonId || !selectedType || !templateSpecies}
                >
                  Descargar plantilla
                </Button>
              </div>
            </div>
          </CardHeader>
          <CardContent className="space-y-6">
            <div className="rounded-xl border border-slate-200 bg-slate-50/50 p-4">
              <div className="mb-3 text-sm font-medium text-slate-700">Filtros de versiones</div>
              <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                <Combobox
                  value={data.season_id}
                  onChange={(val) => setData('season_id', val)}
                  options={seasonOptions}
                  placeholder="Temporada"
                  searchPlaceholder="Buscar temporada..."
                  className="w-full"
                />
                <Combobox
                  value={data.type}
                  onChange={(val) => setData('type', val)}
                  options={typeOptions}
                  placeholder="Tipo"
                  searchPlaceholder="Buscar tipo..."
                  className="w-full"
                />
                <Combobox
                  value={data.species}
                  onChange={(val) => setData('species', val)}
                  options={filterSpeciesOptions}
                  placeholder="Especie"
                  searchPlaceholder="Buscar especie..."
                  className="w-full"
                />
                <Combobox
                  value={data.status}
                  onChange={(val) => setData('status', val)}
                  options={statusOptions}
                  placeholder="Estado"
                  searchPlaceholder="Buscar estado..."
                  className="w-full"
                />
              </div>
            </div>

            <form
              onSubmit={(e) => {
                e.preventDefault();
                importForm.post(route('estimations.upload'), {
                  forceFormData: true,
                  onSuccess: (page) => {
                    const flashError = page?.props?.flash?.error;
                    if (flashError) {
                      return;
                    }

                    toast.success('Importación en cola.');
                    setIsPollingImportFeedback(true);
                    importForm.reset();
                    importForm.setData('type', fallbackType);
                  },
                  onError: (errors) => {
                    toast.error(firstError(errors));
                  },
                });
              }}
              encType="multipart/form-data"
              className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm"
            >
              <div className="mb-3 text-sm font-medium text-slate-700">Subir nueva versión</div>
              <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <div>
                  <label className="mb-1 block text-sm">CSV</label>
                  <input
                    type="file"
                    onChange={(e) => importForm.setData('file', e.target.files[0])}
                    className="w-full rounded-md border border-slate-300 px-2 py-2 text-sm"
                  />
                  {importForm.errors.file && <div className="mt-1 text-sm text-red-600">{importForm.errors.file}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Temporada</label>
                  <select
                    value={importForm.data.season_id}
                    onChange={(e) => importForm.setData('season_id', e.target.value)}
                    className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                  >
                    <option value="">Seleccione</option>
                    {seasons.map((season) => <option key={season.id} value={season.id}>{season.code}</option>)}
                  </select>
                  {importForm.errors.season_id && <div className="mt-1 text-sm text-red-600">{importForm.errors.season_id}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Tipo</label>
                  <select
                    value={importForm.data.type}
                    onChange={(e) => importForm.setData('type', e.target.value)}
                    className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                  >
                    <option value="">Seleccione</option>
                    {importTypeOptions.map((type) => <option key={type.value} value={type.value}>{type.label}</option>)}
                  </select>
                  {importForm.errors.type && <div className="mt-1 text-sm text-red-600">{importForm.errors.type}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Especie</label>
                  <select
                    value={importForm.data.species}
                    onChange={(e) => importForm.setData('species', e.target.value)}
                    className="h-10 w-full rounded-md border border-slate-300 px-3 text-sm"
                  >
                    <option value="">Seleccione</option>
                    {speciesOptions.map((species) => (
                      <option key={species.value} value={species.value}>{species.label}</option>
                    ))}
                  </select>
                  {importForm.errors.species && <div className="mt-1 text-sm text-red-600">{importForm.errors.species}</div>}
                </div>
                <div>
                  <label className="mb-1 block text-sm">Semana inicio</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={importForm.data.period_start_week}
                    onChange={(e) => importForm.setData('period_start_week', e.target.value)}
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm">Semana fin</label>
                  <Input
                    type="number"
                    min="1"
                    max="53"
                    value={importForm.data.period_end_week}
                    onChange={(e) => importForm.setData('period_end_week', e.target.value)}
                  />
                </div>
              </div>
              <div className="mt-4 flex justify-end">
                <Button type="submit" disabled={importForm.processing || !importForm.data.type || !importForm.data.species}>
                  Subir CSV
                </Button>
              </div>
            </form>

            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
              <Table>
                <TableHeader className="bg-slate-50">
                  <TableRow>
                    <TableHead>N° Versión</TableHead>
                    <TableHead>Temporada</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Especie</TableHead>
                    <TableHead>Periodo</TableHead>
                    <TableHead>Origen</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Usuario</TableHead>
                    <TableHead>Filas</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {versionRows.map((version) => (
                    <TableRow key={version.id} className="hover:bg-slate-50/80">
                      <TableCell>{version.id}</TableCell>
                      <TableCell>{version.season?.code || '-'}</TableCell>
                      <TableCell>{typeLabelByCode.get(version.type) || version.type}</TableCell>
                      <TableCell>
                        <span className={`inline-flex rounded-full border px-2 py-0.5 text-xs font-medium ${speciesBadgeClass(version.species)}`}>
                          {speciesLabel(version.species)}
                        </span>
                      </TableCell>
                      <TableCell>{version.period_start_week ? `${version.period_start_week}-${version.period_end_week || ''}` : '-'}</TableCell>
                      <TableCell>{version.source}</TableCell>
                      <TableCell>{versionStatusLabel(version.status)}</TableCell>
                      <TableCell>{version.uploader?.name || '-'}</TableCell>
                      <TableCell>{version.rows_count ?? '-'}</TableCell>
                      <TableCell>{version.created_at || '-'}</TableCell>
                      <TableCell className="text-right">
                        <div className="flex items-center justify-end gap-2">
                          <a
                            href={route('estimations.download', version.id)}
                            className="inline-flex h-8 items-center rounded-md border border-slate-300 px-3 text-xs font-medium text-slate-700 hover:bg-slate-100"
                          >
                            Descargar
                          </a>
                          <Link
                            href={route('estimations.show', version.id)}
                            className="inline-flex h-8 items-center rounded-md bg-indigo-600 px-3 text-xs font-medium text-white hover:bg-indigo-700"
                          >
                            Ver
                          </Link>
                        </div>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>

            <div className="flex flex-wrap items-center gap-2">
              {paginationLinks.map((link, idx) => (
                <Link
                  key={idx}
                  href={link.url || '#'}
                  className={`rounded-md border px-2.5 py-1.5 text-sm ${link.active ? 'border-indigo-600 bg-indigo-50 font-semibold text-indigo-700' : 'border-slate-200 text-slate-600 hover:bg-slate-50'}`}
                  dangerouslySetInnerHTML={{ __html: link.label }}
                />
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
