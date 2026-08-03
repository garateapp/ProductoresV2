import assert from 'node:assert/strict'
import test from 'node:test'

import { applyDetailPatch } from './detailState.js'

test('selecting a material clears the position without losing the material', () => {
  const details = [
    { material_id: '', position_id: '9', cantidad: '', sentido: 'salida', observacion: '' },
  ]

  const next = applyDetailPatch(details, 0, { material_id: '12', position_id: '' })

  assert.equal(next[0].material_id, '12')
  assert.equal(next[0].position_id, '')
})

test('updating a detail does not mutate the current details array', () => {
  const details = [
    { material_id: '8', position_id: '9', cantidad: '', sentido: 'salida', observacion: '' },
  ]

  const next = applyDetailPatch(details, 0, { cantidad: '4.5' })

  assert.equal(next[0].cantidad, '4.5')
  assert.equal(details[0].cantidad, '')
  assert.notEqual(next, details)
  assert.notEqual(next[0], details[0])
})
