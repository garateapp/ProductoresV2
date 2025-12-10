import React, { useMemo, useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';

export default function LoginActivityIndex({ auth, users, totalLogins }) {
  const [search, setSearch] = useState('');

  const filteredUsers = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return users?.data || [];

    return (users?.data || []).filter((user) => {
      const haystack = `${user.name || ''} ${user.email || ''}`.toLowerCase();
      return haystack.includes(term);
    });
  }, [users?.data, search]);

  const summary = useMemo(() => {
    const totalUsers = filteredUsers.length;
    const withActivity = filteredUsers.filter((u) => (u.login_events_count || 0) > 0).length;
    const lastLogin = filteredUsers
      .map((u) => u.last_login_at)
      .filter(Boolean)
      .sort((a, b) => new Date(b) - new Date(a))[0];

    return {
      totalUsers,
      withActivity,
      lastLoginText: lastLogin ? new Date(lastLogin).toLocaleString('es-CL') : 'Sin registros',
    };
  }, [filteredUsers]);

  return (
    <AuthenticatedLayout
      user={auth.user}
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Uso del Portal</h2>}
    >
      <Head title="Uso del Portal" />

      <div className="py-8">
        <div className="max-w-6xl mx-auto sm:px-6 lg:px-8">
          <div className="rounded-2xl bg-gradient-to-r from-emerald-800 to-emerald-600 text-emerald-50 p-6 mb-4 shadow-lg">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p className="text-sm opacity-85">Panel de actividad</p>
                <h1 className="text-2xl font-semibold">Eventos de login</h1>
                <p className="text-xs text-emerald-100 mt-1">
                  Consulta la actividad reciente y filtra r&aacute;pidamente por usuario o correo.
                </p>
              </div>
              <div className="w-full sm:w-64">
                <label className="text-xs uppercase tracking-wide text-emerald-100">Buscar</label>
                <input
                  type="search"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Nombre, email..."
                  className="mt-1 w-full rounded-lg border border-emerald-300/40 bg-emerald-900/30 px-3 py-2 text-sm text-white placeholder:text-emerald-200 focus:border-white focus:outline-none"
                />
                <p className="text-[11px] text-emerald-100/90 mt-1">
                  Filtra los usuarios de esta p&aacute;gina.
                </p>
              </div>
            </div>

            <div className="mt-4 grid grid-cols-1 sm:grid-cols-3 gap-3">
              <div className="rounded-lg bg-white/10 border border-white/15 px-4 py-3">
                <p className="text-xs uppercase tracking-wide text-emerald-100">Inicios de sesi&oacute;n</p>
                <p className="text-lg font-semibold">{totalLogins}</p>
              </div>
              <div className="rounded-lg bg-white/10 border border-white/15 px-4 py-3">
                <p className="text-xs uppercase tracking-wide text-emerald-100">Usuarios listados</p>
                <p className="text-lg font-semibold">{summary.totalUsers}</p>
              </div>
              <div className="rounded-lg bg-white/10 border border-white/15 px-4 py-3">
                <p className="text-xs uppercase tracking-wide text-emerald-100">Último acceso</p>
                <p className="text-lg font-semibold">{summary.lastLoginText}</p>
              </div>
            </div>
          </div>

          <Card>
            <CardHeader className="flex items-center justify-between">
              <CardTitle>Usuarios logueados</CardTitle>
              <div className="text-sm text-gray-600">
                Total de inicios de sesi&oacute;n registrados: <span className="font-semibold">{totalLogins}</span>
              </div>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Usuario</TableHead>
                    <TableHead>Email</TableHead>
                    <TableHead>Inicios de sesi&oacute;n</TableHead>
                    <TableHead>Último acceso</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {filteredUsers.length ? (
                    filteredUsers.map((user) => (
                      <TableRow key={user.id}>
                        <TableCell>{user.name}</TableCell>
                        <TableCell>{user.email}</TableCell>
                        <TableCell>{user.login_events_count || 0}</TableCell>
                        <TableCell>
                          {user.last_login_at ? new Date(user.last_login_at).toLocaleString('es-CL') : 'N/A'}
                        </TableCell>
                      </TableRow>
                    ))
                  ) : (
                    <TableRow>
                      <TableCell colSpan={4} className="text-center text-gray-500">
                        No hay registros que coincidan con la b&uacute;squeda.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>

              <div className="flex justify-end mt-4">
                {(users?.links || []).map((link, idx) =>
                  link.url ? (
                    <Link
                      key={idx}
                      href={link.url}
                      className={`px-3 py-1 text-sm border rounded mx-1 ${
                        link.active
                          ? 'border-indigo-500 bg-indigo-50 text-indigo-600'
                          : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'
                      }`}
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  ) : (
                    <span
                      key={idx}
                      className="px-3 py-1 text-sm border border-gray-200 rounded text-gray-400 mx-1"
                      dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                  )
                )}
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  );
}
