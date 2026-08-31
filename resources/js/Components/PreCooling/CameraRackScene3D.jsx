import { Suspense, useEffect, useMemo, useRef, useState } from 'react'
import { Canvas, events as createPointerEvents, useFrame } from '@react-three/fiber'
import { MathUtils } from 'three'
import {
  Environment,
  Html,
  Line,
  RoundedBox,
  Text,
  useCursor,
} from '@react-three/drei'
import Pallet3D from './Pallet3D'
import FolioInfoPanel from './FolioInfoPanel'
import GarateBrand3D from './GarateBrand3D'
import { buildPositionLabelsByBand } from './cameraRackPositions'
import {
  FirstPersonController,
  FirstPersonNavigationHud,
  useFirstPersonNavigationInput,
} from './FirstPersonNavigation'

function createFirstPersonPointerEvents(store) {
  const eventManager = createPointerEvents(store)

  return {
    ...eventManager,
    compute(event, state, previous) {
      if (typeof document !== 'undefined' && document.pointerLockElement === state.gl.domElement) {
        state.pointer.set(0, 0)
        state.raycaster.setFromCamera(state.pointer, state.camera)
        return
      }

      eventManager.compute(event, state, previous)
    },
  }
}

const SLOT_DEPTH = 1.08
const RACK_DEPTH = 1.04
const LEVEL_HEIGHT = 1.34
const AISLE_WIDTH = 2.45
const CENTRAL_RACK_GAP = 0.34
const POST_SIZE = 0.085
const CANONICAL_BAND_ORDER = ['izquierda', 'central-izq', 'central-dcha', 'derecha']

function normalizeValues(values, fallbackPrefix) {
  if (Array.isArray(values)) return values.map(String)
  const count = Math.max(Number(values) || 0, 1)
  return Array.from({ length: count }).map((_, index) => `${fallbackPrefix}${index + 1}`)
}

function normalizeRackColumns(values) {
  const configured = Array.isArray(values) ? values.map(String) : []
  return Array.from({ length: 3 }, (_, index) => configured[index] || String(index + 1))
}

function normalizeBandRole(value) {
  const normalized = String(value)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/\s+/g, '-')

  if (normalized.includes('central') && /(izq|izquierda)/.test(normalized)) return 'central-izq'
  if (normalized.includes('central') && /(dcha|der|derecha)/.test(normalized)) return 'central-dcha'
  if (/(izq|izquierda)/.test(normalized)) return 'izquierda'
  if (/(dcha|der|derecha)/.test(normalized)) return 'derecha'
  return normalized
}

function orderCameraBands(values) {
  return [...values].sort((left, right) => {
    const leftIndex = CANONICAL_BAND_ORDER.indexOf(normalizeBandRole(left))
    const rightIndex = CANONICAL_BAND_ORDER.indexOf(normalizeBandRole(right))
    if (leftIndex < 0 && rightIndex < 0) return 0
    if (leftIndex < 0) return 1
    if (rightIndex < 0) return -1
    return leftIndex - rightIndex
  })
}

function rowDimensionForBand(band) {
  return {
    izquierda: 'fila_izquierda',
    'central-izq': 'fila_central_izq',
    'central-dcha': 'fila_central_dcha',
    derecha: 'fila_derecha',
  }[normalizeBandRole(band)] || 'fila'
}

function buildRackLayout(parametros, { rackable = true } = {}) {
  const bandas = orderCameraBands(normalizeValues(parametros?.banda, 'B'))
  const legacyRows = normalizeValues(parametros?.fila, 'F')
  const rowsByBand = Object.fromEntries(bandas.map((band) => {
    const dimension = rowDimensionForBand(band)
    const configuredRows = parametros?.[dimension]
    return [band, normalizeValues(configuredRows?.length ? configuredRows : legacyRows, 'F')]
  }))
  const positionLabelsByBand = buildPositionLabelsByBand(bandas, rowsByBand)
  const columnas = rackable
    ? normalizeRackColumns(parametros?.columna)
    : normalizeValues(parametros?.columna, 'C')
  const rackHeights = parametros?.altura?.length ? parametros.altura : parametros?.nivel
  const floorLevels = parametros?.nivel?.length ? parametros.nivel : parametros?.altura
  const niveles = normalizeValues(rackable ? rackHeights : floorLevels, rackable ? 'A' : 'N')
  const rackHeight = Math.max(niveles.length * LEVEL_HEIGHT + 0.22, 1.7)
  const maxPositions = Math.max(...bandas.map((band) => rowsByBand[band].length), 1)
  const runDepth = rackable
    ? Math.max(maxPositions * SLOT_DEPTH, 2.8)
    : Math.max(columnas.length * SLOT_DEPTH, 2.8)

  const rowPitch = RACK_DEPTH + 0.34
  const bandWidths = bandas.map((band) => (
    rackable
      ? columnas.length * RACK_DEPTH
      : RACK_DEPTH + Math.max(rowsByBand[band].length - 1, 0) * rowPitch
  ))
  const gaps = bandas.slice(0, -1).map((band, bandIndex) => {
    const currentRole = normalizeBandRole(band)
    const nextRole = normalizeBandRole(bandas[bandIndex + 1])
    return currentRole === 'central-izq' && nextRole === 'central-dcha'
      ? CENTRAL_RACK_GAP
      : AISLE_WIDTH
  })
  const occupiedWidth = bandWidths.reduce((sum, width) => sum + width, 0)
    + gaps.reduce((sum, gap) => sum + gap, 0)
  let cursorX = -occupiedWidth / 2
  const bandCenters = bandas.map((_, bandIndex) => {
    const center = cursorX + bandWidths[bandIndex] / 2
    cursorX += bandWidths[bandIndex] + (gaps[bandIndex] || 0)
    return center
  })
  const rowOffsetsByBand = Object.fromEntries(bandas.map((band) => [
    band,
    rowsByBand[band].map((_, rowIndex) => (
      rackable ? 0 : (rowIndex - (rowsByBand[band].length - 1) / 2) * rowPitch
    )),
  ]))
  const columnCenters = columnas.map((_, columnIndex) => (
    rackable
      ? (columnIndex - (columnas.length - 1) / 2) * RACK_DEPTH
      : -runDepth / 2 + SLOT_DEPTH * (columnIndex + 0.5)
  ))
  const positionCentersByBand = Object.fromEntries(bandas.map((band) => [
    band,
    rowsByBand[band].map((_, positionIndex) => (
      -runDepth / 2 + SLOT_DEPTH * (positionIndex + 0.5)
    )),
  ]))
  const columnBoundaries = Array.from({ length: columnas.length + 1 }, (_, boundaryIndex) => (
    (boundaryIndex - columnas.length / 2) * RACK_DEPTH
  ))
  const width = Math.max(
    occupiedWidth + 1.8,
    7.5,
  )
  const aisleCenters = gaps.flatMap((gap, index) => (
    gap >= AISLE_WIDTH * 0.8
      ? [(bandCenters[index] + bandCenters[index + 1]) / 2]
      : []
  ))
  const columnCentersByBand = Object.fromEntries(bandas.map((band, bandIndex) => {
    const bandX = bandCenters[bandIndex]
    const aisleX = aisleCenters.reduce((nearest, candidate) => (
      Math.abs(candidate - bandX) < Math.abs(nearest - bandX) ? candidate : nearest
    ), aisleCenters[0] ?? bandX)
    const depthDirection = aisleX < bandX ? -1 : 1
    return [band, columnas.map((_, columnIndex) => (
      depthDirection * (bandWidths[bandIndex] / 2 - RACK_DEPTH * (columnIndex + 0.5))
    ))]
  }))

  return {
    bandas,
    rowsByBand,
    positionLabelsByBand,
    totalRows: bandas.reduce((total, band) => total + rowsByBand[band].length, 0),
    columnas,
    niveles,
    bandCenters,
    rowOffsetsByBand,
    bandWidths,
    aisleCenters,
    columnCenters,
    columnCentersByBand,
    columnBoundaries,
    positionCentersByBand,
    rackHeight,
    runDepth,
    width,
    isDepthLayout: rackable,
  }
}

function buildFreeFloorLayout(parametros) {
  const layout = buildRackLayout(parametros, { rackable: false })
  const stackedPalletHeight = Math.max(layout.niveles.length, 1) * 0.82 + 0.92

  return {
    ...layout,
    rackHeight: Math.max(stackedPalletHeight, 2.35),
  }
}

function Steel({ color, children, ...props }) {
  return (
    <RoundedBox {...props}>
      <meshStandardMaterial color={color} roughness={0.38} metalness={0.72} />
      {children}
    </RoundedBox>
  )
}

function RackEndFrame({ z, rackHeight }) {
  return (
    <group position={[0, 0, z]}>
      {[-RACK_DEPTH / 2, RACK_DEPTH / 2].map((x) => (
        <Steel
          key={`end-post-${x}`}
          color="#07559a"
          args={[POST_SIZE, rackHeight, POST_SIZE]}
          radius={0.012}
          position={[x, rackHeight / 2, 0]}
          castShadow
        />
      ))}
      <Line
        points={[
          [-RACK_DEPTH / 2, 0.18, 0],
          [RACK_DEPTH / 2, rackHeight * 0.48, 0],
          [-RACK_DEPTH / 2, rackHeight * 0.76, 0],
          [RACK_DEPTH / 2, rackHeight - 0.12, 0],
        ]}
        color="#07559a"
        lineWidth={2.3}
      />
    </group>
  )
}

function RollerBed({ y, z }) {
  return (
    <group position={[0, y, z]}>
      {Array.from({ length: 5 }).map((_, rollerIndex) => {
        const rollerZ = (rollerIndex - 2) * 0.18
        return (
          <mesh
            key={`roller-${rollerIndex}`}
            position={[0, 0, rollerZ]}
            rotation={[0, 0, Math.PI / 2]}
            castShadow
          >
            <cylinderGeometry args={[0.026, 0.026, RACK_DEPTH * 0.82, 10]} />
            <meshStandardMaterial color="#a8b0b4" roughness={0.28} metalness={0.86} />
          </mesh>
        )
      })}
    </group>
  )
}

function RackSlot({ band, row, displayPosition, column, level, position, occupied }) {
  const [hovered, setHovered] = useState(false)
  return (
    <group
      position={position}
      onPointerOver={(event) => {
        event.stopPropagation()
        setHovered(true)
        document.body.style.cursor = 'pointer'
      }}
      onPointerOut={() => {
        setHovered(false)
        document.body.style.cursor = 'default'
      }}
    >
      {hovered && (
        <Html center position={[0, 0.42, 0]} distanceFactor={7.5} zIndexRange={[70, 0]}>
          <div className="pointer-events-none min-w-max rounded-lg border border-sky-300/45 bg-slate-950/95 px-3 py-2 text-center text-[11px] font-semibold leading-tight text-white shadow-xl backdrop-blur-sm">
            {`Banda ${band} · Posición ${displayPosition ?? row}`}
            <div className="mt-1 text-[9px] font-normal uppercase tracking-[0.12em] text-sky-300">
              {`Columna ${column} · Altura ${level} · ${occupied ? 'Ocupada' : 'Vacía'}`}
            </div>
          </div>
        </Html>
      )}
      <RoundedBox args={[RACK_DEPTH * 0.72, 0.025, SLOT_DEPTH * 0.72]} radius={0.01}>
        <meshStandardMaterial
          color={occupied ? '#1c6b55' : hovered ? '#4dc994' : '#2a7760'}
          transparent
          opacity={occupied ? 0.18 : hovered ? 0.72 : 0.34}
          roughness={0.72}
        />
      </RoundedBox>
      {!occupied && (
        <Text
          position={[0, 0.026, 0]}
          rotation={[-Math.PI / 2, 0, 0]}
          fontSize={0.072}
          color="#d7fff0"
          anchorX="center"
          anchorY="middle"
          outlineWidth={0.003}
          outlineColor="#173e32"
        >
          {`${band}/${displayPosition ?? row}/${column}/${level}`}
        </Text>
      )}
    </group>
  )
}

function FreeFloorSlot({ band, row, column, position, occupied, levels }) {
  const [hovered, setHovered] = useState(false)

  return (
    <group
      position={position}
      onPointerOver={(event) => {
        event.stopPropagation()
        setHovered(true)
        document.body.style.cursor = 'pointer'
      }}
      onPointerOut={() => {
        setHovered(false)
        document.body.style.cursor = 'default'
      }}
    >
      {hovered && (
        <Html center position={[0, 0.42, 0]} distanceFactor={7.5} zIndexRange={[70, 0]}>
          <div className="pointer-events-none min-w-max rounded-lg border border-amber-300/50 bg-slate-950/95 px-3 py-2 text-center text-[11px] font-semibold leading-tight text-white shadow-xl backdrop-blur-sm">
            {`Banda ${band} · Fila ${row}`}
            <div className="mt-1 text-[9px] font-normal uppercase tracking-[0.12em] text-amber-300">
              {`Columna ${column} · ${occupied ? 'Ocupada' : 'Vacía'} · ${levels} nivel(es)`}
            </div>
          </div>
        </Html>
      )}
      <RoundedBox args={[RACK_DEPTH * 0.82, hovered ? 0.07 : 0.035, SLOT_DEPTH * 0.78]} radius={0.025}>
        <meshStandardMaterial
          color={occupied ? '#26785c' : hovered ? '#e1b83d' : '#c7a52e'}
          transparent
          opacity={occupied ? 0.34 : hovered ? 0.78 : 0.34}
          roughness={0.76}
        />
      </RoundedBox>
      {!occupied && (
        <Text
          position={[0, 0.04, 0]}
          rotation={[-Math.PI / 2, 0, 0]}
          fontSize={0.068}
          color="#fff4bd"
          anchorX="center"
          anchorY="middle"
          outlineWidth={0.003}
          outlineColor="#59490e"
        >
          {`${band}/${row}/${column}`}
        </Text>
      )}
    </group>
  )
}

function FreeFloorStorage({ layout, occupiedKeys }) {
  return (
    <group>
      {layout.bandas.flatMap((band, bandIndex) => (
        layout.rowsByBand[band].map((row, rowIndex) => {
          const x = layout.bandCenters[bandIndex] + layout.rowOffsetsByBand[band][rowIndex]
          const rowHalfWidth = RACK_DEPTH * 0.48
          const frontZ = -layout.runDepth / 2
          const backZ = layout.runDepth / 2

          return (
            <group key={`free-floor-row-${band}-${row}`}>
              {/* Demarcación longitudinal de la fila directamente sobre la losa. */}
              {[-rowHalfWidth, rowHalfWidth].map((offset) => (
                <Line
                  key={`row-side-${offset}`}
                  points={[[x + offset, 0.026, frontZ], [x + offset, 0.026, backZ]]}
                  color="#f0c928"
                  lineWidth={2.1}
                />
              ))}
              {[frontZ, backZ].map((z) => (
                <Line
                  key={`row-end-${z}`}
                  points={[[x - rowHalfWidth, 0.026, z], [x + rowHalfWidth, 0.026, z]]}
                  color="#f0c928"
                  lineWidth={2.1}
                />
              ))}
              <Text
                position={[x, 0.035, frontZ + 0.24]}
                rotation={[-Math.PI / 2, 0, 0]}
                fontSize={0.105}
                color="#fff2a2"
                anchorX="center"
                anchorY="middle"
                outlineWidth={0.004}
                outlineColor="#5b4a0b"
              >
                {`${String(band).toUpperCase()} · FILA ${row}`}
              </Text>

              {layout.columnas.map((column, columnIndex) => {
                const occupied = layout.niveles.some((level) => (
                  occupiedKeys.has(`${band}::${row}::${column}::${level}`)
                ))
                return (
                  <FreeFloorSlot
                    key={`free-slot-${band}-${row}-${column}`}
                    band={band}
                    row={row}
                    column={column}
                    position={[x, 0.035, layout.columnCenters[columnIndex]]}
                    occupied={occupied}
                    levels={layout.niveles.length}
                  />
                )
              })}
            </group>
          )
        })
      ))}
    </group>
  )
}

function getLadderStops(layout) {
  return layout.bandas.flatMap((band, bandIndex) => (
    layout.rowsByBand[band].map((row, rowIndex) => {
      const bandX = layout.bandCenters[bandIndex]
      const aisleX = layout.aisleCenters.reduce((nearest, candidate) => (
        Math.abs(candidate - bandX) < Math.abs(nearest - bandX) ? candidate : nearest
      ), layout.aisleCenters[0] ?? bandX)

      return {
        band,
        row,
        displayPosition: layout.positionLabelsByBand[band]?.[rowIndex] ?? row,
        positionZ: layout.positionCentersByBand[band]?.[rowIndex] ?? 0,
        aisleX,
        direction: bandX >= aisleX ? 1 : -1,
      }
    })
  ))
}

function MobileRackLadder({ layout, ladderState }) {
  const ladderRef = useRef(null)
  const stops = useMemo(() => getLadderStops(layout), [layout])
  const stop = stops[Math.min(ladderState.rowIndex, Math.max(stops.length - 1, 0))]
  const targetX = stop?.aisleX ?? 0
  const targetZ = stop?.positionZ ?? 0
  const targetRotation = stop?.direction === -1 ? Math.PI : 0
  const ladderHeight = Math.min(Math.max(layout.rackHeight - 0.28, 2.8), 5.1)
  const stepCount = Math.max(Math.ceil(ladderHeight / 0.32), 8)
  const slopeRun = 1.05

  useFrame((_, delta) => {
    if (!ladderRef.current) return
    ladderRef.current.position.x = MathUtils.damp(ladderRef.current.position.x, targetX, 5.5, delta)
    ladderRef.current.position.z = MathUtils.damp(ladderRef.current.position.z, targetZ, 5.5, delta)
    ladderRef.current.rotation.y = MathUtils.damp(ladderRef.current.rotation.y, targetRotation, 7, delta)
  })

  return (
    <group ref={ladderRef} position={[targetX, 0, targetZ]} rotation={[0, targetRotation, 0]}>
      <RoundedBox args={[1.45, 0.12, 0.96]} radius={0.035} position={[0, 0.12, 0]} castShadow>
        <meshStandardMaterial color="#e6bd18" roughness={0.42} metalness={0.58} />
      </RoundedBox>

      {[-0.39, 0.39].flatMap((z) => [-0.52, 0.52].map((x) => (
        <mesh key={`ladder-wheel-${x}-${z}`} position={[x, 0.08, z]} rotation={[Math.PI / 2, 0, 0]} castShadow>
          <cylinderGeometry args={[0.105, 0.105, 0.065, 18]} />
          <meshStandardMaterial color="#222a2f" roughness={0.62} metalness={0.42} />
        </mesh>
      )))}

      {[-0.38, 0.38].map((z) => (
        <RoundedBox
          key={`ladder-rail-${z}`}
          args={[0.075, ladderHeight, 0.075]}
          radius={0.02}
          position={[0, ladderHeight / 2 + 0.14, z]}
          rotation={[0, 0, -Math.atan(slopeRun / ladderHeight)]}
          castShadow
        >
          <meshStandardMaterial color="#12669b" roughness={0.36} metalness={0.7} />
        </RoundedBox>
      ))}

      {Array.from({ length: stepCount }).map((_, index) => {
        const ratio = (index + 1) / (stepCount + 1)
        return (
          <RoundedBox
            key={`ladder-step-${index}`}
            args={[0.28, 0.055, 0.72]}
            radius={0.014}
            position={[-slopeRun / 2 + ratio * slopeRun, 0.18 + ratio * ladderHeight, 0]}
            castShadow
          >
            <meshStandardMaterial color="#d7dde0" roughness={0.34} metalness={0.82} />
          </RoundedBox>
        )
      })}

      <RoundedBox
        args={[0.58, 0.1, 0.86]}
        radius={0.025}
        position={[slopeRun / 2, ladderHeight + 0.16, 0]}
        castShadow
      >
        <meshStandardMaterial color="#e6bd18" roughness={0.4} metalness={0.62} />
      </RoundedBox>
      {[-0.39, 0.39].map((z) => (
        <RoundedBox
          key={`ladder-guard-${z}`}
          args={[0.065, 0.92, 0.065]}
          radius={0.018}
          position={[slopeRun / 2 + 0.2, ladderHeight + 0.62, z]}
        >
          <meshStandardMaterial color="#12669b" roughness={0.36} metalness={0.72} />
        </RoundedBox>
      ))}
      <RoundedBox
        args={[0.065, 0.065, 0.84]}
        radius={0.018}
        position={[slopeRun / 2 + 0.2, ladderHeight + 1.03, 0]}
      >
        <meshStandardMaterial color="#12669b" roughness={0.36} metalness={0.72} />
      </RoundedBox>

      <Text
        position={[0, 0.48, -0.5]}
        rotation={[0, Math.PI, 0]}
        fontSize={0.11}
        color="#fff4a8"
        anchorX="center"
        outlineWidth={0.006}
        outlineColor="#51420a"
      >
        ESCALERA MÓVIL
      </Text>
    </group>
  )
}

function RackLadderControls({ layout, ladderState, setLadderState, navigationInput }) {
  const [touchMode, setTouchMode] = useState(false)
  const stops = useMemo(() => getLadderStops(layout), [layout])
  const rowIndex = Math.min(ladderState.rowIndex, Math.max(stops.length - 1, 0))
  const stop = stops[rowIndex]

  const syncLadder = (nextState) => {
    const nextRowIndex = Math.min(nextState.rowIndex, Math.max(stops.length - 1, 0))
    const nextStop = stops[nextRowIndex]
    navigationInput.current.ladder = nextState.active ? {
      active: true,
      x: nextStop?.aisleX ?? 0,
      z: nextStop?.positionZ ?? 0,
      maxY: Math.max(layout.rackHeight - 0.22, 1.65),
    } : null
    navigationInput.current.climbDirection = 0
    setLadderState(nextState)
  }

  const moveRow = (direction) => {
    const nextIndex = (rowIndex + direction + stops.length) % stops.length
    syncLadder({ ...ladderState, rowIndex: nextIndex, active: false })
  }
  const holdClimb = (direction) => (event) => {
    event.preventDefault()
    event.currentTarget.setPointerCapture?.(event.pointerId)
    navigationInput.current.climbDirection = direction
  }
  const stopClimb = () => { navigationInput.current.climbDirection = 0 }

  useEffect(() => {
    const media = window.matchMedia('(pointer: coarse)')
    const update = () => setTouchMode(media.matches)
    update()
    media.addEventListener?.('change', update)
    return () => media.removeEventListener?.('change', update)
  }, [])

  useEffect(() => {
    const isFormTarget = (target) => (
      target instanceof HTMLElement
      && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName))
    )
    const handleKeyDown = (event) => {
      if (touchMode || isFormTarget(event.target) || !stops.length || !layout.columnas.length) return

      if (event.code === 'KeyE' && !event.repeat) {
        event.preventDefault()
        syncLadder({ ...ladderState, rowIndex, active: !ladderState.active })
        return
      }
      if (ladderState.active || event.repeat) return

      if (event.code === 'KeyQ') {
        event.preventDefault()
        moveRow(-1)
      } else if (event.code === 'KeyR') {
        event.preventDefault()
        moveRow(1)
      }
    }

    window.addEventListener('keydown', handleKeyDown, { passive: false })
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [ladderState, rowIndex, touchMode])

  useEffect(() => () => {
    navigationInput.current.ladder = null
    navigationInput.current.climbDirection = 0
  }, [navigationInput])

  if (!stops.length || !layout.columnas.length) return null

  return (
    <div className="absolute right-3 top-1/2 z-30 w-48 -translate-y-1/2 rounded-lg border border-amber-300/35 bg-slate-950/90 p-2.5 text-white shadow-2xl backdrop-blur-md">
      <div className="text-[9px] font-bold uppercase tracking-[0.16em] text-amber-300">Escalera móvil</div>
      <div className="mt-1 text-[10px] font-semibold leading-tight">{`Banda ${stop.band} · Posición ${stop.displayPosition}`}</div>
      <div className="mt-0.5 text-[9px] text-slate-400">3 columnas de profundidad</div>

      {touchMode ? (
        <>
          <div className="mt-2 grid grid-cols-2 gap-1">
            <button type="button" disabled={ladderState.active} className="rounded border border-white/15 bg-white/10 px-2 py-2 text-[10px] hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40" onClick={() => moveRow(-1)}>← Posición</button>
            <button type="button" disabled={ladderState.active} className="rounded border border-white/15 bg-white/10 px-2 py-2 text-[10px] hover:bg-white/20 disabled:cursor-not-allowed disabled:opacity-40" onClick={() => moveRow(1)}>Posición →</button>
          </div>
          <button
            type="button"
            className={`mt-1.5 w-full rounded px-2 py-2 text-[10px] font-bold uppercase tracking-[0.1em] ${ladderState.active ? 'bg-rose-600 hover:bg-rose-500' : 'bg-amber-500 text-slate-950 hover:bg-amber-400'}`}
            onClick={() => syncLadder({ ...ladderState, rowIndex, active: !ladderState.active })}
          >
            {ladderState.active ? 'Salir de la escalera' : 'Usar escalera'}
          </button>
        </>
      ) : (
        <div className="mt-2 space-y-1 border-t border-white/10 pt-2 text-[9px] text-slate-300">
          <div className="flex items-center justify-between"><span>Cambiar posición</span><span className="font-mono text-amber-300">Q / R</span></div>
          <div className={`mt-1.5 flex items-center justify-center gap-2 rounded px-2 py-1.5 font-bold uppercase tracking-[0.1em] ${ladderState.active ? 'bg-rose-600' : 'bg-amber-500 text-slate-950'}`}>
            <span className="rounded border border-current/40 px-1.5 py-0.5 font-mono">E</span>
            {ladderState.active ? 'Salir' : 'Usar escalera'}
          </div>
        </div>
      )}

      {ladderState.active && (
        <div className="mt-1.5 grid grid-cols-2 gap-1">
          <button
            type="button"
            className="touch-none rounded bg-sky-600 px-2 py-2 text-[9px] font-bold hover:bg-sky-500"
            onPointerDown={holdClimb(1)}
            onPointerUp={stopClimb}
            onPointerCancel={stopClimb}
            onPointerLeave={stopClimb}
          >
            ▲ SUBIR
          </button>
          <button
            type="button"
            className="touch-none rounded bg-sky-700 px-2 py-2 text-[9px] font-bold hover:bg-sky-600"
            onPointerDown={holdClimb(-1)}
            onPointerUp={stopClimb}
            onPointerCancel={stopClimb}
            onPointerLeave={stopClimb}
          >
            ▼ BAJAR
          </button>
        </div>
      )}
      <div className="mt-1.5 text-[8px] leading-tight text-slate-500">
        {touchMode
          ? 'Sube o baja con estos botones o con el joystick izquierdo.'
          : 'No necesitas liberar el mouse. En la escalera usa W/S o las flechas para subir y bajar.'}
      </div>
    </div>
  )
}

function RackBand({ band, bandIndex, layout, occupiedKeys }) {
  const {
    columnas,
    niveles,
    columnBoundaries,
    rackHeight,
    runDepth,
    rowsByBand,
    positionCentersByBand,
  } = layout
  const positions = rowsByBand[band]
  const columnCenters = layout.columnCentersByBand[band]
  const bandX = layout.bandCenters[bandIndex]
  const bandWidth = layout.bandWidths[bandIndex]
  const aisleX = layout.aisleCenters.reduce((nearest, candidate) => (
    Math.abs(candidate - bandX) < Math.abs(nearest - bandX) ? candidate : nearest
  ), layout.aisleCenters[0] ?? bandX)
  const faceDirection = aisleX < bandX ? -1 : 1
  const faceX = faceDirection * (bandWidth / 2 + 0.075)
  const faceRotation = faceDirection < 0 ? Math.PI / 2 : -Math.PI / 2
  const positionBoundaries = Array.from({ length: positions.length + 1 }, (_, index) => (
    -runDepth / 2 + index * SLOT_DEPTH
  ))

  return (
    <group position={[bandX, 0, 0]}>
      {positionBoundaries.flatMap((z) => columnBoundaries.map((x) => (
        <Steel
          key={`rack-post-${band}-${x}-${z}`}
          color="#07559a"
          args={[POST_SIZE, rackHeight, POST_SIZE]}
          radius={0.012}
          position={[x, rackHeight / 2, z]}
          castShadow
        />
      )))}

      {positionBoundaries.map((z) => (
        <Line
          key={`rack-brace-${band}-${z}`}
          points={[
            [columnBoundaries[0], 0.2, z],
            [columnBoundaries.at(-1), rackHeight * 0.48, z],
            [columnBoundaries[0], rackHeight * 0.76, z],
            [columnBoundaries.at(-1), rackHeight - 0.12, z],
          ]}
          color="#07559a"
          lineWidth={2.1}
        />
      ))}

      {niveles.flatMap((height, heightIndex) => {
        const beamY = heightIndex * LEVEL_HEIGHT + 0.08
        return [
          ...positionBoundaries.map((z) => (
            <Steel
              key={`rack-beam-${band}-${height}-${z}`}
              color="#ed7614"
              args={[bandWidth + 0.06, 0.13, 0.12]}
              radius={0.012}
              position={[0, beamY, z]}
              castShadow
            />
          )),
          ...positions.flatMap((position, positionIndex) => columnas.map((column, columnIndex) => {
            const x = columnCenters[columnIndex]
            const z = positionCentersByBand[band][positionIndex]
            const displayPosition = layout.positionLabelsByBand[band][positionIndex]
            const occupied = occupiedKeys.has(`${band}::${position}::${column}::${height}`)
            return (
              <RackSlot
                key={`rack-slot-${band}-${position}-${column}-${height}`}
                band={band}
                row={position}
                displayPosition={displayPosition}
                column={column}
                level={height}
                position={[x, beamY + 0.13, z]}
                occupied={occupied}
              />
            )
          })),
        ]
      })}

      <Steel
        color="#f3cf00"
        args={[bandWidth + 0.42, 0.16, 0.18]}
        radius={0.025}
        position={[0, 0.09, -runDepth / 2 - 0.2]}
        castShadow
      />

      {positions.map((position, positionIndex) => (
        <Text
          key={`rack-position-${band}-${position}`}
          position={[faceX, 0.22, positionCentersByBand[band][positionIndex]]}
          rotation={[0, faceRotation, 0]}
          fontSize={0.105}
          color="#ffffff"
          anchorX="center"
          outlineWidth={0.012}
          outlineColor="#17202a"
        >
          {`P${layout.positionLabelsByBand[band][positionIndex]}`}
        </Text>
      ))}

      {columnas.map((column, columnIndex) => (
        <Text
          key={`rack-column-${band}-${column}`}
          position={[columnCenters[columnIndex], 0.3, -runDepth / 2 - 0.32]}
          fontSize={0.095}
          color="#ffe760"
          anchorX="center"
          outlineWidth={0.008}
          outlineColor="#2f3308"
        >
          {`C${column}`}
        </Text>
      ))}

      <Text
        position={[0, 0.48, -runDepth / 2 - 0.36]}
        fontSize={0.14}
        color="#ffe760"
        anchorX="center"
        outlineWidth={0.012}
        outlineColor="#2f3308"
      >
        {`BANDA ${band}`}
      </Text>
    </group>
  )
}

function Evaporator({ position }) {
  return (
    <group position={position}>
      <RoundedBox args={[1.45, 0.72, 0.52]} radius={0.055} castShadow>
        <meshStandardMaterial color="#e4e9eb" roughness={0.4} metalness={0.4} />
      </RoundedBox>
      {[-0.42, 0.42].map((x) => (
        <group key={`evap-fan-${x}`} position={[x, 0, -0.28]}>
          <mesh rotation={[Math.PI / 2, 0, 0]}>
            <torusGeometry args={[0.22, 0.018, 10, 30]} />
            <meshStandardMaterial color="#53616a" roughness={0.35} metalness={0.72} />
          </mesh>
          {[0, Math.PI / 2].map((rotation) => (
            <RoundedBox
              key={rotation}
              args={[0.34, 0.035, 0.025]}
              radius={0.008}
              rotation={[0, 0, rotation]}
            >
              <meshStandardMaterial color="#738087" roughness={0.4} metalness={0.7} />
            </RoundedBox>
          ))}
        </group>
      ))}
    </group>
  )
}

function SlidingColdRoomDoor({ layout, roomHeight, roomDepth, name }) {
  const doorRef = useRef(null)
  const [isOpen, setIsOpen] = useState(false)
  const [isHovered, setIsHovered] = useState(false)
  useCursor(isHovered)

  const frontZ = -roomDepth / 2 - 0.08
  const doorWidth = Math.min(Math.max(layout.width * 0.46, 3.2), layout.width - 1.15)
  const doorHeight = Math.min(Math.max(layout.rackHeight * 0.72, 3.05), roomHeight - 0.42)
  const windowWidth = Math.min(1.12, doorWidth * 0.34)
  const windowHeight = Math.min(0.82, doorHeight * 0.27)
  const sidePanelWidth = (doorWidth - windowWidth) / 2
  const horizontalPanelHeight = (doorHeight - windowHeight) / 2
  const openX = doorWidth + 0.22
  const facadeSideWidth = (layout.width - doorWidth) / 2
  const facadeHeaderHeight = roomHeight - doorHeight

  useFrame((_, delta) => {
    if (!doorRef.current) return
    const targetX = isOpen ? openX : 0
    const smoothing = 1 - Math.exp(-5.2 * delta)
    doorRef.current.position.x += (targetX - doorRef.current.position.x) * smoothing
  })

  const panelMaterial = (
    <meshStandardMaterial
      color={isHovered ? '#f8fbfa' : '#e7eceb'}
      roughness={0.34}
      metalness={0.28}
    />
  )

  return (
    <group>
      {/* Frente frigorífico fijo alrededor del vano de acceso. */}
      {[-1, 1].map((side) => (
        <mesh
          key={`front-wall-${side}`}
          position={[side * (doorWidth / 2 + facadeSideWidth / 2), roomHeight / 2, frontZ + 0.09]}
          receiveShadow
        >
          <boxGeometry args={[facadeSideWidth, roomHeight, 0.16]} />
          <meshStandardMaterial color="#edf1f2" roughness={0.48} metalness={0.15} />
        </mesh>
      ))}
      <mesh position={[0, doorHeight + facadeHeaderHeight / 2, frontZ + 0.09]} receiveShadow>
        <boxGeometry args={[doorWidth, facadeHeaderHeight, 0.16]} />
        <meshStandardMaterial color="#edf1f2" roughness={0.48} metalness={0.15} />
      </mesh>

      {/* Riel exterior: la hoja se guarda a la derecha al abrir. */}
      <RoundedBox
        args={[doorWidth * 2.15, 0.13, 0.15]}
        radius={0.025}
        position={[doorWidth * 0.54, doorHeight + 0.2, frontZ - 0.1]}
        castShadow
      >
        <meshStandardMaterial color="#59666d" roughness={0.3} metalness={0.82} />
      </RoundedBox>

      <group
        ref={doorRef}
        position={[0, doorHeight / 2 + 0.04, frontZ - 0.1]}
        onClick={(event) => {
          event.stopPropagation()
          setIsOpen((current) => !current)
        }}
        onPointerOver={(event) => {
          event.stopPropagation()
          setIsHovered(true)
        }}
        onPointerOut={() => setIsHovered(false)}
      >
        {/* Panel segmentado para que la ventana sea un vano real y no un vidrio sobre una pared opaca. */}
        {[-1, 1].map((side) => (
          <RoundedBox
            key={`door-side-${side}`}
            args={[sidePanelWidth, doorHeight, 0.18]}
            radius={0.045}
            position={[side * (windowWidth / 2 + sidePanelWidth / 2), 0, 0]}
            castShadow
          >
            {panelMaterial}
          </RoundedBox>
        ))}
        {[-1, 1].map((side) => (
          <RoundedBox
            key={`door-horizontal-${side}`}
            args={[windowWidth, horizontalPanelHeight, 0.18]}
            radius={0.035}
            position={[0, side * (windowHeight / 2 + horizontalPanelHeight / 2), 0]}
            castShadow
          >
            {panelMaterial}
          </RoundedBox>
        ))}

        {/* Marco y vidrio de inspección. */}
        {[-1, 1].map((side) => (
          <RoundedBox
            key={`window-vertical-${side}`}
            args={[0.075, windowHeight + 0.13, 0.08]}
            radius={0.018}
            position={[side * (windowWidth / 2 + 0.035), 0, -0.105]}
          >
            <meshStandardMaterial color="#69777e" roughness={0.26} metalness={0.8} />
          </RoundedBox>
        ))}
        {[-1, 1].map((side) => (
          <RoundedBox
            key={`window-horizontal-${side}`}
            args={[windowWidth + 0.13, 0.075, 0.08]}
            radius={0.018}
            position={[0, side * (windowHeight / 2 + 0.035), -0.105]}
          >
            <meshStandardMaterial color="#69777e" roughness={0.26} metalness={0.8} />
          </RoundedBox>
        ))}
        <RoundedBox args={[windowWidth, windowHeight, 0.028]} radius={0.022} position={[0, 0, -0.115]}>
          <meshPhysicalMaterial
            color="#bce7ed"
            roughness={0.08}
            metalness={0}
            transmission={0.78}
            transparent
            opacity={0.48}
            thickness={0.08}
            ior={1.45}
          />
        </RoundedBox>

        <GarateBrand3D
          position={[0, 0, -0.205]}
          rotation={[0, Math.PI, 0]}
          logoWidth={Math.min(1.18, doorWidth * 0.34)}
          logoY={doorHeight * 0.34}
          nameY={doorHeight * 0.19}
          name={name || 'CÁMARA RACKEABLE'}
          fontSize={0.13}
          textColor="#25343a"
          outlineColor="#f4f7f6"
        />

        {/* Tirador accesible desde el borde que inicia el cierre de derecha a izquierda. */}
        <RoundedBox
          args={[0.075, 0.58, 0.11]}
          radius={0.025}
          position={[-doorWidth / 2 + 0.24, -doorHeight * 0.05, -0.17]}
          castShadow
        >
          <meshStandardMaterial color="#df5b23" roughness={0.27} metalness={0.64} />
        </RoundedBox>

        {[-doorWidth * 0.32, doorWidth * 0.32].map((x) => (
          <group key={`door-roller-${x}`} position={[x, doorHeight / 2 + 0.12, 0]}>
            <mesh rotation={[Math.PI / 2, 0, 0]} castShadow>
              <cylinderGeometry args={[0.075, 0.075, 0.065, 18]} />
              <meshStandardMaterial color="#303a40" roughness={0.3} metalness={0.82} />
            </mesh>
            <RoundedBox args={[0.055, 0.22, 0.07]} radius={0.012} position={[0, -0.1, 0]}>
              <meshStandardMaterial color="#59666d" roughness={0.3} metalness={0.8} />
            </RoundedBox>
          </group>
        ))}

        <Text
          position={[0, -doorHeight * 0.39, -0.105]}
          rotation={[0, Math.PI, 0]}
          fontSize={0.105}
          color="#35434a"
          anchorX="center"
          fontWeight={700}
        >
          {isOpen ? 'CLICK PARA CERRAR' : 'CLICK PARA ABRIR'}
        </Text>
      </group>
    </group>
  )
}

function CameraShell({ layout, name }) {
  const depth = layout.runDepth + 3
  const height = layout.rackHeight + 1.25
  const halfWidth = layout.width / 2
  const backZ = depth / 2
  const lightCount = Math.max(Math.ceil(layout.runDepth / 2.3), 4)

  return (
    <group>
      <mesh position={[0, 0, -2.5]} rotation={[-Math.PI / 2, 0, 0]} receiveShadow>
        <planeGeometry args={[layout.width + 2, depth + 8]} />
        <meshStandardMaterial color="#b8bbb8" roughness={0.94} metalness={0.02} />
      </mesh>
      {[-halfWidth, halfWidth].map((x) => (
        <mesh key={`wall-${x}`} position={[x, height / 2, 0]} receiveShadow>
          <boxGeometry args={[0.12, height, depth]} />
          <meshStandardMaterial color="#eef2f2" roughness={0.48} metalness={0.16} />
        </mesh>
      ))}
      <mesh position={[0, height / 2, backZ]} receiveShadow>
        <boxGeometry args={[layout.width, height, 0.12]} />
        <meshStandardMaterial color="#edf1f2" roughness={0.48} metalness={0.15} />
      </mesh>
      <GarateBrand3D
        position={[0, height * 0.62, backZ - 0.075]}
        rotation={[0, Math.PI, 0]}
        logoWidth={Math.min(layout.width * 0.42, 3.5)}
        logoY={0.18}
        nameY={-0.72}
        name={name || 'CÁMARA RACKEABLE'}
        fontSize={0.24}
      />
      <mesh position={[0, height, 0]} receiveShadow>
        <boxGeometry args={[layout.width, 0.12, depth]} />
        <meshStandardMaterial color="#8d9396" roughness={0.38} metalness={0.42} />
      </mesh>
      {Array.from({ length: Math.max(Math.floor(depth / 0.28), 12) }).map((_, index) => {
        const z = -depth / 2 + index * 0.28
        return (
          <Line
            key={`ceiling-rib-${index}`}
            points={[[-halfWidth, height - 0.065, z], [halfWidth, height - 0.065, z]]}
            color="#666d71"
            transparent
            opacity={0.38}
            lineWidth={0.55}
          />
        )
      })}
      {Array.from({ length: lightCount }).map((_, index) => {
        const z = -layout.runDepth / 2 + (index + 0.5) * layout.runDepth / lightCount
        return (
          <group key={`cold-room-light-${index}`} position={[0, height - 0.15, z]}>
            <RoundedBox args={[0.12, 0.06, 1.05]} radius={0.025}>
              <meshStandardMaterial color="#f6ffff" emissive="#dffbff" emissiveIntensity={2.2} />
            </RoundedBox>
            <pointLight color="#e8fbff" intensity={0.22} distance={5.5} position={[0, -0.3, 0]} />
          </group>
        )
      })}
      {[-layout.width * 0.27, 0, layout.width * 0.27].map((x) => (
        <Evaporator key={`evaporator-${x}`} position={[x, height - 0.72, backZ - 0.36]} />
      ))}
      {layout.aisleCenters.map((aisleCenter) => (
        <group key={`aisle-${aisleCenter}`} position={[aisleCenter, 0, 0]}>
          <RoundedBox
            args={[AISLE_WIDTH * 0.82, 0.035, layout.runDepth + 0.65]}
            radius={0.012}
            position={[0, 0.025, 0]}
          >
            <meshStandardMaterial color="#aaadab" roughness={0.92} />
          </RoundedBox>
          {[-AISLE_WIDTH / 2, AISLE_WIDTH / 2].map((x) => (
            <Line
              key={`aisle-line-${x}`}
              points={[[x, 0.025, -layout.runDepth / 2 - 0.35], [x, 0.025, layout.runDepth / 2 + 0.35]]}
              color="#e7cf0f"
              lineWidth={2.2}
            />
          ))}
        </group>
      ))}
      <SlidingColdRoomDoor layout={layout} roomHeight={height} roomDepth={depth} name={name} />
    </group>
  )
}

function CameraRackScene({
  layout,
  folios,
  codigo,
  titulo,
  selectedFolio,
  onSelect,
  cameraType,
  navigationInput,
  ladderState,
}) {
  const isFreeFloor = cameraType === 'planta_libre'
  const occupiedKeys = useMemo(() => new Set((folios || []).map((folio) => (
    `${folio.banda}::${folio.fila}::${folio.columna}::${isFreeFloor ? folio.nivel : folio.altura}`
  ))), [folios, isFreeFloor])

  const positionedFolios = useMemo(() => (folios || []).map((folio, fallbackIndex) => {
    const bandIndex = Math.max(layout.bandas.indexOf(String(folio.banda)), 0)
    const band = layout.bandas[bandIndex]
    const rowIndex = Math.max(layout.rowsByBand[band].indexOf(String(folio.fila)), 0)
    const columnIndex = Math.max(layout.columnas.indexOf(String(folio.columna)), 0)
    const physicalHeight = isFreeFloor ? folio.nivel : folio.altura
    const levelIndex = Math.max(layout.niveles.indexOf(String(physicalHeight)), 0)
    const displayPosition = isFreeFloor
      ? String(folio.fila)
      : layout.positionLabelsByBand[band][rowIndex]
    const bandX = layout.bandCenters[bandIndex]
    const aisleX = layout.aisleCenters.reduce((nearest, candidate) => (
      Math.abs(candidate - bandX) < Math.abs(nearest - bandX) ? candidate : nearest
    ), layout.aisleCenters[0] ?? bandX)
    return {
      ...folio,
      fallbackIndex,
      physicalHeight,
      displayPosition,
      ubicacionVisual: isFreeFloor
        ? null
        : `${folio.banda}/${displayPosition}/${folio.columna}`,
      scenePosition: [
        isFreeFloor
          ? bandX + layout.rowOffsetsByBand[band][rowIndex]
          : bandX + layout.columnCentersByBand[band][columnIndex],
        0.66 + levelIndex * (isFreeFloor ? 0.82 : LEVEL_HEIGHT),
        isFreeFloor
          ? layout.columnCenters[columnIndex]
          : layout.positionCentersByBand[band][rowIndex],
      ],
      sceneRotation: isFreeFloor ? [0, 0, 0] : [0, aisleX < bandX ? Math.PI / 2 : -Math.PI / 2, 0],
    }
  }), [folios, layout, isFreeFloor])

  const sceneHeight = layout.rackHeight + 1.25

  return (
    <>
      <color attach="background" args={['#dfe4e5']} />
      <fog attach="fog" args={['#dfe4e5', layout.runDepth * 1.1, layout.runDepth * 2.8]} />
      <ambientLight intensity={0.38} />
      <hemisphereLight skyColor="#f3fbff" groundColor="#596269" intensity={0.46} />
      <directionalLight
        position={[-5, sceneHeight + 4, -layout.runDepth / 2]}
        intensity={1.15}
        castShadow
        shadow-mapSize={[2048, 2048]}
        shadow-normalBias={0.025}
      />
      <Environment preset="warehouse" environmentIntensity={0.48} />

      <CameraShell layout={layout} name={[codigo, titulo].filter(Boolean).join(' · ')} />
      <FirstPersonController
        inputRef={navigationInput}
        initialPosition={[0, 1.65, -layout.runDepth / 2 - 5.2]}
        bounds={{
          minX: -layout.width / 2 + 0.32,
          maxX: layout.width / 2 - 0.32,
          minZ: -layout.runDepth / 2 - 5.55,
          maxZ: layout.runDepth / 2 + 1.12,
        }}
      />
      {isFreeFloor ? (
        <FreeFloorStorage layout={layout} occupiedKeys={occupiedKeys} />
      ) : (
        layout.bandas.map((band, bandIndex) => (
          <RackBand
            key={`rack-band-${band}`}
            band={band}
            bandIndex={bandIndex}
            layout={layout}
            occupiedKeys={occupiedKeys}
          />
        ))
      )}
      {!isFreeFloor && layout.niveles.length > 2 && (
        <MobileRackLadder layout={layout} ladderState={ladderState} />
      )}
      {positionedFolios.map((folio) => (
        <Pallet3D
          key={folio.id || folio.fallbackIndex}
          position={folio.scenePosition}
          rotation={folio.sceneRotation}
          folio={folio.folio}
          cajas={folio.cajas}
          especie={folio.especie}
          nivel={folio.physicalHeight}
          levelPrefix={isFreeFloor ? 'N' : 'ALT '}
          tooltip={isFreeFloor
            ? `Banda ${folio.banda} · Fila ${folio.fila} · Columna ${folio.columna} · Nivel ${folio.physicalHeight}`
            : `Banda ${folio.banda} · Posición ${folio.displayPosition} · Columna ${folio.columna} · Altura ${folio.physicalHeight}`}
          isSelected={selectedFolio?.id === folio.id}
          onClick={() => onSelect(folio)}
        />
      ))}

      <Text
        position={[0, sceneHeight - 0.65, layout.runDepth / 2 + 1.34]}
        rotation={[0, Math.PI, 0]}
        fontSize={0.28}
        color="#20313a"
        anchorX="center"
        fontWeight={700}
      >
        {`${codigo || ''} · ${titulo || (isFreeFloor ? 'CÁMARA PLANTA LIBRE' : 'CÁMARA RACKEABLE')}`}
      </Text>
    </>
  )
}

export default function CameraRackScene3D({ parametros, folios, codigo, titulo, tipo = 'rackeable' }) {
  const [selectedFolio, setSelectedFolio] = useState(null)
  const [ladderState, setLadderState] = useState({ rowIndex: 0, active: false })
  const navigationInput = useFirstPersonNavigationInput()
  const isFreeFloor = tipo === 'planta_libre'
  const layout = useMemo(
    () => isFreeFloor ? buildFreeFloorLayout(parametros) : buildRackLayout(parametros),
    [parametros, isFreeFloor],
  )
  const cameraPosition = useMemo(() => [
    0,
    1.65,
    -layout.runDepth / 2 - 5.2,
  ], [layout])

  return (
    <div className="relative h-[620px] w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-[0_22px_50px_-28px_rgba(15,23,42,0.72)]">
      <Canvas
        camera={{ position: cameraPosition, fov: 54, near: 0.1, far: 180 }}
        events={createFirstPersonPointerEvents}
        shadows
        dpr={[1, 1.75]}
        gl={{ antialias: true, toneMapping: 3, toneMappingExposure: 1.08 }}
        onPointerMissed={() => setSelectedFolio(null)}
      >
        <Suspense fallback={null}>
          <CameraRackScene
            layout={layout}
            folios={folios}
            codigo={codigo}
            titulo={titulo}
            selectedFolio={selectedFolio}
            cameraType={tipo}
            navigationInput={navigationInput}
            ladderState={ladderState}
            onSelect={(folio) => setSelectedFolio((current) => current?.id === folio.id ? null : folio)}
          />
        </Suspense>
      </Canvas>

      <div className="pointer-events-none absolute left-3 top-3 border border-white/10 bg-slate-950/82 px-3 py-2 text-[10px] text-slate-300 shadow-lg backdrop-blur-md">
        <div className="font-semibold uppercase tracking-[0.16em] text-white">
          {isFreeFloor ? 'Cámara de planta libre' : 'Cámara rackeable'}
        </div>
        <div className="mt-1 text-slate-400">Camina en primera persona · clic en la puerta para abrir</div>
        <div className="mt-2 border-t border-white/10 pt-2 font-mono text-[9px]">
          {layout.bandas.length} bandas · {layout.totalRows} {isFreeFloor ? 'filas' : 'posiciones'} · {layout.columnas.length} columnas · {layout.niveles.length} {isFreeFloor ? 'niveles' : 'alturas'}
        </div>
      </div>
      <div className="pointer-events-none absolute left-3 top-28 hidden items-center gap-3 border border-white/10 bg-slate-950/82 px-3 py-2 text-[9px] text-slate-300 backdrop-blur-md md:flex">
        {isFreeFloor ? (
          <>
            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#f0c928]" /> Filas demarcadas</span>
            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#a8abad]" /> Pasillos</span>
          </>
        ) : (
          <>
            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#07559a]" /> Montantes</span>
            <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#ed7614]" /> Vigas</span>
          </>
        )}
        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#2a7760]" /> Posiciones vacías</span>
        <span>{folios?.length || 0} folios</span>
      </div>
      <FirstPersonNavigationHud inputRef={navigationInput} />
      {!isFreeFloor && layout.niveles.length > 2 && (
        <RackLadderControls
          layout={layout}
          ladderState={ladderState}
          setLadderState={setLadderState}
          navigationInput={navigationInput}
        />
      )}
      <FolioInfoPanel
        folio={selectedFolio}
        levelLabel={isFreeFloor ? 'Nivel' : 'Altura'}
        levelValue={isFreeFloor ? selectedFolio?.nivel : selectedFolio?.altura}
        onClose={() => setSelectedFolio(null)}
      />
    </div>
  )
}
