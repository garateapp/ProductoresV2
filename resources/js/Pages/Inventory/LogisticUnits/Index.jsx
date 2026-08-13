import React, { useEffect, useState } from 'react'
import { Link, router, useForm, usePage } from '@inertiajs/react'
import axios from 'axios'
import QRCode from 'qrcode'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import SearchableSelect from '@/Components/SearchableSelect'
import { ChevronDown, ChevronRight, Eye, MoveHorizontal, Package, Plus, Printer, QrCode, Split, Trash2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription,
} from '@/Components/ui/dialog'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table'
import {
  buildLogisticUnitLabelHtml,
  buildLotLabelHtml,
  createLogisticUnitLabelData,
  generateLogisticUnitLabelZPL,
  generateMaterialLabelZPL,
} from './logisticUnitLabel'
import { splitEvenly } from './logisticUnitSplit'
import qz from 'qz-tray'
import { toast } from 'sonner'
export default function InventoryLogisticUnits({ units, materials = [], locations = [], statuses = [], filters = {}, materialRequests = [], technicalSheets = [], wasteReasons = [], wasteTypes = [], nextLotCode = '' }) {
  const [expandedLpns, setExpandedLpns] = useState(new Set())
  const [transferModal, setTransferModal] = useState({ open: false, type: 'full', unit: null, position: null })
  const [labelModal, setLabelModal] = useState({ open: false, ...createLogisticUnitLabelData() })
  const [labelCopies, setLabelCopies] = useState(1)
  const [lotCopies, setLotCopies] = useState(2)
  const [printingLot, setPrintingLot] = useState(null)
  const [lotModal, setLotModal] = useState({ open: false, unit: null, labels: [], loading: false })
  const [productionModal, setProductionModal] = useState({ open: false })
  const [showRequestDetail, setShowRequestDetail] = useState(false)
  const [productionLpnCode, setProductionLpnCode] = useState('')
  const [printers, setPrinters] = useState([])
  const [selectedPrinter, setSelectedPrinter] = useState(() => window.localStorage?.getItem('inventory.zplPrinter') || '')

  const [editModal, setEditModal] = useState({ open: false, unit: null })
  const [deleteConfirm, setDeleteConfirm] = useState({ open: false, unit: null, reason: '' })

  const [splitModal, setSplitModal] = useState({ open: false, unit: null })

  const splitForm = useForm({
    pallet_count: '2',
    spatial_prefix: '',
    spatial_column: '',
    spatial_row: '',
  })

  const openSplitModal = (unit) => {
    splitForm.reset()
    splitForm.setData({
      pallet_count: '2',
      spatial_prefix: unit.spatial_prefix || '',
      spatial_column: unit.spatial_column || '',
      spatial_row: unit.spatial_row || '',
    })
    setSplitModal({ open: true, unit })
  }

  const submitSplit = (e) => {
    e.preventDefault()
    if (!splitModal.unit) return

    splitForm.post(route('inventory.logistic-units.split', splitModal.unit.id), {
      preserveScroll: true,
      onSuccess: () => {
        setSplitModal({ open: false, unit: null })
        splitForm.reset()
        toast.success('LPN dividido en pallets.')
      },
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al dividir el LPN.')
      },
    })
  }

  const splitPreview = splitModal.unit
    ? splitEvenly(splitModal.unit.available_quantity, splitForm.data.pallet_count)
    : []

  const splitTotal = splitPreview.reduce((sum, q) => sum + q, 0)
  const splitSumMatches = Math.abs(splitTotal - Number(splitModal.unit?.available_quantity || 0)) < 0.0001


  const editForm = useForm({
    license_plate_number: '',
    spatial_prefix: '',
    spatial_column: '',
    spatial_row: '',
    lot_code: '',
    supplier_lot: '',
    production_batch: '',
    dispatch_guide: '',
    available_quantity: '',
  })

  const productionForm = useForm({
    technical_sheet_id: '',
    location_id: '',
    quantity: '',
    inputs: [],
  })

  const tsOptions = technicalSheets.map(ts => ({ value: String(ts.id), label: `${ts.material?.nombre} (Versión ${ts.version})` }))
  const wasteReasonOptions = wasteReasons.map(r => ({value: String(r.id), label: r.nombre}))
  const wasteTypeOptions = wasteTypes.map(t => ({value: String(t.id), label: t.nombre}))
  const selectedTechnicalSheet = technicalSheets.find((item) => String(item.id) === String(productionForm.data.technical_sheet_id))

  const [availability, setAvailability] = useState({ loading: false, data: null, error: null })
  const hasShortage = availability.data?.some((item) => item.shortage > 0)
  const isAvailabilityInvalid = !availability.data || hasShortage || availability.error

  const checkAvailability = async () => {
    const { technical_sheet_id, quantity, location_id } = productionForm.data
    if (!technical_sheet_id || !quantity || !location_id) {
        setAvailability({ loading: false, data: null, error: null })
        return
    }

    setAvailability(prev => ({ ...prev, loading: true }))
    try {
      const response = await window.axios.post(route('inventory.transformation.check-availability'), {
        technical_sheet_id,
        quantity,
        location_id
      })
      setAvailability({ loading: false, data: response.data.availability, error: null })
    } catch (e) {
      setAvailability({ loading: false, data: null, error: e.response?.data?.message || 'Error validando disponibilidad' })
      if (e.response?.status === 422 && e.response.data.errors?.details) {
          setAvailability(prev => ({ ...prev, data: e.response.data.errors.details }))
      }
    }
  }

  useEffect(() => {
    const timer = setTimeout(() => {
      checkAvailability()
    }, 500)
    return () => clearTimeout(timer)
  }, [productionForm.data.technical_sheet_id, productionForm.data.quantity, productionForm.data.location_id])

  const openProduction = () => {
    productionForm.reset()
    setProductionLpnCode('')
    setProductionModal({ open: true })
  }

  const loadPrinters = async () => {
    try {
      await connectQz()
      const list = await qz.printers.find()
      setPrinters(list)
    } catch (err) {
      console.error('Error loading printers:', err)
    }
  }

  const handlePrinterChange = (printer) => {
    setSelectedPrinter(printer)
    if (printer) {
      window.localStorage?.setItem('inventory.zplPrinter', printer)
    } else {
      window.localStorage?.removeItem('inventory.zplPrinter')
    }
  }

  const addInput = async (lpnCode) => {
    const code = String(lpnCode || '').trim()
    if (!code) return

    if (!selectedTechnicalSheet) {
      toast.error('Seleccione una ficha técnica primero')
      return
    }

    try {
        const response = await window.axios.get(route('inventory.logistic-units.by-code', code))
        const unit = response.data

        // Verificar si el material del LPN pertenece a la ficha técnica (incluyendo reemplazos)
        const allowedMaterialIds = [
            ...(selectedTechnicalSheet.unit_items || []).flatMap(i => [i.material_id, i.replacement_material_id]),
            ...(selectedTechnicalSheet.pallet_items || []).flatMap(i => [i.material_id, i.replacement_material_id])
        ].filter(Boolean)

        if (!allowedMaterialIds.includes(unit.material_id)) {
            toast.error(`El material ${unit.material?.nombre} no pertenece a la ficha técnica (ni como principal ni como reemplazo)`)
            return
        }

        if (productionForm.data.inputs.some((item) => item.lpn_code === unit.license_plate_number)) {
          return
        }

        productionForm.setData('inputs', [...productionForm.data.inputs, {
            lpn_code: unit.license_plate_number,
            material_id: unit.material_id,
            material_label: unit.material ? `${unit.material.codigo} · ${unit.material.nombre}` : '-',
            location_label: unit.location ? `${unit.location.codigo} · ${unit.location.nombre}` : '-',
            available_quantity: unit.available_quantity,
            consumed: 0,
            wastes: [],
        }])
    } catch (e) {
        toast.error('LPN no encontrado o inválido')
    }
  }

  const updateProductionInput = (index, changes) => {
    const inputs = [...productionForm.data.inputs]
    inputs[index] = { ...inputs[index], ...changes }
    productionForm.setData('inputs', inputs)
  }

  const addWasteToInput = (index) => {
    const input = productionForm.data.inputs[index]
    updateProductionInput(index, {
      wastes: [...(input.wastes || []), { quantity: '', waste_reason_id: '', waste_type_id: '' }],
    })
  }

  const updateWaste = (inputIndex, wasteIndex, changes) => {
    const inputs = [...productionForm.data.inputs]
    const wastes = [...(inputs[inputIndex].wastes || [])]
    wastes[wasteIndex] = { ...wastes[wasteIndex], ...changes }
    inputs[inputIndex] = { ...inputs[inputIndex], wastes }
    productionForm.setData('inputs', inputs)
  }

  const removeWaste = (inputIndex, wasteIndex) => {
    const inputs = [...productionForm.data.inputs]
    inputs[inputIndex] = {
      ...inputs[inputIndex],
      wastes: (inputs[inputIndex].wastes || []).filter((_, index) => index !== wasteIndex),
    }
    productionForm.setData('inputs', inputs)
  }

  const inputTotal = (input) => Number(input.consumed || 0)
    + (input.wastes || []).reduce((total, waste) => total + Number(waste.quantity || 0), 0)

  const submitProduction = (e) => {
    e.preventDefault()

    const inputs = productionForm.data.inputs.map((input) => ({
      lpn_code: input.lpn_code,
      consumed: input.consumed || 0,
      wastes: (input.wastes || []).map((waste) => ({
        quantity: waste.quantity,
        waste_reason_id: waste.waste_reason_id,
        waste_type_id: waste.waste_type_id,
      })),
    }))

    productionForm.clearErrors()
    if (inputs.length === 0) {
      productionForm.setError('inputs', 'Debes agregar al menos un LPN de insumo.')
      return
    }

    router.post(route('inventory.transformation.store'), {
      technical_sheet_id: productionForm.data.technical_sheet_id,
      location_id: productionForm.data.location_id,
      quantity: productionForm.data.quantity,
      inputs,
    }, {
      preserveScroll: true,
      onSuccess: () => {
        setProductionModal({ open: false })
        productionForm.reset()
        setProductionLpnCode('')
      },
      onError: (errors) => {
        productionForm.setError(errors)
      }
    })
  }
  const openEditModal = (unit) => {
    editForm.setData({
      license_plate_number: unit.license_plate_number,
      spatial_prefix: unit.spatial_prefix || '',
      spatial_column: unit.spatial_column || '',
      spatial_row: unit.spatial_row || '',
      lot_code: unit.lot_code || '',
      supplier_lot: unit.supplier_lot || '',
      production_batch: unit.production_batch || '',
      dispatch_guide: unit.dispatch_guide || '',
      available_quantity: unit.available_quantity || '',
    })
    setEditModal({ open: true, unit })
  }

  const submitEdit = (e) => {
    e.preventDefault()
    editForm.put(route('inventory.logistic-units.update', editModal.unit.id), {
      preserveScroll: true,
      onSuccess: () => {
        setEditModal({ open: false, unit: null })
        editForm.reset()
        toast.success('Pallet actualizado.')
      },
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al actualizar.')
      },
    })
  }

  const confirmDelete = () => {
    if (!deleteConfirm.unit) return

    router.delete(route('inventory.logistic-units.destroy', deleteConfirm.unit.id), {
      data: { reason: deleteConfirm.reason || undefined },
      preserveScroll: true,
      onSuccess: () => {
        setDeleteConfirm({ open: false, unit: null, reason: '' })
        toast.success('Pallet eliminado.')
      },
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al eliminar.')
      },
    })
  }

  const [labelQrDataUrl, setLabelQrDataUrl] = useState('')
  const [lastSuggestedLpn, setLastSuggestedLpn] = useState('')

  const toggleExpand = (id) => {
    const next = new Set(expandedLpns)
    if (next.has(id)) next.delete(id)
    else next.add(id)
    setExpandedLpns(next)
  }

  const { props } = usePage()
  const filterForm = useForm({
    q: filters.q || '',
    material_id: filters.material_id || '',
    location_id: filters.location_id || '',
    status: filters.status || '',
  })
  const createForm = useForm({
    license_plate_number: '',
    material_id: '',
    current_location_id: '',
    spatial_prefix: '',
    spatial_column: '',
    spatial_row: '',
    base_quantity: '',
    available_quantity: '',
    lot_code: nextLotCode || '',
    supplier_lot: '',
    production_batch: '',
    received_at: '',
    dispatch_guide: '',
    pallet_count: '1',
  })

  const palletCount = Number(createForm.data.pallet_count || 1)
  const isBulkCreate = palletCount > 1

  useEffect(() => {
    createForm.setData('lot_code', nextLotCode || '')
  }, [nextLotCode])

  const transferForm = useForm({
    to_location_id: '',
    quantity: '',
    logistic_unit_id: null,
    position_id: null,
    material_request_id: '',
  })

  const requestOptions = materialRequests.map(r => ({ value: String(r.id), label: r.label }))
  const selectedRequest = materialRequests.find(r => String(r.id) === String(transferForm.data.material_request_id))

  const handleRequestChange = (option) => {
    const request = materialRequests.find(r => String(r.id) === String(option?.value || ''))
    transferForm.setData({
      ...transferForm.data,
      material_request_id: option?.value || '',
      to_location_id: request ? String(request.destination_location_id) : '',
    })
  }

  const materialOptions = materials.map((item) => ({
    value: String(item.id),
    label: `${item.codigo} · ${item.nombre}${item.service_name ? ` · ${item.service_name}` : ''}`,
  }))
  const locationOptions = locations.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` }))
  const statusOptions = statuses.map((item) => ({ value: item, label: item }))
  const selectedCreateMaterial = materials.find((item) => String(item.id) === String(createForm.data.material_id)) || null
  const selectedCreateLocation = locations.find((item) => String(item.id) === String(createForm.data.current_location_id)) || null

  useEffect(() => {
    let cancelled = false

    const qrLpn = labelModal.dispatchGuide ? `${labelModal.lpn}-${labelModal.dispatchGuide}` : labelModal.lpn

    if (!labelModal.open || !labelModal.lpn) {
      setLabelQrDataUrl('')
      return () => {
        cancelled = true
      }
    }

    setLabelQrDataUrl('')
    QRCode.toDataURL(qrLpn, {
      errorCorrectionLevel: 'M',
      margin: 1,
      width: 360,
    }).then((url) => {
      if (!cancelled) {
        setLabelQrDataUrl(url)
      }
    }).catch(() => {
      if (!cancelled) {
        setLabelQrDataUrl('')
      }
    })

    return () => {
      cancelled = true
    }
  }, [labelModal.open, labelModal.lpn, labelModal.dispatchGuide])

  useEffect(() => {
    if (labelModal.open) {
      loadPrinters()
    }
  }, [labelModal.open])

  const setCreateMaterial = (option) => {
    const material = materials.find((item) => String(item.id) === String(option?.value)) || null
    const suggestedLpn = material?.suggested_lpn || ''
    const shouldApplySuggestion = !createForm.data.license_plate_number || createForm.data.license_plate_number === lastSuggestedLpn

    createForm.setData({
      ...createForm.data,
      material_id: option?.value || '',
      license_plate_number: shouldApplySuggestion ? suggestedLpn : createForm.data.license_plate_number,
    })
    setLastSuggestedLpn(suggestedLpn)
  }

  const createLabelData = () => ({
    ...createLogisticUnitLabelData({
      lpn: createForm.data.license_plate_number,
      dispatch_guide: createForm.data.dispatch_guide,
      material: selectedCreateMaterial,
      location: selectedCreateLocation,
      spatialPosition: spatialPositionLabel(createForm.data.spatial_prefix, createForm.data.spatial_column, createForm.data.spatial_row),
      lotCode: createForm.data.lot_code,
      supplierLot: createForm.data.supplier_lot,
      quantity: createForm.data.available_quantity || createForm.data.base_quantity,
    }),
  })

  const spatialPositionLabel = (prefix, column, row) => {
    const parts = []
    if (prefix) parts.push(`Prefijo ${prefix}`)
    if (column) parts.push(`Columna ${column}`)
    if (row) parts.push(`Fila ${row}`)

    return parts.join(' · ')
  }

  const openLabelModal = (labelData) => {
    const materialId = labelData.material?.id
    const isSemiFinished = materialId && technicalSheets.some(ts => ts.material?.id === materialId)
    const label = createLogisticUnitLabelData({ ...labelData, labelType: isSemiFinished ? 'semielaborado' : 'material' })

    if (!label.lpn) {
      return
    }

    setLabelModal({ open: true, ...label })
  }

  const isQzConnected = () => {
    return Boolean(qz.websocket?.isActive?.())
  }

  const connectQz = async () => {
    if (isQzConnected()) {
      return
    }

    try {
      await qz.websocket.connect({
        retries: 2,
        delay: 1,
      })
    } catch (error) {
      console.error('Error conectando QZ Tray:', error)

      throw new Error(
        'No se pudo conectar con QZ Tray. Verifica que QZ Tray esté instalado y abierto.'
      )
    }
  }

  const normalizePrinterList = (printers) => {
    if (!printers) {
      return []
    }

    if (Array.isArray(printers)) {
      return printers
    }

    return [printers]
  }

  const resolveQzPrinter = async () => {
    if (selectedPrinter) {
      try {
        const list = normalizePrinterList(await qz.printers.find())
        if (list.includes(selectedPrinter)) {
          return selectedPrinter
        }
      } catch (error) {
        console.error('Error verificando impresora seleccionada:', error)
      }
    }

    const storedPrinter = window.localStorage?.getItem('inventory.zplPrinter')
    let printers = []

    try {
      printers = normalizePrinterList(await qz.printers.find())
    } catch (error) {
      console.error('Error listando impresoras QZ:', error)
    }

    if (storedPrinter && printers.includes(storedPrinter)) {
      return storedPrinter
    }

    if (selectedPrinter && printers.includes(selectedPrinter)) {
      return selectedPrinter
    }

    const zplPrinter = printers.find((printer) => {
      const name = String(printer || '').toLowerCase()

      return (
        name.includes('zebra') ||
        name.includes('zdesigner') ||
        name.includes('zpl') ||
        name.includes('zd') ||
        name.includes('zt') ||
        name.includes('gx') ||
        name.includes('gk') ||
        name.includes('ZDesigner')
      )
    })

    if (zplPrinter) {
      window.localStorage?.setItem('inventory.zplPrinter', zplPrinter)
      return zplPrinter
    }

    try {
      const defaultPrinter = await qz.printers.getDefault()

      if (defaultPrinter) {
        window.localStorage?.setItem('inventory.zplPrinter', defaultPrinter)
        return defaultPrinter
      }
    } catch (error) {
      console.error('Error obteniendo impresora por defecto QZ:', error)
    }

    throw new Error('No se encontró una impresora disponible para QZ Tray.')
  }

  const withTimeout = (promise, ms, message) => {
    promise.catch(() => {})
    return Promise.race([
      promise,
      new Promise((_, reject) => {
        setTimeout(() => reject(new Error(message)), ms)
      }),
    ])
  }

  const printWithQz = async (zpl, copies = 1) => {
    if (!zpl || typeof zpl !== 'string') {
      throw new Error('El ZPL está vacío o es inválido.')
    }

    await withTimeout(connectQz(), 15000, 'Se agotó el tiempo esperando conexión con QZ Tray.')

    const printer = await withTimeout(resolveQzPrinter(), 15000, 'Se agotó el tiempo buscando una impresora disponible.')
    const config = qz.configs.create(printer, {
      copies,
      encoding: 'UTF-8',
      rasterize: false,
    })

    const data = [
      {
        type: 'raw',
        format: 'plain',
        data: zpl,
      },
    ]

    await withTimeout(qz.print(config, data), 30000, 'Se agotó el tiempo enviando la etiqueta a la impresora.')

    return printer
  }

  const printCurrentLabel = async () => {
    if (!labelModal.lpn) {
      return
    }

    const zpl = generateLabelZpl(labelModal)
    const copies = labelCopies

    try {
      const printer = await printWithQz(zpl, copies)

      toast.success(`Etiqueta enviada a ${printer} (${copies} copia${copies > 1 ? 's' : ''})`)
    } catch (err) {
      console.error('Error printing with QZ:', err)

      toast.error(err.message || 'No se pudo imprimir con QZ Tray.')
      alert(`${err.message || 'No se pudo imprimir con QZ Tray.'}\n\nSe abrirá la vista previa.`)

      openPrintPreview()
    }
  }

  const openPrintPreview = () => {
    const printWindow = window.open('', '_blank', 'width=780,height=520')
    if (!printWindow) {
      return
    }

    printWindow.document.open()
    printWindow.document.write(buildLogisticUnitLabelHtml(labelModal, labelQrDataUrl))
    printWindow.document.close()
    printWindow.focus()
    printWindow.setTimeout(() => printWindow.print(), 250)
  }

  const buildLabelDataFromUnit = (unit) => {
    const materialId = unit.material?.id
    const isSemiFinished = materialId && technicalSheets.some(ts => ts.material?.id === materialId)

    return createLogisticUnitLabelData({
      lpn: unit.license_plate_number,
      dispatch_guide: unit.dispatch_guide,
      material: unit.material,
      location: unit.location,
      spatialPosition: spatialPositionLabel(unit.spatial_prefix, unit.spatial_column, unit.spatial_row),
      lotCode: unit.lot_code,
      supplierLot: unit.supplier_lot,
      quantity: unit.available_quantity,
      unit: unit.unit,
      labelType: isSemiFinished ? 'semielaborado' : 'material',
    })
  }

  const generateLabelZpl = (label) => {
    return label.labelType === 'semielaborado'
      ? generateLogisticUnitLabelZPL(label)
      : generateMaterialLabelZPL(label)
  }

  const openLotPrintPreview = (labels) => {
    const printWindow = window.open('', '_blank', 'width=780,height=520')
    if (!printWindow) {
      return
    }

    Promise.all(labels.map((label) => {
      const qrText = label.dispatchGuide ? `${label.lpn}-${label.dispatchGuide}` : label.lpn

      return QRCode.toDataURL(qrText, {
        errorCorrectionLevel: 'M',
        margin: 1,
        width: 360,
      }).catch(() => '')
    })).then((qrDataUrls) => {
      printWindow.document.open()
      printWindow.document.write(buildLotLabelHtml(labels, qrDataUrls))
      printWindow.document.close()
      printWindow.focus()
      printWindow.setTimeout(() => printWindow.print(), 250)
    })
  }

  const openLotModal = async (unit) => {
    if (!unit.lot_code || lotModal.open) {
      return
    }

    setLotModal({ open: true, unit, labels: [], loading: true })
    loadPrinters()

    try {
      const { data } = await axios.get(route('inventory.logistic-units.print-lot', unit.lot_code))
      const labels = (data.units || [])
        .map(buildLabelDataFromUnit)
        .filter((label) => label.lpn)

      if (!labels.length) {
        setLotModal({ open: false, unit: null, labels: [], loading: false })
        toast.error(`El lote ${unit.lot_code} no tiene LPN activos para imprimir.`)
        return
      }

      setLotModal({ open: true, unit, labels, loading: false })
    } catch (err) {
      console.error('Error cargando LPN del lote:', err)
      setLotModal({ open: false, unit: null, labels: [], loading: false })
      toast.error(err.message || 'No se pudieron cargar los LPN del lote.')
    }
  }

  const getLotCopies = () => Math.max(1, parseInt(lotCopies, 10) || 1)

  const printLotLabels = async () => {
    const { labels, unit } = lotModal

    if (!labels.length || !unit?.lot_code) {
      return
    }

    setPrintingLot(unit.lot_code)

    try {
      const copies = getLotCopies()
      const zplAll = labels
        .flatMap((label) => Array(copies).fill(generateLabelZpl(label)))
        .join('\n')

      const printer = await printWithQz(zplAll, 1)

      toast.success(`Lote ${unit.lot_code}: ${labels.length} LPN × ${copies} copia${copies > 1 ? 's' : ''} enviadas a ${printer}`)
    } catch (err) {
      console.error('Error imprimiendo lote:', err)

      toast.error(err.message || 'No se pudo imprimir el lote con QZ Tray.')

      previewLotLabels()
    } finally {
      setPrintingLot(null)
    }
  }

  const previewLotLabels = () => {
    const { labels } = lotModal

    if (!labels.length) {
      return
    }

    const copies = getLotCopies()
    openLotPrintPreview(labels.flatMap((label) => Array(copies).fill(label)))
  }

  const applyFilters = (event) => {
    event.preventDefault()
    router.get(route('inventory.logistic-units.index'), filterForm.data, { preserveState: true, preserveScroll: true })
  }

  const submit = (event) => {
    event.preventDefault()
    const labelData = createLabelData()
    createForm.post(route('inventory.logistic-units.store'), {
      preserveScroll: true,
      onSuccess: () => {
        createForm.reset()
        setLastSuggestedLpn('')
        if (!isBulkCreate) {
          openLabelModal(labelData)
        }
      },
    })
  }

  const openFullTransfer = (unit) => {
    transferForm.reset()
    transferForm.setData({
      logistic_unit_id: unit.id,
      to_location_id: '',
      quantity: unit.available_quantity,
    })
    setTransferModal({ open: true, type: 'full', unit })
  }

  const openPartialTransfer = (unit, pos) => {
    transferForm.reset()
    transferForm.setData({
      logistic_unit_id: unit.id,
      position_id: pos.id,
      quantity: pos.quantity,
      to_location_id: '',
    })
    setTransferModal({ open: true, type: 'partial', unit, position: pos })
  }

  const submitTransfer = (e) => {
    e.preventDefault()
    const isPartial = transferModal.type === 'partial'
    const url = isPartial
      ? route('inventory.logistic-units.transfer-position', transferModal.position.id)
      : route('inventory.logistic-units.relocate', transferModal.unit.id)

    transferForm.post(url, {
      onSuccess: () => {
        setTransferModal({ ...transferModal, open: false })
        toast.success(isPartial ? 'Traslado parcial registrado correctamente.' : 'Pallet trasladado correctamente.')
      },
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al realizar el traslado.')
      },
    })
  }

  return (
    <div className=" mx-auto py-10 space-y-4">
      <Card>
        <CardHeader>
          <div className="flex justify-between items-center">
            <CardTitle>Pallets / LPN</CardTitle>
            <Button variant="secondary" onClick={openProduction}>Producir Semielaborado</Button>
          </div>
        </CardHeader>
      <Dialog open={productionModal.open} onOpenChange={(val) => setProductionModal({ ...productionModal, open: val })}>
  <DialogContent className="max-h-[92vh] overflow-hidden p-0 sm:max-w-7xl">
    <DialogHeader className="border-b px-6 py-5">
      <DialogTitle className="text-xl">Registrar producción semielaborado</DialogTitle>
      <DialogDescription>
        Selecciona la ficha técnica, escanea los insumos y registra consumos o mermas.
      </DialogDescription>
    </DialogHeader>

    <form onSubmit={submitProduction} className="flex max-h-[calc(92vh-88px)] flex-col">
      <div className="flex-1 space-y-6 overflow-y-auto px-6 py-5">
        <section className="rounded-xl border bg-slate-50/70 p-4">
          <div className="mb-3">
            <h3 className="text-sm font-semibold text-slate-900">Datos de producción</h3>
            <p className="text-xs text-slate-500">Define qué semielaborado se generará y dónde quedará ubicado.</p>
          </div>

          <div className="grid gap-4 lg:grid-cols-[1.5fr_0.7fr_1fr]">
            <div className="space-y-2">
              <Label>Ficha técnica</Label>
              <SearchableSelect
                options={tsOptions}
                value={tsOptions.find((item) => item.value === String(productionForm.data.technical_sheet_id)) || null}
                onChange={(opt) => productionForm.setData('technical_sheet_id', opt?.value || '')}
                placeholder="Selecciona ficha técnica"
                menuPortalTarget={null}
              />
              {selectedTechnicalSheet?.material && (
                <p className="text-xs text-slate-500">
                  Semielaborado: {selectedTechnicalSheet.material.nombre}
                </p>
              )}
            </div>

            <div className="space-y-2">
              <Label>Cantidad Producida</Label>
              <Input
                type="number"
                placeholder="Cantidad"
                value={productionForm.data.quantity}
                onChange={(e) => productionForm.setData('quantity', e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label>Ubicación destino</Label>
              <SearchableSelect
                options={locationOptions}
                value={locationOptions.find((item) => item.value === String(productionForm.data.location_id)) || null}
                onChange={(option) => productionForm.setData('location_id', option?.value || '')}
                placeholder="Ubicación del nuevo pallet"
                menuPortalTarget={null}
              />
            </div>
            </div>

            {availability.data && (
            <div className="mt-4 rounded-lg border bg-slate-100/50 p-3 text-xs">
              <h4 className="mb-2 font-bold flex items-center gap-2">
                <Package className="h-3 w-3" /> Disponibilidad en ubicación
              </h4>
              <div className="space-y-1">
                {availability.data.map((item, idx) => (
                  <div key={idx} className="flex justify-between items-center py-1 border-b last:border-0 border-slate-200">
                    <span className="font-medium text-slate-700">{item.codigo} · {item.nombre}</span>
                    <div className="flex gap-4">
                       <span>Req: {Number(item.required).toLocaleString('es-CL')}</span>
                       <span>Disp: {Number(item.available).toLocaleString('es-CL')}</span>
                       {item.shortage > 0 ? (
                         <span className="text-red-600 font-bold">Faltan {Number(item.shortage).toLocaleString('es-CL')}</span>
                       ) : (
                         <span className="text-green-600 font-semibold italic">Disponible</span>
                       )}
                    </div>
                  </div>
                ))}
              </div>
              {availability.error && <p className="text-red-600 mt-2 font-bold">{availability.error}</p>}
            </div>
            )}

        </section>

        {(productionForm.errors.technical_sheet_id ||
          productionForm.errors.location_id ||
          productionForm.errors.quantity ||
          productionForm.errors.inputs) && (
          <div className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {productionForm.errors.technical_sheet_id ||
              productionForm.errors.location_id ||
              productionForm.errors.quantity ||
              productionForm.errors.inputs}
          </div>
        )}

        <section className="rounded-xl border p-4">
          <div className="mb-3 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
              <h3 className="text-sm font-semibold text-slate-900">Insumos</h3>
              <p className="text-xs text-slate-500">Escanea un LPN y presiona Enter para agregarlo.</p>
            </div>

            <div className="flex w-full gap-2 md:max-w-xl">
              <Input
                value={productionLpnCode}
                onChange={(e) => setProductionLpnCode(e.target.value)}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    if (!isAvailabilityInvalid) {
                        addInput(productionLpnCode)
                        setProductionLpnCode('')
                    }
                  }
                }}
                disabled={isAvailabilityInvalid}
                placeholder={isAvailabilityInvalid ? "Corrija stock faltante para escanear..." : "Escanear LPN..."}
              />
              <Button
                type="button"
                variant="outline"
                disabled={isAvailabilityInvalid}
                onClick={() => {
                  addInput(productionLpnCode)
                  setProductionLpnCode('')
                }}
              >
                <Plus className="mr-1 h-4 w-4" />
                Agregar
              </Button>
            </div>
          </div>

          <div className="space-y-3">
            {productionForm.data.inputs.length === 0 && (
              <div className="rounded-lg border border-dashed bg-slate-50 px-4 py-10 text-center text-sm text-slate-500">
                Escanea un LPN para agregar insumos.
              </div>
            )}

            {productionForm.data.inputs.map((input, index) => {
              const exceedsStock = inputTotal(input) > Number(input.available_quantity || 0)

              return (
                <div key={index} className="rounded-xl border bg-white p-4 shadow-sm">
                  <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                      <div className="font-mono text-sm font-semibold text-slate-900">{input.lpn_code}</div>
                      <div className="mt-1 text-sm font-medium text-slate-700">{input.material_label}</div>
                      <div className="text-xs text-slate-500">{input.location_label}</div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                      <div className="rounded-lg bg-slate-50 px-3 py-2 text-right">
                        <div className="text-xs text-slate-500">Disponible</div>
                        <div className="font-semibold text-slate-900">
                          {Number(input.available_quantity || 0).toLocaleString('es-CL')}
                        </div>
                        {exceedsStock && (
                          <div className="text-xs font-medium text-red-600">Supera stock</div>
                        )}
                      </div>

                      <Button
                        type="button"
                        variant="destructive"
                        size="sm"
                        onClick={() =>
                          productionForm.setData(
                            'inputs',
                            productionForm.data.inputs.filter((_, i) => i !== index)
                          )
                        }
                      >
                        <Trash2 className="mr-1 h-4 w-4" />
                        Quitar
                      </Button>
                    </div>
                  </div>

                  <div className="grid gap-4 lg:grid-cols-[180px_1fr]">
                    <div className="space-y-2">
                      <Label>Consumo</Label>
                      <Input
                        type="number"
                        min="1"
                        step="1"
                        value={input.consumed}
                        onChange={(e) => updateProductionInput(index, { consumed: e.target.value })}
                      />
                    </div>

                    <div className="space-y-2">
                      <div className="flex items-center justify-between gap-3">
                        <Label>Mermas</Label>
                        <Button
                          type="button"
                          variant="outline"
                          size="sm"
                          onClick={() => addWasteToInput(index)}
                        >
                          <Plus className="mr-1 h-4 w-4" />
                          Agregar merma
                        </Button>
                      </div>

                      {(input.wastes || []).length === 0 && (
                        <div className="rounded-lg bg-slate-50 px-3 py-3 text-sm text-slate-500">
                          Sin mermas registradas para este insumo.
                        </div>
                      )}

                      <div className="space-y-2">
                        {(input.wastes || []).map((waste, wasteIndex) => (
                          <div
                            key={wasteIndex}
                            className="grid gap-2 rounded-lg border bg-slate-50/60 p-3 md:grid-cols-[120px_1fr_1fr_auto]"
                          >
                            <Input
                              type="number"
                              min="0"
                              step="0.0001"
                              value={waste.quantity}
                              onChange={(e) => updateWaste(index, wasteIndex, { quantity: e.target.value })}
                              placeholder="Cantidad"
                            />

                            <SearchableSelect
                              options={wasteReasonOptions}
                              value={wasteReasonOptions.find((item) => item.value === String(waste.waste_reason_id)) || null}
                              onChange={(opt) => updateWaste(index, wasteIndex, { waste_reason_id: opt?.value || '' })}
                              placeholder="Motivo"
                              menuPortalTarget={null}
                            />

                            <SearchableSelect
                              options={wasteTypeOptions}
                              value={wasteTypeOptions.find((item) => item.value === String(waste.waste_type_id)) || null}
                              onChange={(opt) => updateWaste(index, wasteIndex, { waste_type_id: opt?.value || '' })}
                              placeholder="Tipo"
                              menuPortalTarget={null}
                            />

                            <Button
                              type="button"
                              variant="ghost"
                              size="icon"
                              onClick={() => removeWaste(index, wasteIndex)}
                            >
                              <Trash2 className="h-4 w-4 text-red-600" />
                            </Button>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </section>
      </div>

      <DialogFooter className="border-t bg-white px-6 py-4">
        <Button
          type="button"
          variant="ghost"
          onClick={() => setProductionModal({ ...productionModal, open: false })}
        >
          Cancelar
        </Button>
        <Button type="submit" disabled={productionForm.processing || isAvailabilityInvalid}>
          {productionForm.processing ? 'Registrando...' : 'Registrar producción'}
        </Button>
      </DialogFooter>
    </form>
  </DialogContent>
</Dialog>
        <CardContent className="space-y-4">
          {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
          {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}

          <form onSubmit={submit} className="grid gap-3 rounded border bg-gray-50 p-4 md:grid-cols-4">
            <div>
              <Label>N° Pallets</Label>
              <Input
                type="number"
                min="1"
                step="1"
                value={createForm.data.pallet_count}
                onChange={(e) => createForm.setData('pallet_count', e.target.value)}
              />
              {createForm.errors.pallet_count && <div className="mt-1 text-sm text-red-600">{createForm.errors.pallet_count}</div>}
            </div>
            <div>
              <Label>LPN</Label>
              <div className="flex gap-2">
                <Input
                  value={createForm.data.license_plate_number}
                  onChange={(e) => createForm.setData('license_plate_number', e.target.value)}
                  disabled={isBulkCreate}
                  placeholder={isBulkCreate ? 'Se asigna automáticamente' : ''}
                />
                {!isBulkCreate && (
                  <Button type="button" variant="outline" onClick={() => openLabelModal(createLabelData())} disabled={!createForm.data.license_plate_number}>
                    <QrCode className="h-4 w-4" />
                  </Button>
                )}
              </div>
              {isBulkCreate && (
                <p className="mt-1 text-xs text-slate-500">El sistema asignará el correlativo automático a cada pallet.</p>
              )}
              {!isBulkCreate && selectedCreateMaterial?.suggested_lpn && createForm.data.license_plate_number !== selectedCreateMaterial.suggested_lpn ? (
                <button
                  type="button"
                  className="mt-1 text-xs font-medium text-indigo-700 hover:underline"
                  onClick={() => {
                    createForm.setData('license_plate_number', selectedCreateMaterial.suggested_lpn)
                    setLastSuggestedLpn(selectedCreateMaterial.suggested_lpn)
                  }}
                >
                  Usar sugerido {selectedCreateMaterial.suggested_lpn}
                </button>
              ) : null}
              {createForm.errors.license_plate_number && <div className="mt-1 text-sm text-red-600">{createForm.errors.license_plate_number}</div>}
            </div>
            <div>
              <Label>Material</Label>
              <SearchableSelect
                options={materialOptions}
                value={materialOptions.find((item) => item.value === String(createForm.data.material_id)) || null}
                onChange={setCreateMaterial}
                placeholder="Selecciona material"
              />
              {createForm.errors.material_id && <div className="mt-1 text-sm text-red-600">{createForm.errors.material_id}</div>}
            </div>
            <div>
              <Label>Ubicación</Label>
              <SearchableSelect
                options={locationOptions}
                value={locationOptions.find((item) => item.value === String(createForm.data.current_location_id)) || null}
                onChange={(option) => createForm.setData('current_location_id', option?.value || '')}
                placeholder="Selecciona ubicación"
              />
            </div>
            <div>
              <Label>Prefijo</Label>
              <Input value={createForm.data.spatial_prefix} onChange={(e) => createForm.setData('spatial_prefix', e.target.value)} placeholder="Opcional" />
              {createForm.errors.spatial_prefix && <div className="mt-1 text-sm text-red-600">{createForm.errors.spatial_prefix}</div>}
            </div>
            <div>
              <Label>Columna</Label>
              <Input value={createForm.data.spatial_column} onChange={(e) => createForm.setData('spatial_column', e.target.value)} placeholder="Ej: A" />
              {createForm.errors.spatial_column && <div className="mt-1 text-sm text-red-600">{createForm.errors.spatial_column}</div>}
            </div>
            <div>
              <Label>Fila</Label>
              <Input value={createForm.data.spatial_row} onChange={(e) => createForm.setData('spatial_row', e.target.value)} placeholder="Ej: 03" />
              {createForm.errors.spatial_row && <div className="mt-1 text-sm text-red-600">{createForm.errors.spatial_row}</div>}
            </div>
            <div>
              <Label>{isBulkCreate ? 'Cantidad base por pallet' : 'Cantidad base'}</Label>
              <Input type="number" step="0.0001" value={createForm.data.base_quantity} onChange={(e) => createForm.setData('base_quantity', e.target.value)} />
            </div>
            <div>
              <Label>{isBulkCreate ? 'Cantidad disponible por pallet' : 'Cantidad disponible'}</Label>
              <Input type="number" step="0.0001" value={createForm.data.available_quantity} onChange={(e) => createForm.setData('available_quantity', e.target.value)} />
            </div>
            <div>
              <Label>Lote</Label>
              <Input value={createForm.data.lot_code} onChange={(e) => createForm.setData('lot_code', e.target.value)} placeholder={nextLotCode || ''} />
              <p className="mt-1 text-xs text-slate-500">Correlativo automático L[numero]; se asigna el mismo lote a todos los pallets del registro.</p>
            </div>
            <div>
              <Label>Lote proveedor</Label>
              <Input value={createForm.data.supplier_lot} onChange={(e) => createForm.setData('supplier_lot', e.target.value)} />
            </div>
            <div>
              <Label>Guía de despacho</Label>
              <Input value={createForm.data.dispatch_guide} onChange={(e) => createForm.setData('dispatch_guide', e.target.value)} placeholder="N° guía de despacho" />
            </div>
            <div className="flex items-end">
              <Button type="submit" disabled={createForm.processing}>{createForm.processing ? 'Guardando...' : (isBulkCreate ? `Registrar ${palletCount} pallets` : 'Registrar pallet')}</Button>
            </div>
          </form>

          <form onSubmit={applyFilters} className="grid gap-3 rounded border p-4 md:grid-cols-4">
            <Input value={filterForm.data.q} onChange={(e) => filterForm.setData('q', e.target.value)} placeholder="LPN o lote" />
            <SearchableSelect
              options={materialOptions}
              value={materialOptions.find((item) => item.value === String(filterForm.data.material_id)) || null}
              onChange={(option) => filterForm.setData('material_id', option?.value || '')}
              placeholder="Todos los materiales"
            />
            <SearchableSelect
              options={locationOptions}
              value={locationOptions.find((item) => item.value === String(filterForm.data.location_id)) || null}
              onChange={(option) => filterForm.setData('location_id', option?.value || '')}
              placeholder="Todas las ubicaciones"
            />
            <div className="flex gap-2">
              <SearchableSelect
                options={statusOptions}
                value={statusOptions.find((item) => item.value === String(filterForm.data.status)) || null}
                onChange={(option) => filterForm.setData('status', option?.value || '')}
                placeholder="Todos los estados"
              />
              <Button type="submit">Filtrar</Button>
            </div>
          </form>

          <div className="flex items-center justify-end gap-2">
            <Label className="shrink-0 text-sm text-muted-foreground">Copias por LPN (lote)</Label>
            <Input
              type="number"
              min={1}
              max={99}
              value={lotCopies}
              onChange={(e) => setLotCopies(Math.max(1, parseInt(e.target.value, 10) || 1))}
              className="w-20 text-center"
            />
          </div>

          <Table>
            <TableHeader>
              <TableRow>
                <TableHead className="w-[40px]"></TableHead>
                <TableHead>LPN</TableHead>
                <TableHead>Material</TableHead>
                <TableHead className="text-right">Disponible Total</TableHead>
                <TableHead>Ubicación geoespacial</TableHead>
                <TableHead>Estado</TableHead>
                <TableHead className="text-right">Acciones</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {(units?.data || []).map((unit) => (
                <React.Fragment key={unit.id}>
                  <TableRow className="hover:bg-muted/50 cursor-pointer" onClick={() => toggleExpand(unit.id)}>
                    <TableCell>
                      {expandedLpns.has(unit.id) ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                    </TableCell>
                    <TableCell className="font-mono font-medium">{unit.dispatch_guide ? `${unit.license_plate_number}-${unit.dispatch_guide}` : unit.license_plate_number}</TableCell>
                    <TableCell>{unit.material ? `${unit.material.codigo} · ${unit.material.nombre}` : '-'}</TableCell>
                    <TableCell className="text-right font-bold">{Number(unit.available_quantity).toLocaleString('es-CL')}</TableCell>
                    <TableCell>{spatialPositionLabel(unit.spatial_prefix, unit.spatial_column, unit.spatial_row) || '-'}</TableCell>
                    <TableCell>{unit.status}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button variant="outline" size="sm" disabled={!unit.lot_code || Boolean(printingLot)} onClick={(e) => { e.stopPropagation(); openLotModal(unit); }}>
                          <Printer className="h-4 w-4 mr-1" /> {printingLot && printingLot === unit.lot_code ? 'Imprimiendo...' : (unit.lot_code || 'Sin lote')}
                        </Button>
                        <Button variant="outline" size="sm" onClick={(e) => { e.stopPropagation(); openLabelModal({ lpn: unit.license_plate_number, dispatch_guide: unit.dispatch_guide, material: unit.material, location: unit.location, spatialPosition: spatialPositionLabel(unit.spatial_prefix, unit.spatial_column, unit.spatial_row), lotCode: unit.lot_code, supplierLot: unit.supplier_lot, quantity: unit.available_quantity, unit: unit.unit }); }}>
                          <Printer className="h-4 w-4 mr-1" /> Etiqueta
                        </Button>
                        <Button variant="outline" size="sm" disabled={unit.status !== 'active'} onClick={(e) => { e.stopPropagation(); openFullTransfer(unit); }}>
                          <MoveHorizontal className="h-4 w-4 mr-1" /> Traslado LPN
                        </Button>
                        <Button variant="outline" size="sm" disabled={unit.status !== 'active'} onClick={(e) => { e.stopPropagation(); openSplitModal(unit); }}>
                          <Split className="h-4 w-4 mr-1" /> Dividir
                        </Button>
                        <Button variant="outline" size="sm" onClick={(e) => { e.stopPropagation(); openEditModal(unit); }}>
                          Editar
                        </Button>
                        <Button variant="outline" size="sm" className="text-red-600 border-red-200 hover:bg-red-50" onClick={(e) => { e.stopPropagation(); setDeleteConfirm({ open: true, unit, reason: '' }); }}>
                          Eliminar
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>

                  {expandedLpns.has(unit.id) && (
                    <TableRow className="bg-muted/30">
                      <TableCell colSpan={7} className="p-4">
                        <div className="pl-8 space-y-2">
                          <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground flex items-center gap-2">
                            <Package className="h-3 w-3" /> Posiciones de Stock (Desglose Real)
                          </h4>
                          <Table>
                            <TableHeader>
                              <TableRow className="hover:bg-transparent">
                                <TableHead className="h-8">Ubicación</TableHead>
                                <TableHead className="h-8">Lote / Ref</TableHead>
                                <TableHead className="text-right h-8">Cantidad</TableHead>
                                <TableHead className="text-right h-8">Acción</TableHead>
                              </TableRow>
                            </TableHeader>
                            <TableBody>
                              {(unit.positions || []).map((pos) => (
                                <TableRow key={pos.id} className="hover:bg-transparent">
                                  <TableCell>{pos.location?.codigo || 'Sin ubicación'}</TableCell>
                                  <TableCell className="font-mono text-xs">{pos.lot_code || '-'}</TableCell>
                                  <TableCell className="text-right font-medium">{Number(pos.quantity).toLocaleString('es-CL')}</TableCell>
                                  <TableCell className="text-right">
                                    <Button variant="ghost" size="sm" disabled={unit.status !== 'active'} className="h-7 text-indigo-600" onClick={() => openPartialTransfer(unit, pos)}>
                                      Traslado Parcial
                                    </Button>
                                  </TableCell>
                                </TableRow>
                              ))}
                              {(!unit.positions || unit.positions.length === 0) && (
                                <TableRow><TableCell colSpan={4} className="text-center text-xs text-muted-foreground">No hay posiciones activas.</TableCell></TableRow>
                              )}
                            </TableBody>
                          </Table>
                        </div>
                      </TableCell>
                    </TableRow>
                  )}
                </React.Fragment>
              ))}
            </TableBody>
          </Table>

          {units?.links?.length ? (
            <div className="flex justify-between text-sm text-gray-600">
              <div>Mostrando {units.from ?? 0} a {units.to ?? 0} de {units.total ?? 0}</div>
              <div className="flex gap-1">
                {units.links.map((link, index) => (
                  <Link
                    key={`${link.label}-${index}`}
                    href={link.url || '#'}
                    preserveScroll
                    preserveState
                    className={`rounded border px-3 py-1 ${link.active ? 'bg-indigo-50 text-indigo-700' : 'bg-white'} ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                  />
                ))}
              </div>
            </div>
          ) : null}
        </CardContent>
      </Card>

      <Dialog open={transferModal.open} onOpenChange={(val) => setTransferModal({ ...transferModal, open: val })}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{transferModal.type === 'full' ? 'Trasladar LPN Completo' : 'Traslado Parcial de Posición'}</DialogTitle>
          </DialogHeader>
          <form onSubmit={submitTransfer} className="space-y-4">
            <div className="space-y-2">
              <Label>Solicitud de Materiales (Aprobada)</Label>
              <div className="flex gap-2">
                <div className="flex-1">
                  <SearchableSelect
                    options={requestOptions}
                    value={requestOptions.find(o => o.value === String(transferForm.data.material_request_id))}
                    onChange={handleRequestChange}
                    placeholder="Busca folio de solicitud..."
                  />
                </div>
                {transferForm.data.material_request_id && (
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => setShowRequestDetail(true)}
                    title="Ver detalle"
                  >
                    <Eye className="w-4 h-4" />
                  </Button>
                )}
              </div>
              {transferForm.errors.material_request_id && <p className="text-sm text-red-500">{transferForm.errors.material_request_id}</p>}
            </div>

            <div>
              <Label>Ubicación destino</Label>
              <SearchableSelect
                options={locationOptions}
                value={locationOptions.find(o => o.value === String(transferForm.data.to_location_id))}
                onChange={(opt) => transferForm.setData('to_location_id', opt?.value || '')}
                isDisabled={Boolean(transferForm.data.material_request_id)}
              />
              {transferForm.errors.to_location_id && <p className="text-sm text-red-500">{transferForm.errors.to_location_id}</p>}
            </div>
            {transferModal.type === 'partial' && (
              <div>
                <Label>Cantidad a trasladar</Label>
                <Input
                  type="number"
                  step="0.0001"
                  max={transferModal.position?.quantity}
                  value={transferForm.data.quantity}
                  onChange={(e) => transferForm.setData('quantity', e.target.value)}
                />
                <p className="text-xs text-muted-foreground mt-1">Máximo disponible en esta posición: {transferModal.position?.quantity}</p>
                {transferForm.errors.quantity && <p className="text-sm text-red-500">{transferForm.errors.quantity}</p>}
              </div>
            )}
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => setTransferModal({ ...transferModal, open: false })}>Cancelar</Button>
              <Button type="submit" disabled={transferForm.processing}>
                {transferForm.processing ? 'Procesando...' : 'Confirmar traslado'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={labelModal.open} onOpenChange={(val) => setLabelModal({ ...labelModal, open: val })}>
        <DialogContent className="sm:max-w-[700px] max-h-[90vh] overflow-auto">
          <DialogHeader>
            <DialogTitle>{labelModal.labelType === 'semielaborado' ? 'Etiqueta semielaborado 150x100 mm (Horizontal)' : 'Etiqueta material 150x100 mm (Horizontal)'}</DialogTitle>
          </DialogHeader>
          <div className="flex justify-center p-4 bg-gray-100">
            <div className="relative">
              <div className="absolute -top-5 left-1/2 -translate-x-1/2 text-xs text-gray-500">150 mm</div>
              <div className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-6 text-xs text-gray-500">100 mm</div>
              {labelModal.labelType === 'semielaborado' ? (
                <iframe
                  srcDoc={buildLogisticUnitLabelHtml(labelModal, labelQrDataUrl)}
                  title="Etiqueta"
                  className="border-2 border-black bg-white"
                  style={{ width: '400px', height: '270px' }}
                />
              ) : (
                <div className="bg-white p-4 border-2 border-black text-xs font-mono" style={{ width: '400px', height: '270px' }}>
                  <div className="flex flex-col h-full">
                    <div className="text-[10px] font-bold mb-1">NOVAFRESH · MATERIALES</div>
                    <div className="grid grid-cols-3 gap-2 border-b pb-1 mb-2 text-[9px]">
                      <div><span className="text-gray-400">FECHA</span><br/>{labelModal.date || '-'}</div>
                      <div><span className="text-gray-400">HORA</span><br/>{labelModal.time || '-'}</div>
                      <div><span className="text-gray-400">CANTIDAD</span><br/><strong>{labelModal.quantity || '0'}</strong></div>
                    </div>
                    <div className="flex-1 flex flex-col justify-center items-center gap-1">
                      <div className="text-center">
                        <div className="text-[9px] text-gray-400">DESCRIPCIÓN</div>
                        <div className="text-sm font-bold">{labelModal.productDescription || '-'}</div>
                      </div>
                      <div className="text-center">
                        <div className="text-[9px] text-gray-400">LPN</div>
                        <div className="text-xs">{labelModal.dispatchGuide ? `${labelModal.lpn}-${labelModal.dispatchGuide}` : (labelModal.lpn || '-')}</div>
                      </div>
                      <div className="text-center">
                        <div className="text-[9px] text-gray-400">LOTE</div>
                        <div className="text-xs">{labelModal.lotCode || labelModal.lot || '-'}</div>
                      </div>
                      <div className="text-center">
                        <div className="text-[9px] text-gray-400">UBICACIÓN</div>
                        <div className="text-xs">{labelModal.location || '-'}</div>
                      </div>
                    </div>
                    {labelQrDataUrl && (
                      <div className="flex justify-end">
                        <img src={labelQrDataUrl} alt="QR" className="w-12 h-12" />
                      </div>
                    )}
                  </div>
                </div>
              )}
            </div>
          </div>
          <div className="px-4 pb-2">
            <Label>Impresora QZ Tray</Label>
            <select
              value={selectedPrinter}
              onChange={(e) => handlePrinterChange(e.target.value)}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <option value="">-- Auto-detectar --</option>
              {printers.map((printer) => (
                <option key={printer} value={printer}>{printer}</option>
              ))}
            </select>
          </div>
          <div className="px-4 pb-2 flex items-center gap-3">
            <Label className="shrink-0">Copias</Label>
            <Input
              type="number"
              min={1}
              max={99}
              value={labelCopies}
              onChange={(e) => setLabelCopies(Math.max(1, parseInt(e.target.value, 10) || 1))}
              className="w-20 text-center"
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => setLabelModal({ ...labelModal, open: false })}>Cerrar</Button>
            <Button type="button" onClick={printCurrentLabel}>
              <Printer className="h-4 w-4 mr-1" /> Imprimir (ZPL/QZ Tray)
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={lotModal.open} onOpenChange={(val) => { if (!val) { setLotModal({ open: false, unit: null, labels: [], loading: false }) } }}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Imprimir lote {lotModal.unit?.lot_code || ''}</DialogTitle>
            <DialogDescription>
              {lotModal.loading
                ? 'Cargando LPN activos del lote...'
                : `${lotModal.labels.length} LPN activo(s) · ${lotModal.labels.length * getLotCopies()} etiqueta(s) en total`}
            </DialogDescription>
          </DialogHeader>

          {!lotModal.loading && lotModal.labels.length > 0 && (
            <div className="max-h-48 space-y-1 overflow-y-auto rounded-md border border-input p-2">
              {lotModal.labels.map((label, idx) => (
                <div key={idx} className="flex items-center justify-between gap-2 rounded px-2 py-1 text-sm hover:bg-muted/50">
                  <span className="font-mono font-medium">{label.lpn}{label.dispatchGuide ? `-${label.dispatchGuide}` : ''}</span>
                  <span className="truncate text-xs text-muted-foreground">
                    {label.productDescription || ''}
                  </span>
                </div>
              ))}
            </div>
          )}

          <div>
            <Label>Impresora QZ Tray</Label>
            <select
              value={selectedPrinter}
              onChange={(e) => handlePrinterChange(e.target.value)}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <option value="">-- Auto-detectar --</option>
              {printers.map((printer) => (
                <option key={printer} value={printer}>{printer}</option>
              ))}
            </select>
          </div>

          <div className="flex items-center gap-3">
            <Label className="shrink-0">Copias por LPN</Label>
            <Input
              type="number"
              min={1}
              max={99}
              value={lotCopies}
              onChange={(e) => setLotCopies(Math.max(1, parseInt(e.target.value, 10) || 1))}
              className="w-20 text-center"
            />
          </div>

          <DialogFooter>
            <Button type="button" variant="ghost" onClick={previewLotLabels} disabled={lotModal.loading || !lotModal.labels.length}>
              <Eye className="h-4 w-4 mr-1" /> Vista previa
            </Button>
            <Button type="button" variant="ghost" onClick={() => setLotModal({ open: false, unit: null, labels: [], loading: false })}>Cerrar</Button>
            <Button type="button" onClick={printLotLabels} disabled={lotModal.loading || !lotModal.labels.length}>
              <Printer className="h-4 w-4 mr-1" /> Imprimir todo (ZPL/QZ Tray)
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={editModal.open} onOpenChange={(val) => { if (!val) { setEditModal({ open: false, unit: null }); editForm.reset(); } }}>
        <DialogContent className="sm:max-w-lg">
          <DialogHeader>
            <DialogTitle>Editar Pallet {editModal.unit?.license_plate_number}</DialogTitle>
          </DialogHeader>
          <form onSubmit={submitEdit} className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>LPN</Label>
                <Input
                  value={editForm.data.license_plate_number}
                  onChange={(e) => editForm.setData('license_plate_number', e.target.value)}
                />
                {editForm.errors.license_plate_number && <p className="text-sm text-red-500">{editForm.errors.license_plate_number}</p>}
              </div>
              <div className="space-y-2">
                <Label>Guía de despacho</Label>
                <Input
                  value={editForm.data.dispatch_guide}
                  onChange={(e) => editForm.setData('dispatch_guide', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Prefijo</Label>
                <Input
                  value={editForm.data.spatial_prefix}
                  onChange={(e) => editForm.setData('spatial_prefix', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Columna</Label>
                <Input
                  value={editForm.data.spatial_column}
                  onChange={(e) => editForm.setData('spatial_column', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Fila</Label>
                <Input
                  value={editForm.data.spatial_row}
                  onChange={(e) => editForm.setData('spatial_row', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Lote</Label>
                <Input
                  value={editForm.data.lot_code}
                  onChange={(e) => editForm.setData('lot_code', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Lote proveedor</Label>
                <Input
                  value={editForm.data.supplier_lot}
                  onChange={(e) => editForm.setData('supplier_lot', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Lote producción</Label>
                <Input
                  value={editForm.data.production_batch}
                  onChange={(e) => editForm.setData('production_batch', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>Cantidad disponible</Label>
                <Input
                  type="number"
                  step="0.0001"
                  value={editForm.data.available_quantity}
                  onChange={(e) => editForm.setData('available_quantity', e.target.value)}
                />
                {editForm.errors.available_quantity && <p className="text-sm text-red-500">{editForm.errors.available_quantity}</p>}
              </div>
            </div>
            <DialogFooter>
              <Button type="button" variant="ghost" onClick={() => { setEditModal({ open: false, unit: null }); editForm.reset(); }}>Cancelar</Button>
              <Button type="submit" disabled={editForm.processing}>
                {editForm.processing ? 'Guardando...' : 'Guardar cambios'}
              </Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={deleteConfirm.open} onOpenChange={(val) => setDeleteConfirm({ ...deleteConfirm, open: val })}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Eliminar Pallet</DialogTitle>
            <DialogDescription>
              ¿Estás seguro de eliminar el pallet <strong>{deleteConfirm.unit?.license_plate_number}</strong>?
              Se cerrará el LPN y se eliminarán todas sus posiciones de stock.
              Esta acción queda registrada en la auditoría del sistema.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2">
            <Label>Motivo (opcional)</Label>
            <Input
              value={deleteConfirm.reason}
              onChange={(e) => setDeleteConfirm({ ...deleteConfirm, reason: e.target.value })}
              placeholder="Ej: Pallet dañado, error de creación..."
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="ghost" onClick={() => setDeleteConfirm({ open: false, unit: null, reason: '' })}>Cancelar</Button>
            <Button type="button" variant="destructive" onClick={confirmDelete}>
              Eliminar pallet
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={splitModal.open} onOpenChange={(val) => { if (!val) { setSplitModal({ open: false, unit: null }); splitForm.reset(); } }}>
        <DialogContent className="max-h-[92vh] overflow-hidden p-0 sm:max-w-2xl">
          <DialogHeader className="border-b px-6 py-5">
            <DialogTitle>Dividir LPN {splitModal.unit?.license_plate_number}</DialogTitle>
            <DialogDescription>
              El LPN registrado a nivel de camión se dividirá en pallets con el correlativo automático.
              La posición geoespacial será la misma para todos y podrás ajustarla luego en cada pallet.
            </DialogDescription>
          </DialogHeader>
          <form onSubmit={submitSplit} className="flex max-h-[calc(92vh-88px)] flex-col">
            <div className="flex-1 overflow-y-auto px-6 py-5">
              <div className="grid gap-4 sm:grid-cols-2">
                <div className="space-y-4">
                  <div className="rounded-lg bg-slate-50 px-4 py-3 text-sm">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Cantidad total disponible</span>
                      <span className="font-bold">{Number(splitModal.unit?.available_quantity || 0).toLocaleString('es-CL')}</span>
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label>N° de pallets</Label>
                    <div className="flex items-end gap-2">
                      <Input
                        type="number"
                        min="2"
                        max="100"
                        step="1"
                        className="flex-1"
                        value={splitForm.data.pallet_count}
                        onChange={(e) => splitForm.setData('pallet_count', e.target.value)}
                      />
                      <Button
                        type="submit"
                        disabled={splitForm.processing || splitPreview.length === 0 || !splitSumMatches}
                      >{splitForm.processing ? 'Dividiendo...' : `Dividir en ${splitForm.data.pallet_count} pallets`}
                      </Button>
                    </div>
                    {splitForm.errors.pallet_count && <p className="text-sm text-red-500">{splitForm.errors.pallet_count}</p>}
                  </div>

                  <div className="grid grid-cols-3 gap-3">
                    <div className="space-y-2">
                      <Label>Prefijo</Label>
                      <Input value={splitForm.data.spatial_prefix} onChange={(e) => splitForm.setData('spatial_prefix', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label>Columna</Label>
                      <Input value={splitForm.data.spatial_column} onChange={(e) => splitForm.setData('spatial_column', e.target.value)} />
                    </div>
                    <div className="space-y-2">
                      <Label>Fila</Label>
                      <Input value={splitForm.data.spatial_row} onChange={(e) => splitForm.setData('spatial_row', e.target.value)} />
                    </div>
                  </div>

                  {splitForm.errors.logistic_unit && <p className="text-sm text-red-500">{splitForm.errors.logistic_unit}</p>}
                </div>

                <div>
                  {splitPreview.length > 0 && (
                    <div className="max-h-[55vh] overflow-y-auto rounded-lg border bg-white p-3">
                      <h4 className="mb-2 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Reparto resultante</h4>
                      <ul className="space-y-1">
                        {splitPreview.map((quantity, index) => (
                          <li key={index} className="flex justify-between text-sm">
                            <span className="text-slate-500">Pallet {index + 1}</span>
                            <span className="font-medium">{quantity.toLocaleString('es-CL', { maximumFractionDigits: 4 })}</span>
                          </li>
                        ))}
                      </ul>
                      <p className={`mt-2 text-xs ${splitSumMatches ? 'text-green-600' : 'text-red-600'}`}>
                        {splitSumMatches ? 'La suma coincide con el total disponible.' : 'La suma no coincide con el total disponible.'}
                      </p>
                    </div>
                  )}
                </div>
              </div>
            </div>
          </form>
        </DialogContent>
      </Dialog>

      <Dialog open={showRequestDetail} onOpenChange={setShowRequestDetail}>
        <DialogContent className="max-w-3xl">
          <DialogHeader>
            <DialogTitle>Items de la Solicitud Seleccionada</DialogTitle>
          </DialogHeader>
          {selectedRequest && (
            <div className="space-y-4">
              <div className="border rounded-lg overflow-hidden">
                <Table>
                  <TableHeader className="bg-slate-50">
                    <TableRow>
                      <TableHead>Material</TableHead>
                      <TableHead className="text-right">Cant. Solicitada</TableHead>
                      <TableHead>Notas</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {selectedRequest.items?.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell className="font-medium">{item.material?.codigo} · {item.material?.nombre}</TableCell>
                        <TableCell className="text-right">{Number(item.cantidad_solicitada).toLocaleString('es-CL')}</TableCell>
                        <TableCell className="text-sm text-muted-foreground">{item.notes || '-'}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
              <div className="flex justify-end">
                <Button variant="secondary" onClick={() => setShowRequestDetail(false)}>Cerrar</Button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  )
}

InventoryLogisticUnits.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Pallets / LPN</h2>}
  />
)
