import { RoundedBox } from '@react-three/drei'

export default function RedDoor({ position = [0, 3, 10], width = 4, height = 5 }) {
  const panelWidth = width / 3
  const panelHeight = height / 4

  return (
    <group position={position}>
      {/* Main door frame */}
      <RoundedBox args={[width + 0.3, height + 0.3, 0.15]} radius={0.02} position={[0, 0, -0.05]}>
        <meshStandardMaterial color="#1f2937" roughness={0.5} metalness={0.3} />
      </RoundedBox>

      {/* Door panels - red */}
      {Array.from({ length: 4 }).map((_, row) =>
        Array.from({ length: 3 }).map((_, col) => (
          <RoundedBox
            key={`dp-${row}-${col}`}
            args={[panelWidth - 0.08, panelHeight - 0.08, 0.08]}
            radius={0.02}
            position={[
              (col - 1) * panelWidth,
              (row - 1.5) * panelHeight,
              0.05,
            ]}
          >
            <meshStandardMaterial
              color="#dc2626"
              roughness={0.4}
              metalness={0.2}
            />
          </RoundedBox>
        ))
      )}

      {/* Glass inserts (small windows) */}
      {[1, 2].map((row) =>
        [0, 1, 2].map((col) => (
          <RoundedBox
            key={`gw-${row}-${col}`}
            args={[panelWidth * 0.4, panelHeight * 0.3, 0.02]}
            radius={0.01}
            position={[
              (col - 1) * panelWidth,
              (row - 1.5) * panelHeight,
              0.1,
            ]}
          >
            <meshStandardMaterial
              color="#93c5fd"
              transparent
              opacity={0.4}
              roughness={0.1}
              metalness={0.8}
            />
          </RoundedBox>
        ))
      )}

      {/* Handle */}
      <RoundedBox args={[0.08, 0.6, 0.08]} radius={0.02} position={[panelWidth * 0.8, 0, 0.15]}>
        <meshStandardMaterial color="#fbbf24" roughness={0.3} metalness={0.7} />
      </RoundedBox>
    </group>
  )
}
