import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Map, Box } from 'lucide-react'
import PlantaCanvas2D from '@/Components/PreCooling/PlantaCanvas2D'

export default function PlantaLayout({ tuneles, camaras, productoTerminado }) {
  const [selected, setSelected] = useState(null)

  return (
    <AuthenticatedLayout
      header={
        <div className="flex items-center gap-3">
          <Map className="w-5 h-5 text-greenex-vibrant-green" />
          <h2 className="font-semibold text-xl text-gray-800">
            Layout de Planta
          </h2>
          <Badge variant="outline" className="ml-2">
            {tuneles.length} TN · {camaras.length} CA · {productoTerminado.length} PT
          </Badge>
        </div>
      }
    >
      <Head title="Prefrío · Layout de Planta" />

      <div className="py-12">
        <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
          {/* Legend */}
          <div className="flex gap-4 text-sm">
            <div className="flex items-center gap-2">
              <span className="w-3 h-3 rounded bg-green-200 border border-green-600" />
              Túneles
            </div>
            <div className="flex items-center gap-2">
              <span className="w-3 h-3 rounded bg-blue-200 border border-blue-600" />
              Cámaras
            </div>
            <div className="flex items-center gap-2">
              <span className="w-3 h-3 rounded bg-amber-100 border border-amber-600" />
              Producto Terminado
            </div>
          </div>

          {/* 2D Map */}
          <Card>
            <CardContent className="pt-6">
              <PlantaCanvas2D
                tuneles={tuneles}
                camaras={camaras}
                productoTerminado={productoTerminado}
                selected={selected}
                onSelect={setSelected}
              />
            </CardContent>
          </Card>

          {/* Detail Panel */}
          {selected && (
            <Card>
              <CardContent className="pt-6">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-3">
                    <Badge
                      variant={selected.tipo === 'tunel' ? 'default' : selected.tipo === 'camara' ? 'secondary' : 'outline'}
                    >
                      {selected.codigo}
                    </Badge>
                    <h3 className="font-semibold text-lg">{selected.nombre}</h3>
                    <span className="text-sm text-gray-500">
                      {ZONE_LABELS[selected.tipo]}
                    </span>
                  </div>
                  <Button variant="ghost" size="sm" onClick={() => setSelected(null)}>
                    ✕
                  </Button>
                </div>

                <div className="grid grid-cols-4 gap-4 mt-4 text-sm">
                  <div>
                    <span className="text-gray-500">Filas:</span>{' '}
                    <span className="font-medium">{selected.filas}</span>
                  </div>
                  <div>
                    <span className="text-gray-500">Columnas:</span>{' '}
                    <span className="font-medium">{selected.columnas}</span>
                  </div>
                  <div>
                    <span className="text-gray-500">Alto máx:</span>{' '}
                    <span className="font-medium">{selected.alto_maximo}</span>
                  </div>
                  <div>
                    <span className="text-gray-500">Posición:</span>{' '}
                    <span className="font-medium">{selected.pos_x}, {selected.pos_y}</span>
                  </div>
                </div>

                <div className="flex gap-3 mt-4">
                  {selected.tipo === 'tunel' && (
                    <>
                      <Button
                        size="sm"
                        onClick={() => router.visit(route('prefrio.matriz.tunel'))}
                      >
                        <Box className="w-4 h-4 mr-1" /> Ver matriz túnel
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => router.visit(route('prefrio.matriz.tunel'))}
                      >
                        <Map className="w-4 h-4 mr-1" /> Ver 2D
                      </Button>
                    </>
                  )}
                  {selected.tipo === 'camara' && (
                    <>
                      <Button
                        size="sm"
                        onClick={() => router.visit(route('prefrio.matriz.camara'))}
                      >
                        <Box className="w-4 h-4 mr-1" /> Ver estiba 3D
                      </Button>
                      <Button
                        size="sm"
                        variant="outline"
                        onClick={() => router.visit(route('prefrio.matriz.camara'))}
                      >
                        <Map className="w-4 h-4 mr-1" /> Ver 2D
                      </Button>
                    </>
                  )}
                </div>
              </CardContent>
            </Card>
          )}
        </div>
      </div>
    </AuthenticatedLayout>
  )
}

const ZONE_LABELS = {
  tunel: 'Túnel',
  camara: 'Cámara',
  pt: 'Producto Terminado',
}
