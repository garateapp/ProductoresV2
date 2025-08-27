import React, { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function AssignRoles({ user, roles, userRoles }) {
  const initialRoles = Array.isArray(userRoles) ? userRoles.map(id => Number(id)) : [];
  const [selectedRoles, setSelectedRoles] = useState(initialRoles);

  console.log('Initial selectedRoles:', selectedRoles);
  console.log('typeof selectedRoles:', typeof selectedRoles);
  console.log('Array.isArray(selectedRoles):', Array.isArray(selectedRoles));

  function handleCheckboxChange(roleId) {
    const id = Number(roleId);
    console.log('Before change - selectedRoles:', selectedRoles);
    setSelectedRoles(prev => {
      console.log('prev:', prev);
      if (prev.includes(id)) {
        return prev.filter(r => r !== id);
      } else {
        return [...prev, id];
      }
    });
  }

  function submit(e) {
    e.preventDefault();
    router.post(route('users.syncRoles', user.id), {
      roles: selectedRoles,
    });
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
                    checked={selectedRoles.includes(Number(role.id))}
                    onChange={() => handleCheckboxChange(role.id)}
                  />
                  <Label htmlFor={`role-${role.id}`}>{role.name}</Label>
                </div>
              ))}
            </div>
            <Button type="submit">Assign Roles</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

AssignRoles.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Assign Roles</h2>} />;
