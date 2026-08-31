import { useEffect, useMemo, useRef, useState } from 'react'
import { useFrame } from '@react-three/fiber'
import { Html, RoundedBox, Text, useCursor } from '@react-three/drei'
import {
  CanvasTexture,
  MathUtils,
  MeshPhysicalMaterial,
  MeshStandardMaterial,
  RepeatWrapping,
  SRGBColorSpace,
} from 'three'

const SPECIES_COLORS = {
  cereza: '#c92a2a',
  uva: '#7141a8',
  arandano: '#2858a6',
  kiwi: '#76932f',
  naranja: '#d96719',
  limon: '#d4aa16',
  mandarina: '#e6781f',
}

const DEFAULT_COLOR = '#536b62'
let sharedSurfaceTextures

function makeCanvasTexture(size, draw) {
  if (typeof document === 'undefined') return null

  const canvas = document.createElement('canvas')
  canvas.width = size
  canvas.height = size
  const context = canvas.getContext('2d')
  draw(context, size)

  const texture = new CanvasTexture(canvas)
  texture.colorSpace = SRGBColorSpace
  texture.wrapS = RepeatWrapping
  texture.wrapT = RepeatWrapping
  return texture
}

function createSurfaceTextures() {
  const wood = makeCanvasTexture(256, (context, size) => {
    context.fillStyle = '#a97845'
    context.fillRect(0, 0, size, size)

    for (let y = 5; y < size; y += 7) {
      const wave = 2 + (y % 13)
      context.beginPath()
      context.moveTo(0, y)
      for (let x = 0; x <= size; x += 12) {
        context.lineTo(x, y + Math.sin((x + y) * 0.055) * wave)
      }
      context.strokeStyle = y % 3 === 0 ? 'rgba(68, 37, 16, 0.22)' : 'rgba(255, 220, 165, 0.13)'
      context.lineWidth = y % 4 === 0 ? 2 : 1
      context.stroke()
    }

    ;[[44, 62, 10], [184, 150, 13], [112, 226, 7]].forEach(([x, y, radius]) => {
      context.beginPath()
      context.ellipse(x, y, radius * 1.8, radius, -0.18, 0, Math.PI * 2)
      context.strokeStyle = 'rgba(55, 29, 13, 0.48)'
      context.lineWidth = 3
      context.stroke()
      context.beginPath()
      context.ellipse(x, y, radius * 0.55, radius * 0.35, -0.18, 0, Math.PI * 2)
      context.fillStyle = 'rgba(55, 29, 13, 0.42)'
      context.fill()
    })
  })

  const cardboard = makeCanvasTexture(256, (context, size) => {
    context.fillStyle = '#b98a52'
    context.fillRect(0, 0, size, size)

    for (let y = 0; y < size; y += 3) {
      context.fillStyle = y % 9 === 0 ? 'rgba(85, 51, 23, 0.10)' : 'rgba(255, 237, 198, 0.055)'
      context.fillRect(0, y, size, 1)
    }

    for (let index = 0; index < 180; index += 1) {
      const x = (index * 73) % size
      const y = (index * 151) % size
      const alpha = 0.025 + (index % 5) * 0.01
      context.fillStyle = `rgba(56, 34, 18, ${alpha})`
      context.fillRect(x, y, 1 + (index % 2), 1)
    }
  })

  if (wood) wood.repeat.set(2.8, 1)
  if (cardboard) cardboard.repeat.set(1.5, 1.5)

  return { wood, cardboard }
}

function getSurfaceTextures() {
  if (!sharedSurfaceTextures) sharedSurfaceTextures = createSurfaceTextures()
  return sharedSurfaceTextures
}

function getColor(especie) {
  if (!especie) return DEFAULT_COLOR
  return SPECIES_COLORS[especie.toLowerCase()] || DEFAULT_COLOR
}

function hashText(value) {
  return String(value || 'pallet').split('').reduce((hash, character) => {
    return ((hash << 5) - hash + character.charCodeAt(0)) | 0
  }, 0)
}

function buildCartons(folio) {
  const seed = Math.abs(hashText(folio))
  const cartons = []

  for (let layer = 0; layer < 3; layer += 1) {
    for (let row = 0; row < 2; row += 1) {
      for (let column = 0; column < 2; column += 1) {
        const index = layer * 4 + row * 2 + column
        const offsetSeed = seed + index * 37
        cartons.push({
          key: `${layer}-${row}-${column}`,
          layer,
          isFront: row === 0,
          position: [
            (column === 0 ? -0.205 : 0.205) + Math.sin(offsetSeed) * 0.006,
            -0.218 + layer * 0.215,
            (row === 0 ? -0.205 : 0.205) + Math.cos(offsetSeed * 0.73) * 0.006,
          ],
          rotation: [0, Math.sin(offsetSeed * 1.31) * 0.018, 0],
        })
      }
    }
  }

  return cartons
}

function PalletBase({ material }) {
  const deckPositions = [-0.35, -0.175, 0, 0.175, 0.35]
  const runnerPositions = [-0.34, 0, 0.34]

  return (
    <group>
      {deckPositions.map((z) => (
        <RoundedBox
          key={`deck-${z}`}
          args={[0.9, 0.052, 0.135]}
          radius={0.012}
          position={[0, -0.385, z]}
          material={material}
          castShadow
          receiveShadow
        />
      ))}

      {runnerPositions.map((x) => (
        <RoundedBox
          key={`runner-${x}`}
          args={[0.13, 0.075, 0.82]}
          radius={0.01}
          position={[x, -0.445, 0]}
          material={material}
          castShadow
          receiveShadow
        />
      ))}

      {runnerPositions.flatMap((x) => [-0.34, 0, 0.34].map((z) => (
        <RoundedBox
          key={`block-${x}-${z}`}
          args={[0.15, 0.09, 0.15]}
          radius={0.012}
          position={[x, -0.505, z]}
          material={material}
          castShadow
          receiveShadow
        />
      )))}

      {[-0.34, 0, 0.34].map((z) => (
        <RoundedBox
          key={`bottom-${z}`}
          args={[0.88, 0.035, 0.12]}
          radius={0.009}
          position={[0, -0.565, z]}
          material={material}
          castShadow
          receiveShadow
        />
      ))}
    </group>
  )
}

function Carton({ carton, material, accentMaterial, tapeMaterial }) {
  const [x, y, z] = carton.position

  return (
    <group position={[x, y, z]} rotation={carton.rotation}>
      <RoundedBox
        args={[0.392, 0.202, 0.392]}
        radius={0.012}
        smoothness={2}
        material={material}
        castShadow
        receiveShadow
      />

      <mesh position={[0, 0.052, -0.199]} material={accentMaterial}>
        <boxGeometry args={[0.35, 0.034, 0.006]} />
      </mesh>

      <mesh position={[0, 0.104, 0]} material={tapeMaterial}>
        <boxGeometry args={[0.055, 0.006, 0.39]} />
      </mesh>

      {carton.isFront && [-0.095, 0.095].map((ventX) => (
        <RoundedBox
          key={`vent-${ventX}`}
          args={[0.065, 0.025, 0.007]}
          radius={0.01}
          position={[ventX, -0.035, -0.201]}
        >
          <meshStandardMaterial color="#322416" roughness={1} />
        </RoundedBox>
      ))}
    </group>
  )
}

export default function Pallet3D({ position, rotation = [0, 0, 0], folio, cajas, especie, nivel, levelPrefix = 'N', isSelected, onClick, tooltip }) {
  const palletRef = useRef()
  const [hovered, setHovered] = useState(false)
  const color = useMemo(() => getColor(especie), [especie])
  const cartons = useMemo(() => buildCartons(folio), [folio])
  const textures = useMemo(() => getSurfaceTextures(), [])
  useCursor(hovered, 'pointer', 'auto')

  const materials = useMemo(() => ({
    wood: new MeshStandardMaterial({
      color: '#c1925c',
      map: textures.wood,
      roughness: 0.88,
      metalness: 0,
    }),
    cardboard: new MeshStandardMaterial({
      color: '#d0a36c',
      map: textures.cardboard,
      roughness: 0.93,
      metalness: 0,
    }),
    accent: new MeshStandardMaterial({ color, roughness: 0.62, metalness: 0.02 }),
    tape: new MeshPhysicalMaterial({
      color: '#d9c7a0',
      roughness: 0.34,
      metalness: 0,
      clearcoat: 0.28,
    }),
    film: new MeshPhysicalMaterial({
      color: '#dff6f8',
      transparent: true,
      opacity: 0.16,
      roughness: 0.16,
      metalness: 0,
      clearcoat: 0.75,
      clearcoatRoughness: 0.16,
      depthWrite: false,
    }),
  }), [color, textures])

  useEffect(() => () => {
    Object.values(materials).forEach((material) => material.dispose())
  }, [materials])

  useFrame((_, delta) => {
    if (!palletRef.current) return
    const target = hovered ? 1.025 : 1
    const scale = MathUtils.damp(palletRef.current.scale.x, target, 11, delta)
    palletRef.current.scale.setScalar(scale)
  })

  const label = [
    folio || 'SIN FOLIO',
    String(especie || 'FRUTA').toUpperCase(),
    `${Number(cajas) || 0} CAJAS${nivel ? ` · ${levelPrefix}${nivel}` : ''}`,
  ].join('\n')

  return (
    <group position={position} rotation={rotation}>
      {isSelected && (
        <mesh position={[0, -0.578, 0]} rotation={[-Math.PI / 2, 0, 0]}>
          <ringGeometry args={[0.49, 0.555, 40]} />
          <meshBasicMaterial color="#49e28a" transparent opacity={0.9} toneMapped={false} />
        </mesh>
      )}

      <group
        ref={palletRef}
        onPointerDown={(event) => {
          event.stopPropagation()
          onClick?.(event)
        }}
        onPointerOver={(event) => {
          event.stopPropagation()
          setHovered(true)
        }}
        onPointerOut={() => setHovered(false)}
      >
        {hovered && tooltip && (
          <Html center position={[0, 0.78, 0]} distanceFactor={7.5} zIndexRange={[80, 0]}>
            <div className="pointer-events-none min-w-max rounded-lg border border-emerald-300/45 bg-slate-950/95 px-3 py-2 text-center text-[11px] font-semibold leading-tight text-white shadow-xl backdrop-blur-sm">
              {tooltip}
              <div className="mt-1 text-[9px] font-normal uppercase tracking-[0.13em] text-emerald-300">
                Folio {folio || 'sin identificar'}
              </div>
            </div>
          </Html>
        )}
        <PalletBase material={materials.wood} />

        {cartons.map((carton) => (
          <Carton
            key={carton.key}
            carton={carton}
            material={materials.cardboard}
            accentMaterial={materials.accent}
            tapeMaterial={materials.tape}
          />
        ))}

        {/* Stretch film around the complete load. */}
        <RoundedBox
          args={[0.845, 0.64, 0.845]}
          radius={0.018}
          smoothness={2}
          position={[0, -0.005, 0]}
          material={materials.film}
        />
        {[-0.2, -0.02, 0.16].map((y) => (
          <RoundedBox
            key={`film-${y}`}
            args={[0.86, 0.028, 0.86]}
            radius={0.01}
            position={[0, y, 0]}
            material={materials.film}
          />
        ))}

        {/* Logistics label faces the tunnel entrance (-Z). */}
        <RoundedBox args={[0.34, 0.155, 0.012]} radius={0.009} position={[0.205, -0.005, -0.432]}>
          <meshStandardMaterial color="#f7f5ed" roughness={0.78} />
        </RoundedBox>
        <Text
          position={[0.205, -0.005, -0.44]}
          rotation={[0, Math.PI, 0]}
          fontSize={0.032}
          lineHeight={1.22}
          color="#18201d"
          anchorX="center"
          anchorY="middle"
          maxWidth={0.29}
          textAlign="center"
          fontWeight={700}
        >
          {label}
        </Text>
      </group>
    </group>
  )
}
