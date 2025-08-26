import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function AssignRoles({ user, roles, userRoles }) {
  const { data, setData, post, errors } = useForm({
    roles: userRoles || [],
  });

  function handleCheckboxChange(roleId) {
    setData('roles', (prevRoles) =>
      prevRoles.includes(roleId)
        ? prevRoles.filter((id) => id !== roleId)
        : [...prevRoles, roleId]
    );
  }

  function submit(e) {
    e.preventDefault();
    post(route('users.syncRoles', user.id));
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader>
          <CardTitle>Assign Roles to {user.name}</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              {roles.map((role) => (
                <div key={role.id} className="flex items-center space-x-2">
                  <input
                    type="checkbox"
                    id={`role-${role.id}`}
                    checked={data.roles.includes(role.id)}
                    onChange={() => handleCheckboxChange(role.id)}
                  />
                  <Label htmlFor={`role-${role.id}`}>
                    {role.name}
                  </Label>
                </div>
              ))}
            </div>
            {errors.roles && <div className="text-red-500 text-sm">{errors.roles}</div>}
            <Button type="submit">Assign Roles</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

AssignRoles.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Assign Roles</h2>} />;