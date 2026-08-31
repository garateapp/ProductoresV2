import { useState, useMemo } from 'react'

const ZONE_COLORS = {
  tunel: { bg: '#dcfce7', border: '#16a34a', label: 'Túnel' },
  camara: { bg: '#dbeafe', border: '#2563eb', label: 'Cámara' },
  pt: { bg: '#fef3c7', border: '#d97706', label: 'Prod. Terminado' },
}

const CELL = 24
const PADDING = 60

export default function PlantaCanvas2D({ tuneles, camaras, productoTerminado, selected, onSelect }) {
  const zones = useMemo(() => {
    return [
      ...tuneles.map(z => ({ ...z, tipo: 'tunel' })),
      ...camaras.map(z => ({ ...z, tipo: 'camara' })),
      ...productoTerminado.map(z => ({ ...z, tipo: 'pt' })),
    ]
  }, [tuneles, camaras, productoTerminado])

  const viewBox = useMemo(() => {
    if (!zones.length) return '0 0 1200 800'
    const maxX = Math.max(...zones.map(z => Number(z.pos_x) + (z.columnas || 1) * 3)) + PADDING * 2
    const maxY = Math.max(...zones.map(z => Number(z.pos_y) + (z.filas || 1) * 3)) + PADDING * 2
    return `0 0 ${maxX * CELL} ${maxY * CELL}`
  }, [zones])

  return (
    <div className="w-full overflow-auto bg-gray-50 rounded-lg border" style={{ minHeight: 500 }}>
      <svg viewBox={viewBox} className="w-full h-full" style={{ minHeight: 500 }}>
        <defs>
          <pattern id="grid" width={CELL} height={CELL} patternUnits="userSpaceOnUse">
            <path d={`M ${CELL} 0 L 0 0 0 ${CELL}`} fill="none" stroke="#e5e7eb" strokeWidth="0.5" />
          </pattern>
        </defs>
        <rect width="100%" height="100%" fill="url(#grid)" />

        {zones.map((zone) => {
          const colors = ZONE_COLORS[zone.tipo]
          const w = Math.max((zone.columnas || 1), 1) * 3 * CELL
          const h = Math.max((zone.filas || 1), 1) * 3 * CELL
          const x = Number(zone.pos_x) * CELL + PADDING
          const y = Number(zone.pos_y) * CELL + PADDING
          const isSelected = selected?.id === zone.id

          return (
            <g
              key={zone.id}
              onClick={() => onSelect(zone)}
              className="cursor-pointer"
              style={{ transition: 'transform 0.15s' }}
            >
              <rect
                x={x}
                y={y}
                width={w}
                height={h}
                fill={colors.bg}
                stroke={isSelected ? '#000' : colors.border}
                strokeWidth={isSelected ? 3 : 1.5}
                rx={4}
                opacity={isSelected ? 1 : 0.9}
              />
              <text
                x={x + w / 2}
                y={y + h / 2 - 8}
                textAnchor="middle"
                fontSize="11"
                fontWeight="bold"
                fill={colors.border}
              >
                {zone.codigo}
              </text>
              <text
                x={x + w / 2}
                y={y + h / 2 + 8}
                textAnchor="middle"
                fontSize="9"
                fill="#374151"
              >
                {zone.nombre}
              </text>
              <text
                x={x + w / 2}
                y={y + h / 2 + 22}
                textAnchor="middle"
                fontSize="8"
                fill="#6b7280"
              >
                {zone.filas}×{zone.columnas} · alto {zone.alto_maximo}
              </text>
            </g>
          )
        })}

        {zones.length === 0 && (
          <text x="50%" y="50%" textAnchor="middle" fontSize="14" fill="#9ca3af">
            Sin bodegas sincronizadas. Ejecuta: php artisan prefrio:sync-bodegas
          </text>
        )}
      </svg>
    </div>
  )
}
