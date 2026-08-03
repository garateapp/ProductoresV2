export function locationSubmissionCode(location) {
  return location?.scan_code || location?.path_code || location?.codigo || ''
}
