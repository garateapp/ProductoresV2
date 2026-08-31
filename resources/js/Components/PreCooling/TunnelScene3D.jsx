import { Suspense, useCallback, useEffect, useMemo, useRef, useState } from 'react'
import { Canvas, useFrame } from '@react-three/fiber'
import {
  BufferGeometry,
  CanvasTexture,
  CatmullRomCurve3,
  DoubleSide,
  Float32BufferAttribute,
  PlaneGeometry,
  RepeatWrapping,
  SRGBColorSpace,
  TubeGeometry,
  Vector3,
} from 'three'
import {
  Environment,
  Html,
  Line,
  RoundedBox,
  Text,
} from '@react-three/drei'
import Pallet3D from './Pallet3D'
import FolioInfoPanel from './FolioInfoPanel'
import GarateBrand3D from './GarateBrand3D'
import {
  FirstPersonController,
  FirstPersonNavigationHud,
  useFirstPersonNavigationInput,
} from './FirstPersonNavigation'
import useTunnelLayout from './hooks/useTunnelLayout'

const COLORS = {
  concrete: '#cbd1d5',
  concreteLine: '#8c969d',
  wall: '#e8ecee',
  ceiling: '#dce2e5',
  machine: '#a5232b',
  safety: '#d5cf22',
  location: '#3d9d73',
  locationDark: '#236a4c',
}

const TUNNEL_TYPE_LABELS = {
  californiano: 'Californiano',
  modular: 'Modular',
  evaporador_central: 'Evaporador central',
}

function normalizeTunnelType(type) {
  return Object.hasOwn(TUNNEL_TYPE_LABELS, type) ? type : 'evaporador_central'
}

function deterministicNoise(index) {
  const value = Math.sin(index * 12.9898 + 78.233) * 43758.5453
  return value - Math.floor(value)
}

function useConcreteTextures(length, width) {
  const textures = useMemo(() => {
    const size = 256
    const albedoCanvas = document.createElement('canvas')
    const roughnessCanvas = document.createElement('canvas')
    albedoCanvas.width = albedoCanvas.height = size
    roughnessCanvas.width = roughnessCanvas.height = size
    const albedoContext = albedoCanvas.getContext('2d')
    const roughnessContext = roughnessCanvas.getContext('2d')
    const albedo = albedoContext.createImageData(size, size)
    const roughness = roughnessContext.createImageData(size, size)

    for (let pixel = 0; pixel < size * size; pixel += 1) {
      const noise = deterministicNoise(pixel)
      const grain = Math.round((noise - 0.5) * 24)
      const base = 190 + grain
      albedo.data[pixel * 4] = base - 5
      albedo.data[pixel * 4 + 1] = base
      albedo.data[pixel * 4 + 2] = base + 2
      albedo.data[pixel * 4 + 3] = 255
      const rough = 205 + Math.round(noise * 42)
      roughness.data[pixel * 4] = rough
      roughness.data[pixel * 4 + 1] = rough
      roughness.data[pixel * 4 + 2] = rough
      roughness.data[pixel * 4 + 3] = 255
    }
    albedoContext.putImageData(albedo, 0, 0)
    roughnessContext.putImageData(roughness, 0, 0)

    ;[[48, 72, 34], [190, 154, 22], [116, 218, 18]].forEach(([x, y, radius], index) => {
      const stain = albedoContext.createRadialGradient(x, y, 0, x, y, radius)
      stain.addColorStop(0, `rgba(72,82,86,${0.12 + index * 0.02})`)
      stain.addColorStop(1, 'rgba(72,82,86,0)')
      albedoContext.fillStyle = stain
      albedoContext.fillRect(x - radius, y - radius, radius * 2, radius * 2)
    })
    albedoContext.strokeStyle = 'rgba(72,80,84,0.18)'
    albedoContext.lineWidth = 1
    albedoContext.beginPath()
    albedoContext.moveTo(16, 34)
    albedoContext.lineTo(74, 86)
    albedoContext.lineTo(132, 80)
    albedoContext.lineTo(224, 142)
    albedoContext.stroke()

    const colorMap = new CanvasTexture(albedoCanvas)
    const roughnessMap = new CanvasTexture(roughnessCanvas)
    colorMap.colorSpace = SRGBColorSpace
    ;[colorMap, roughnessMap].forEach((texture) => {
      texture.wrapS = RepeatWrapping
      texture.wrapT = RepeatWrapping
      texture.repeat.set(Math.max(width * 0.7, 4), Math.max(length * 0.7, 6))
    })
    return { colorMap, roughnessMap }
  }, [length, width])

  useEffect(() => () => {
    textures.colorMap.dispose()
    textures.roughnessMap.dispose()
  }, [textures])

  return textures
}

function useColdPanelTexture(length, height) {
  const texture = useMemo(() => {
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = 256
    const context = canvas.getContext('2d')
    const gradient = context.createLinearGradient(0, 0, 256, 0)
    gradient.addColorStop(0, '#d4dade')
    gradient.addColorStop(0.5, '#f2f4f5')
    gradient.addColorStop(1, '#cbd2d6')
    context.fillStyle = gradient
    context.fillRect(0, 0, 256, 256)
    for (let x = 0; x <= 256; x += 32) {
      context.fillStyle = 'rgba(75,88,96,0.2)'
      context.fillRect(x, 0, 2, 256)
      context.fillStyle = 'rgba(255,255,255,0.55)'
      context.fillRect(x + 2, 0, 1, 256)
    }
    for (let index = 0; index < 340; index += 1) {
      const x = (index * 47) % 256
      const y = (index * 83) % 256
      context.fillStyle = `rgba(70,82,88,${0.012 + deterministicNoise(index) * 0.025})`
      context.fillRect(x, y, 1, 1)
    }
    const panelTexture = new CanvasTexture(canvas)
    panelTexture.colorSpace = SRGBColorSpace
    panelTexture.wrapS = RepeatWrapping
    panelTexture.wrapT = RepeatWrapping
    panelTexture.repeat.set(Math.max(length / 2.4, 4), Math.max(height / 1.2, 3))
    return panelTexture
  }, [length, height])

  useEffect(() => () => texture.dispose(), [texture])
  return texture
}

function IndustrialFloor({ length, width }) {
  const halfLength = length / 2
  const halfWidth = width / 2
  const longitudinalLines = Math.max(Math.floor(width / 0.9), 8)
  const transverseLines = Math.max(Math.floor(length / 0.9), 12)
  const { colorMap, roughnessMap } = useConcreteTextures(length, width)

  return (
    <group>
      <mesh position={[0, 0, -2.5]} rotation={[-Math.PI / 2, 0, 0]} receiveShadow>
        <planeGeometry args={[width + 1.8, length + 8]} />
        <meshStandardMaterial
          map={colorMap}
          roughnessMap={roughnessMap}
          color="#d7dbdd"
          roughness={0.96}
          metalness={0.01}
        />
      </mesh>

      {Array.from({ length: longitudinalLines + 1 }).map((_, index) => {
        const x = -halfWidth + (index / longitudinalLines) * width
        return (
          <Line
            key={`floor-x-${index}`}
            points={[[x, 0.008, -halfLength - 6], [x, 0.008, halfLength + 1]]}
            color={COLORS.concreteLine}
            transparent
            opacity={0.32}
            lineWidth={0.6}
          />
        )
      })}

      {Array.from({ length: transverseLines + 1 }).map((_, index) => {
        const z = -halfLength + (index / transverseLines) * length
        return (
          <Line
            key={`floor-z-${index}`}
            points={[[-halfWidth, 0.009, z], [halfWidth, 0.009, z]]}
            color={COLORS.concreteLine}
            transparent
            opacity={0.32}
            lineWidth={0.6}
          />
        )
      })}

      <group position={[0, 0.018, 0]}>
        <RoundedBox args={[0.24, 0.035, length - 1]} radius={0.012} position={[width / 2 - 0.72, 0, 0]}>
          <meshStandardMaterial color="#4a555c" roughness={0.38} metalness={0.72} />
        </RoundedBox>
        {Array.from({ length: Math.max(Math.floor(length / 0.45), 12) }).map((_, index) => (
          <RoundedBox
            key={`drain-grate-${index}`}
            args={[0.32, 0.025, 0.035]}
            radius={0.005}
            position={[width / 2 - 0.72, 0.026, -halfLength + 0.55 + index * 0.45]}
          >
            <meshStandardMaterial color="#8d969b" roughness={0.32} metalness={0.82} />
          </RoundedBox>
        ))}
      </group>

    </group>
  )
}

function TunnelShell({ length, width, height }) {
  const halfLength = length / 2
  const halfWidth = width / 2
  const lightCount = Math.min(Math.max(Math.floor(length / 3), 4), 7)
  const beamCount = Math.max(Math.floor(length / 2), 6)
  const panelTexture = useColdPanelTexture(length, height)

  return (
    <group>
      <mesh position={[-halfWidth - 0.08, height / 2, 0]} receiveShadow>
        <boxGeometry args={[0.14, height, length + 1.2]} />
        <meshStandardMaterial map={panelTexture} color="#edf1f2" roughness={0.48} metalness={0.18} side={2} />
      </mesh>
      <mesh position={[halfWidth + 0.08, height / 2, 0]} receiveShadow>
        <boxGeometry args={[0.14, height, length + 1.2]} />
        <meshStandardMaterial map={panelTexture} color="#edf1f2" roughness={0.48} metalness={0.18} side={2} />
      </mesh>
      <mesh position={[0, height + 0.04, 0]} receiveShadow>
        <boxGeometry args={[width + 0.4, 0.12, length + 1.2]} />
        <meshStandardMaterial map={panelTexture} color="#e8edef" roughness={0.5} metalness={0.16} />
      </mesh>
      <mesh position={[0, height / 2, halfLength + 0.08]} receiveShadow>
        <boxGeometry args={[width + 0.16, height, 0.14]} />
        <meshStandardMaterial map={panelTexture} color="#edf1f2" roughness={0.5} metalness={0.16} />
      </mesh>

      {Array.from({ length: beamCount }).map((_, index) => {
        const z = -halfLength + (index / Math.max(beamCount - 1, 1)) * length
        return (
          <RoundedBox key={`ceiling-beam-${index}`} args={[width, 0.12, 0.12]} radius={0.015} position={[0, height - 0.08, z]}>
            <meshStandardMaterial color="#aeb8be" roughness={0.68} metalness={0.2} />
          </RoundedBox>
        )
      })}

      {[-1, 1].map((side) => (
        <group key={`wall-protection-${side}`}>
          <RoundedBox
            args={[0.12, 0.52, length]}
            radius={0.018}
            position={[side * (halfWidth - 0.02), 0.26, 0]}
          >
            <meshStandardMaterial color="#8d979d" roughness={0.36} metalness={0.72} />
          </RoundedBox>
          {Array.from({ length: Math.max(Math.floor(length / 2.4), 5) }).map((_, index) => (
            <mesh
              key={`panel-rivet-${side}-${index}`}
              position={[side * (halfWidth - 0.1), 0.44, -halfLength + 1.2 + index * 2.4]}
            >
              <sphereGeometry args={[0.025, 10, 8]} />
              <meshStandardMaterial color="#465159" roughness={0.28} metalness={0.9} />
            </mesh>
          ))}
        </group>
      ))}

      {Array.from({ length: lightCount }).map((_, index) => {
        const z = -halfLength + 1.2 + (index / Math.max(lightCount - 1, 1)) * (length - 2.4)
        return (
          <group key={`light-${index}`} position={[-0.9, height - 0.22, z]}>
            <mesh rotation={[0, 0, Math.PI / 2]}>
              <boxGeometry args={[0.08, 0.8, 0.12]} />
              <meshStandardMaterial color="#fff7c2" emissive="#fff2a0" emissiveIntensity={1.3} />
            </mesh>
            <pointLight color="#eaf5ff" intensity={0.42} distance={5.8} decay={2} />
          </group>
        )
      })}

    </group>
  )
}

function Beacon({ active, inversion, position }) {
  const lens = useRef()
  const light = useRef()
  const beaconColor = inversion ? '#f59e0b' : '#ef2f35'

  useFrame((state) => {
    if (!lens.current || !light.current) return
    const pulse = active ? 1.3 + Math.sin(state.clock.elapsedTime * 7) * 0.7 : 0.08
    lens.current.emissiveIntensity = pulse
    light.current.intensity = active ? 1.1 + Math.sin(state.clock.elapsedTime * 7) * 0.55 : 0
  })

  return (
    <group position={position}>
      <mesh position={[0, -0.08, 0]}>
        <cylinderGeometry args={[0.17, 0.19, 0.12, 20]} />
        <meshStandardMaterial color="#252b30" roughness={0.4} metalness={0.7} />
      </mesh>
      <mesh position={[0, 0.08, 0]}>
        <sphereGeometry args={[0.15, 24, 14]} />
        <meshPhysicalMaterial
          ref={lens}
          color={active ? beaconColor : '#4b3032'}
          emissive={beaconColor}
          emissiveIntensity={0.08}
          roughness={0.24}
          transmission={0.18}
          thickness={0.08}
        />
      </mesh>
      <pointLight ref={light} position={[0, 0.08, -0.12]} color={beaconColor} intensity={0} distance={3.4} />
    </group>
  )
}

function RedDoor({ position, width, label, processStarted, processInversion }) {
  const doorWidth = Math.min(width * 0.54, 4.5)
  const doorHeight = 3.3
  const sidePanelWidth = Math.max((width - doorWidth) / 2, 0.4)
  const movingDoor = useRef()
  const roller = useRef()
  const openingProgress = useRef(0)
  const [open, setOpen] = useState(false)

  const toggle = useCallback((event) => {
    event.stopPropagation()
    setOpen((current) => !current)
  }, [])

  useEffect(() => () => { document.body.style.cursor = 'default' }, [])

  useFrame((_, delta) => {
    if (!movingDoor.current) return
    const target = open ? 1 : 0
    openingProgress.current += (target - openingProgress.current) * Math.min(1, delta * 4.8)
    const progress = openingProgress.current
    movingDoor.current.position.y = progress * doorHeight * 0.465
    movingDoor.current.scale.y = Math.max(0.07, 1 - progress * 0.93)
    if (roller.current) roller.current.rotation.x = -progress * Math.PI * 8
  })

  return (
    <group
      position={position}
      onClick={toggle}
      onPointerOver={(event) => {
        event.stopPropagation()
        document.body.style.cursor = 'pointer'
      }}
      onPointerOut={() => { document.body.style.cursor = 'default' }}
    >
      {[-1, 1].map((side) => (
        <mesh
          key={`portal-wall-${side}`}
          position={[side * (doorWidth / 2 + sidePanelWidth / 2), 0, 0.09]}
          receiveShadow
        >
          <boxGeometry args={[sidePanelWidth, doorHeight + 0.36, 0.13]} />
          <meshStandardMaterial color={COLORS.wall} roughness={0.86} />
        </mesh>
      ))}
      <mesh position={[0, doorHeight / 2 + 0.46, 0.09]} receiveShadow>
        <boxGeometry args={[width, 0.72, 0.13]} />
        <meshStandardMaterial color={COLORS.wall} roughness={0.86} />
      </mesh>
      {[-1, 1].map((side) => (
        <RoundedBox
          key={`door-frame-${side}`}
          args={[0.14, doorHeight + 0.32, 0.18]}
          radius={0.02}
          position={[side * (doorWidth / 2 + 0.1), 0, 0]}
        >
          <meshStandardMaterial color="#3f454a" roughness={0.46} metalness={0.62} />
        </RoundedBox>
      ))}
      <RoundedBox args={[doorWidth + 0.34, 0.14, 0.18]} radius={0.02} position={[0, doorHeight / 2 + 0.1, 0]}>
        <meshStandardMaterial color="#3f454a" roughness={0.46} metalness={0.62} />
      </RoundedBox>
      <mesh ref={roller} position={[0, doorHeight / 2 + 0.28, -0.03]} rotation={[0, 0, Math.PI / 2]} castShadow>
        <cylinderGeometry args={[0.2, 0.2, doorWidth + 0.18, 24]} />
        <meshStandardMaterial color="#7d1b22" roughness={0.56} metalness={0.22} />
      </mesh>
      <group ref={movingDoor}>
        {Array.from({ length: 13 }).map((_, index) => {
          const slatHeight = doorHeight / 13
          return (
            <RoundedBox
              key={`door-slat-${index}`}
              args={[doorWidth - 0.06, slatHeight - 0.018, 0.1]}
              radius={0.012}
              position={[0, -doorHeight / 2 + slatHeight * (index + 0.5), -0.13]}
            >
              <meshStandardMaterial color={COLORS.machine} roughness={0.44} metalness={0.18} />
            </RoundedBox>
          )
        })}
        <GarateBrand3D
          position={[0, 0, -0.205]}
          rotation={[0, Math.PI, 0]}
          logoWidth={Math.min(doorWidth * 0.48, 1.8)}
          logoY={0.34}
          nameY={-0.42}
          name={label || 'TÚNEL'}
          fontSize={0.24}
          textColor="#ffffff"
          outlineColor="#4e0c11"
        />
      </group>
      <Beacon
        active={processStarted}
        inversion={processInversion}
        position={[doorWidth / 2 - 0.25, doorHeight / 2 + 0.47, -0.16]}
      />
      {processInversion && (
        <Text
          position={[0, doorHeight / 2 + 0.48, -0.19]}
          rotation={[0, Math.PI, 0]}
          fontSize={0.18}
          color="#ffd066"
          anchorX="center"
          anchorY="middle"
          outlineWidth={0.01}
          outlineColor="#4a2a00"
        >
          TÚNEL EN INVERSIÓN
        </Text>
      )}
    </group>
  )
}

function PipeRun({ points, radius, color, roughness = 0.32, metalness = 0.68 }) {
  const geometry = useMemo(() => {
    const curve = new CatmullRomCurve3(points.map((point) => new Vector3(...point)), false, 'catmullrom', 0.22)
    return new TubeGeometry(curve, Math.max(points.length * 24, 64), radius, 12, false)
  }, [points, radius])

  useEffect(() => () => geometry.dispose(), [geometry])

  return (
    <mesh geometry={geometry} castShadow>
      <meshPhysicalMaterial
        color={color}
        roughness={roughness}
        metalness={metalness}
        clearcoat={0.35}
        clearcoatRoughness={0.28}
      />
    </mesh>
  )
}

function ValveWheel({ position, color = '#b4232c' }) {
  return (
    <group position={position} rotation={[0, Math.PI / 2, 0]}>
      <mesh>
        <torusGeometry args={[0.18, 0.025, 10, 28]} />
        <meshStandardMaterial color={color} roughness={0.36} metalness={0.72} />
      </mesh>
      {[0, Math.PI / 2].map((angle) => (
        <RoundedBox key={angle} args={[0.38, 0.025, 0.025]} radius={0.006} rotation={[0, 0, angle]}>
          <meshStandardMaterial color={color} roughness={0.36} metalness={0.72} />
        </RoundedBox>
      ))}
      <mesh>
        <cylinderGeometry args={[0.055, 0.055, 0.12, 14]} />
        <meshStandardMaterial color="#68747b" roughness={0.28} metalness={0.86} />
      </mesh>
    </group>
  )
}

function RefrigerationPipes({ layout, ceilingHeight }) {
  const halfLength = layout.tunnelLength / 2
  const halfWidth = layout.structureWidth / 2
  const supportCount = Math.max(Math.floor(layout.tunnelLength / 2.7), 5)
  const suctionPoints = useMemo(() => [
    [-halfWidth + 0.52, ceilingHeight - 0.72, halfLength - 0.4],
    [-halfWidth + 0.52, ceilingHeight - 0.72, -halfLength + 1.55],
    [-halfWidth + 0.8, ceilingHeight - 0.86, -halfLength + 1.18],
    [-1.28, ceilingHeight - 1.0, -halfLength + 1.08],
  ], [halfLength, halfWidth, ceilingHeight])
  const liquidPoints = useMemo(() => [
    [halfWidth - 0.48, ceilingHeight - 0.48, halfLength - 0.42],
    [halfWidth - 0.48, ceilingHeight - 0.48, -halfLength + 1.52],
    [halfWidth - 0.76, ceilingHeight - 0.7, -halfLength + 1.16],
    [1.3, ceilingHeight - 0.92, -halfLength + 1.06],
  ], [halfLength, halfWidth, ceilingHeight])
  const hotGasPoints = useMemo(() => [
    [halfWidth - 0.76, ceilingHeight - 0.28, halfLength - 0.45],
    [halfWidth - 0.76, ceilingHeight - 0.28, -halfLength + 1.44],
    [0.42, ceilingHeight - 0.76, -halfLength + 1.02],
  ], [halfLength, halfWidth, ceilingHeight])

  return (
    <group>
      <PipeRun points={suctionPoints} radius={0.115} color="#2f78aa" roughness={0.58} metalness={0.3} />
      <PipeRun points={liquidPoints} radius={0.055} color="#b96f3e" roughness={0.26} metalness={0.88} />
      <PipeRun points={hotGasPoints} radius={0.065} color="#8c2e35" roughness={0.34} metalness={0.7} />

      {Array.from({ length: supportCount }).map((_, index) => {
        const z = -halfLength + 0.8 + index * (layout.tunnelLength - 1.6) / Math.max(supportCount - 1, 1)
        return (
          <group key={`pipe-support-${index}`} position={[-halfWidth + 0.52, ceilingHeight - 0.5, z]}>
            <RoundedBox args={[0.035, 0.42, 0.035]} radius={0.007}>
              <meshStandardMaterial color="#66727a" roughness={0.34} metalness={0.8} />
            </RoundedBox>
            <mesh position={[0, -0.22, 0]} rotation={[Math.PI / 2, 0, 0]}>
              <torusGeometry args={[0.13, 0.018, 8, 20]} />
              <meshStandardMaterial color="#66727a" roughness={0.34} metalness={0.82} />
            </mesh>
          </group>
        )
      })}

      <ValveWheel position={[-halfWidth + 0.52, ceilingHeight - 0.72, -halfLength + 2.15]} />
      <ValveWheel position={[halfWidth - 0.48, ceilingHeight - 0.48, -halfLength + 2.55]} color="#305f8a" />

      <group position={[halfWidth - 0.48, ceilingHeight - 0.08, -halfLength + 2.15]}>
        <mesh rotation={[Math.PI / 2, 0, 0]}>
          <cylinderGeometry args={[0.16, 0.16, 0.075, 24]} />
          <meshStandardMaterial color="#e8ecee" roughness={0.25} metalness={0.48} />
        </mesh>
        <mesh position={[0, 0, -0.045]} rotation={[0, 0, -0.7]}>
          <boxGeometry args={[0.012, 0.12, 0.012]} />
          <meshBasicMaterial color="#b91c1c" />
        </mesh>
      </group>
    </group>
  )
}

function FanRotor({ position, active, reverse }) {
  const rotor = useRef()

  useFrame((_, delta) => {
    if (!rotor.current) return
    const direction = reverse ? -1 : 1
    rotor.current.rotation.z += direction * delta * (active ? 8.5 : 0.35)
  })

  return (
    <group position={position}>
      <mesh>
        <torusGeometry args={[0.37, 0.035, 12, 36]} />
        <meshStandardMaterial color="#4c5960" roughness={0.28} metalness={0.82} />
      </mesh>
      <group ref={rotor}>
        {Array.from({ length: 5 }).map((_, index) => (
          <group key={`fan-blade-${index}`} rotation={[0, 0, index * Math.PI * 0.4]}>
            <RoundedBox args={[0.12, 0.34, 0.035]} radius={0.045} position={[0, 0.17, -0.015]}>
              <meshStandardMaterial color="#65747d" roughness={0.28} metalness={0.74} />
            </RoundedBox>
          </group>
        ))}
        <mesh position={[0, 0, -0.04]}>
          <cylinderGeometry args={[0.085, 0.085, 0.12, 18]} />
          <meshStandardMaterial color="#313c43" roughness={0.25} metalness={0.86} />
        </mesh>
      </group>
      {[0, Math.PI / 2].map((angle) => (
        <RoundedBox key={angle} args={[0.78, 0.018, 0.018]} radius={0.004} rotation={[0, 0, angle]} position={[0, 0, -0.09]}>
          <meshStandardMaterial color="#95a0a6" roughness={0.25} metalness={0.78} />
        </RoundedBox>
      ))}
    </group>
  )
}

function ColdMist({ width, active, reverse, position, flowDirection = -1 }) {
  const mist = useRef()
  const geometry = useMemo(() => {
    const points = new Float32Array(110 * 3)
    for (let index = 0; index < 110; index += 1) {
      points[index * 3] = (deterministicNoise(index * 3) - 0.5) * width * 0.82
      points[index * 3 + 1] = (deterministicNoise(index * 3 + 1) - 0.5) * 0.65
      points[index * 3 + 2] = flowDirection * (0.2 + deterministicNoise(index * 3 + 2) * 3.8)
    }
    const buffer = new BufferGeometry()
    buffer.setAttribute('position', new Float32BufferAttribute(points, 3))
    return buffer
  }, [flowDirection, width])

  useEffect(() => () => geometry.dispose(), [geometry])

  useFrame((_, delta) => {
    if (!mist.current || !active) return
    const positions = mist.current.geometry.attributes.position
    const direction = reverse ? -flowDirection : flowDirection
    for (let index = 0; index < positions.count; index += 1) {
      let z = positions.getZ(index) + direction * delta * (0.24 + deterministicNoise(index) * 0.32)
      if (direction < 0 && z < -4.2) z = 0.18
      if (direction > 0 && z > 4.2) z = -0.18
      positions.setZ(index, z)
    }
    positions.needsUpdate = true
  })

  if (!active) return null

  return (
    <points ref={mist} geometry={geometry} position={position}>
      <pointsMaterial color="#dff6ff" size={0.045} transparent opacity={0.22} depthWrite={false} sizeAttenuation />
    </points>
  )
}

function EvaporatorBank({ layout, ceilingHeight, active, inversion }) {
  const width = Math.min(layout.structureWidth * 0.66, 5.1)
  const halfLength = layout.tunnelLength / 2
  const moduleWidth = width * 0.39
  const position = [0, ceilingHeight - 0.92, -halfLength + 1.12]

  return (
    <group position={position}>
      {[-1, 1].map((side) => (
        <group key={`central-evaporator-module-${side}`} position={[side * width * 0.265, 0, 0]}>
          <RoundedBox args={[moduleWidth, 1.16, 1.08]} radius={0.04} castShadow>
            <meshPhysicalMaterial
              color="#d7dcde"
              roughness={0.3}
              metalness={0.58}
              clearcoat={0.28}
              clearcoatRoughness={0.34}
            />
          </RoundedBox>
          <RoundedBox args={[moduleWidth - 0.16, 0.84, 0.045]} radius={0.012} position={[0, 0, -0.565]}>
            <meshStandardMaterial color="#68757b" roughness={0.5} metalness={0.52} />
          </RoundedBox>
          {Array.from({ length: 9 }).map((_, index) => (
            <RoundedBox
              key={`central-fin-${side}-${index}`}
              args={[0.018, 0.76, 0.02]}
              radius={0.003}
              position={[-moduleWidth / 2 + 0.13 + index * (moduleWidth - 0.26) / 8, 0, -0.595]}
            >
              <meshStandardMaterial color="#a1aaae" roughness={0.3} metalness={0.82} />
            </RoundedBox>
          ))}
          <RoundedBox
            args={[moduleWidth + 0.1, 0.36, 1.12]}
            radius={0.03}
            position={[0, -0.69, 0.05]}
            rotation={[0, 0, side * -0.1]}
          >
            <meshStandardMaterial color="#aeb7bb" roughness={0.38} metalness={0.68} />
          </RoundedBox>
        </group>
      ))}

      <RoundedBox args={[width * 0.18, 1.04, 1.02]} radius={0.03} position={[0, -0.02, 0]} castShadow>
        <meshStandardMaterial color="#8e989d" roughness={0.4} metalness={0.7} />
      </RoundedBox>
      <RoundedBox args={[width * 0.23, 0.5, 0.78]} radius={0.035} position={[0, -0.72, 0.18]} castShadow>
        <meshStandardMaterial color="#99232c" roughness={0.82} metalness={0.03} />
      </RoundedBox>

      {[width * -0.31, 0, width * 0.31].map((x) => (
        <group key={`evaporator-hanger-${x}`} position={[x, 0.94, 0]}>
          <mesh>
            <cylinderGeometry args={[0.022, 0.022, 0.72, 10]} />
            <meshStandardMaterial color="#5e686d" roughness={0.3} metalness={0.84} />
          </mesh>
          <RoundedBox args={[0.18, 0.045, 0.18]} radius={0.008} position={[0, 0.36, 0]}>
            <meshStandardMaterial color="#747e83" roughness={0.34} metalness={0.78} />
          </RoundedBox>
        </group>
      ))}

      <group position={[0, 0.05, -0.58]}>
        <RoundedBox args={[0.48, 0.54, 0.08]} radius={0.025}>
          <meshStandardMaterial color="#e5e8e9" roughness={0.44} metalness={0.42} />
        </RoundedBox>
        <mesh position={[0.15, -0.04, -0.052]}>
          <circleGeometry args={[0.028, 16]} />
          <meshBasicMaterial color={active ? '#45c27c' : '#777f82'} />
        </mesh>
      </group>

      <PipeRun
        points={[
          [-width / 2 + 0.2, -0.67, 0.48],
          [width / 2 - 0.25, -0.67, 0.48],
          [width / 2 - 0.08, -0.88, 0.48],
        ]}
        radius={0.035}
        color="#8a9296"
        roughness={0.4}
        metalness={0.76}
      />
      <Text position={[width * 0.27, 0.35, -0.585]} fontSize={0.1} color="#455258" anchorX="center">
        EVAPORADOR CENTRAL
      </Text>
      {[-width / 2 + 0.14, width / 2 - 0.14].map((x) => (
        <mesh key={`frost-${x}`} position={[x, 0.45, -0.66]}>
          <sphereGeometry args={[0.13, 14, 10]} />
          <meshPhysicalMaterial color="#e9fbff" roughness={0.92} transparent opacity={0.48} />
        </mesh>
      ))}
      <ColdMist
        width={width * 0.72}
        active={active}
        reverse={inversion}
        flowDirection={1}
        position={[0, -0.72, 0.28]}
      />
    </group>
  )
}

function useModularFabricTexture(length) {
  const texture = useMemo(() => {
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = 256
    const context = canvas.getContext('2d')
    const gradient = context.createLinearGradient(0, 0, 256, 0)
    gradient.addColorStop(0, '#641319')
    gradient.addColorStop(0.22, '#a52b34')
    gradient.addColorStop(0.5, '#7e1820')
    gradient.addColorStop(0.78, '#b1333d')
    gradient.addColorStop(1, '#68151b')
    context.fillStyle = gradient
    context.fillRect(0, 0, 256, 256)

    for (let x = 0; x < 256; x += 4) {
      context.fillStyle = `rgba(255,255,255,${0.025 + deterministicNoise(x) * 0.04})`
      context.fillRect(x, 0, 1, 256)
    }
    for (let y = 0; y < 256; y += 7) {
      context.fillStyle = 'rgba(42,3,7,0.055)'
      context.fillRect(0, y, 256, 1)
    }

    const fabric = new CanvasTexture(canvas)
    fabric.colorSpace = SRGBColorSpace
    fabric.wrapS = RepeatWrapping
    fabric.wrapT = RepeatWrapping
    fabric.repeat.set(1.4, Math.max(length / 3, 2))
    return fabric
  }, [length])

  useEffect(() => () => texture.dispose(), [texture])
  return texture
}

function ModularSideUnit({ side, z, halfWidth, depth, fabricTexture }) {
  const wallX = side * (halfWidth - 0.13)
  const innerX = side * (halfWidth - 0.27)
  const grilleX = side * (halfWidth - 0.205)
  const frameZ = depth * 0.43

  return (
    <group>
      <RoundedBox args={[0.08, 1.3, depth * 0.78]} radius={0.018} position={[wallX, 0.95, z]} castShadow>
        <meshStandardMaterial color="#b8c1c6" roughness={0.42} metalness={0.62} />
      </RoundedBox>

      <RoundedBox args={[0.045, 1.08, depth * 0.68]} radius={0.012} position={[grilleX, 0.97, z]}>
        <meshStandardMaterial color="#3f4b51" roughness={0.54} metalness={0.5} />
      </RoundedBox>

      {Array.from({ length: 8 }).map((_, index) => (
        <RoundedBox
          key={`coil-fin-${side}-${z}-${index}`}
          args={[0.038, 0.018, depth * 0.62]}
          radius={0.003}
          position={[grilleX - side * 0.025, 0.52 + index * 0.13, z]}
        >
          <meshStandardMaterial color="#9da8ad" roughness={0.3} metalness={0.84} />
        </RoundedBox>
      ))}

      {[-1, 1].map((edge) => (
        <RoundedBox
          key={`padded-post-${side}-${z}-${edge}`}
          args={[0.19, 1.52, 0.13]}
          radius={0.038}
          position={[innerX, 0.87, z + edge * frameZ]}
          castShadow
        >
          <meshStandardMaterial map={fabricTexture} color="#a6242e" roughness={0.9} metalness={0.01} />
        </RoundedBox>
      ))}

      <RoundedBox
        args={[0.22, 0.16, depth * 0.92]}
        radius={0.045}
        position={[innerX, 0.17, z]}
        castShadow
      >
        <meshStandardMaterial map={fabricTexture} color="#8e1d26" roughness={0.92} metalness={0.01} />
      </RoundedBox>

      <RoundedBox
        args={[0.42, 0.34, depth * 0.9]}
        radius={0.06}
        position={[innerX - side * 0.08, 1.69, z]}
        rotation={[0, 0, side * 0.11]}
        castShadow
      >
        <meshStandardMaterial map={fabricTexture} color="#a92b35" roughness={0.88} metalness={0.01} />
      </RoundedBox>

      <RoundedBox args={[0.11, 0.11, depth]} radius={0.025} position={[innerX, 1.55, z]}>
        <meshStandardMaterial color="#8c1d25" roughness={0.74} metalness={0.08} />
      </RoundedBox>
    </group>
  )
}

function ModularRefrigerationPipes({ layout, ceilingHeight, moduleCenters }) {
  const halfWidth = layout.structureWidth / 2
  const pipeLength = Math.max(layout.tunnelLength - 0.9, 1)

  return (
    <group>
      {[-1, 1].map((side) => (
        <group key={`modular-pipes-${side}`}>
          <mesh
            position={[side * (halfWidth - 0.46), ceilingHeight - 0.38, 0]}
            rotation={[Math.PI / 2, 0, 0]}
            castShadow
          >
            <cylinderGeometry args={[0.075, 0.075, pipeLength, 14]} />
            <meshStandardMaterial color="#397ea7" roughness={0.5} metalness={0.42} />
          </mesh>
          <mesh
            position={[side * (halfWidth - 0.7), ceilingHeight - 0.22, 0]}
            rotation={[Math.PI / 2, 0, 0]}
            castShadow
          >
            <cylinderGeometry args={[0.038, 0.038, pipeLength, 12]} />
            <meshStandardMaterial color="#b66b3c" roughness={0.25} metalness={0.86} />
          </mesh>

          {moduleCenters.map((z, index) => (
            <group key={`modular-drop-${side}-${index}`}>
              <mesh position={[side * (halfWidth - 0.46), ceilingHeight - 1.02, z]} castShadow>
                <cylinderGeometry args={[0.035, 0.035, 1.2, 10]} />
                <meshStandardMaterial color="#4c86a5" roughness={0.5} metalness={0.45} />
              </mesh>
              <mesh position={[side * (halfWidth - 0.58), ceilingHeight - 1.59, z]} rotation={[0, 0, Math.PI / 2]}>
                <cylinderGeometry args={[0.03, 0.03, 0.26, 10]} />
                <meshStandardMaterial color="#ad683d" roughness={0.28} metalness={0.82} />
              </mesh>
            </group>
          ))}
        </group>
      ))}
    </group>
  )
}

function ModularCoolingSystem({ layout, ceilingHeight, active, inversion }) {
  const halfLength = layout.tunnelLength / 2
  const halfWidth = layout.structureWidth / 2
  const moduleDepth = Math.min(Math.max(layout.POSITION_SPACING * 0.78, 0.72), 1.08)
  const moduleCenters = useMemo(() => {
    return Array.from({ length: layout.totalPositions }).map((_, index) => (
      getPositionZ(layout, index, 'modular')
    ))
  }, [layout])
  const fabricTexture = useModularFabricTexture(layout.tunnelLength)
  const fanY = ceilingHeight - 1.12
  const fanGap = Math.min(layout.structureWidth * 0.18, 0.95)

  return (
    <group>
      <ModularRefrigerationPipes
        layout={layout}
        ceilingHeight={ceilingHeight}
        moduleCenters={moduleCenters}
      />

      {[-1, 1].flatMap((side) => moduleCenters.map((z, index) => (
        <ModularSideUnit
          key={`side-module-${side}-${index}`}
          side={side}
          z={z}
          halfWidth={halfWidth}
          depth={moduleDepth}
          fabricTexture={fabricTexture}
        />
      )))}

      <group position={[0, fanY, halfLength - 0.16]}>
        {[-fanGap, fanGap].map((x, index) => (
          <group key={`modular-fan-${index}`} position={[x, 0, 0]}>
            <RoundedBox args={[1.02, 1.02, 0.24]} radius={0.045} castShadow>
              <meshPhysicalMaterial
                color="#c4ccd0"
                roughness={0.36}
                metalness={0.6}
                clearcoat={0.2}
                clearcoatRoughness={0.42}
              />
            </RoundedBox>
            <RoundedBox args={[0.86, 0.86, 0.035]} radius={0.04} position={[0, 0, -0.135]}>
              <meshStandardMaterial color="#303c42" roughness={0.5} metalness={0.5} />
            </RoundedBox>
            <FanRotor position={[0, 0, -0.18]} active={active} reverse={inversion} />
          </group>
        ))}
        <RoundedBox args={[fanGap * 2 + 1.35, 0.13, 0.32]} radius={0.025} position={[0, -0.65, 0]}>
          <meshStandardMaterial color="#828e94" roughness={0.42} metalness={0.7} />
        </RoundedBox>
      </group>

      <Text
        position={[0, ceilingHeight - 0.28, halfLength - 0.31]}
        rotation={[0, Math.PI, 0]}
        fontSize={0.13}
        color="#3a454b"
        anchorX="center"
      >
        SISTEMA MODULAR DE PREFRÍO
      </Text>
      <ColdMist
        width={Math.min(layout.structureWidth * 0.55, 3.8)}
        active={active}
        reverse={inversion}
        position={[0, fanY, halfLength - 0.4]}
      />
    </group>
  )
}

function useCalifornianFabricTexture(length) {
  const texture = useMemo(() => {
    const canvas = document.createElement('canvas')
    canvas.width = canvas.height = 256
    const context = canvas.getContext('2d')
    const gradient = context.createLinearGradient(0, 0, 256, 0)
    gradient.addColorStop(0, '#071a30')
    gradient.addColorStop(0.2, '#143d68')
    gradient.addColorStop(0.48, '#0b2b4d')
    gradient.addColorStop(0.76, '#174875')
    gradient.addColorStop(1, '#081c33')
    context.fillStyle = gradient
    context.fillRect(0, 0, 256, 256)

    for (let x = 0; x < 256; x += 3) {
      const highlight = 0.018 + deterministicNoise(x * 5) * 0.045
      context.fillStyle = `rgba(220,239,255,${highlight})`
      context.fillRect(x, 0, 1, 256)
    }
    for (let y = 0; y < 256; y += 9) {
      context.fillStyle = 'rgba(0,5,14,0.09)'
      context.fillRect(0, y, 256, 1)
    }

    const fabric = new CanvasTexture(canvas)
    fabric.colorSpace = SRGBColorSpace
    fabric.wrapS = RepeatWrapping
    fabric.wrapT = RepeatWrapping
    fabric.repeat.set(1.6, Math.max(length / 2.4, 3))
    return fabric
  }, [length])

  useEffect(() => () => texture.dispose(), [texture])
  return texture
}

function CalifornianCurtainPanel({ position, width, height, texture, rotation = [0, Math.PI / 2, 0] }) {
  const geometry = useMemo(() => {
    const sheet = new PlaneGeometry(width, height, 14, 22)
    const positions = sheet.attributes.position
    for (let index = 0; index < positions.count; index += 1) {
      const x = positions.getX(index)
      const y = positions.getY(index)
      const fold = Math.sin(x * 10.5 + y * 1.7) * 0.035 + Math.sin(y * 4.1) * 0.012
      positions.setZ(index, fold)
    }
    positions.needsUpdate = true
    sheet.computeVertexNormals()
    return sheet
  }, [height, width])

  useEffect(() => () => geometry.dispose(), [geometry])

  return (
    <mesh geometry={geometry} position={position} rotation={rotation} castShadow receiveShadow>
      <meshStandardMaterial map={texture} color="#123d68" roughness={0.92} metalness={0.01} side={DoubleSide} />
    </mesh>
  )
}

function CalifornianSidePlenum({ side, layout, ceilingHeight, fabricTexture, active }) {
  const plenum = useRef()
  const halfWidth = layout.structureWidth / 2
  const ductLength = Math.max(layout.tunnelLength - 1.05, 1.4)
  const ductX = side * (halfWidth - 0.42)
  const ductY = ceilingHeight - 1.36
  const braceCount = Math.max(Math.floor(layout.tunnelLength / 1.55), 5)

  useFrame((_, delta) => {
    if (!plenum.current) return
    const target = active ? -0.2 : 0
    plenum.current.position.y += (target - plenum.current.position.y) * Math.min(1, delta * 3.2)
  })

  return (
    <group ref={plenum}>
      <RoundedBox
        args={[0.68, 0.5, ductLength]}
        radius={0.07}
        position={[ductX, ductY, 0.12]}
        rotation={[0, 0, side * 0.24]}
        castShadow
      >
        <meshStandardMaterial map={fabricTexture} color="#123c66" roughness={0.9} metalness={0.01} />
      </RoundedBox>

      <RoundedBox
        args={[0.12, 0.13, ductLength + 0.12]}
        radius={0.025}
        position={[side * (halfWidth - 0.7), ductY - 0.22, 0.12]}
      >
        <meshStandardMaterial color="#6e797f" roughness={0.34} metalness={0.78} />
      </RoundedBox>

      {Array.from({ length: braceCount }).map((_, index) => {
        const z = -layout.tunnelLength / 2 + 0.72
          + index * (layout.tunnelLength - 1.44) / Math.max(braceCount - 1, 1)
        return (
          <group key={`californian-brace-${side}-${index}`} position={[0, 0, z]}>
            <RoundedBox
              args={[0.045, 0.82, 0.045]}
              radius={0.008}
              position={[side * (halfWidth - 0.55), ductY + 0.02, 0]}
              rotation={[0, 0, side * -0.31]}
            >
              <meshStandardMaterial color="#a8b0b4" roughness={0.32} metalness={0.82} />
            </RoundedBox>
            <mesh position={[side * (halfWidth - 0.45), ductY + 0.26, 0]} rotation={[Math.PI / 2, 0, 0]}>
              <torusGeometry args={[0.055, 0.012, 8, 18]} />
              <meshStandardMaterial color="#777f84" roughness={0.3} metalness={0.84} />
            </mesh>
          </group>
        )
      })}
    </group>
  )
}

function CalifornianCoolingSystem({ layout, ceilingHeight, active, inversion }) {
  const halfLength = layout.tunnelLength / 2
  const halfWidth = layout.structureWidth / 2
  const fabricTexture = useCalifornianFabricTexture(layout.tunnelLength)
  const fanY = ceilingHeight - 1.32
  const fanGap = Math.min(layout.structureWidth * 0.18, 0.92)
  const curtainHeight = Math.min(2.45, ceilingHeight - 1.35)
  const curtainY = curtainHeight / 2 + 0.12

  return (
    <group>
      {[-1, 1].map((side) => (
        <CalifornianSidePlenum
          key={`californian-plenum-${side}`}
          side={side}
          layout={layout}
          ceilingHeight={ceilingHeight}
          fabricTexture={fabricTexture}
          active={active}
        />
      ))}

      {/* Flexible inlet and rear skirts seal the pallet rows against the plenums. */}
      {[-1, 1].flatMap((side) => [
        -halfLength + 0.62,
        halfLength - 1.02,
      ].map((z, index) => (
        <CalifornianCurtainPanel
          key={`californian-skirt-${side}-${index}`}
          position={[side * (halfWidth - 0.7), curtainY, z]}
          width={0.95}
          height={curtainHeight}
          texture={fabricTexture}
        />
      )))}

      {/* Yellow wheel guides and bumpers visible in the reference tunnel. */}
      {[-1, 1].map((side) => (
        <group key={`californian-guide-${side}`}>
          <RoundedBox
            args={[0.16, 0.16, layout.tunnelLength - 0.7]}
            radius={0.025}
            position={[side * (halfWidth - 0.58), 0.09, 0]}
            castShadow
          >
            <meshStandardMaterial color="#d8bd12" roughness={0.55} metalness={0.18} />
          </RoundedBox>
          <Line
            points={[
              [side * (halfWidth - 1.05), 0.025, -halfLength + 0.5],
              [side * (halfWidth - 1.05), 0.025, halfLength - 0.65],
            ]}
            color="#d6b914"
            lineWidth={2.2}
          />
        </group>
      ))}

      {/* Rear suction chamber: the characteristic twin fan wall. */}
      <group position={[0, 0, halfLength - 0.18]}>
        <RoundedBox
          args={[Math.min(layout.structureWidth * 0.56, 4.7), 1.26, 0.34]}
          radius={0.055}
          position={[0, 0.75, 0]}
          castShadow
        >
          <meshStandardMaterial map={fabricTexture} color="#071b30" roughness={0.9} metalness={0.01} />
        </RoundedBox>
        {[-fanGap, fanGap].map((x, index) => (
          <group key={`californian-fan-${index}`} position={[x, fanY, -0.04]}>
            <RoundedBox args={[1.08, 1.08, 0.28]} radius={0.045} castShadow>
              <meshPhysicalMaterial
                color="#b9c1c4"
                roughness={0.4}
                metalness={0.64}
                clearcoat={0.18}
                clearcoatRoughness={0.44}
              />
            </RoundedBox>
            <RoundedBox args={[0.91, 0.91, 0.04]} radius={0.035} position={[0, 0, -0.165]}>
              <meshStandardMaterial color="#263238" roughness={0.5} metalness={0.52} />
            </RoundedBox>
            <FanRotor position={[0, 0, -0.215]} active={active} reverse={inversion} />
          </group>
        ))}
        <RoundedBox
          args={[fanGap * 2 + 1.42, 0.14, 0.36]}
          radius={0.026}
          position={[0, fanY - 0.66, 0]}
        >
          <meshStandardMaterial color="#7b858a" roughness={0.4} metalness={0.72} />
        </RoundedBox>
      </group>

      {/* Electrical conduit feeding the rear fan bank. */}
      <mesh position={[0, ceilingHeight - 0.24, halfLength - 0.22]} rotation={[0, 0, Math.PI / 2]}>
        <cylinderGeometry args={[0.025, 0.025, fanGap * 2 + 1.4, 10]} />
        <meshStandardMaterial color="#68737a" roughness={0.35} metalness={0.8} />
      </mesh>

      <Text
        position={[0, ceilingHeight - 0.48, halfLength - 0.36]}
        rotation={[0, Math.PI, 0]}
        fontSize={0.13}
        color="#32444e"
        anchorX="center"
      >
        SISTEMA CALIFORNIANO · AIRE FORZADO
      </Text>
      <ColdMist
        width={Math.min(layout.structureWidth * 0.5, 3.6)}
        active={active}
        reverse={inversion}
        position={[0, fanY, halfLength - 0.46]}
      />
    </group>
  )
}

function TunnelCurtain({ length, width, ceilingHeight, lowered, active, inversion }) {
  const curtain = useRef()
  const canopyLength = Math.max(length - 0.7, 1)
  const braceCount = Math.max(Math.floor(length / 1.7), 5)
  const fanCount = Math.min(Math.max(Math.floor(length / 3.2), 2), 4)
  const texture = useMemo(() => {
    const canvas = document.createElement('canvas')
    canvas.width = 192
    canvas.height = 192
    const context = canvas.getContext('2d')
    const base = context.createLinearGradient(0, 0, canvas.width, 0)
    base.addColorStop(0, '#77161d')
    base.addColorStop(0.22, '#a92831')
    base.addColorStop(0.5, '#821b23')
    base.addColorStop(0.78, '#ac3038')
    base.addColorStop(1, '#71141b')
    context.fillStyle = base
    context.fillRect(0, 0, canvas.width, canvas.height)

    for (let x = 0; x < canvas.width; x += 3) {
      const alpha = 0.025 + ((Math.sin(x * 0.63) + 1) * 0.018)
      context.fillStyle = `rgba(255,255,255,${alpha})`
      context.fillRect(x, 0, 1, canvas.height)
    }
    for (let y = 0; y < canvas.height; y += 4) {
      const alpha = 0.025 + ((Math.cos(y * 0.47) + 1) * 0.014)
      context.fillStyle = `rgba(35,4,7,${alpha})`
      context.fillRect(0, y, canvas.width, 1)
    }

    const canvasTexture = new CanvasTexture(canvas)
    canvasTexture.colorSpace = SRGBColorSpace
    canvasTexture.wrapS = RepeatWrapping
    canvasTexture.wrapT = RepeatWrapping
    canvasTexture.repeat.set(3.5, Math.max(length * 0.75, 4))
    return canvasTexture
  }, [length])

  const geometry = useMemo(() => {
    const sheet = new PlaneGeometry(width, canopyLength, 24, 72)
    const positions = sheet.attributes.position
    for (let index = 0; index < positions.count; index += 1) {
      const x = positions.getX(index)
      const y = positions.getY(index)
      const wrinkle = Math.sin(x * 8.5 + y * 0.7) * 0.035 + Math.sin(y * 2.8) * 0.018
      positions.setZ(index, wrinkle)
    }
    positions.needsUpdate = true
    sheet.computeVertexNormals()
    return sheet
  }, [canopyLength, width])

  const dividerGeometry = useMemo(() => {
    const divider = new PlaneGeometry(canopyLength * 0.88, 0.82, 48, 10)
    const positions = divider.attributes.position
    for (let index = 0; index < positions.count; index += 1) {
      const x = positions.getX(index)
      const y = positions.getY(index)
      positions.setZ(index, Math.sin(x * 2.9 + y * 4.2) * 0.028 + Math.sin(x * 7.1) * 0.012)
    }
    positions.needsUpdate = true
    divider.computeVertexNormals()
    return divider
  }, [canopyLength])

  useEffect(() => () => {
    texture.dispose()
    geometry.dispose()
    dividerGeometry.dispose()
  }, [texture, geometry, dividerGeometry])

  useFrame((state) => {
    if (!curtain.current) return
    const raisedY = ceilingHeight - 0.58
    const loweredY = Math.max(2.45, ceilingHeight * 0.5)
    const targetY = lowered ? loweredY : raisedY
    curtain.current.position.y += (targetY - curtain.current.position.y) * 0.035
    texture.offset.y = Math.sin(state.clock.elapsedTime * 0.35) * 0.008
  })

  return (
    <group ref={curtain} position={[0, ceilingHeight - 0.58, 0]}>
      <mesh geometry={geometry} rotation={[-Math.PI / 2, 0, 0]} castShadow receiveShadow>
        <meshStandardMaterial
          map={texture}
          color="#9a252d"
          roughness={0.88}
          metalness={0.01}
          side={DoubleSide}
        />
      </mesh>

      {[-1, 1].map((side) => (
        <RoundedBox
          key={`canopy-side-roll-${side}`}
          args={[0.24, 0.17, canopyLength + 0.06]}
          radius={0.055}
          position={[side * (width / 2 - 0.08), -0.055, 0]}
          castShadow
        >
          <meshStandardMaterial map={texture} color="#9c2630" roughness={0.9} metalness={0.01} />
        </RoundedBox>
      ))}

      {[-canopyLength / 2, canopyLength / 2].map((z) => (
        <RoundedBox
          key={`canopy-end-roll-${z}`}
          args={[width, 0.19, 0.25]}
          radius={0.055}
          position={[0, -0.055, z]}
          castShadow
        >
          <meshStandardMaterial map={texture} color="#9e2832" roughness={0.9} metalness={0.01} />
        </RoundedBox>
      ))}

      {Array.from({ length: braceCount }).map((_, index) => {
        const z = -canopyLength / 2 + index * canopyLength / Math.max(braceCount - 1, 1)
        return (
          <group key={`central-canopy-brace-${index}`} position={[0, -0.09, z]}>
            <RoundedBox args={[width + 0.08, 0.045, 0.055]} radius={0.009}>
              <meshStandardMaterial color="#747f84" roughness={0.34} metalness={0.8} />
            </RoundedBox>
            {[-1, 1].map((side) => (
              <mesh key={`brace-hook-${side}`} position={[side * (width / 2 + 0.02), 0.14, 0]}>
                <cylinderGeometry args={[0.012, 0.012, 0.3, 8]} />
                <meshStandardMaterial color="#667177" roughness={0.32} metalness={0.84} />
              </mesh>
            ))}
          </group>
        )
      })}

      <mesh
        geometry={dividerGeometry}
        position={[0, -0.48, 0.18]}
        rotation={[0, Math.PI / 2, 0]}
        castShadow
        receiveShadow
      >
        <meshStandardMaterial map={texture} color="#8f2029" roughness={0.92} metalness={0.01} side={DoubleSide} />
      </mesh>

      {Array.from({ length: fanCount }).map((_, index) => {
        const z = -canopyLength * 0.28 + index * canopyLength * 0.56 / Math.max(fanCount - 1, 1)
        return (
          <group
            key={`under-canopy-fan-${index}`}
            position={[0, -0.19, z]}
            rotation={[Math.PI / 2, 0, 0]}
            scale={0.72}
          >
            <FanRotor position={[0, 0, 0]} active={active} reverse={inversion} />
          </group>
        )
      })}

      <RoundedBox args={[width * 0.36, 0.38, 0.58]} radius={0.05} position={[0, -0.24, -canopyLength / 2 + 0.12]}>
        <meshStandardMaterial map={texture} color="#98242d" roughness={0.9} metalness={0.01} />
      </RoundedBox>
    </group>
  )
}
function getPalletZone(layout, tunnelType) {
  if (tunnelType === 'modular' || tunnelType === 'californiano') {
    const halfWidth = layout.structureWidth / 2
    const laneWidth = 1.05
    const wallInset = tunnelType === 'californiano' ? 1.12 : 0.86
    const bandCenters = Array.from({ length: layout.totalBands }).map((_, bandIndex) => {
      const side = bandIndex % 2 === 0 ? -1 : 1
      const depthFromWall = wallInset + Math.floor(bandIndex / 2) * laneWidth
      return side * Math.max(halfWidth - depthFromWall, 0.62)
    })
    const boundaryXs = [...new Set(bandCenters.flatMap((x) => [
      Number((x - laneWidth / 2).toFixed(3)),
      Number((x + laneWidth / 2).toFixed(3)),
    ]))]

    return { laneWidth, bandCenters, boundaryXs }
  }

  const zoneWidth = Math.min(
    layout.structureWidth * 0.62,
    Math.max(layout.totalBands * 1.2, 2.6),
  )
  const laneWidth = zoneWidth / Math.max(layout.totalBands, 1)
  const minX = -zoneWidth / 2

  return {
    zoneWidth,
    laneWidth,
    minX,
    bandCenters: Array.from({ length: layout.totalBands }).map((_, index) => (
      minX + laneWidth * (index + 0.5)
    )),
    boundaryXs: Array.from({ length: layout.totalBands + 1 }).map((_, index) => (
      minX + index * laneWidth
    )),
  }
}

function getPositionZ(layout, positionIndex, tunnelType) {
  const halfLength = layout.tunnelLength / 2
  if (tunnelType === 'californiano') {
    return halfLength - positionIndex * layout.POSITION_SPACING - layout.POSITION_SPACING / 2
  }
  const entranceInset = tunnelType === 'modular' ? 0.72 : 0
  return -halfLength
    + entranceInset
    + positionIndex * layout.POSITION_SPACING
    + layout.POSITION_SPACING / 2
}

function getStackLevelCount(layout) {
  return Math.max(layout.niveles.length, 1)
}

function PositionSlot({
  position,
  width,
  depth,
  label,
  folios = [],
  totalLevels,
  canAssign,
  onEmptySelect,
  onOccupiedSelect,
}) {
  const [hovered, setHovered] = useState(false)
  const occupiedCount = folios.length
  const capacity = Math.max(totalLevels, 1)
  const hasCapacity = occupiedCount < capacity
  const isOccupied = occupiedCount > 0
  const canAdd = canAssign && hasCapacity
  const isInteractive = isOccupied || canAdd
  const baseColor = !hasCapacity ? '#2f6f55' : canAdd ? COLORS.location : '#77827e'
  const hoverColor = canAdd ? '#55b98b' : '#3f8b69'

  const handleClick = (event) => {
    event.stopPropagation()
    if (canAdd) {
      onEmptySelect?.()
      return
    }
    if (isOccupied) onOccupiedSelect?.(folios[0])
  }

  return (
    <group
      position={position}
      onClick={isInteractive ? handleClick : undefined}
      onPointerOver={(event) => {
        event.stopPropagation()
        setHovered(true)
        document.body.style.cursor = isInteractive ? 'pointer' : 'default'
      }}
      onPointerOut={() => {
        setHovered(false)
        document.body.style.cursor = 'default'
      }}
    >
      {hovered && (
        <Html center position={[0, 0.38, 0]} distanceFactor={7.5} zIndexRange={[70, 0]}>
          <div className="pointer-events-none min-w-max rounded-lg border border-emerald-300/45 bg-slate-950/95 px-3 py-2 text-center text-[11px] font-semibold leading-tight text-white shadow-xl backdrop-blur-sm">
            {label}
            <div className="mt-1 text-[9px] font-normal uppercase tracking-[0.12em] text-emerald-300">
              {`${occupiedCount}/${capacity} niveles · ${canAdd ? 'Disponible para asignar' : hasCapacity ? 'Vacía' : 'Completa'}`}
            </div>
          </div>
        </Html>
      )}
      <RoundedBox args={[width, hovered ? 0.08 : 0.045, depth]} radius={0.025}>
        <meshStandardMaterial
          color={hovered ? hoverColor : baseColor}
          roughness={0.68}
          transparent
          opacity={isInteractive ? 0.96 : 0.62}
        />
      </RoundedBox>
      <Text
        position={[0, hovered ? 0.052 : 0.032, 0]}
        rotation={[-Math.PI / 2, 0, 0]}
        fontSize={Math.min(0.105, width * 0.11)}
        color="#f4fff9"
        anchorX="center"
        anchorY="middle"
        textAlign="center"
        outlineWidth={0.004}
        outlineColor={COLORS.locationDark}
      >
        {`${label}\n${occupiedCount}/${capacity} NIVELES · ${canAdd ? 'ASIGNAR' : hasCapacity ? 'VACIA' : 'COMPLETA'}`}
      </Text>
    </group>
  )
}

function FloorLocations({
  layout,
  tunnelType,
  folios,
  alturaActual,
  canAssign,
  onEmptyPositionSelect,
  onOccupiedPositionSelect,
}) {
  const { bandas, posiciones, totalBands, totalPositions, POSITION_SPACING } = layout
  const { laneWidth, bandCenters, boundaryXs } = getPalletZone(layout, tunnelType)
  const positionCenters = Array.from({ length: totalPositions }).map((_, index) => (
    getPositionZ(layout, index, tunnelType)
  ))
  const firstCenterZ = positionCenters[0] ?? 0
  const lastCenterZ = positionCenters[positionCenters.length - 1] ?? firstCenterZ
  const occupiedLength = Math.max(
    Math.abs(lastCenterZ - firstCenterZ) + POSITION_SPACING * 0.82,
    1.4,
  )
  const occupiedCenterZ = (firstCenterZ + lastCenterZ) / 2
  const foliosByLocation = useMemo(() => {
    const indexed = new Map()
    ;(folios || []).forEach((folio) => {
      if (String(folio.altura) !== String(alturaActual)) return
      const key = `${folio.banda}::${folio.posicion}`
      indexed.set(key, [...(indexed.get(key) || []), folio])
    })
    return indexed
  }, [folios, alturaActual])

  if (!totalBands || !totalPositions) return null

  return (
    <group>
      {boundaryXs.map((x, boundaryIndex) => (
        <RoundedBox
          key={`safety-divider-${boundaryIndex}`}
          args={[0.12, 0.12, occupiedLength]}
          radius={0.025}
          position={[x, 0.065, occupiedCenterZ]}
        >
          <meshStandardMaterial color={COLORS.safety} roughness={0.7} />
        </RoundedBox>
      ))}

      {bandas.map((band, bandIndex) => {
        const x = bandCenters[bandIndex]
        return posiciones.map((position, positionIndex) => {
          const z = getPositionZ(layout, positionIndex, tunnelType)
          const locationFolios = foliosByLocation.get(`${band}::${position}`) || []
          return (
            <PositionSlot
              key={`location-${band}-${position}`}
              position={[x, 0.027, z]}
              width={laneWidth * 0.78}
              depth={POSITION_SPACING * 0.72}
              label={`${band} · ${position}`}
              folios={locationFolios}
              totalLevels={layout.niveles.length}
              canAssign={canAssign}
              onEmptySelect={() => onEmptyPositionSelect?.(band, position)}
              onOccupiedSelect={onOccupiedPositionSelect}
            />
          )
        })
      })}
    </group>
  )
}

function TunnelFolios({ layout, tunnelType, folios, alturaActual, selectedFolio, onSelect }) {
  const positionedFolios = useMemo(() => {
    const {
      bandas,
      niveles,
      posiciones,
    } = layout
    const { bandCenters } = getPalletZone(layout, tunnelType)

    return (folios || [])
      .filter((folio) => String(folio.altura) === String(alturaActual))
      .map((folio, fallbackIndex) => {
        const bandIndex = Math.max(bandas.indexOf(String(folio.banda)), 0)
        const storageLevelIndex = Math.max(niveles.indexOf(String(folio.nivel)), 0)
        const positionIndex = Math.max(posiciones.indexOf(String(folio.posicion)), 0)

        return {
          ...folio,
          fallbackIndex,
          scenePosition: [
            bandCenters[bandIndex],
            0.66 + storageLevelIndex * 0.82,
            getPositionZ(layout, positionIndex, tunnelType),
          ],
        }
      })
  }, [folios, alturaActual, layout, tunnelType])

  return positionedFolios.map((folio) => (
    <Pallet3D
      key={folio.id || folio.fallbackIndex}
      position={folio.scenePosition}
      folio={folio.folio}
      cajas={folio.cajas}
      especie={folio.especie}
      nivel={folio.nivel}
      tooltip={`Banda ${folio.banda} · Posición ${folio.posicion} · Nivel ${folio.nivel}`}
      isSelected={selectedFolio?.id === folio.id}
      onClick={(event) => {
        event?.stopPropagation?.()
        onSelect(folio)
      }}
    />
  ))
}

function SceneLabels({ layout, tunnelType }) {
  const halfLength = layout.tunnelLength / 2
  const zoneLabel = tunnelType === 'modular'
    ? 'ZONA MODULAR DE PALLETS'
    : tunnelType === 'californiano'
      ? 'TÚNEL CALIFORNIANO · FLUJO FORZADO'
      : 'ZONA CENTRAL DE PALLETS'

  return (
    <group>
      <Text
        position={[0, 1.48, -halfLength + 0.7]}
        rotation={[0, Math.PI, 0]}
        fontSize={0.22}
        color="#fff9a8"
        anchorX="center"
        outlineWidth={0.01}
        outlineColor="#514d08"
      >
        {zoneLabel}
      </Text>
    </group>
  )
}

function Scene({
  layout,
  folios,
  selectedFolio,
  onSelect,
  onEmptyPositionSelect,
  alturaActual,
  canAssign,
  processStarted,
  processInversion,
  tunnelType,
  codigo,
  titulo,
  navigationInput,
}) {
  const palletStackHeight = Math.max(getStackLevelCount(layout) * 0.82 + 0.9, 1.3)
  const ceilingHeight = Math.max(palletStackHeight + 1.15, 5.2)
  const halfLength = layout.tunnelLength / 2

  return (
    <>
      <color attach="background" args={['#e4e9ec']} />
      <fog attach="fog" args={['#dfe5e8', layout.tunnelLength * 0.75, layout.tunnelLength * 2.1]} />

      <ambientLight intensity={0.34} />
      <hemisphereLight skyColor="#eaf4f8" groundColor="#657078" intensity={0.42} />
      <directionalLight
        position={[-4, ceilingHeight + 2, -halfLength]}
        intensity={1.15}
        castShadow
        shadow-mapSize={[2048, 2048]}
        shadow-camera-near={0.1}
        shadow-camera-far={40}
        shadow-normalBias={0.025}
      />
      <Environment preset="warehouse" environmentIntensity={0.55} />

      <IndustrialFloor length={layout.tunnelLength} width={layout.structureWidth} />
      <TunnelShell length={layout.tunnelLength} width={layout.structureWidth} height={ceilingHeight} />
      <GarateBrand3D
        position={[0, ceilingHeight * 0.62, halfLength - 0.075]}
        rotation={[0, Math.PI, 0]}
        logoWidth={Math.min(layout.structureWidth * 0.42, 3.5)}
        logoY={0.18}
        nameY={-0.72}
        name={[codigo, titulo].filter(Boolean).join(' · ') || 'TÚNEL DE PREFRÍO'}
        fontSize={0.24}
      />
      <FirstPersonController
        inputRef={navigationInput}
        initialPosition={[0, 1.65, -halfLength - 5.2]}
        bounds={{
          minX: -layout.structureWidth / 2 + 0.34,
          maxX: layout.structureWidth / 2 - 0.34,
          minZ: -halfLength - 5.55,
          maxZ: halfLength - 0.34,
        }}
      />
      <FloorLocations
        layout={layout}
        tunnelType={tunnelType}
        folios={folios}
        alturaActual={alturaActual}
        canAssign={canAssign}
        onEmptyPositionSelect={onEmptyPositionSelect}
        onOccupiedPositionSelect={onSelect}
      />
      {tunnelType === 'modular' ? (
        <ModularCoolingSystem
          layout={layout}
          ceilingHeight={ceilingHeight}
          active={processStarted}
          inversion={processInversion}
        />
      ) : tunnelType === 'californiano' ? (
        <CalifornianCoolingSystem
          layout={layout}
          ceilingHeight={ceilingHeight}
          active={processStarted}
          inversion={processInversion}
        />
      ) : (
        <>
          <RefrigerationPipes layout={layout} ceilingHeight={ceilingHeight} />
          <EvaporatorBank
            layout={layout}
            ceilingHeight={ceilingHeight}
            active={processStarted}
            inversion={processInversion}
          />
          <TunnelCurtain
            length={layout.tunnelLength}
            width={layout.structureWidth * 0.72}
            ceilingHeight={ceilingHeight}
            lowered={processStarted}
            active={processStarted}
            inversion={processInversion}
          />
        </>
      )}
      <RedDoor
        position={[0, 1.72, -halfLength + 0.08]}
        width={layout.structureWidth}
        label={titulo || codigo}
        processStarted={processStarted}
        processInversion={processInversion}
      />
      <TunnelFolios
        layout={layout}
        tunnelType={tunnelType}
        folios={folios}
        alturaActual={alturaActual}
        selectedFolio={selectedFolio}
        onSelect={onSelect}
      />
      <SceneLabels layout={layout} tunnelType={tunnelType} />

    </>
  )
}

export default function TunnelScene3D({
  parametros,
  folios,
  tipoTunel = 'evaporador_central',
  alturaActual,
  canAssign = false,
  processStarted = false,
  processInversion = false,
  codigo,
  titulo,
  mode = 'tunel',
  onFolioSelect,
  onEmptyPositionSelect,
}) {
  const [selectedFolio, setSelectedFolio] = useState(null)
  const navigationInput = useFirstPersonNavigationInput()
  const layout = useTunnelLayout(parametros, folios)
  const tunnelType = normalizeTunnelType(tipoTunel)
  const palletStackHeight = Math.max(getStackLevelCount(layout) * 0.82 + 0.9, 1.3)
  const ceilingHeight = Math.max(palletStackHeight + 1.15, 5.2)
  const cameraPosition = useMemo(
    () => [0, 1.65, -layout.tunnelLength / 2 - 5.2],
    [layout.tunnelLength],
  )

  const handleSelect = useCallback((folio) => {
    setSelectedFolio((current) => current?.id === folio.id ? null : folio)
    onFolioSelect?.(folio)
  }, [onFolioSelect])

  return (
    <div className="relative h-[620px] w-full overflow-hidden rounded-xl border border-slate-700 bg-slate-900 shadow-[0_22px_50px_-28px_rgba(15,23,42,0.72)]">
      <Canvas
        camera={{ position: cameraPosition, fov: 56, near: 0.1, far: 160 }}
        shadows
        dpr={[1, 1.75]}
        gl={{ antialias: true, toneMapping: 3, toneMappingExposure: 1.08 }}
        onPointerMissed={() => setSelectedFolio(null)}
      >
        <Suspense fallback={null}>
          <Scene
            layout={layout}
            folios={folios}
            selectedFolio={selectedFolio}
            onSelect={handleSelect}
            onEmptyPositionSelect={onEmptyPositionSelect}
            alturaActual={alturaActual}
            canAssign={canAssign}
            processStarted={processStarted}
            processInversion={processInversion}
            tunnelType={tunnelType}
            codigo={codigo}
            titulo={titulo}
            navigationInput={navigationInput}
          />
        </Suspense>
      </Canvas>

      <div className="pointer-events-none absolute left-3 top-3 max-w-[260px] border border-white/10 bg-slate-950/82 px-3 py-2 text-[10px] text-slate-300 shadow-lg backdrop-blur-md">
        <div className="font-semibold uppercase tracking-[0.16em] text-white">
          {mode === 'tunel' ? 'Túnel de prefrío' : 'Cámara'}
        </div>
        <div className="mt-1 font-mono text-[9px] uppercase tracking-[0.12em] text-cyan-200">
          Sistema {TUNNEL_TYPE_LABELS[tunnelType]}
        </div>
        <div className="mt-1 text-slate-400">Camina en primera persona dentro del túnel</div>
        <div className="mt-2 border-t border-white/10 pt-2 font-mono text-[9px]">
          {layout.totalBands} bandas · {layout.totalPositions} posiciones · {layout.totalLevels} alturas
        </div>
      </div>

      <aside className="pointer-events-none absolute right-3 top-3 max-h-[260px] w-40 overflow-hidden border border-white/10 bg-slate-950/82 p-2 text-white shadow-lg backdrop-blur-md">
        <div className="border-b border-white/10 pb-1.5 text-[9px] font-bold uppercase tracking-[0.14em]">
          Niveles configurados
        </div>
        <div className="mt-1.5 grid max-h-[205px] grid-cols-2 gap-1 overflow-y-auto">
          {(layout.niveles.length ? layout.niveles : layout.alturas).map((nivel) => (
            <div key={nivel} className="border border-white/10 bg-white/[0.06] px-1.5 py-1 font-mono text-[9px]">
              {nivel}
            </div>
          ))}
        </div>
      </aside>

      <div className="pointer-events-none absolute left-3 top-32 hidden items-center gap-3 border border-white/10 bg-slate-950/82 px-3 py-2 text-[9px] text-slate-300 backdrop-blur-md md:flex">
        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#d5cf22]" /> Separadores</span>
        <span className="inline-flex items-center gap-1.5"><span className="h-2 w-2 bg-[#3d9d73]" /> Ubicaciones centrales</span>
        <span>{folios?.length || 0} folios</span>
      </div>

      <FirstPersonNavigationHud inputRef={navigationInput} />

      <FolioInfoPanel folio={selectedFolio} onClose={() => setSelectedFolio(null)} />
    </div>
  )
}
