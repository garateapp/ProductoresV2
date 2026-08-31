import { useMemo } from 'react'

const BAND_SPACING = 1.8
const LEVEL_HEIGHT = 0.55
const POSITION_SPACING = 0.9
const MIN_TUNNEL_LENGTH = 12
const MIN_STRUCTURE_WIDTH = 8

export default function useTunnelLayout(parametros, folios = []) {
  return useMemo(() => {
    const bandas = parametros?.banda ?? []
    const posiciones = parametros?.posicion ?? []
    const alturas = parametros?.altura ?? []
    const niveles = parametros?.nivel ?? []

    const totalBands = bandas.length
    const totalLevels = alturas.length
    const totalPositions = posiciones.length

    const structureWidth = Math.max(
      MIN_STRUCTURE_WIDTH,
      totalBands * BAND_SPACING + 2
    )

    const tunnelLength = Math.max(
      MIN_TUNNEL_LENGTH,
      totalPositions * POSITION_SPACING + 5
    )

    const systemHeight = totalLevels * LEVEL_HEIGHT

    const bandIndex = (banda) => {
      const idx = bandas.indexOf(String(banda))
      return idx >= 0 ? idx : 0
    }

    const levelIndex = (altura) => {
      const idx = alturas.indexOf(String(altura))
      return idx >= 0 ? idx : 0
    }

    const positionIndex = (posicion) => {
      const idx = posiciones.indexOf(String(posicion))
      return idx >= 0 ? idx : 0
    }

    const folioPosition = (folio) => {
      const bi = bandIndex(folio.banda)
      const li = levelIndex(folio.altura)
      const pi = positionIndex(folio.posicion)

      const halfWidth = (totalBands - 1) * BAND_SPACING / 2
      const halfLength = tunnelLength / 2

      const x = bi * BAND_SPACING - halfWidth
      const y = li * LEVEL_HEIGHT + 0.65
      const z = pi * POSITION_SPACING - halfLength + POSITION_SPACING / 2

      return [x, y, z]
    }

    return {
      bandas,
      posiciones,
      alturas,
      niveles,
      totalBands,
      totalLevels,
      totalPositions,
      BAND_SPACING,
      LEVEL_HEIGHT,
      POSITION_SPACING,
      structureWidth,
      tunnelLength,
      systemHeight,
      bandIndex,
      levelIndex,
      positionIndex,
      folioPosition,
    }
  }, [parametros, folios])
}
