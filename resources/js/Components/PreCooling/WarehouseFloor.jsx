import { useMemo } from 'react'
import { Text, Line } from '@react-three/drei'

const FLOOR_COLOR = '#d1d5db'
const LINE_COLOR = '#eab308'
const MARKER_COLOR = '#22c55e'

export default function WarehouseFloor({ tunnelLength = 20, width = 10 }) {
  const halfL = tunnelLength / 2
  const halfW = width / 2

  const safetyLines = useMemo(() => {
    const lines = []
    const laneWidth = 2.8

    // Left safety line
    lines.push({
      key: 'sl-left',
      points: [
        [-laneWidth, 0.01, -halfL],
        [-laneWidth, 0.01, halfL],
      ],
    })
    // Right safety line
    lines.push({
      key: 'sl-right',
      points: [
        [laneWidth, 0.01, -halfL],
        [laneWidth, 0.01, halfL],
      ],
    })
    // Dashed center line
    for (let z = -halfL; z < halfL; z += 1.5) {
      lines.push({
        key: `cl-${z}`,
        points: [
          [0, 0.01, z],
          [0, 0.01, Math.min(z + 0.6, halfL)],
        ],
      })
    }
    return lines
  }, [tunnelLength])

  const positionMarkers = useMemo(() => {
    const markers = []
    for (let i = 1; i <= Math.floor(tunnelLength / 2.5); i++) {
      markers.push({
        key: `pm-${i}`,
        text: `Pos ${String(i).padStart(2, '0')}`,
        position: [0, 0.02, (i - 1) * 2.5 - halfL + 1.25],
      })
    }
    return markers
  }, [tunnelLength])

  return (
    <group>
      {/* Main floor */}
      <mesh rotation={[-Math.PI / 2, 0, 0]} position={[0, 0, 0]} receiveShadow>
        <planeGeometry args={[width + 4, tunnelLength + 2]} />
        <meshStandardMaterial color={FLOOR_COLOR} roughness={0.9} metalness={0.05} />
      </mesh>

      {/* Floor grid lines */}
      {Array.from({ length: Math.floor(width + 4) + 1 }).map((_, i) => (
        <Line
          key={`gx-${i}`}
          points={[
            [i - (width + 4) / 2, 0.005, -halfL - 1],
            [i - (width + 4) / 2, 0.005, halfL + 1],
          ]}
          color="#b0b0b0"
          lineWidth={0.5}
        />
      ))}
      {Array.from({ length: Math.floor(tunnelLength + 2) + 1 }).map((_, i) => (
        <Line
          key={`gz-${i}`}
          points={[
            [-(width + 4) / 2, 0.005, i - halfL - 1],
          [(width + 4) / 2, 0.005, i - halfL - 1],
          ]}
          color="#b0b0b0"
          lineWidth={0.5}
        />
      ))}

      {/* Safety lines - yellow */}
      {safetyLines.map((line) => (
        <Line
          key={line.key}
          points={line.points}
          color={LINE_COLOR}
          lineWidth={3}
        />
      ))}

      {/* Position markers */}
      {positionMarkers.map((m) => (
        <group key={m.key} position={m.position}>
          <mesh rotation={[-Math.PI / 2, 0, 0]}>
            <planeGeometry args={[0.8, 0.4]} />
            <meshStandardMaterial color={MARKER_COLOR} transparent opacity={0.7} />
          </mesh>
          <Text
            position={[0, 0.05, 0]}
            fontSize={0.15}
            color="#ffffff"
            anchorX="center"
            anchorY="middle"
            rotation={[-Math.PI / 2, 0, 0]}
          >
            {m.text}
          </Text>
        </group>
      ))}
    </group>
  )
}
