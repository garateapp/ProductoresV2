import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { toast, Toaster } from 'sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Plus, Pencil, Loader2 } from 'lucide-react'

export default function MaquinasIndex({ maquinas }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    activo: true,
  })

  const openDialog = (maquina = null) => {
    if (maquina) {
      setEditing(maquina)
      setData({
        codigo: maquina.codigo,
        nombre: maquina.nombre,
        activo: !!maquina.activo,
      })
    } else {
      setEditing(null)
      reset()
    }
    setIsOpen(true)
  }

  const handleSubmit = (e) => {
    e.preventDefault()
    const options = {
      onSuccess: () => {
        setIsOpen(false)
        toast.success(editing ? 'Máquina actualizada correctamente' : 'Máquina creada correctamente')
      },
    }
    if (editing) {
      patch(route('detenciones.maquinas.update', editing.id), options)
    } else {
      post(route('detenciones.maquinas.store'), options)
    }
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Detenciones · Máquinas</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nueva Máquina
          </Button>
        </div>
      }
    >
      <Head title="Detenciones · Máquinas" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Código</TableHead>
                    <TableHead>Nombre</TableHead>
                    <TableHead>Turnos registrados</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {maquinas.map((maquina) => (
                    <TableRow key={maquina.id}>
                      <TableCell className="font-mono">{maquina.codigo}</TableCell>
                      <TableCell>{maquina.nombre}</TableCell>
                      <TableCell>{maquina.turnos}</TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs w-fit ${maquina.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {maquina.activo ? 'Activa' : 'Inactiva'}
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(maquina)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {maquinas.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-gray-500 py-6">
                        Sin máquinas registradas.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={isOpen} onOpenChange={setIsOpen}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{editing ? `Editar Máquina · ${editing.codigo}` : 'Nueva Máquina'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="codigo">Código</Label>
              <Input
                id="codigo"
                value={data.codigo}
                onChange={(e) => setData('codigo', e.target.value)}
                disabled={!!editing}
                placeholder="Ej: MAQ-01"
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
                placeholder="Ej: Armadora de cajas línea 1"
                required
              />
              {errors.nombre && <p className="text-red-500 text-xs mt-1">{errors.nombre}</p>}
            </div>
            {editing && (
              <div className="flex items-center space-x-2">
                <Switch id="activo" checked={data.activo} onCheckedChange={(checked) => setData('activo', checked)} />
                <Label htmlFor="activo">Activa</Label>
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setIsOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>
                {processing && <Loader2 className="w-4 h-4 animate-spin" />}
                Guardar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}