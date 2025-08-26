import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function AssignPermissions({ role, permissions, rolePermissions }) {
  const { data, setData, post, errors } = useForm({
    permissions: rolePermissions || [],
  });

  function handleCheckboxChange(permissionId) {
    setData('permissions', (prevPermissions) =>
      prevPermissions.includes(permissionId)
        ? prevPermissions.filter((id) => id !== permissionId)
        : [...prevPermissions, permissionId]
    );
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
                <div key={permission.id} className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    id={`permission-${permission.id}`}
                    checked={data.permissions.includes(permission.id)}
                    onChange={() => handleCheckboxChange(permission.id)}
                  />
                  <Label htmlFor={`permission-${permission.id}`}>
                    {permission.name}
                  </Label>
                </div>
              ))}
            </div>
            {errors.permissions && <div className="text-red-500 text-sm">{errors.permissions}</div>}
            <Button type="submit">Assign Permissions</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

AssignPermissions.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Assign Permissions</h2>} />;