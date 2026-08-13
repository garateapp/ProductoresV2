import assert from 'node:assert/strict'
import test from 'node:test'

import {
  buildLogisticUnitLabelHtml,
  buildLotLabelHtml,
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

test('builds the 100 by 150 mm tarja ZPL with the full product description', () => {
  const label = createLogisticUnitLabelData({
    lpn: '249',
    materialCode: 'IN00200500237',
    materialName: 'CAJA CE 500X300X120 MASTER GARCES',
    lotCode: 'OC00031805-LO12214',
    quantity: 128,
    unit: 'UN',
  }, { now: fixedDate })

  const zpl = generateLogisticUnitLabelZPL(label)

  assert.match(zpl, /\^PW799/)
  assert.match(zpl, /\^LL1199/)
  assert.match(zpl, /\^FO16,16\^GB610,1167,6\^FS/)
  assert.match(zpl, /\^FO635,988\^BQR,2,7\^FDQA,249\^FS/)
  assert.match(zpl, /\^FO740,30\^A0R,28,28\^FDG[áa]rate Hermanos\^FS/)
  assert.match(zpl, /\^TBR,1100,110\^FO335,40\^A0R,\d+,\d+\^FDIN00200500237 · CAJA CE 500X300X120 MASTER GARCES\^FS/)
})

test('always prints the product description, shrinking the font to fit the 150 mm label', () => {
  const label = createLogisticUnitLabelData({
    lpn: '249',
    materialCode: 'IN00200500237',
    materialName: 'CAJA CE 500X300X120 MASTER GARCES XTL EXTRA LARGA PARA ENSAYO DE LABORATORIO',
    lotCode: 'L101',
    quantity: 128,
    unit: 'UN',
  }, { now: fixedDate })

  const zpl = generateLogisticUnitLabelZPL(label)

  assert.match(zpl, /\^FO335,40\^A0R,(\d+),\1\^FDIN00200500237 · CAJA CE 500X300X120 MASTER GARCES XTL EXTRA LARGA PARA ENSAYO DE LABORATORIO\^FS/)
  const fontMatch = zpl.match(/\^FO335,40\^A0R,(\d+),\1\^FD/)
  assert.ok(fontMatch)
  assert.ok(Number(fontMatch[1]) < 90)
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

test('builds a stacked lot document with one page per label', () => {
  const labels = [1, 2].map((n) => createLogisticUnitLabelData({
    lpn: `249-${n}`,
    materialCode: 'IN00200500237',
    materialName: 'CAJA CE 500X300X120 MASTER GARCES',
    lotCode: 'L101',
    quantity: 128,
    unit: 'UN',
  }, { now: fixedDate }))

  const html = buildLotLabelHtml(labels, ['qr1', 'qr2'])

  assert.match(html, /class="label-sheet"/)
  assert.equal(html.match(/class="label-sheet"/g).length, 2)
  assert.match(html, /page-break-after:\s*always;/)
  assert.match(html, /249-1/)
  assert.match(html, /249-2/)
  assert.match(html, /src="qr2"/)
})
