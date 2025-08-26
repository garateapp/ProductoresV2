import React from 'react';
import { useForm, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

export default function Edit({ user }) {
  const { data, setData, put, errors, post } = useForm({
    name: user.name || '',
    email: user.email || '',
    password: '',
    password_confirmation: '',
  });

  const { flash } = usePage().props;

  function submit(e) {
    e.preventDefault();
    put(route('users.update', user.id));
  }

  function changePassword(e) {
      e.preventDefault();
      post(route('users.reset-password', user.id), {
          preserveScroll: true,
          onSuccess: () => {
              setData('password', '');
              setData('password_confirmation', '');
          }
      });
  }

  return (
    <div className="container mx-auto py-10">
        {flash && flash.success && (
            <div className="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>{flash.success}</p>
            </div>
        )}
      <Card>
        <CardHeader>
          <CardTitle>Edit User</CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={submit} className="space-y-4">
            <div>
              <Label htmlFor="name">Name</Label>
              <Input
                id="name"
                type="text"
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
              />
              {errors.name && <div className="text-red-500 text-sm">{errors.name}</div>}
            </div>
            <div>
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                type="email"
                value={data.email}
                onChange={(e) => setData('email', e.target.value)}
              />
              {errors.email && <div className="text-red-500 text-sm">{errors.email}</div>}
            </div>
            <Button type="submit">Update User</Button>
          </form>

          <hr className="my-6" />

          <h2 className="text-xl font-bold mb-4">Change Password</h2>
          <form onSubmit={changePassword} className="space-y-4">
            <div>
                <Label htmlFor="password">New Password</Label>
                <Input
                    id="password"
                    type="password"
                    value={data.password}
                    onChange={(e) => setData('password', e.target.value)}
                />
                {errors.password && <div className="text-red-500 text-sm">{errors.password}</div>}
            </div>
            <div>
                <Label htmlFor="password_confirmation">Confirm New Password</Label>
                <Input
                    id="password_confirmation"
                    type="password"
                    value={data.password_confirmation}
                    onChange={(e) => setData('password_confirmation', e.target.value)}
                />
            </div>
            <Button type="submit">Change Password</Button>
          </form>
        </CardContent>
      </Card>
    </div>
  );
}

Edit.layout = page => <AuthenticatedLayout children={page} header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Edit User</h2>} />;

