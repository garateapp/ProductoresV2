import { X } from 'lucide-react'

const ESTADO_COLORS = {
  ingresado: 'bg-yellow-100 text-yellow-800 border-yellow-300',
  iniciado: 'bg-blue-100 text-blue-800 border-blue-300',
  salido: 'bg-green-100 text-green-800 border-green-300',
}

function formatFecha(fecha) {
  if (!fecha) return '—'
  const d = new Date(fecha)
  if (isNaN(d.getTime())) return '—'
  return d.toLocaleDateString('es-CL', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

export default function FolioInfoPanel({ folio, onClose, levelLabel = 'Nivel', levelValue }) {
  if (!folio) return null

  const estado = folio.estado || folio.loadEstado || 'ingresado'
  const estadoClass = ESTADO_COLORS[estado] || ESTADO_COLORS.ingresado
  const ubicacion = folio.ubicacionVisual || folio.posicion || [folio.banda, folio.fila, folio.columna]
    .filter((value) => value != null && value !== '')
    .join('/') || '—'

  return (
    <div className="absolute top-3 right-3 w-72 bg-white dark:bg-gray-900 rounded-lg shadow-xl border border-gray-200 dark:border-gray-700 z-10 overflow-hidden">
      <div className="flex items-center justify-between px-4 py-3 bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div className="flex items-center gap-2">
          <div className="w-3 h-3 rounded-full bg-green-500" />
          <span className="font-mono font-bold text-sm text-gray-900 dark:text-gray-100">
            {folio.folio || 'Sin folio'}
          </span>
        </div>
        <button
          onClick={onClose}
          className="p-1 rounded hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
        >
          <X className="w-4 h-4 text-gray-500" />
        </button>
      </div>

      <div className="p-4 space-y-3">
        <div className="grid grid-cols-2 gap-2 text-xs">
          <InfoRow label="Cajas" value={folio.cajas ?? '—'} />
          <InfoRow label="Especie" value={folio.especie || '—'} />
          <InfoRow label={levelLabel} value={levelValue ?? folio.nivel ?? '—'} />
          <InfoRow label="Posición" value={ubicacion} />
        </div>

        <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
          <div className="text-[10px] uppercase tracking-wider text-gray-400 mb-2 font-semibold">
            Proceso
          </div>
          <div className="grid grid-cols-2 gap-2 text-xs">
            <InfoRow label="Túnel" value={folio.tunel || folio.tunelCodigo || '—'} />
            <InfoRow label="Cámara" value={folio.camara || folio.camaraCodigo || '—'} />
            <InfoRow label="Inicio" value={formatFecha(folio.fecha_hora_inicio)} />
            <InfoRow label="Temperatura" value={folio.temperatura_objetivo != null ? `${folio.temperatura_objetivo}°C` : '—'} />
          </div>
        </div>

        <div className="flex items-center gap-2">
          <span className="text-[10px] uppercase tracking-wider text-gray-400 font-semibold">Estado</span>
          <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full border ${estadoClass}`}>
            {estado.charAt(0).toUpperCase() + estado.slice(1)}
          </span>
        </div>

        {folio.observaciones && (
          <div className="border-t border-gray-200 dark:border-gray-700 pt-3">
            <div className="text-[10px] uppercase tracking-wider text-gray-400 mb-1 font-semibold">
              Observaciones
            </div>
            <p className="text-xs text-gray-600 dark:text-gray-400 leading-relaxed">
              {folio.observaciones}
            </p>
          </div>
        )}
      </div>
    </div>
  )
}

function InfoRow({ label, value }) {
  return (
    <div>
      <span className="text-gray-400 dark:text-gray-500">{label}:</span>{' '}
      <span className="font-medium text-gray-700 dark:text-gray-300">{value}</span>
    </div>
  )
}
