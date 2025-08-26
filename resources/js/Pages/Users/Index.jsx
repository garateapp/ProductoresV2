import React from 'react';
import { Link, useForm, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

const Pagination = ({ links }) => (
    <div className="flex justify-center mt-4">
        {
            links.map((link, key) => (
                link.url === null ?
                    (<div
                        key={key}
                        className="mb-1 mr-1 px-4 py-3 text-gray-400 text-sm leading-4 border rounded"
                    >{link.label}</div>) :
                    (<Link
                        key={key}
                        className="mb-1 mr-1 px-4 py-3 focus:text-indigo-500 text-sm leading-4 hover:bg-white border focus:border-indigo-500 rounded"
                        href={link.url}
                    >{link.label}</Link>)
            ))
        }
    </div>
);

export default function Index({ users, filters }) {
  const { delete: destroy } = useForm();
  const [search, setSearch] = React.useState(filters.search || '');

  function handleDelete(e, user) {
    e.preventDefault();
    if (confirm('Are you sure you want to delete this user?')) {
      destroy(route('users.destroy', user.id));
    }
  }

  const handleSearch = (e) => {
      e.preventDefault();
      router.get(route('users.index'), { search }, { preserveState: true });
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
            <form onSubmit={handleSearch} className="flex items-center mb-4">
                <Input
                    type="text"
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Search..."
                    className="mr-2"
                />
                <Button type="submit">Search</Button>
            </form>
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Tipo de Usuario</TableHead>
                <TableHead>CSG</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {users.data.map(user => (
                <TableRow key={user.id}>
                  <TableCell>{user.name}</TableCell>
                  <TableCell>{user.email}</TableCell>
                  <TableCell>{user.user_type}</TableCell>
                  <TableCell>{user.csg}</TableCell>
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
          <Pagination links={users.links} />
        </CardContent>
      </Card>
    </div>
  );
}

Index.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Users</h2>} />;
