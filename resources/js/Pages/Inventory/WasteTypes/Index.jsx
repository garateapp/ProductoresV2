import { useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Plus, Pencil } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
  DialogFooter,
} from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

export default function WasteTypesIndex({ types }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editingType, setEditingType] = useState(null)
  
  const { data, setData, post, put, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    activo: true,
    permite_devolucion: false,
  })

  const openDialog = (type = null) => {
    if (type) {
      setEditingType(type)
      setData({ 
        codigo: type.codigo, 
        nombre: type.nombre, 
        activo: type.activo,
        permite_devolucion: !!type.permite_devolucion 
      })
    } else {
      setEditingType(null)
      reset()
    }
    setIsOpen(true)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    if (editingType) {
      put(route('inventory.waste-types.update', editingType.id), {
        onSuccess: () => {
          setIsOpen(false)
          toast.success('Tipo actualizado correctamente')
        }
      })
    } else {
      post(route('inventory.waste-types.store'), {
        onSuccess: () => {
          setIsOpen(false)
          toast.success('Tipo creado correctamente')
        }
      })
    }
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Tipos de Merma</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Tipo
          </Button>
        </div>
      }
    >
      <Head title="Tipos de Merma" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-4xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Código</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Devolución</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {types.map((type) => (
                    <TableRow key={type.id}>
                      <TableCell className="font-mono">{type.codigo}</TableCell>
                      <TableCell>{type.nombre}</TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${type.permite_devolucion ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}`}>
                          {type.permite_devolucion ? 'Sí' : 'No'}
                        </span>
                      </TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${type.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {type.activo ? 'Sí' : 'No'}
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(type)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editingType ? 'Editar Tipo de Merma' : 'Nuevo Tipo de Merma'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="codigo">Código</Label>
              <Input
                id="codigo"
                value={data.codigo}
                onChange={(e) => setData('codigo', e.target.value)}
                disabled={!!editingType}
                required
              />
              {errors.codigo && <p className="text-red-500 text-xs mt-1">{errors.codigo}</p>}
            </div>
            <div>
              <Label htmlFor="nombre">Nombre</Label>
              <Input
                id="nombre"
                value={data.nombre}
                onChange={(e) => setData('nombre', e.target.value)}
                required
              />
              {errors.nombre && <p className="text-red-500 text-xs mt-1">{errors.nombre}</p>}
            </div>
            <div className="flex items-center space-x-2">
              <Switch
                id="permite_devolucion"
                checked={data.permite_devolucion}
                onCheckedChange={(checked) => setData('permite_devolucion', checked)}
              />
              <Label htmlFor="permite_devolucion">Permite devolución a Bodega Central</Label>
            </div>
            {editingType && (
              <div className="flex items-center space-x-2">
                <Switch
                  id="activo"
                  checked={data.activo}
                  onCheckedChange={(checked) => setData('activo', checked)}
                />
                <Label htmlFor="activo">Activo</Label>
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>Guardar</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
