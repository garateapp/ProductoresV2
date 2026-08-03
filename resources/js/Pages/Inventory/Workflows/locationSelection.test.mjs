import assert from 'node:assert/strict'
import test from 'node:test'

import { locationSubmissionCode } from './locationSelection.js'

test('prefers scan code when selecting a location for workflow submission', () => {
  const location = {
    codigo: 'BOD-01',
    scan_code: 'LOC-BOD-01',
    path_code: 'PLANTA/BOD-01',
  }

  assert.equal(locationSubmissionCode(location), 'LOC-BOD-01')
})

test('falls back to path code and then plain code', () => {
  assert.equal(locationSubmissionCode({ path_code: 'PLANTA/RACK-01', codigo: 'RACK-01' }), 'PLANTA/RACK-01')
  assert.equal(locationSubmissionCode({ codigo: 'RACK-01' }), 'RACK-01')
  assert.equal(locationSubmissionCode(null), '')
})
