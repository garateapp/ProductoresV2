import { Suspense, useState, useMemo } from 'react'
import { Canvas } from '@react-three/fiber'
import { OrbitControls, Environment, Grid, Text, Line } from '@react-three/drei'
import Pallet3D from './Pallet3D'
import Rack3D from './Rack3D'
import FolioInfoPanel from './FolioInfoPanel'

export default function Estiba3D({
  filas,
  columnas,
  altoMaximo,
  folios,
  codigo,
  titulo,
  mode = 'tunel',
  onFolioSelect,
}) {
  const [selectedFolio, setSelectedFolio] = useState(null)

  const handleSelect = (folio) => {
    setSelectedFolio((prev) => (prev?.id === folio.id ? null : folio))
    onFolioSelect?.(folio)
  }

  const SPACING = 1.1

  const parsed = useMemo(() => {
    if (!folios?.length) return []
    return folios.map((f, idx) => {
      let x, y, z
      if (mode === 'tunel') {
        x = (parseInt(f.banda) - 1) * SPACING
        z = (parseInt(f.posicion) - 1) * SPACING
        y = (parseInt(f.altura) - 1) * SPACING
      } else {
        x = (parseInt(f.columna) - 1) * SPACING
        z = (parseInt(f.fila) - 1) * SPACING
        y = (parseInt(f.nivel || f.altura || 1) - 1) * SPACING
      }
      return { ...f, x: x || 0, y: y || 0, z: z || 0, idx }
    })
  }, [folios, mode, SPACING])

  const slotsX = Math.max(columnas || 4, 1)
  const slotsZ = Math.max(filas || 4, 1)
  const levels = Math.max(altoMaximo || 3, 3)

  const halfW = (slotsX * SPACING) / 2
  const halfD = (slotsZ * SPACING) / 2

  const gridW = slotsX * SPACING + 2
  const gridD = slotsZ * SPACING + 2
  const wallHeight = levels * SPACING + 1.5

  return (
    <div className="w-full rounded-lg overflow-hidden border border-gray-700 bg-gray-900 relative" style={{ height: 480 }}>
      <Canvas camera={{ position: [8, 6, 8], fov: 48 }} shadows dpr={[1, 2]}>
        <Suspense fallback={null}>
          <ambientLight intensity={0.4} />
          <directionalLight position={[6, 12, 6]} intensity={1} castShadow shadow-mapSize={[1024, 1024]} />
          <pointLight position={[0, wallHeight, 0]} intensity={0.15} color="#60a5fa" />
          <hemisphereLight skyColor="#e0e7ff" groundColor="#1e293b" intensity={0.25} />

          <Environment preset="warehouse" />

          <Grid
            args={[40, 40]}
            cellSize={1}
            cellThickness={0.3}
            cellColor="#1e293b"
            sectionSize={5}
            fadeDistance={30}
            position={[0, -0.5, 0]}
          />

          {/* Floor */}
          <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, -0.5, 0]} receiveShadow>
            <planeGeometry args={[gridW + 2, gridD + 2]} />
            <meshStandardMaterial color="#1e293b" roughness={0.9} metalness={0.1} />
          </mesh>

          {/* Chamber walls */}
          <mesh position={[0, wallHeight / 2 - 0.5, -halfD - 0.5]}>
            <boxGeometry args={[gridW + 1, wallHeight, 0.1]} />
            <meshStandardMaterial color="#334155" transparent opacity={0.15} side={2} />
          </mesh>
          <mesh position={[-halfW - 0.5, wallHeight / 2 - 2, 0]}>
            <boxGeometry args={[0.1, wallHeight, gridD + 1]} />
            <meshStandardMaterial color="#334155" transparent opacity={0.12} side={2} />
          </mesh>
          <mesh position={[halfW + 0.5, wallHeight / 2 - 2, 0]}>
            <boxGeometry args={[0.1, wallHeight, gridD + 1]} />
            <meshStandardMaterial color="#334155" transparent opacity={0.08} side={2} />
          </mesh>

          {/* Floor boundary */}
          <Line
            points={[
              [-halfW, -0.5, -halfD],
              [halfW, -0.5, -halfD],
              [halfW, -0.5, halfD],
              [-halfW, -0.5, halfD],
              [-halfW, -0.5, -halfD],
            ]}
            color="#3b82f6"
            lineWidth={2}
          />

          <Rack3D filas={filas} columnas={columnas} altoMaximo={altoMaximo} spacing={SPACING} />

          {parsed.map((f) => (
            <Pallet3D
              key={f.id || f.idx}
              position={[f.x, f.y, f.z]}
              folio={f.folio}
              cajas={f.cajas}
              especie={f.especie}
              nivel={f.nivel}
              isSelected={selectedFolio?.id === f.id}
              onClick={() => handleSelect(f)}
            />
          ))}

          {/* Labels */}
          <Text position={[halfW + 0.2, -0.6, -halfD - 1]} fontSize={0.22} color="#93c5fd" anchorX="center">
            {mode === 'tunel' ? 'Bandas' : 'Columnas'}
          </Text>
          <Text
            position={[-halfW - 1.2, -0.6, halfD / 2 - 0.5]}
            fontSize={0.22}
            color="#93c5fd"
            anchorX="center"
            rotation={[0, -Math.PI / 2, 0]}
          >
            {mode === 'tunel' ? 'Posiciones' : 'Filas'}
          </Text>

          <Text position={[0, wallHeight + 0.2, -halfD - 0.5]} fontSize={0.35} color="#e0e7ff" anchorX="center" fontWeight="bold">
            {titulo || codigo || ''}
          </Text>

          <OrbitControls
            enablePan
            enableZoom
            enableRotate
            minDistance={2}
            maxDistance={25}
            maxPolarAngle={Math.PI / 2.05}
          />
        </Suspense>
      </Canvas>

      {/* Legend */}
      <div className="absolute top-3 left-3 text-[11px] text-gray-400 space-y-1 bg-gray-900/80 backdrop-blur-sm rounded-md px-3 py-2 border border-gray-700/50">
        <div className="font-semibold text-gray-300 mb-1">{mode === 'tunel' ? 'Túnel' : 'Cámara'}</div>
        <div className="flex items-center gap-2"><span className="w-2 h-2 rounded-full bg-red-500 inline-block" /> Cereza</div>
        <div className="flex items-center gap-2"><span className="w-2 h-2 rounded-full bg-purple-600 inline-block" /> Uva</div>
        <div className="flex items-center gap-2"><span className="w-2 h-2 rounded-full bg-blue-700 inline-block" /> Arándano</div>
        <div className="flex items-center gap-2"><span className="w-2 h-2 rounded-full bg-lime-600 inline-block" /> Kiwi</div>
        <div className="flex items-center gap-2"><span className="w-2 h-2 rounded-full bg-orange-600 inline-block" /> Naranja</div>
        <div className="mt-1 pt-1 border-t border-gray-700">
          <span>{folios?.length || 0} folio(s)</span>
        </div>
      </div>

      <FolioInfoPanel folio={selectedFolio} onClose={() => setSelectedFolio(null)} />
    </div>
  )
}
