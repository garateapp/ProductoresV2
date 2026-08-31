import { useRef, useMemo } from 'react'
import { useFrame } from '@react-three/fiber'
import { Text } from '@react-three/drei'

export default function PositionLabel3D({ text, position, color = '#22c55e', fontSize = 0.18 }) {
  const ref = useRef()

  useFrame(({ camera }) => {
    if (!ref.current) return
    const dist = camera.position.distanceTo(ref.current.position)
    const scale = Math.min(Math.max(1 - dist * 0.03, 0.4), 1.2)
    ref.current.scale.setScalar(scale)
    ref.current.material.opacity = Math.min(Math.max(1 - dist * 0.02, 0.2), 1)
  })

  return (
    <Text
      ref={ref}
      position={position}
      fontSize={fontSize}
      color={color}
      anchorX="center"
      anchorY="middle"
      rotation={[-Math.PI / 2, 0, 0]}
      outlineWidth={0.005}
      outlineColor="#000000"
      fillOpacity={1}
    >
      {text}
    </Text>
  )
}
