import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import { Badge } from '@/Components/ui/badge'
import ParametrizacionEditor from '@/Components/PreCooling/ParametrizacionEditor'
import { Plus, Pencil } from 'lucide-react'
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/Components/ui/dialog'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { toast, Toaster } from 'sonner'

const DIMENSIONES = [
  { key: 'banda', label: 'Bandas' },
  { key: 'posicion', label: 'Posiciones' },
  { key: 'altura', label: 'Alturas' },
  { key: 'nivel', label: 'Niveles' },
]

const TIPOS = [
  { value: 'californiano', label: 'Californiano' },
  { value: 'modular', label: 'Modular' },
  { value: 'evaporador_central', label: 'Evaporador Central' },
]

const VACIOS = { banda: [], posicion: [], altura: [], nivel: [] }

export default function TunelesIndex({ tuneles }) {
  const [isOpen, setIsOpen] = useState(false)
  const [editing, setEditing] = useState(null)

  const { data, setData, post, patch, processing, errors, reset } = useForm({
    codigo: '',
    nombre: '',
    tipo: 'californiano',
    activo: true,
    parametros: VACIOS,
  })

  const openDialog = (tunel = null) => {
    if (tunel) {
      setEditing(tunel)
      setData({
        codigo: tunel.codigo,
        nombre: tunel.nombre,
        tipo: tunel.tipo || 'californiano',
        activo: !!tunel.activo,
        parametros: {
          banda: [...(tunel.parametros?.banda || [])],
          posicion: [...(tunel.parametros?.posicion || [])],
          altura: [...(tunel.parametros?.altura || [])],
          nivel: [...(tunel.parametros?.nivel || [])],
        },
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
        toast.success(editing ? 'Túnel actualizado correctamente' : 'Túnel creado correctamente')
      },
    }
    if (editing) {
      patch(route('prefrio.tuneles.update', editing.id), options)
    } else {
      post(route('prefrio.tuneles.store'), options)
    }
  }

  const parametrosBloqueados = !!editing?.tiene_cargas

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Túneles</h2>
          <Button onClick={() => openDialog()}>
            <Plus className="w-4 h-4 mr-2" /> Nuevo Túnel
          </Button>
        </div>
      }
    >
      <Head title="Prefrío · Túneles" />
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
                    <TableHead>Parametrización</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {tuneles.map((tunel) => (
                    <TableRow key={tunel.id}>
                      <TableCell className="font-mono">{tunel.codigo}</TableCell>
                      <TableCell>{tunel.nombre}</TableCell>
                      <TableCell>
                        <span className="px-2 py-1 rounded text-xs bg-blue-50 text-blue-800">
                          {TIPOS.find((t) => t.value === tunel.tipo)?.label || tunel.tipo}
                        </span>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {Object.entries(tunel.parametros || {}).map(([dim, valores]) =>
                            valores.length > 0 ? (
                              <span key={dim} className="px-2 py-1 rounded text-xs bg-gray-100 text-gray-700">
                                {dim}: {valores.join(', ')}
                              </span>
                            ) : null
                          )}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-col gap-1">
                          <span className={`px-2 py-1 rounded text-xs w-fit ${tunel.activo ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                            {tunel.activo ? 'Activo' : 'Inactivo'}
                          </span>
                          {tunel.tiene_cargas && <Badge variant="destructive">Con cargas</Badge>}
                        </div>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => openDialog(tunel)}>
                          <Pencil className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {tuneles.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center text-gray-500 py-6">
                        Sin túneles registrados.
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
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>{editing ? `Editar Túnel · ${editing.codigo}` : 'Nuevo Túnel'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
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
            </div>

            <div>
              <Label htmlFor="tipo">Tipo de Túnel</Label>
              <Select value={data.tipo} onValueChange={(value) => setData('tipo', value)}>
                <SelectTrigger>
                  <SelectValue placeholder="Seleccionar tipo" />
                </SelectTrigger>
                <SelectContent>
                  {TIPOS.map((t) => (
                    <SelectItem key={t.value} value={t.value}>{t.label}</SelectItem>
                  ))}
                </SelectContent>
              </Select>
              {errors.tipo && <p className="text-red-500 text-xs mt-1">{errors.tipo}</p>}
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <Label>Matriz del túnel</Label>
                {parametrosBloqueados && (
                  <Badge variant="destructive">Parametrización bloqueada por cargas activas</Badge>
                )}
              </div>
              <ParametrizacionEditor
                dimensions={DIMENSIONES}
                values={data.parametros}
                onChange={(next) => setData('parametros', next)}
                disabled={parametrosBloqueados}
              />
              {errors.parametros && <p className="text-red-500 text-xs mt-1">{errors.parametros}</p>}
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
