import { useForm, Head } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import Checkbox from '@/Components/Checkbox'
import { Label } from '@/Components/ui/label'

export default function LocationUsers({ location, assignedUsers, allUsers }) {
  const { data, setData, post, processing } = useForm({
    user_ids: assignedUsers.map(u => u.id),
  })

  const handleToggleUser = (userId) => {
    const currentIds = [...data.user_ids]
    if (currentIds.includes(userId)) {
      setData('user_ids', currentIds.filter(id => id !== userId))
    } else {
      setData('user_ids', [...currentIds, userId])
    }
  }

  const submit = (e) => {
    e.preventDefault()
    post(route('inventory.locations.users.sync', location.id))
  }

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Ubicación: {location.nombre} · Encargados</h2>}
    >
      <Head title={`Encargados - ${location.nombre}`} />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Asociar usuarios que recibirán solicitudes</CardTitle>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  {allUsers.map((user) => (
                    <div key={user.id} className="flex items-center space-x-2 border p-3 rounded hover:bg-gray-50">
                      <Checkbox
                        id={`user-${user.id}`}
                        checked={data.user_ids.includes(user.id)}
                        onChange={() => handleToggleUser(user.id)}
                      />
                      <Label htmlFor={`user-${user.id}`} className="cursor-pointer flex-1 py-1">{user.name}</Label>
                    </div>
                  ))}
                </div>

                <div className="flex items-center justify-end">
                  <Button disabled={processing}>
                    Guardar Cambios
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
