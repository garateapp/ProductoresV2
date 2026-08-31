import { useMemo } from 'react'
import { RoundedBox } from '@react-three/drei'

const POST_COLOR = '#4a5568'
const BEAM_COLOR = '#6b7280'
const POST_SIZE = [0.06, 1, 0.06]
const BEAM_THICKNESS = 0.04

export default function Rack3D({ filas, columnas, altoMaximo, spacing = 1.1 }) {
  const elements = useMemo(() => {
    const posts = []
    const beams = []

    const slotsX = Math.max(columnas || 1, 1)
    const slotsZ = Math.max(filas || 1, 1)
    const levels = Math.max(altoMaximo || 1, 1)

    const halfW = (slotsX * spacing) / 2
    const halfD = (slotsZ * spacing) / 2

    for (let lvl = 0; lvl <= levels; lvl++) {
      const y = lvl * spacing - 0.5
      for (let xi = 0; xi <= slotsX; xi++) {
        for (let zi = 0; zi <= slotsZ; zi++) {
          const x = xi * spacing - halfW
          const z = zi * spacing - halfD
          if (lvl < levels) {
            posts.push({
              key: `p-${lvl}-${xi}-${zi}`,
              position: [x, y + spacing / 2, z],
            })
          }
        }
      }

      if (lvl > 0) {
        for (let xi = 0; xi < slotsX; xi++) {
          const x = xi * spacing - halfW + spacing / 2
          beams.push({
            key: `bx-${lvl}-${xi}`,
            position: [x, y, -halfD],
            size: [BEAM_THICKNESS, BEAM_THICKNESS, slotsZ * spacing],
            rotate: false,
          })
          beams.push({
            key: `bz-${lvl}-${xi}`,
            position: [-halfW, y, x],
            size: [slotsZ * spacing, BEAM_THICKNESS, BEAM_THICKNESS],
            rotate: false,
          })
        }
      }
    }

    return { posts, beams }
  }, [filas, columnas, altoMaximo, spacing])

  return (
    <group>
      {elements.posts.map((p) => (
        <RoundedBox key={p.key} args={POST_SIZE} radius={0.01} position={p.position}>
          <meshStandardMaterial color={POST_COLOR} roughness={0.6} metalness={0.4} />
        </RoundedBox>
      ))}

      {elements.beams.map((b) => (
        <RoundedBox key={b.key} args={b.size} radius={0.005} position={b.position}>
          <meshStandardMaterial color={BEAM_COLOR} roughness={0.5} metalness={0.5} />
        </RoundedBox>
      ))}
    </group>
  )
}
