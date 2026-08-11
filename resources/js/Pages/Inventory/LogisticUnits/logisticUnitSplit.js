export const splitEvenly = (total, count) => {
  const quantity = Number(total ?? 0)
  const parts = Number(count ?? 0)

  if (!Number.isFinite(quantity) || !Number.isFinite(parts) || parts < 2 || quantity <= 0) {
    return []
  }

  const perPallet = Math.round((quantity / parts) * 10000) / 10000
  const quantities = []

  for (let index = 0; index < parts; index++) {
    const value = index === parts - 1
      ? Math.round((quantity - perPallet * (parts - 1)) * 10000) / 10000
      : perPallet

    if (value <= 0) {
      return []
    }

    quantities.push(value)
  }

  return quantities
}
