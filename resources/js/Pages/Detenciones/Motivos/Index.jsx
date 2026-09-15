import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { toast, Toaster } from 'sonner'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/Components/ui/dialog'
import { Plus, Pencil, Loader2, Layers } from 'lucide-react'

export default function MotivosIndex({ tipos }) {
  const [tipoDialog, setTipoDialog] = useState(null) // null | {modo:'crear'} | {modo:'editar', tipo}
  const [causaDialog, setCausaDialog] = useState(null) // null | {tipo} | {tipo, causa}

  const tipoForm = useForm({ codigo: '', nombre: '', activo: true })
  const causaForm = useForm({ codigo: '', nombre: '', activo: true })

  const openTipo = (tipo = null) => {
    if (tipo) {
      tipoForm.setData({ codigo: tipo.codigo, nombre: tipo.nombre, activo: !!tipo.activo })
      setTipoDialog({ modo: 'editar', tipo })
    } else {
      tipoForm.reset()
      setTipoDialog({ modo: 'crear' })
    }
  }

  const openCausa = (tipo, causa = null) => {
    if (causa) {
      causaForm.setData({ codigo: causa.codigo, nombre: causa.nombre, activo: !!causa.activo })
    } else {
      causaForm.reset()
    }
    setCausaDialog({ tipo, causa })
  }

  const submitTipo = (e) => {
    e.preventDefault()
    const isEdit = tipoDialog?.modo === 'editar'
    const options = {
      onSuccess: () => {
        setTipoDialog(null)
        toast.success(isEdit ? 'Tipo actualizado correctamente' : 'Tipo creado correctamente')
      },
    }
    if (isEdit) {
      tipoForm.patch(route('detenciones.motivos.tipos.update', tipoDialog.tipo.id), options)
    } else {
      tipoForm.post(route('detenciones.motivos.tipos.store'), options)
    }
  }

  const submitCausa = (e) => {
    e.preventDefault()
    const tipo = causaDialog.tipo
    const causa = causaDialog.causa
    const options = {
      onSuccess: () => {
        setCausaDialog(null)
        toast.success(causa ? 'Causa actualizada correctamente' : 'Causa creada correctamente')
      },
    }
    if (causa) {
      causaForm.patch(route('detenciones.motivos.causas.update', causa.id), options)
    } else {
      causaForm.post(route('detenciones.motivos.tipos.causas.store', tipo.id), options)
    }
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Detenciones · Motivos</h2>
          <Button onClick={() => openTipo()}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Tipo
          </Button>
        </div>
      }
    >
      <Head title="Detenciones · Motivos" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-5xl mx-auto sm:px-6 lg:px-8">
          <Card>
            <CardContent className="pt-6">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Código</TableHead>
                    <TableHead>Tipo</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Causas</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tipos.map((tipo) => (
                    <TableRow key={tipo.id} className="align-top">
                      <TableCell className="font-mono">{tipo.codigo}</TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2">
                          <Layers className="h-4 w-4 text-gray-400" />
                          <span className="font-medium">{tipo.nombre}</span>
                        </div>
                      </TableCell>
                      <TableCell>
                        <span className={`px-2 py-1 rounded text-xs w-fit ${tipo.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                          {tipo.activo ? 'Activo' : 'Inactivo'}
                        </span>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1.5">
                          {tipo.causas.map((causa) => (
                            <span key={causa.id} className="group inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-1 text-xs text-gray-700">
                              <span className="font-mono text-gray-400">{causa.codigo}</span>
                              {causa.nombre}
                              {!causa.activo && <Badge variant="secondary" className="text-[10px]">inactiva</Badge>}
                              <Button variant="ghost" size="sm" className="h-4 w-4 p-0 text-gray-400 hover:text-gray-800" onClick={() => openCausa(tipo, causa)}>
                                <Pencil className="h-3 w-3" />
                              </Button>
                            </span>
                          ))}
                          <Button variant="ghost" size="sm" className="h-6 text-xs text-gray-500" onClick={() => openCausa(tipo)}>
                            <Plus className="h-3 w-3 mr-1" /> Causa
                          </Button>
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openTipo(tipo)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {tipos.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={5} className="text-center text-gray-500 py-6">
                        Sin tipos de motivo registrados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Tipo dialog */}
      <Dialog open={!!tipoDialog} onOpenChange={() => setTipoDialog(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{tipoDialog?.modo === 'editar' ? `Editar Tipo · ${tipoDialog.tipo.codigo}` : 'Nuevo Tipo'}</DialogTitle>
            <DialogDescription>Primer nivel del motivo de detención.</DialogDescription>
          </DialogHeader>
          <form onSubmit={submitTipo} className="space-y-4">
            <div>
              <Label htmlFor="tipo-codigo">Código</Label>
              <Input id="tipo-codigo" value={tipoForm.data.codigo} onChange={(e) => tipoForm.setData('codigo', e.target.value)} disabled={tipoDialog?.modo === 'editar'} placeholder="Ej: MEC" required />
              {tipoForm.errors.codigo && <p className="text-red-500 text-xs mt-1">{tipoForm.errors.codigo}</p>}
            </div>
            <div>
              <Label htmlFor="tipo-nombre">Nombre</Label>
              <Input id="tipo-nombre" value={tipoForm.data.nombre} onChange={(e) => tipoForm.setData('nombre', e.target.value)} placeholder="Ej: Avería mecánica" required />
              {tipoForm.errors.nombre && <p className="text-red-500 text-xs mt-1">{tipoForm.errors.nombre}</p>}
            </div>
            {tipoDialog?.modo === 'editar' && (
              <div className="flex items-center space-x-2">
                <Switch id="tipo-activo" checked={tipoForm.data.activo} onCheckedChange={(checked) => tipoForm.setData('activo', checked)} />
                <Label htmlFor="tipo-activo">Activo</Label>
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setTipoDialog(null)}>Cancelar</Button>
              <Button type="submit" disabled={tipoForm.processing}>
                {tipoForm.processing && <Loader2 className="w-4 h-4 animate-spin" />}
                Guardar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      {/* Causa dialog */}
      <Dialog open={!!causaDialog} onOpenChange={() => setCausaDialog(null)}>
        <DialogContent className="max-w-md">
          <DialogHeader>
            <DialogTitle>{causaDialog?.causa ? `Editar Causa · ${causaDialog.causa.codigo}` : `Nueva Causa · ${causaDialog?.tipo?.nombre || ''}`}</DialogTitle>
            <DialogDescription>Segundo nivel del motivo de detención.</DialogDescription>
          </DialogHeader>
          <form onSubmit={submitCausa} className="space-y-4">
            <div>
              <Label htmlFor="causa-codigo">Código</Label>
              <Input id="causa-codigo" value={causaForm.data.codigo} onChange={(e) => causaForm.setData('codigo', e.target.value)} disabled={!!causaDialog?.causa} placeholder="Ej: ROD" required />
              {causaForm.errors.codigo && <p className="text-red-500 text-xs mt-1">{causaForm.errors.codigo}</p>}
            </div>
            <div>
              <Label htmlFor="causa-nombre">Nombre</Label>
              <Input id="causa-nombre" value={causaForm.data.nombre} onChange={(e) => causaForm.setData('nombre', e.target.value)} placeholder="Ej: Cambio de rodamiento" required />
              {causaForm.errors.nombre && <p className="text-red-500 text-xs mt-1">{causaForm.errors.nombre}</p>}
            </div>
            {causaDialog?.causa && (
              <div className="flex items-center space-x-2">
                <Switch id="causa-activo" checked={causaForm.data.activo} onCheckedChange={(checked) => causaForm.setData('activo', checked)} />
                <Label htmlFor="causa-activo">Activa</Label>
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="secondary" onClick={() => setCausaDialog(null)}>Cancelar</Button>
              <Button type="submit" disabled={causaForm.processing}>
                {causaForm.processing && <Loader2 className="w-4 h-4 animate-spin" />}
                Guardar
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </AuthenticatedLayout>
  )
}