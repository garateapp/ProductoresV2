import assert from 'node:assert/strict'
import test from 'node:test'

import {
  buildLogisticUnitLabelHtml,
  createLogisticUnitLabelData,
  generateLogisticUnitLabelZPL,
} from './logisticUnitLabel.js'

const fixedDate = new Date('2026-02-27T15:51:54')

test('builds a 100 by 150 mm label that fills the portrait media without browser down-scaling', () => {
  const label = createLogisticUnitLabelData({
    license_plate_number: '249',
    material: {
      codigo: 'IN00200500237',
      nombre: 'CAJA CE 500X300X120 MASTER GARCES',
    },
    location: {
      codigo: 'PLANTA',
      nombre: 'MOLINA',
    },
    lot_code: 'OC00031805-LO12214',
    available_quantity: 128,
    unit: 'UN',
  }, { now: fixedDate })

  const html = buildLogisticUnitLabelHtml(label, 'data:image/png;base64,qr')

  assert.match(html, /@page\s*\{\s*size:\s*100mm 150mm;\s*margin:\s*0;/)
  assert.match(html, /html\s*\{[^}]*width:\s*100mm;[^}]*height:\s*150mm;[^}]*padding:\s*0;/)
  assert.match(html, /body\s*\{[^}]*width:\s*100mm;[^}]*height:\s*150mm;[^}]*padding:\s*3mm 2mm;/)
  assert.match(html, /\.frame\s*\{[^}]*width:\s*100%;[^}]*height:\s*100%;/)
  assert.match(html, /grid-template-columns:\s*7mm minmax\(0,\s*1fr\) 18mm 22mm 16mm;/)
  assert.doesNotMatch(html, /width:\s*148mm/)
  assert.doesNotMatch(html, /height:\s*138mm/)
  assert.doesNotMatch(html, /grid-template-columns:\s*7mm 58mm 30mm 36mm 13mm;/)
})

test('builds ZPL with 2 mm side margins, 3 mm vertical margins, and content extending near the bottom', () => {
  const label = createLogisticUnitLabelData({
    lpn: '249',
    materialCode: 'IN00200500237',
    materialName: 'CAJA CE 500X300X120 MASTER GARCES',
    lotCode: 'OC00031805-LO12214',
    quantity: 128,
    unit: 'UN',
  }, { now: fixedDate })

  const zpl = generateLogisticUnitLabelZPL(label)

  assert.match(zpl, /\^PW1181/)
  assert.match(zpl, /\^LL1772/)
  assert.match(zpl, /\^FO24,35\^GB1133,1702,2\^FS/)
  assert.match(zpl, /\^FO\d+,12\d{2}\^A0N,16,16\^FDFORMATO:/)
  assert.doesNotMatch(zpl, /\^FO24,24\^GB1133,1724,1133\^FS/)
})

test('renders semielaborado fields and escapes dynamic text', () => {
  const label = createLogisticUnitLabelData({
    lpn: 'LPN-"249"',
    materialCode: 'IN<020>',
    materialName: 'BOLSA <CRISTAL>',
    location: 'BODEGA & CAMARA',
    lotCode: 'OC00035692-LO13436',
    quantity: 384,
    unit: 'UN',
  }, { now: fixedDate })

  const html = buildLogisticUnitLabelHtml(label, 'data:image/png;base64,qr')

  assert.match(html, /NovaFresh - Materiales/)
  assert.match(html, /BOLSA &lt;CRISTAL&gt;/)
  assert.match(html, /IN&lt;020&gt;/)
  assert.match(html, /LPN-&quot;249&quot;/)
  assert.match(html, /OC00035692-LO13436/)
  assert.match(html, /384 UN/)
  assert.doesNotMatch(html, /BOLSA <CRISTAL>/)
})
