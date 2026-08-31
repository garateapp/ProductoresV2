import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Plus, Pencil } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

export default function TiposProcesosIndex({ tiposProcesos }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  const { data, setData, post, put, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    tiempo_objetivo_minutos: '',
    activo: true,
  })

  const openDialog = (tipo = null) => {
    if (tipo) {
      setEditing(tipo)
      setData({
        codigo: tipo.codigo,
        nombre: tipo.nombre,
        tiempo_objetivo_minutos: tipo.tiempo_objetivo_minutos ?? '',
        activo: !!tipo.activo,
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
        toast.success(editing ? 'Tipo de proceso actualizado correctamente' : 'Tipo de proceso creado correctamente')
      },
    }
    if (editing) {
      put(route('prefrio.tipos-procesos.update', editing.id), options)
    } else {
      post(route('prefrio.tipos-procesos.store'), options)
    }
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Tipos de Proceso</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Tipo
          </Button>
        </div>
      }
    >
      <Head title="Prefrío · Tipos de Proceso" />
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
                    <TableHead>Tiempo objetivo (min)</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tiposProcesos.map((tipo) => (
                    <TableRow key={tipo.id}>
                      <TableCell className="font-mono">{tipo.codigo}</TableCell>
                      <TableCell>{tipo.nombre}</TableCell>
                      <TableCell>{tipo.tiempo_objetivo_minutos ?? '—'}</TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${tipo.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {tipo.activo ? 'Sí' : 'No'}
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(tipo)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {tiposProcesos.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-gray-500 py-6">
                        Sin tipos de proceso registrados.
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
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Editar Tipo de Proceso' : 'Nuevo Tipo de Proceso'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <Label htmlFor="codigo">Código</Label>
              <Input
                id="codigo"
                value={data.codigo}
                onChange={(e) => setData('codigo', e.target.value)}
                disabled={!!editing}
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
            <div>
              <Label htmlFor="tiempo_objetivo_minutos">Tiempo objetivo (minutos)</Label>
              <Input
                id="tiempo_objetivo_minutos"
                type="number"
                min="0"
                value={data.tiempo_objetivo_minutos}
                onChange={(e) => setData('tiempo_objetivo_minutos', e.target.value === '' ? '' : Number(e.target.value))}
                placeholder="Opcional"
              />
              {errors.tiempo_objetivo_minutos && (
                <p className="text-red-500 text-xs mt-1">{errors.tiempo_objetivo_minutos}</p>
              )}
            </div>
            {editing && (
              <div className="flex items-center space-x-2">
                <Switch id="activo" checked={data.activo} onCheckedChange={(checked) => setData('activo', checked)} />
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
