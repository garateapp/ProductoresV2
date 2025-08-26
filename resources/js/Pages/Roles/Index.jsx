import React from 'react';
import { Link, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Index({ roles }) {
  const { delete: destroy } = useForm();

  function handleDelete(e, role) {
    e.preventDefault();
    if (confirm('Are you sure you want to delete this role?')) {
      destroy(route('roles.destroy', role.id));
    }
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Roles</CardTitle>
          <Link href={route('roles.create')}>
            <Button>Create Role</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {roles.map(role => (
                <TableRow key={role.id}>
                  <TableCell>{role.name}</TableCell>
                  <TableCell>
                    <Link href={route('roles.edit', role.id)} className="mr-2">
                      <Button variant="outline">Edit</Button>
                    </Link>
                    <Link href={route('roles.assignPermissions', role.id)} className="mr-2">
                      <Button variant="outline">Assign Permissions</Button>
                    </Link>
                    <Button variant="destructive" onClick={(e) => handleDelete(e, role)}>Delete</Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Roles</h2>} />;
