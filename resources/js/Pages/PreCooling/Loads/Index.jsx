import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'
import { Badge } from '@/Components/ui/badge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select'
import { ArrowRight, Filter } from 'lucide-react'
import { Toaster } from 'sonner'

const ESTADOS = {
  ingresado: { label: 'Ingresado', className: 'bg-amber-100 text-amber-800' },
  iniciado: { label: 'Iniciado', className: 'bg-green-100 text-green-800' },
  salido: { label: 'Salido', className: 'bg-slate-200 text-slate-700' },
}

export default function LoadsIndex({ cargas, tuneles }) {
  const [filtroEstado, setFiltroEstado] = useState('')
  const [filtroTunel, setFiltroTunel] = useState('')

  const aplicarFiltros = (key, value) => {
    const params = {}
    if (key === 'estado') {
      setFiltroEstado(value)
      if (value) params.estado = value
      if (filtroTunel) params.tunel_id = filtroTunel
    } else {
      setFiltroTunel(value)
      if (filtroEstado) params.estado = filtroEstado
      if (value) params.tunel_id = value
    }
    router.get(route('prefrio.loads.index'), params, { preserveState: true, preserveScroll: true })
  }

  const irAMatriz = (carga) => {
    router.get(route('prefrio.matriz.tunel'), { tunel_id: carga.tunel_id || cargas.find(c => c.id === carga.id)?.tunel_id }, { preserveState: true })
  }

  const verDetalle = (carga) => {
    router.get(route('prefrio.matriz.tunel'), { tunel_id: cargas.find(c => c.id === carga.id)?.tunel_id }, { preserveState: true })
  }

  return (
    <AuthenticatedLayout
      header={
        <div className="flex justify-between items-center">
          <h2 className="font-semibold text-xl text-gray-800 leading-tight">Prefrío · Procesos</h2>
        </div>
      }
    >
      <Head title="Prefrío · Procesos" />
      <Toaster />

      <div className="py-12">
        <div className="max-w-6xl mx-auto sm:px-6 lg:px-8 space-y-4">
          <Card>
            <CardContent className="pt-6 flex flex-col sm:flex-row items-start sm:items-end gap-4">
              <div className="flex items-center gap-2 text-sm text-gray-600">
                <Filter className="w-4 h-4" /> Filtros:
              </div>
              <div className="w-full sm:w-48">
                <Select value={filtroEstado} onValueChange={(v) => aplicarFiltros('estado', v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Todos los estados" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    {Object.entries(ESTADOS).map(([key, { label }]) => (
                      <SelectItem key={key} value={key}>{label}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="w-full sm:w-56">
                <Select value={filtroTunel} onValueChange={(v) => aplicarFiltros('tunel_id', v === 'all' ? '' : v)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Todos los túneles" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    {tuneles.map((t) => (
                      <SelectItem key={t.id} value={String(t.id)}>{t.codigo} · {t.nombre}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="pt-6 overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Número</TableHead>
                    <TableHead>Tipo Proceso</TableHead>
                    <TableHead>Túnel</TableHead>
                    <TableHead>Estado</TableHead>
                    <TableHead>Cámara Destino</TableHead>
                    <TableHead>Temp. Objetivo</TableHead>
                    <TableHead>Folios</TableHead>
                    <TableHead>Inicio</TableHead>
                    <TableHead>Inversión</TableHead>
                    <TableHead>Término</TableHead>
                    <TableHead>Descarga</TableHead>
                    <TableHead className="text-right">Acciones</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {cargas.map((carga) => (
                    <TableRow key={carga.id}>
                      <TableCell className="font-mono font-bold">{carga.numero}</TableCell>
                      <TableCell>{carga.tipo_proceso_nombre || carga.tipo_proceso}</TableCell>
                      <TableCell>{carga.tunel} · {carga.tunel_nombre}</TableCell>
                      <TableCell>
                        <Badge className={ESTADOS[carga.estado]?.className}>
                          {ESTADOS[carga.estado]?.label}
                        </Badge>
                      </TableCell>
                      <TableCell>{carga.camara_destino ? `${carga.camara_destino} · ${carga.camara_destino_nombre}` : '—'}</TableCell>
                      <TableCell>{carga.temperatura_objetivo != null ? `${carga.temperatura_objetivo} °C` : '—'}</TableCell>
                      <TableCell className="text-center">{carga.folios_count}</TableCell>
                      <TableCell className="text-xs">{carga.fecha_hora_inicio || '—'}</TableCell>
                      <TableCell className="text-xs">{carga.fecha_hora_inversion || '—'}</TableCell>
                      <TableCell className="text-xs">{carga.fecha_hora_termino || '—'}</TableCell>
                      <TableCell className="text-xs">{carga.fecha_hora_descarga || '—'}</TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm" onClick={() => irAMatriz(carga)}>
                          <ArrowRight className="w-4 h-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {cargas.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={12} className="text-center text-gray-500 py-6">
                        No hay procesos registrados.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>
    </AuthenticatedLayout>
  )
}
