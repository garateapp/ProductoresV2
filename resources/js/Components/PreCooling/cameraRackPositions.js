export function buildConsecutivePositionsByBand(bands, countsByBand) {
  let nextPosition = 1

  return Object.fromEntries(bands.map((band) => {
    const count = Math.max(Number(countsByBand[band]) || 0, 0)
    const positions = Array.from({ length: count }, () => String(nextPosition++))
    return [band, positions]
  }))
}

export function buildPositionLabelsByBand(bands, rowsByBand) {
  const countsByBand = Object.fromEntries(
    bands.map((band) => [band, rowsByBand[band]?.length || 0]),
  )

  return buildConsecutivePositionsByBand(bands, countsByBand)
}

export function getConsecutivePositionLabel(bands, rowsByBand, band, row) {
  const bandIndex = bands.indexOf(band)
  const rowIndex = rowsByBand[band]?.indexOf(String(row)) ?? -1
  if (bandIndex < 0 || rowIndex < 0) return String(row ?? '')

  const previousPositions = bands
    .slice(0, bandIndex)
    .reduce((total, currentBand) => total + (rowsByBand[currentBand]?.length || 0), 0)

  return String(previousPositions + rowIndex + 1)
}
