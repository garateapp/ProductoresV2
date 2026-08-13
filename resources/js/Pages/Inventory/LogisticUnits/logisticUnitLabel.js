import qz from 'qz-tray'

const DPI = 203

const mmToDots = (mm) => Math.round(mm * DPI / 25.4)




export const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
  '&': '&amp;',
  '<': '&lt;',
  '>': '&gt;',
  '"': '&quot;',
  "'": '&#39;',
})[char])

const cleanText = (value, fallback = '-') => {
  const text = String(value ?? '').trim()

  return text || fallback
}

const parseMaterial = (material) => {
  if (!material) {
    return { code: '', name: '' }
  }

  if (typeof material === 'string') {
    const [code, ...nameParts] = material.split('·').map((part) => part.trim())

    return {
      code: nameParts.length ? code : '',
      name: nameParts.length ? nameParts.join(' · ') : material.trim(),
    }
  }

  return {
    code: material.codigo || material.code || '',
    name: material.nombre || material.name || '',
  }
}

const locationLabel = (location) => {
  if (!location) {
    return ''
  }

  if (typeof location === 'string') {
    return location
  }

  return [location.codigo, location.nombre].filter(Boolean).join(' ')
}

const unitLabel = (unit) => {
  if (!unit) {
    return ''
  }

  return typeof unit === 'string' ? unit : unit.codigo || unit.code || unit.nombre || unit.name || ''
}

const pad = (value) => String(value).padStart(2, '0')

const formatDate = (date) => `${pad(date.getDate())}-${pad(date.getMonth() + 1)}-${date.getFullYear()}`

const formatTime = (date) => `${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`

const formatQuantity = (quantity) => {
  if (quantity === null || quantity === undefined || quantity === '') {
    return ''
  }

  const numeric = Number(quantity)
  if (!Number.isFinite(numeric)) {
    return String(quantity)
  }

  return numeric.toLocaleString('es-CL', {
    maximumFractionDigits: 4,
  })
}

export const createLogisticUnitLabelData = (source = {}, options = {}) => {
  const now = options.now || new Date()
  const material = parseMaterial(source.material)
  const materialCode = cleanText(source.materialCode || source.material_code || material.code, '')
  const materialName = cleanText(source.materialName || source.material_name || material.name, '')
  const quantity = formatQuantity(source.quantity ?? source.available_quantity ?? source.availableQuantity)
  const unit = cleanText(source.unitCode || source.unit_code || unitLabel(source.unit), '')
  const lpn = cleanText(source.lpn || source.license_plate_number || source.licensePlateNumber, '')
  const lotCode = cleanText(source.lotCode || source.lot_code || source.supplierLot || source.supplier_lot, '')
  const location = cleanText(source.locationLabel || locationLabel(source.location), '')
  const productDescription = cleanText(source.productDescription || source.description || [materialCode, materialName].filter(Boolean).join(' · '))
  const dispatchGuide = cleanText(source.dispatchGuide || source.dispatch_guide || '', '')

  return {
    lpn,
    dispatchGuide,
    labelType: source.labelType || 'material',
    palletNumber: cleanText(source.palletNumber || source.pallet_number || lpn),
    materialCode: materialCode || '-',
    materialName: materialName || productDescription,
    productDescription,
    format: cleanText(source.format || source.presentation || source.spatialPosition || source.spatial_position, ''),
    lotCode: lotCode || '-',
    location: location || '-',
    quantity: quantity ? `${quantity}${unit ? ` ${unit}` : ''}` : '-',
    date: source.date || formatDate(now),
    time: source.time || formatTime(now),
    center: cleanText(source.center || source.centro || 'CENTRO DE ARMADO'),
    plant: cleanText(source.plant || source.planta || 'CODEGUA'),
    marcaLinea: cleanText(source.marcaLinea || source.marca_linea || 'CRYSTAL'),
    product2Desc: cleanText(source.product2Desc || ''),
    lpn2: cleanText(source.lpn2 || ''),
    lotCode2: cleanText(source.lotCode2 || ''),
    quantity2: source.quantity2 || '0',
    product3Desc: cleanText(source.product3Desc || ''),
    lpn3: cleanText(source.lpn3 || ''),
    lotCode3: cleanText(source.lotCode3 || ''),
    quantity3: source.quantity3 || '0',
    product4Desc: cleanText(source.product4Desc || ''),
    lpn4: cleanText(source.lpn4 || ''),
    lotCode4: cleanText(source.lotCode4 || ''),
    quantity4: source.quantity4 || '0',
    product5Desc: cleanText(source.product5Desc || ''),
    lpn5: cleanText(source.lpn5 || ''),
    lotCode5: cleanText(source.lotCode5 || ''),
    quantity5: source.quantity5 || '0',
  }
}

const encodeZplText = (text) => {
  return String(text ?? '')
    .replace(/\\/g, '\\\\')
    .replace(/\^/g, '\\^')
    .replace(/~/g, '\\~')
}

const generateQrZpl = (data, x, y, moduleWidth = 6) => {
  const qrCommand = `^FO${x},${y}^BQN,2,${moduleWidth}^FDQA,${encodeZplText(data)}^FS`
  return qrCommand
}

export const generateLogisticUnitLabelZPL = (label) => {
  const enc = encodeZplText
  const lpnDisplay = label.dispatchGuide ? `${label.lpn}-${label.dispatchGuide}` : (label.lpn || '-')

  const description = cleanText(label.productDescription, '-')
  const descFont = Math.min(90, Math.max(18, Math.floor((1199 - 40) / Math.max(1, description.length))))

  return [
    '^XA',
    '^CI28',
    '^FWN',
    '^LH0,0',
    '^PR4',
    '^MD20',
    '^PW799',
    '^LL1199',
    '^FO16,16^GB610,1167,6^FS',
    `^FO630,988^BQR,2,4^FDQA,${enc(lpnDisplay)}^FS`,
    `^FO660,30^A0R,26,26^FD${enc(label.center || 'CENTRO DE ARMADO')}^FS`,
    `^FO700,30^A0R,28,28^FDGárate Hermanos^FS`,
    `^FO26,820^A0R,38,38^FD${enc(label.date || '-')}^FS`,
    '^FO185,40^A0R,68,68^FDCant:^FS',
    `^FO185,200^A0R,90,90^FD${enc(label.quantity || '0')}^FS`,
    `^FO335,40^A0R,70,70^FB900,3,0,L,0^FD${enc(label.materialCode || '-')}^FS`,
    `^FO265,40^A0R,70,70^FB900,3,0,L,0^FD${enc(label.materialName || '-')}^FS`,
    `^FO115,40^A0R,64,64^FD${enc(lpnDisplay)}^FS`,
    `^FO26,40^A0R,48,48^FD${enc(label.lot || label.lotCode || '-')}^FS`,
    '^XZ',
  ].join('')
}

export const generateMaterialLabelZPL = (label) => {
  const enc = encodeZplText
  const lpnDisplay = label.dispatchGuide ? `${label.lpn}-${label.dispatchGuide}` : (label.lpn || '-')
    const description = cleanText(label.productDescription, '-')
  const descFont = Math.min(90, Math.max(18, Math.floor((1199 - 40) / Math.max(1, description.length))))

  return [
    '^XA',
    '^CI28',
    '^FWN',
    '^LH0,0',
    '^PR4',
    '^MD20',
    '^PW799',
    '^LL1199',
    '^FO16,16^GB610,1167,6^FS',
    `^FO630,988^BQR,2,4^FDQA,${enc(lpnDisplay)}^FS`,
    `^FO660,30^A0R,26,26^FD${enc(label.center || 'CENTRO DE ARMADO')}^FS`,
    `^FO700,30^A0R,28,28^FDGárate Hermanos^FS`,
    `^FO26,820^A0R,38,38^FD${enc(label.date || '-')}^FS`,
    '^FO185,40^A0R,68,68^FDCant:^FS',
    `^FO185,200^A0R,90,90^FD${enc(label.quantity || '0')}^FS`,
    `^FO335,40^A0R,70,70^FB900,3,0,C,0^FD${enc(label.materialCode || '-')}^FS`,
    `^FO265,40^A0R,70,70^FB900,3,0,C,0^FD${enc(label.materialName || '-')}^FS`,
    `^FO115,40^A0R,64,64^FD${enc(lpnDisplay)}^FS`,
    `^FO26,40^A0R,48,48^FD${enc(label.lot || label.lotCode || '-')}^FS`,
    '^XZ',
  ].join('')
}

const SHEET_WIDTH_MM = 100
const SHEET_HEIGHT_MM = 150


const buildLabelSheet = (label, qrDataUrl = '') => {
  const qrMarkup = qrDataUrl
    ? `<img class="qr" src="${escapeHtml(qrDataUrl)}" alt="QR ${escapeHtml(label.lpn)}">`
    : '<div class="qr qr-placeholder">QR</div>'

  const tableRows = [
    { desc: label.productDescription || 'CAJA C/E 500X300X120', lpn: label.lpn || '-', lote: label.lotCode || '-', qty: label.quantity || '0' },
    { desc: label.product2Desc || 'MALETA C/E 2KG', lpn: label.lpn2 || '-', lote: label.lotCode2 || '-', qty: label.quantity2 || '0' },
    { desc: label.product3Desc || 'BOLSA CRISTAL SP-4.5KG', lpn: label.lpn3 || '-', lote: label.lotCode3 || '-', qty: label.quantity3 || '0' },
    { desc: label.product4Desc || 'CEREZA', lpn: label.lpn4 || '-', lote: label.lotCode4 || '-', qty: label.quantity4 || '0' },
    { desc: label.product5Desc || 'ABSOR TRIL-MILLAR', lpn: label.lpn5 || '-', lote: label.lotCode5 || '-', qty: label.quantity5 || '0' },
  ]

  return `  <section class="frame">
    <div class="title-section">
      <div class="title-main">NovaFresh - Materiales</div>
      <div class="title-plant">PLANTA: ${escapeHtml(label.plant || 'CODEGUA')}</div>
    </div>
    <div class="header-info">
      <div class="header-col">
        <span>UBICACIÓN</span>
        <span>${escapeHtml(label.center || '-')}</span>
      </div>
      <div class="header-col">
        <span>FECHA</span>
        <span>${escapeHtml(label.date)}</span>
      </div>
      <div class="header-col">
        <span>HORA</span>
        <span>${escapeHtml(label.time)}</span>
      </div>

    </div>
    <div class="table-wrap">
      <div class="table-header">
        <div>#</div>
        <div>DESCRIPCION</div>
        <div>LPN ORIGEN</div>
        <div>LOTE</div>
        <div>CANTIDAD</div>
      </div>
      ${tableRows.map((row, idx) => `
      <div class="table-row">
        <div class="num">${idx + 1}</div>
        <div class="desc">${escapeHtml(row.desc)}</div>
        <div class="lpn">${escapeHtml(row.lpn)}</div>
        <div class="lot">${escapeHtml(row.lote)}</div>
        <div class="qty">${escapeHtml(row.qty)}</div>
      </div>
      `).join('')}
    </div>
    <div class="footer-section">
      <div class="footer-left">
        <span>FORMATO: ${escapeHtml(label.format || '-')}</span>
        <span>MARCA/LINEA: ${escapeHtml(label.marcaLinea || 'CRYSTAL')}</span>
      </div>
      <div class="footer-right">
        <span>Codigo QR LPN</span>
        ${qrMarkup}
        <span>${escapeHtml(label.palletNumber || '-')}</span>
      </div>
    </div>
  </section>`
}

const LABEL_CSS = `
    @page {
      size: ${SHEET_WIDTH_MM}mm ${SHEET_HEIGHT_MM}mm;
      margin: 0;
    }

    * { box-sizing: border-box; }

    html {
      width: 100mm;
      height: 150mm;
      margin: 0;
      padding: 0;
      overflow: hidden;
    }

    body {
      color: #000;
      display: flex;
      font-family: Arial, Helvetica, sans-serif;
      width: ${SHEET_WIDTH_MM}mm;
      height: ${SHEET_HEIGHT_MM}mm;
      margin: 0;
      padding: 3mm 2mm;
      overflow: hidden;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }

    .frame {
      width: 100%;
      height: 100%;
      border: 1.2pt solid #000;
      display: flex;
      flex-direction: column;
      padding: 2mm;
      overflow: hidden;
    }

    .title-section {
      font-size: 9pt;
      font-weight: 900;
      margin-bottom: 1mm;
    }

    .title-main { font-size: 11pt; }
    .title-plant { font-size: 7pt; font-weight: 700; }

    .header-info {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 22mm 22mm;
      gap: 1.5mm;
      margin-bottom: 1mm;
      font-size: 7pt;
      font-weight: 700;
      padding-bottom: 1mm;
      border-bottom: 1pt solid #000;
    }

    .header-col {
      display: flex;
      flex-direction: column;
    }

    .header-col span:first-child {
      font-size: 7pt;
      color: #666;
    }

    .header-col span:last-child {
      font-size: 8pt;
      font-weight: 900;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .table-wrap {
      border: 1.5pt solid #000;
      display: flex;
      flex: 1 1 auto;
      flex-direction: column;
      margin-bottom: 1mm;
      width: 100%;
    }

    .table-header,
    .table-row {
      display: grid;
      grid-template-columns: 7mm minmax(0, 1fr) 18mm 22mm 16mm;
      align-items: center;
    }

    .table-header {
      background: #ccc;
      font-size: 7pt;
      font-weight: 900;
      text-align: center;
      border-bottom: 1pt solid #000;
      min-height: 6mm;
    }

    .table-row {
      flex: 1 1 0;
      font-size: 7pt;
      border-bottom: 1pt solid #000;
      min-height: 6mm;
    }

    .table-row:last-child { border-bottom: none; }

    .table-header > div,
    .table-row > div {
      padding: 0.6mm;
      border-right: 1pt solid #000;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .table-header > div:last-child,
    .table-row > div:last-child {
      border-right: none;
    }

    .num {
      text-align: center;
      font-weight: 700;
    }

    .qty {
      text-align: right;
      font-weight: 700;
    }

    .footer-section {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      font-size: 7pt;
      font-weight: 700;
    }

    .footer-left {
      display: flex;
      flex-direction: column;
      gap: 0.5mm;
    }

    .footer-right {
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .qr {
      width: 30mm;
      height: 30mm;
      object-fit: contain;
    }

    .qr-placeholder {
      border: 1pt dashed #999;
      width: 14mm;
      height: 14mm;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 6pt;
    }

    @media print {
      body { margin: 0; }
    }
`

export const buildLogisticUnitLabelHtml = (label, qrDataUrl = '') => `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Etiqueta ${escapeHtml(label.lpn)}</title>
  <style>${LABEL_CSS}
  </style>
</head>
<body>
${buildLabelSheet(label, qrDataUrl)}
</body>
</html>`

export const buildLotLabelHtml = (labels = [], qrDataUrls = []) => `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Etiquetas de lote (${labels.length})</title>
  <style>${LABEL_CSS}
    html {
      height: auto !important;
      overflow: visible !important;
    }

    body {
      display: block !important;
      height: auto !important;
      overflow: visible !important;
      padding: 0 !important;
    }

    .label-sheet {
      display: flex;
      width: 100mm;
      height: 150mm;
      padding: 3mm 2mm;
      page-break-after: always;
    }

    .label-sheet:last-child {
      page-break-after: auto;
    }
  </style>
</head>
<body>
${labels.map((label, index) => `<div class="label-sheet">${buildLabelSheet(label, qrDataUrls[index] || '')}</div>`).join('\n')}
</body>
</html>`
