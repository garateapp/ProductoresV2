import { useEffect } from 'react'
import { Text, useTexture } from '@react-three/drei'
import { SRGBColorSpace } from 'three'

const LOGO_ASPECT_RATIO = 503 / 232

export default function GarateBrand3D({
  position = [0, 0, 0],
  rotation = [0, 0, 0],
  logoWidth = 1.8,
  logoY = 0.2,
  nameY = -0.42,
  name = '',
  fontSize = 0.2,
  textColor = '#263238',
  outlineColor = '#f7faf9',
}) {
  const logoTexture = useTexture('/img/logo_garate.png')

  useEffect(() => {
    logoTexture.colorSpace = SRGBColorSpace
    logoTexture.needsUpdate = true
  }, [logoTexture])

  return (
    <group position={position} rotation={rotation}>
      <mesh position={[0, logoY, 0]} renderOrder={4}>
        <planeGeometry args={[logoWidth, logoWidth / LOGO_ASPECT_RATIO]} />
        <meshBasicMaterial
          map={logoTexture}
          transparent
          alphaTest={0.02}
          depthWrite={false}
          toneMapped={false}
        />
      </mesh>
      {!!name && (
        <Text
          position={[0, nameY, 0.006]}
          fontSize={fontSize}
          maxWidth={logoWidth * 1.35}
          color={textColor}
          anchorX="center"
          anchorY="middle"
          textAlign="center"
          fontWeight={700}
          outlineWidth={fontSize * 0.035}
          outlineColor={outlineColor}
        >
          {name}
        </Text>
      )}
    </group>
  )
}
