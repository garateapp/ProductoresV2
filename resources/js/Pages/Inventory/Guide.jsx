import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'

const sections = [
  {
    title: 'Antes de comenzar',
    items: [
      'Verifica que cada ubicación crítica tenga etiqueta escaneable visible.',
      'Verifica que cada pallet/LPN tenga su etiqueta legible.',
      'Usa Escaneo operativo para traslados y mermas detectadas en terreno.',
      'Usa Movimientos cuando necesites registrar operaciones manuales o con varias líneas.',
      'No hagas ajustes manuales si el caso puede resolverse con un movimiento trazado.',
    ],
  },
  {
    title: 'Traslado por escaneo',
    items: [
      '1. Entra a Inventario > Escaneo operativo > Traslado.',
      '2. Escanea uno o más pallets/LPN del mismo origen operativo.',
      '3. Escanea la ubicación destino.',
      '4. Revisa el resumen antes de confirmar: los pallets deben pertenecer a la misma ubicación origen.',
      '5. Al confirmar, el pallet queda en tránsito y el despacho conserva trazabilidad por posiciones.',
      '6. La recepción o el rechazo se confirman después desde Inventario > Movimientos.',
    ],
  },
  {
    title: 'Merma por escaneo',
    items: [
      '1. Entra a Inventario > Escaneo operativo > Merma.',
      '2. Escanea la ubicación exacta donde se detecta la pérdida.',
      '3. Indica el pallet/LPN o el material afectado.',
      '4. Si existen posiciones de stock en la ubicación origen, selecciona la posición correcta.',
      '5. Ingresa la cantidad afectada y selecciona el motivo.',
      '6. Si corresponde, registra la ubicación de cuarentena.',
      '7. Confirma y revisa que la merma quede asociada a la ubicación de ocurrencia y, si aplica, a la posición origen.',
    ],
  },
  {
    title: 'Movimientos manuales',
    items: [
      '1. Entra a Inventario > Movimientos.',
      '2. Define la cabecera: tipo, fecha, ubicación origen, ubicación destino y motivo si corresponde.',
      '3. Agrega una o más líneas de detalle por material.',
      '4. Para CONSUMO, MERMA y AJUSTE_NEG, selecciona posición origen cuando exista stock modelado por posiciones.',
      '5. Usa referencia de producción cuando el movimiento se relacione con una producción registrada.',
      '6. Aplica el movimiento solo cuando la cabecera y las líneas coincidan con la operación física.',
    ],
  },
  {
    title: 'Pallets / LPN y posiciones',
    items: [
      'Inventario > Pallets / LPN permite revisar saldo disponible, ubicación actual y posiciones asociadas.',
      'Un traslado parcial mueve solo la posición seleccionada; un traslado completo mueve todas las posiciones del LPN en la ubicación origen.',
      'Si el stock de un pallet/LPN está distribuido por posiciones, la operación debe respetar esa distribución.',
      'Las posiciones son la referencia física principal para consumo, merma y trazabilidad fina.',
    ],
  },
  {
    title: 'Mermas y disposición',
    items: [
      'Inventario > Mermas por ubicación muestra el ciclo completo de cada merma.',
      'Una merma puede pasar por revisión, cuarentena y disposición según su estado.',
      'Si la merma provino de una posición específica, la tabla y el detalle la muestran para auditoría.',
      'Usa Revisar, Enviar a cuarentena y Disponer según la etapa operativa real del caso.',
    ],
  },
  {
    title: 'Producción y consumo teórico',
    items: [
      'Inventario > Producción registra la producción operativa asociada a un proceso.',
      'Selecciona proceso y embalaje para que el sistema calcule cajas, pallets y consumo teórico.',
      'Activa edición manual solo cuando exista una excepción operativa real.',
      'Usa la vista previa teórica para validar la ficha técnica antes de guardar.',
    ],
  },
  {
    title: 'Consulta y seguimiento',
    items: [
      'Inventario > Movimientos muestra el estado del movimiento, el resumen de líneas y la trazabilidad por pallet/LPN.',
      'Los traslados muestran el detalle de posiciones vinculadas al despacho cuando corresponde.',
      'Inventario > Mermas por ubicación permite filtrar por ubicación, material, motivo, estado y fechas.',
      'El hash de receipt y de ledger sigue disponible para auditoría en movimientos aplicados.',
    ],
  },
  {
    title: 'Buenas prácticas',
    items: [
      'Registra la merma donde ocurre, no solo donde termina físicamente.',
      'Evita mover pallets/LPN sin escaneo o sin movimiento registrado.',
      'Si el stock está modelado por posiciones, no consumas ni ajustes contra stock agregado ignorando la posición.',
      'Si un código falla, repórtalo y no inventes ubicaciones manuales.',
      'Si la cantidad real no coincide, deja observación breve y avisa a supervisión.',
    ],
  },
  {
    title: 'Comandos de soporte',
    items: [
      'php artisan inventory:ledger-verify',
      'php artisan inventory:ledger-bootstrap --dry-run',
      'php artisan inventory:locations-sync-scan-codes --dry-run',
    ],
  },
]

export default function InventoryGuide({ roles = [] }) {
  return (
    <div className="container mx-auto py-10 space-y-4 max-w-5xl">
      <Card>
        <CardHeader>
          <CardTitle>Instructivo de uso · Inventario trazable</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4 text-sm text-gray-700">
          <p>
            Este instructivo resume cómo usar el módulo de inventario con pallets/LPN, posiciones de stock,
            escaneo operativo, movimientos manuales, producción y mermas con trazabilidad.
          </p>
          <p>
            Perfiles principales: {roles.join(', ')}.
          </p>
        </CardContent>
      </Card>

      {sections.map((section) => (
        <Card key={section.title}>
          <CardHeader>
            <CardTitle>{section.title}</CardTitle>
          </CardHeader>
          <CardContent>
            <ul className="list-disc space-y-2 pl-5 text-sm text-gray-700">
              {section.items.map((item) => (
                <li key={item}>{item}</li>
              ))}
            </ul>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

InventoryGuide.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Instructivo de uso</h2>}
  />
)
