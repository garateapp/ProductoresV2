export function applyDetailPatch(details, index, patch) {
  return details.map((detail, currentIndex) => (
    currentIndex === index ? { ...detail, ...patch } : detail
  ))
}
