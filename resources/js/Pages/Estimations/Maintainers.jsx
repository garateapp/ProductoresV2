import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Toaster, toast } from 'sonner';

const firstError = (errors) => {
  const value = Object.values(errors || {})[0];
  if (!value) return 'Ocurrió un error.';
  return Array.isArray(value) ? value.join(', ') : value;
};

function SeasonWeeks({ season }) {
  const [editingWeekId, setEditingWeekId] = useState(null);
  const weekForm = useForm({
    week_number: '',
    start_date: '',
    end_date: '',
    is_active: true,
  });
  const weekEditForm = useForm({
    week_number: '',
    start_date: '',
    end_date: '',
    is_active: true,
  });

  const startEdit = (week) => {
    setEditingWeekId(week.id);
    weekEditForm.setData({
      week_number: week.week_number,
      start_date: week.start_date || '',
      end_date: week.end_date || '',
      is_active: !!week.is_active,
    });
  };

  const cancelEdit = () => {
    setEditingWeekId(null);
    weekEditForm.reset();
  };

  return (
    <div className="mt-3">
      <form
        onSubmit={(e) => {
          e.preventDefault();
          weekForm.post(route('estimations.weeks.store', { estimation_season: season.id }), {
            preserveScroll: true,
            onSuccess: () => {
              toast.success('Semana creada.');
              weekForm.reset();
            },
            onError: (errors) => {
              toast.error(firstError(errors));
            },
          });
        }}
        className="flex flex-wrap items-end gap-2 mb-3"
      >
        <div>
          <label className="block text-xs">Semana</label>
          <Input type="number" min="1" max="53" value={weekForm.data.week_number} onChange={(e) => weekForm.setData('week_number', e.target.value)} />
        </div>
        <div>
          <label className="block text-xs">Inicio</label>
          <Input type="date" value={weekForm.data.start_date} onChange={(e) => weekForm.setData('start_date', e.target.value)} />
        </div>
        <div>
          <label className="block text-xs">Fin</label>
          <Input type="date" value={weekForm.data.end_date} onChange={(e) => weekForm.setData('end_date', e.target.value)} />
        </div>
        <label className="flex items-center gap-2 text-xs">
          <input
            type="checkbox"
            checked={!!weekForm.data.is_active}
            onChange={(e) => weekForm.setData('is_active', e.target.checked)}
          />
          Activa
        </label>
        <Button type="submit" size="sm" disabled={weekForm.processing}>Agregar semana</Button>
      </form>

      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Semana</TableHead>
            <TableHead>Inicio</TableHead>
            <TableHead>Fin</TableHead>
            <TableHead>Activa</TableHead>
            <TableHead></TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {season.weeks?.map((week) => (
            <TableRow key={week.id}>
              {editingWeekId === week.id ? (
                <>
                  <TableCell>
                    <Input type="number" min="1" max="53" value={weekEditForm.data.week_number} onChange={(e) => weekEditForm.setData('week_number', e.target.value)} />
                  </TableCell>
                  <TableCell>
                    <Input type="date" value={weekEditForm.data.start_date} onChange={(e) => weekEditForm.setData('start_date', e.target.value)} />
                  </TableCell>
                  <TableCell>
                    <Input type="date" value={weekEditForm.data.end_date} onChange={(e) => weekEditForm.setData('end_date', e.target.value)} />
                  </TableCell>
                  <TableCell>
                    <input
                      type="checkbox"
                      checked={!!weekEditForm.data.is_active}
                      onChange={(e) => weekEditForm.setData('is_active', e.target.checked)}
                    />
                  </TableCell>
                  <TableCell className="flex gap-2">
                    <Button
                      size="sm"
                      onClick={() => weekEditForm.patch(route('estimations.weeks.update', { estimation_week: week.id }), {
                        preserveScroll: true,
                        onSuccess: () => {
                          toast.success('Semana actualizada.');
                          cancelEdit();
                        },
                        onError: (errors) => {
                          toast.error(firstError(errors));
                        },
                      })}
                      disabled={weekEditForm.processing}
                    >
                      Guardar
                    </Button>
                    <Button size="sm" variant="secondary" onClick={cancelEdit}>Cancelar</Button>
                  </TableCell>
                </>
              ) : (
                <>
                  <TableCell>{week.week_number}</TableCell>
                  <TableCell>{week.start_date || '-'}</TableCell>
                  <TableCell>{week.end_date || '-'}</TableCell>
                  <TableCell>{week.is_active ? 'Sí' : 'No'}</TableCell>
                  <TableCell className="flex gap-2">
                    <Button size="sm" variant="secondary" onClick={() => startEdit(week)}>Editar</Button>
                    <Button
                      size="sm"
                      variant="destructive"
                      onClick={() => weekEditForm.delete(route('estimations.weeks.destroy', { estimation_week: week.id }), {
                        preserveScroll: true,
                        onSuccess: () => {
                          toast.success('Semana eliminada.');
                          cancelEdit();
                        },
                        onError: (errors) => {
                          toast.error(firstError(errors));
                        },
                      })}
                    >
                      Eliminar
                    </Button>
                  </TableCell>
                </>
              )}
            </TableRow>
          ))}
        </TableBody>
      </Table>
    </div>
  );
}

export default function Maintainers({ auth, seasons, statuses }) {
  const [editingSeasonId, setEditingSeasonId] = useState(null);
  const [editingStatusId, setEditingStatusId] = useState(null);

  const seasonForm = useForm({
    code: '',
    name: '',
    start_date: '',
    end_date: '',
    is_active: true,
  });

  const seasonEditForm = useForm({
    code: '',
    name: '',
    start_date: '',
    end_date: '',
    is_active: true,
  });

  const statusForm = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
  });

  const statusEditForm = useForm({
    name: '',
    sort_order: 0,
    is_active: true,
  });

  const seasonRows = useMemo(() => seasons || [], [seasons]);
  const statusRows = useMemo(() => statuses || [], [statuses]);

  const startSeasonEdit = (season) => {
    setEditingSeasonId(season.id);
    seasonEditForm.setData({
      code: season.code,
      name: season.name || '',
      start_date: season.start_date || '',
      end_date: season.end_date || '',
      is_active: !!season.is_active,
    });
  };

  const cancelSeasonEdit = () => {
    setEditingSeasonId(null);
    seasonEditForm.reset();
  };

  const startStatusEdit = (status) => {
    setEditingStatusId(status.id);
    statusEditForm.setData({
      name: status.name,
      sort_order: status.sort_order || 0,
      is_active: !!status.is_active,
    });
  };

  const cancelStatusEdit = () => {
    setEditingStatusId(null);
    statusEditForm.reset();
  };

  return (
    <AuthenticatedLayout user={auth?.user} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Mantenedores</h2>}>
      <Head title="Mantenedores de Estimaciones" />
      <Toaster richColors position="top-right" />
      <div className="container mx-auto py-10 space-y-6">
        <Card>
          <CardHeader>
            <CardTitle>Temporadas</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                seasonForm.post(route('estimations.seasons.store'), {
                  preserveScroll: true,
                  onSuccess: () => {
                    toast.success('Temporada creada.');
                    seasonForm.reset();
                  },
                  onError: (errors) => {
                    toast.error(firstError(errors));
                  },
                });
              }}
              className="flex flex-wrap items-end gap-2 mb-4"
            >
              <div>
                <label className="block text-xs">Código</label>
                <Input value={seasonForm.data.code} onChange={(e) => seasonForm.setData('code', e.target.value)} placeholder="T25-26" />
              </div>
              <div>
                <label className="block text-xs">Nombre</label>
                <Input value={seasonForm.data.name} onChange={(e) => seasonForm.setData('name', e.target.value)} placeholder="Temporada 25-26" />
              </div>
              <div>
                <label className="block text-xs">Inicio</label>
                <Input type="date" value={seasonForm.data.start_date} onChange={(e) => seasonForm.setData('start_date', e.target.value)} />
              </div>
              <div>
                <label className="block text-xs">Fin</label>
                <Input type="date" value={seasonForm.data.end_date} onChange={(e) => seasonForm.setData('end_date', e.target.value)} />
              </div>
              <label className="flex items-center gap-2 text-xs">
                <input
                  type="checkbox"
                  checked={!!seasonForm.data.is_active}
                  onChange={(e) => seasonForm.setData('is_active', e.target.checked)}
                />
                Activa
              </label>
              <Button type="submit" disabled={seasonForm.processing}>Crear</Button>
            </form>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Código</TableHead>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Inicio</TableHead>
                  <TableHead>Fin</TableHead>
                  <TableHead>Activa</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {seasonRows.map((season) => (
                  <React.Fragment key={season.id}>
                    <TableRow>
                      {editingSeasonId === season.id ? (
                        <>
                          <TableCell>
                            <Input value={seasonEditForm.data.code} onChange={(e) => seasonEditForm.setData('code', e.target.value)} />
                          </TableCell>
                          <TableCell>
                            <Input value={seasonEditForm.data.name} onChange={(e) => seasonEditForm.setData('name', e.target.value)} />
                          </TableCell>
                          <TableCell>
                            <Input type="date" value={seasonEditForm.data.start_date} onChange={(e) => seasonEditForm.setData('start_date', e.target.value)} />
                          </TableCell>
                          <TableCell>
                            <Input type="date" value={seasonEditForm.data.end_date} onChange={(e) => seasonEditForm.setData('end_date', e.target.value)} />
                          </TableCell>
                          <TableCell>
                            <input
                              type="checkbox"
                              checked={!!seasonEditForm.data.is_active}
                              onChange={(e) => seasonEditForm.setData('is_active', e.target.checked)}
                            />
                          </TableCell>
                          <TableCell className="flex gap-2">
                            <Button
                              size="sm"
                              onClick={() => seasonEditForm.patch(route('estimations.seasons.update', { estimation_season: season.id }), {
                                preserveScroll: true,
                                onSuccess: () => {
                                  toast.success('Temporada actualizada.');
                                  cancelSeasonEdit();
                                },
                                onError: (errors) => {
                                  toast.error(firstError(errors));
                                },
                              })}
                              disabled={seasonEditForm.processing}
                            >
                              Guardar
                            </Button>
                            <Button size="sm" variant="secondary" onClick={cancelSeasonEdit}>Cancelar</Button>
                          </TableCell>
                        </>
                      ) : (
                        <>
                          <TableCell>{season.code}</TableCell>
                          <TableCell>{season.name || '-'}</TableCell>
                          <TableCell>{season.start_date || '-'}</TableCell>
                          <TableCell>{season.end_date || '-'}</TableCell>
                          <TableCell>{season.is_active ? 'Sí' : 'No'}</TableCell>
                          <TableCell className="flex gap-2">
                            <Button size="sm" variant="secondary" onClick={() => startSeasonEdit(season)}>Editar</Button>
                            <Button
                              size="sm"
                              variant="destructive"
                              onClick={() => seasonEditForm.delete(route('estimations.seasons.destroy', { estimation_season: season.id }), {
                                preserveScroll: true,
                                onSuccess: () => {
                                  toast.success('Temporada eliminada.');
                                  cancelSeasonEdit();
                                },
                                onError: (errors) => {
                                  toast.error(firstError(errors));
                                },
                              })}
                            >
                              Eliminar
                            </Button>
                          </TableCell>
                        </>
                      )}
                    </TableRow>
                    <TableRow>
                      <TableCell colSpan={6}>
                        <SeasonWeeks season={season} />
                      </TableCell>
                    </TableRow>
                  </React.Fragment>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>Status</CardTitle>
          </CardHeader>
          <CardContent>
            <form
              onSubmit={(e) => {
                e.preventDefault();
                statusForm.post(route('estimations.statuses.store'), {
                  preserveScroll: true,
                  onSuccess: () => {
                    toast.success('Status creado.');
                    statusForm.reset();
                  },
                  onError: (errors) => {
                    toast.error(firstError(errors));
                  },
                });
              }}
              className="flex flex-wrap items-end gap-2 mb-4"
            >
              <div>
                <label className="block text-xs">Nombre</label>
                <Input value={statusForm.data.name} onChange={(e) => statusForm.setData('name', e.target.value)} placeholder="Activo" />
              </div>
              <div>
                <label className="block text-xs">Orden</label>
                <Input type="number" min="0" value={statusForm.data.sort_order} onChange={(e) => statusForm.setData('sort_order', e.target.value)} />
              </div>
              <label className="flex items-center gap-2 text-xs">
                <input
                  type="checkbox"
                  checked={!!statusForm.data.is_active}
                  onChange={(e) => statusForm.setData('is_active', e.target.checked)}
                />
                Activo
              </label>
              <Button type="submit" disabled={statusForm.processing}>Crear</Button>
            </form>

            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Nombre</TableHead>
                  <TableHead>Orden</TableHead>
                  <TableHead>Activo</TableHead>
                  <TableHead></TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {statusRows.map((status) => (
                  <TableRow key={status.id}>
                    {editingStatusId === status.id ? (
                      <>
                        <TableCell>
                          <Input value={statusEditForm.data.name} onChange={(e) => statusEditForm.setData('name', e.target.value)} />
                        </TableCell>
                        <TableCell>
                          <Input type="number" min="0" value={statusEditForm.data.sort_order} onChange={(e) => statusEditForm.setData('sort_order', e.target.value)} />
                        </TableCell>
                        <TableCell>
                          <input
                            type="checkbox"
                            checked={!!statusEditForm.data.is_active}
                            onChange={(e) => statusEditForm.setData('is_active', e.target.checked)}
                          />
                        </TableCell>
                        <TableCell className="flex gap-2">
                          <Button
                            size="sm"
                            onClick={() => statusEditForm.patch(route('estimations.statuses.update', { estimation_status: status.id }), {
                              preserveScroll: true,
                              onSuccess: () => {
                                toast.success('Status actualizado.');
                                cancelStatusEdit();
                              },
                              onError: (errors) => {
                                toast.error(firstError(errors));
                              },
                            })}
                            disabled={statusEditForm.processing}
                          >
                            Guardar
                          </Button>
                          <Button size="sm" variant="secondary" onClick={cancelStatusEdit}>Cancelar</Button>
                        </TableCell>
                      </>
                    ) : (
                      <>
                        <TableCell>{status.name}</TableCell>
                        <TableCell>{status.sort_order}</TableCell>
                        <TableCell>{status.is_active ? 'Sí' : 'No'}</TableCell>
                        <TableCell className="flex gap-2">
                          <Button size="sm" variant="secondary" onClick={() => startStatusEdit(status)}>Editar</Button>
                          <Button
                            size="sm"
                            variant="destructive"
                            onClick={() => statusEditForm.delete(route('estimations.statuses.destroy', { estimation_status: status.id }), {
                              preserveScroll: true,
                              onSuccess: () => {
                                toast.success('Status eliminado.');
                                cancelStatusEdit();
                              },
                              onError: (errors) => {
                                toast.error(firstError(errors));
                              },
                            })}
                          >
                            Eliminar
                          </Button>
                        </TableCell>
                      </>
                    )}
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </AuthenticatedLayout>
  );
}
