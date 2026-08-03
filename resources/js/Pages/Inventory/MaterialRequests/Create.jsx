import { useState } from 'react'
import { useForm, Head, Link } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import SearchableSelect from '@/Components/SearchableSelect'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Trash2, Plus } from 'lucide-react'

export default function CreateMaterialRequest({ locations, materials, stocks }) {
  const { data, setData, post, processing, errors } = useForm({
    origin_location_id: '',
    destination_location_id: '',
    fecha_requerida: '',
    observacion: '',
    items: [{ material_id: '', cantidad: '', notas: '' }]
  })

  const locationOptions = locations.map(l => ({ value: String(l.id), label: l.nombre }))
  const materialOptions = materials.map(m => ({ value: String(m.id), label: `${m.codigo} · ${m.nombre}` }))

  function getStock(materialId) {
    if (!data.origin_location_id || !materialId) return null
    const row = stocks.find(s => String(s.location_id) === data.origin_location_id && String(s.material_id) === String(materialId))
    return row ? Number(row.stock_actual) : 0
  }

  const addItem = () => {
    setData('items', [...data.items, { material_id: '', cantidad: '', notas: '' }])
  }

  const removeItem = (index) => {
    const newItems = [...data.items]
    newItems.splice(index, 1)
    setData('items', newItems)
  }

  const updateItem = (index, field, value) => {
    const newItems = [...data.items]
    newItems[index][field] = value
    setData('items', newItems)
  }

  const submit = (e) => {
    e.preventDefault()
    post(route('inventory.material-requests.store'))
  }

  return (
    <AuthenticatedLayout
      header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Nueva Solicitud de Materiales</h2>}
    >
      <Head title="Nueva Solicitud" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
          <form onSubmit={submit} className="space-y-6">
            <Card>
              <CardHeader>
                <CardTitle>Información General</CardTitle>
              </CardHeader>
              <CardContent>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <Label>Ubicación Origen (Quién provee)</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={locationOptions.find(o => o.value === data.origin_location_id)}
                      onChange={(o) => setData('origin_location_id', o?.value || '')}
                      placeholder="Selecciona origen"
                    />
                    {errors.origin_location_id && <p className="text-sm text-red-600">{errors.origin_location_id}</p>}
                  </div>

                  <div className="space-y-2">
                    <Label>Ubicación Destino (Quién recibe)</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={locationOptions.find(o => o.value === data.destination_location_id)}
                      onChange={(o) => setData('destination_location_id', o?.value || '')}
                      placeholder="Selecciona destino"
                    />
                    {errors.destination_location_id && <p className="text-sm text-red-600">{errors.destination_location_id}</p>}
                  </div>

                  <div className="space-y-2">
                    <Label>Fecha Requerida</Label>
                    <Input 
                      type="date" 
                      value={data.fecha_requerida} 
                      onChange={e => setData('fecha_requerida', e.target.value)}
                    />
                  </div>

                  <div className="space-y-2">
                    <Label>Observación</Label>
                    <Textarea 
                      value={data.observacion} 
                      onChange={e => setData('observacion', e.target.value)}
                    />
                  </div>
                </div>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Materiales Solicitados</CardTitle>
                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                  <Plus className="w-4 h-4 mr-2" /> Agregar Item
                </Button>
              </CardHeader>
              <CardContent>
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead className="w-[35%]">Material</TableHead>
                      <TableHead className="text-right w-[15%]">Stock Disp.</TableHead>
                      <TableHead className="text-right w-[15%]">Cantidad Sol.</TableHead>
                      <TableHead>Notas</TableHead>
                      <TableHead className="w-10"></TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {data.items.map((item, index) => {
                      const stock = getStock(item.material_id)
                      const qty = parseFloat(item.cantidad) || 0
                      const insufficient = stock !== null && qty > 0 && qty > stock
                      return (
                      <TableRow key={index}>
                        <TableCell>
                          <SearchableSelect
                            options={materialOptions}
                            value={materialOptions.find(o => o.value === item.material_id)}
                            onChange={(o) => updateItem(index, 'material_id', o?.value || '')}
                            placeholder="Buscar material..."
                          />
                        </TableCell>
                        <TableCell className="text-right">
                          {stock !== null ? (
                            <span className={`text-sm font-mono ${stock > 0 ? 'text-green-600' : 'text-red-500'}`}>
                              {Number(stock).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                            </span>
                          ) : (
                            <span className="text-xs text-slate-400">—</span>
                          )}
                        </TableCell>
                        <TableCell>
                          <Input
                            type="number"
                            step="0.0001"
                            value={item.cantidad}
                            onChange={e => updateItem(index, 'cantidad', e.target.value)}
                            className={insufficient ? 'border-red-400 bg-red-50' : ''}
                          />
                        </TableCell>
                        <TableCell>
                          <Input
                            value={item.notas}
                            onChange={e => updateItem(index, 'notas', e.target.value)}
                          />
                        </TableCell>
                        <TableCell>
                          {data.items.length > 1 && (
                            <Button type="button" variant="ghost" size="sm" onClick={() => removeItem(index)}>
                              <Trash2 className="w-4 h-4 text-red-500" />
                            </Button>
                          )}
                        </TableCell>
                      </TableRow>
                    )})}
                  </TableBody>
                </Table>
                {errors.items && <p className="text-sm text-red-600 mt-2">{errors.items}</p>}
                {Object.keys(errors).filter(k => k.startsWith('items.')).map(k => (
                  <p key={k} className="text-sm text-red-600 mt-1">{errors[k]}</p>
                ))}
              </CardContent>
            </Card>

            <div className="flex items-center justify-end space-x-4">
              <Link href={route('inventory.material-requests.index')}>
                <Button variant="ghost" type="button">Cancelar</Button>
              </Link>
              <Button disabled={processing} type="submit">
                Crear Solicitud
              </Button>
            </div>
          </form>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
