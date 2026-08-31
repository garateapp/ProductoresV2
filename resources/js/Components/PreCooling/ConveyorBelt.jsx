import { useMemo, useRef } from 'react'
import { useFrame } from '@react-three/fiber'
import { RoundedBox } from '@react-three/drei'

const ROLLER_COLOR = '#2563eb'
const FRAME_COLOR = '#1e40af'
const LEG_COLOR = '#9ca3af'
const ROLLER_RADIUS = 0.04
const ROLLER_LENGTH = 1.8

function Roller({ position, rotation = [0, 0, 0] }) {
  return (
    <mesh position={position} rotation={rotation} castShadow>
      <cylinderGeometry args={[ROLLER_RADIUS, ROLLER_RADIUS, ROLLER_LENGTH, 8]} />
      <meshStandardMaterial color={ROLLER_COLOR} roughness={0.3} metalness={0.6} />
    </mesh>
  )
}

function ConveyorSection({ position, numRollers = 6 }) {
  const rollers = useMemo(() => {
    const items = []
    for (let i = 0; i < numRollers; i++) {
      items.push(
        <Roller
          key={i}
          position={[0, 0.08, (i - (numRollers - 1) / 2) * 0.3]}
          rotation={[0, 0, Math.PI / 2]}
        />
      )
    }
    return items
  }, [numRollers])

  return (
    <group position={position}>
      {/* Frame - blue industrial */}
      <RoundedBox args={[1.9, 0.06, numRollers * 0.3 + 0.2]} radius={0.01} position={[0, 0.12, 0]}>
        <meshStandardMaterial color={FRAME_COLOR} roughness={0.4} metalness={0.5} />
      </RoundedBox>

      {/* Side rails */}
      <RoundedBox args={[0.04, 0.1, numRollers * 0.3 + 0.2]} radius={0.005} position={[-0.95, 0.08, 0]}>
        <meshStandardMaterial color={ROLLER_COLOR} roughness={0.3} metalness={0.6} />
      </RoundedBox>
      <RoundedBox args={[0.04, 0.1, numRollers * 0.3 + 0.2]} radius={0.005} position={[0.95, 0.08, 0]}>
        <meshStandardMaterial color={ROLLER_COLOR} roughness={0.3} metalness={0.6} />
      </RoundedBox>

      {/* Rollers */}
      {rollers}

      {/* Support legs */}
      {[-0.8, 0.8].map((x) => (
        <RoundedBox key={x} args={[0.06, 0.8, 0.06]} radius={0.01} position={[x, -0.4, 0]}>
          <meshStandardMaterial color={LEG_COLOR} roughness={0.5} metalness={0.4} />
        </RoundedBox>
      ))}
    </group>
  )
}

export default function ConveyorBelt({ levels = 3, sections = 8, spacing = 2.5, tunnelLength }) {
  const levelHeight = 1.2

  return (
    <group>
      {Array.from({ length: levels }).map((_, lvl) =>
        Array.from({ length: sections }).map((_, sec) => {
          const x = -3.5
          const y = lvl * levelHeight + 0.8
          const z = sec * spacing - (sections * spacing) / 2 + spacing / 2

          return (
            <ConveyorSection
              key={`c-${lvl}-${sec}`}
              position={[x, y, z]}
              numRollers={6}
            />
          )
        })
      )}
    </group>
  )
}
