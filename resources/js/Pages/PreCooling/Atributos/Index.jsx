import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { Textarea } from '@/Components/ui/textarea'
import { Plus, Pencil } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { toast, Toaster } from 'sonner'

const TIPO_DATOS = [
  { value: 'texto', label: 'Texto' },
  { value: 'numero', label: 'Número' },
  { value: 'fecha', label: 'Fecha' },
  { value: 'select', label: 'Selección' },
]

export default function AtributosIndex({ atributos }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  const { data, setData, post, put, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    tipo_dato: 'texto',
    opciones: '',
    requerido: false,
    activo: true,
  })

  const openDialog = (atributo = null) => {
    if (atributo) {
      setEditing(atributo)
      setData({
        codigo: atributo.codigo,
        nombre: atributo.nombre,
        tipo_dato: atributo.tipo_dato,
        opciones: (atributo.opciones || []).join(', '),
        requerido: !!atributo.requerido,
        activo: !!atributo.activo,
      })
    } else {
      setEditing(null)
      reset()
    }
    setIsOpen(true)
  }

  const buildPayload = () => ({
    ...data,
    opciones:
      data.tipo_dato === 'select'
        ? data.opciones
        : null,
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const options = {
      onSuccess: () => {
        setIsOpen(false)
        toast.success(editing ? 'Atributo actualizado correctamente' : 'Atributo creado correctamente')
      },
    }
    if (editing) {
      put(route('prefrio.atributos.update', editing.id), buildPayload(), options)
    } else {
      post(route('prefrio.atributos.store'), buildPayload(), options)
    }
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Atributos</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Atributo
          </Button>
        </div>
      }
    >
      <Head title="Prefrío · Atributos" />
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
                    <TableHead>Tipo</TableHead>
                    <TableHead>Opciones</TableHead>
                    <TableHead>Requerido</TableHead>
                    <TableHead>Activo</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {atributos.map((atributo) => (
                    <TableRow key={atributo.id}>
                      <TableCell className="font-mono">{atributo.codigo}</TableCell>
                      <TableCell>{atributo.nombre}</TableCell>
                      <TableCell>{atributo.tipo_dato}</TableCell>
                      <TableCell className="max-w-xs truncate">
                        {(atributo.opciones || []).join(', ') || '—'}
                      </TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${atributo.requerido ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800'}`}>
                          {atributo.requerido ? 'Sí' : 'No'}
                        </span>
                      </TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs ${atributo.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {atributo.activo ? 'Sí' : 'No'}
                        </span>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(atributo)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {atributos.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center text-gray-500 py-6">
                        Sin atributos registrados.
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
            <DialogTitle>{editing ? 'Editar Atributo' : 'Nuevo Atributo'}</DialogTitle>
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
              <Label>Tipo de dato</Label>
              <Select value={data.tipo_dato} onValueChange={(value) => setData('tipo_dato', value)}>
                <SelectTrigger className="w-full">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TIPO_DATOS.map((tipo) => (
                    <SelectItem key={tipo.value} value={tipo.value}>
                      {tipo.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.tipo_dato && <p className="text-red-500 text-xs mt-1">{errors.tipo_dato}</p>}
            </div>
            {data.tipo_dato === 'select' && (
              <div>
                <Label htmlFor="opciones">Opciones (separadas por coma)</Label>
                <Textarea
                  id="opciones"
                  value={data.opciones}
                  onChange={(e) => setData('opciones', e.target.value)}
                  placeholder="Ej: NORMAL, URGENTE, RETRASO"
                />
                {errors.opciones && <p className="text-red-500 text-xs mt-1">{errors.opciones}</p>}
              </div>
            )}
            <div className="flex items-center space-x-2">
              <Switch
                id="requerido"
                checked={data.requerido}
                onCheckedChange={(checked) => setData('requerido', checked)}
              />
              <Label htmlFor="requerido">Requerido</Label>
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
