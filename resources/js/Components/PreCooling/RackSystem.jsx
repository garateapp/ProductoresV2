import { useMemo } from 'react'
import { RoundedBox, Text } from '@react-three/drei'

const BEAM_COLOR = '#374151'
const POST_COLOR = '#6b7280'
const PALLET_COLOR = '#8B7355'
const BOX_COLOR = '#d97706'

function PalletUnit({ position }) {
  return (
    <group position={position}>
      {/* Pallet base */}
      <RoundedBox args={[0.9, 0.1, 0.9]} radius={0.01} position={[0, 0, 0]}>
        <meshStandardMaterial color={PALLET_COLOR} roughness={0.85} metalness={0.05} />
      </RoundedBox>

      {/* Product box */}
      <RoundedBox args={[0.8, 0.55, 0.8]} radius={0.02} position={[0, 0.33, 0]}>
        <meshStandardMaterial color={BOX_COLOR} roughness={0.4} metalness={0.08} transparent opacity={0.9} />
      </RoundedBox>
    </group>
  )
}

function RackLevel({ position, numSlots = 4, slotWidth = 1.1 }) {
  const halfW = (numSlots * slotWidth) / 2

  return (
    <group position={position}>
      {/* Horizontal beams */}
      <RoundedBox args={[numSlots * slotWidth + 0.2, 0.06, 0.9]} radius={0.01} position={[0, 0.6, 0]}>
        <meshStandardMaterial color={BEAM_COLOR} roughness={0.5} metalness={0.5} />
      </RoundedBox>

      {/* Vertical posts at ends */}
      {[-halfW - 0.05, halfW + 0.05].map((x) => (
        <RoundedBox key={x} args={[0.06, 1.2, 0.06]} radius={0.01} position={[x, 0, 0]}>
          <meshStandardMaterial color={POST_COLOR} roughness={0.5} metalness={0.4} />
        </RoundedBox>
      ))}
    </group>
  )
}

export default function RackSystem({ levels = 6, slots = 4, sections = 8, spacing = 2.5 }) {
  const levelHeight = 1.2

  return (
    <group>
      {Array.from({ length: levels }).map((_, lvl) =>
        Array.from({ length: sections }).map((_, sec) => {
          const x = 3.5
          const y = lvl * levelHeight + 0.8
          const z = sec * spacing - (sections * spacing) / 2 + spacing / 2

          return (
            <group key={`r-${lvl}-${sec}`} position={[x, y, z]}>
              <RackLevel numSlots={slots} />
              {Array.from({ length: slots }).map((_, s) => (
                <PalletUnit
                  key={`p-${s}`}
                  position={[(s - (slots - 1) / 2) * 1.1, 0.05, 0]}
                />
              ))}
            </group>
          )
        })
      )}
    </group>
  )
}
