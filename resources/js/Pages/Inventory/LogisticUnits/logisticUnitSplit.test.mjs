import assert from 'node:assert/strict'
import test from 'node:test'

import { splitEvenly } from './logisticUnitSplit.js'

test('splits a quantity into equal parts keeping the remainder in the last pallet', () => {
  assert.deepEqual(splitEvenly(10, 2), [5, 5])
  assert.deepEqual(splitEvenly(1000, 4), [250, 250, 250, 250])
  assert.deepEqual(splitEvenly(26, 5), [5.2, 5.2, 5.2, 5.2, 5.2])
})

test('keeps a 4-decimal remainder in the last pallet so the sum matches the total', () => {
  const quantities = splitEvenly(1000, 3)
  assert.equal(quantities.length, 3)
  assert.deepEqual(quantities, [333.3333, 333.3333, 333.3334])
  assert.equal(quantities.reduce((sum, q) => sum + q, 0), 1000)
})

test('returns an empty list for invalid inputs', () => {
  assert.deepEqual(splitEvenly(0, 3), [])
  assert.deepEqual(splitEvenly(-5, 3), [])
  assert.deepEqual(splitEvenly(10, 1), [])
  assert.deepEqual(splitEvenly(10, 0), [])
  assert.deepEqual(splitEvenly(null, 3), [])
})

test('returns empty when a pallet would end up with zero quantity', () => {
  assert.deepEqual(splitEvenly(0.0001, 5), [])
})
