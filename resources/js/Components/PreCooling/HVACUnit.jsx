import { RoundedBox, Text } from '@react-three/drei'

export default function HVACUnit({ position = [0, 5.5, 9] }) {
  return (
    <group position={position}>
      {/* Main body */}
      <RoundedBox args={[3, 1.2, 1.5]} radius={0.05} position={[0, 0, 0]}>
        <meshStandardMaterial color="#1e40af" roughness={0.4} metalness={0.5} />
      </RoundedBox>

      {/* Fan grills */}
      {[-0.8, 0.8].map((x) => (
        <RoundedBox key={`fan-${x}`} args={[0.8, 0.8, 0.05]} radius={0.02} position={[x, 0, 0.78]}>
          <meshStandardMaterial color="#374151" roughness={0.5} metalness={0.6} />
        </RoundedBox>
      ))}

      {/* Red accent panels */}
      <RoundedBox args={[3.1, 0.15, 1.6]} radius={0.02} position={[0, 0.55, 0]}>
        <meshStandardMaterial color="#dc2626" roughness={0.4} metalness={0.3} />
      </RoundedBox>
      <RoundedBox args={[3.1, 0.15, 1.6]} radius={0.02} position={[0, -0.55, 0]}>
        <meshStandardMaterial color="#dc2626" roughness={0.4} metalness={0.3} />
      </RoundedBox>

      {/* Support brackets */}
      {[-1.2, 1.2].map((x) => (
        <RoundedBox key={`br-${x}`} args={[0.08, 1, 0.08]} radius={0.01} position={[x, -0.9, 0]}>
          <meshStandardMaterial color="#6b7280" roughness={0.5} metalness={0.4} />
        </RoundedBox>
      ))}

      {/* Ceiling pipes */}
      <RoundedBox args={[0.12, 0.12, 8]} radius={0.03} position={[-1.5, 1.5, 0]}>
        <meshStandardMaterial color="#60a5fa" roughness={0.3} metalness={0.5} />
      </RoundedBox>
      <RoundedBox args={[0.12, 0.12, 8]} radius={0.03} position={[1.5, 1.5, 0]}>
        <meshStandardMaterial color="#60a5fa" roughness={0.3} metalness={0.5} />
      </RoundedBox>
    </group>
  )
}
