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

export default function Index({ users }) {
  const { delete: destroy } = useForm();

  function handleDelete(e, user) {
    e.preventDefault();
    if (confirm('Are you sure you want to delete this user?')) {
      destroy(route('users.destroy', user.id));
    }
  }

  return (
    <div className="container mx-auto py-10">
      <Card>
        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
          <CardTitle className="text-2xl font-bold">Users</CardTitle>
          <Link href={route('users.create')}>
            <Button>Create User</Button>
          </Link>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.map(user => (
                <TableRow key={user.id}>
                  <TableCell>{user.name}</TableCell>
                  <TableCell>{user.email}</TableCell>
                  <TableCell>
                    <Link href={route('users.edit', user.id)} className="mr-2">
                      <Button variant="outline">Edit</Button>
                    </Link>
                    <Link href={route('users.assignRoles', user.id)} className="mr-2">
                      <Button variant="outline">Assign Roles</Button>
                    </Link>
                    <Button variant="destructive" onClick={(e) => handleDelete(e, user)}>Delete</Button>
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

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Users</h2>} />;
