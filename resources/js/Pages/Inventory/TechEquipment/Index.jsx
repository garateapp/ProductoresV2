import { useState } from 'react'
import { Head, Link, router, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Badge } from '@/Components/ui/badge'
import { Button } from '@/Components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Textarea } from '@/Components/ui/textarea'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { ArrowLeft, Pencil, Plus, Save } from 'lucide-react'

const emptyForm = {
  marca: '',
  fecha: '',
  numero_serie: '',
  descripcion: '',
  activo: true,
}

const formatFecha = (value) => {
  if (!value) return '-'
  const parsed = new Date(String(value).length === 10 ? `${value}T00:00:00` : value)
  return isNaN(parsed) ? value : parsed.toLocaleDateString('es-CL')
}

export default function TechEquipmentIndex({ equipment }) {
  const [editing, setEditing] = useState(null)
  const [modalOpen, setModalOpen] = useState(false)
  const { data, setData, post, patch, processing, errors, reset } = useForm(emptyForm)

  const startCreate = () => {
    setEditing(null)
    reset()
    setData(emptyForm)
    setModalOpen(true)
  }

  const startEdit = (item) => {
    setEditing(item)
    setData({
      marca: item.marca,
      fecha: item.fecha || '',
      numero_serie: item.numero_serie,
      descripcion: item.descripcion || '',
      activo: Boolean(item.activo),
    })
    setModalOpen(true)
  }

  const submit = (event) => {
    event.preventDefault()
    if (editing?.id) {
      patch(route('inventory.tech-equipment.update', editing.id), {
        preserveScroll: true,
        onSuccess: () => setModalOpen(false),
      })
      return
    }
    post(route('inventory.tech-equipment.store'), {
      preserveScroll: true,
      onSuccess: () => {
        setModalOpen(false)
        reset()
        setData(emptyForm)
      },
    })
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center justify-between gap-4">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Equipos Tecnológicos</h2>
          <div className="flex gap-2">
            <Link href={route('inventory.tech-equipment-deliveries.index')}>
              <Button variant="outline">
                <ArrowLeft className="w-4 h-4 mr-2" />
                Volver a actas
              </Button>
            </Link>
            <Button onClick={startCreate}>
              <Plus className="w-4 h-4 mr-2" />
              Nuevo equipo
            </Button>
          </div>
        </div>
      }
    >
      <Head title="Equipos Tecnológicos" />

      <div className="py-10">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardHeader>
              <CardTitle>Mantenedor de Equipos</CardTitle>
            </CardHeader>
            <CardContent>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Marca</TableHead>
                    <TableHead>Fecha</TableHead>
                    <TableHead>N° de Serie</TableHead>
                    <TableHead>Características</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {equipment.data.map((item) => (
                    <TableRow key={item.id}>
                      <TableCell className="font-medium">{item.marca}</TableCell>
                      <TableCell>{formatFecha(item.fecha)}</TableCell>
                      <TableCell className="font-mono">{item.numero_serie}</TableCell>
                      <TableCell className="max-w-sm">
                        <p className="truncate" title={item.descripcion}>{item.descripcion || '-'}</p>
                      </TableCell>
                      <TableCell>
                        <Badge variant={item.activo ? 'default' : 'outline'}>{item.activo ? 'Activo' : 'Inactivo'}</Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" title="Editar" onClick={() => startEdit(item)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {equipment.data.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-slate-400 py-8">
                        No hay equipos registrados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
              {equipment.links && (
                <div className="mt-4 flex flex-wrap gap-1">
                  {equipment.links.map((link, index) =>
                    link.url ? (
                      <Button key={index} asChild variant={link.active ? 'default' : 'outline'} size="sm" onClick={() => router.visit(link.url)}>
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                      </Button>
                    ) : (
                      <span key={index} className="px-1 text-slate-400">{link.label}</span>
                    ),
                  )}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      <Dialog open={modalOpen} onOpenChange={setModalOpen}>
        <DialogContent className="sm:max-w-lg">
          <form onSubmit={submit} className="space-y-5">
            <DialogHeader>
              <DialogTitle>{editing?.id ? 'Editar equipo' : 'Nuevo equipo'}</DialogTitle>
              <DialogDescription>
                Los equipos no se toman del inventario, se registran aquí para su entrega.
              </DialogDescription>
            </DialogHeader>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="marca">Marca</Label>
                <Input
                  id="marca"
                  value={data.marca}
                  onChange={(event) => setData('marca', event.target.value)}
                  placeholder="Ej: Lenovo, Dell, HP"
                />
                {errors.marca && <p className="text-sm text-red-600">{errors.marca}</p>}
              </div>
              <div className="space-y-2">
                <Label htmlFor="fecha">Fecha</Label>
                <Input
                  id="fecha"
                  type="date"
                  value={data.fecha}
                  onChange={(event) => setData('fecha', event.target.value)}
                />
                {errors.fecha && <p className="text-sm text-red-600">{errors.fecha}</p>}
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="numero_serie">N° de Serie</Label>
                <Input
                  id="numero_serie"
                  value={data.numero_serie}
                  onChange={(event) => setData('numero_serie', event.target.value)}
                  placeholder="Número de serie del equipo"
                />
                {errors.numero_serie && <p className="text-sm text-red-600">{errors.numero_serie}</p>}
              </div>
              <div className="space-y-2 sm:col-span-2">
                <Label htmlFor="descripcion">Características del equipo</Label>
                <Textarea
                  id="descripcion"
                  value={data.descripcion}
                  onChange={(event) => setData('descripcion', event.target.value)}
                  placeholder="Procesador, RAM, disco, estado, etc."
                />
                {errors.descripcion && <p className="text-sm text-red-600">{errors.descripcion}</p>}
              </div>
              <div className="flex items-center justify-between sm:col-span-2">
                <Label htmlFor="activo">Activo</Label>
                <Switch id="activo" checked={data.activo} onCheckedChange={(value) => setData('activo', Boolean(value))} />
              </div>
            </div>

            <DialogFooter className="gap-2">
              <Button type="button" variant="outline" onClick={() => setModalOpen(false)}>Cancelar</Button>
              <Button type="submit" disabled={processing}>
                <Save className="w-4 h-4 mr-2" />
                {editing?.id ? 'Guardar cambios' : 'Crear equipo'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}
