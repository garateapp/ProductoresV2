import React, { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function AssignPermissions({ role, permissions, rolePermissions }) {
  const initial = Array.isArray(rolePermissions) ? rolePermissions : [];
  const [selected, setSelected] = useState(initial);
  const { data, setData, post, errors, processing } = useForm({
    permissions: initial,
  });

  function togglePermission(permissionId) {
    const next = selected.includes(permissionId)
      ? selected.filter((id) => id !== permissionId)
      : [...selected, permissionId];
    setSelected(next);
    setData('permissions', next);
  }

  function submit(e) {
    e.preventDefault();
    post(route('roles.syncPermissions', role.id));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Assign Permissions to {role.name}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              {permissions.map((permission) => (
                <div
                  key={permission.id}
                  className="flex items-center justify-between rounded-lg border p-3 cursor-pointer select-none"
                  onClick={() => togglePermission(permission.id)}
                >
                  <Label>{permission.name}</Label>
                  <div
                    role="switch"
                    aria-checked={selected.includes(permission.id)}
                    className={`relative inline-flex h-[24px] w-[44px] shrink-0 items-center rounded-full border-2 border-transparent transition-colors ${
                      selected.includes(permission.id)
                        ? 'bg-primary'
                        : 'bg-input'
                    }`}
                  >
                    <span
                      className={`pointer-events-none block h-5 w-5 rounded-full bg-background shadow-lg ring-0 transition-transform ${
                        selected.includes(permission.id)
                          ? 'translate-x-5'
                          : 'translate-x-0'
                      }`}
                    />
                  </div>
                </div>
              ))}
            </div>
            {errors.permissions && <div className="text-red-500 text-sm">{errors.permissions}</div>}
            <Button type="submit" disabled={processing}>Assign Permissions</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

AssignPermissions.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Assign Permissions</h2>} />;