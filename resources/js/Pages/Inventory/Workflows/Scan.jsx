import { useEffect, useMemo, useState } from 'react'
import { useForm, usePage } from '@inertiajs/react'
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout'
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Switch } from '@/Components/ui/switch'
import SearchableSelect from '@/Components/SearchableSelect'
import { toast } from 'sonner'
import { locationSubmissionCode } from './locationSelection'
import { Eye, Trash2, Plus } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table'

export default function InventoryScanWorkflow({ wasteReasons = [], wasteTypes = [], materials = [], locations = [], materialRequests = [] }) {
  const { props } = usePage()
  const [mode, setMode] = useState('transfer')
  const [showRequestDetail, setShowRequestDetail] = useState(false)
  const [transferUnitInput, setTransferUnitInput] = useState('')
  const [transferUnitLookup, setTransferUnitLookup] = useState({ loading: false, error: null })
  const [transferReferenceByCode, setTransferReferenceByCode] = useState({})
  const [instantInput, setInstantInput] = useState('')
  const [instantLookup, setInstantLookup] = useState({ loading: false, error: null })
  const [instantReferences, setInstantReferences] = useState({})
  const [lpnSearch, setLpnSearch] = useState({ results: [], loading: false, error: null })
  const [selectedSearchMaterial, setSelectedSearchMaterial] = useState(null)
  const [wasteReference, setWasteReference] = useState({ positions: [], loading: false, error: null })
  const transferForm = useForm({
    material_request_id: '',
    logistic_unit_codes: [],
    transfer_items: [],
    destination_code: '',
    destination_location_id: '',
    observacion: '',
  })

  const instantForm = useForm({
    material_request_id: '',
    destination_location_id: '',
    logistic_unit_codes: [],
    transfer_items: [],
    observacion: '',
  })

  const wasteForm = useForm({
    detected_location_code: '',
    logistic_unit_code: '',
    material_id: '',
    position_id: '',
    quantity: '',
    waste_reason_id: '',
    waste_type_id: '',
    is_waste_pallet: false,
    quarantine_location_code: '',
    notes: '',
  })

  const handleRequestChange = (option) => {
    const request = materialRequests.find(r => String(r.id) === String(option?.value || ''))
    // Limpiar el otro form para evitar conflictos en el detalle
    if (instantForm.data.material_request_id) {
      instantForm.setData('material_request_id', '')
    }
    transferForm.setData({
      ...transferForm.data,
      material_request_id: option?.value || '',
      destination_location_id: request ? String(request.destination_location_id) : '',
    })
  }

  const handleInstantRequestChange = (option) => {
    const request = materialRequests.find(r => String(r.id) === String(option?.value || ''))
    if (transferForm.data.material_request_id) {
      transferForm.setData('material_request_id', '')
    }
    instantForm.setData({
      ...instantForm.data,
      material_request_id: option?.value || '',
      destination_location_id: request ? String(request.destination_location_id) : '',
    })
  }

  const requestOptions = useMemo(() => materialRequests.map(r => ({ value: String(r.id), label: r.label })), [materialRequests])
  const selectedRequest = useMemo(() => {
    const requestId = transferForm.data.material_request_id || instantForm.data.material_request_id
    return materialRequests.find(r => String(r.id) === String(requestId))
  }, [materialRequests, transferForm.data.material_request_id, instantForm.data.material_request_id])

  const wasteReasonOptions = useMemo(() => wasteReasons.map((item) => ({ value: String(item.id), label: item.nombre })), [wasteReasons])
  const wasteTypeOptions = useMemo(() => wasteTypes.map((item) => ({ value: String(item.id), label: item.nombre })), [wasteTypes])
  const materialOptions = useMemo(() => materials.map((item) => ({ value: String(item.id), label: `${item.codigo} · ${item.nombre}` })), [materials])
  const locationOptions = useMemo(() => locations.map((item) => ({
    value: String(item.id),
    label: `${item.path_code || item.codigo} · ${item.nombre}`,
  })), [locations])
  const resolveLocationByCode = (rawCode) => {
    const needle = String(rawCode || '').trim().toLowerCase()

    if (!needle) {
      return null
    }

    return locations.find((location) => (
      [location.codigo, location.scan_code, location.path_code]
        .filter(Boolean)
        .some((value) => String(value).trim().toLowerCase() === needle)
    )) || null
  }
  const resolvedDetectedLocation = useMemo(() => {
    return resolveLocationByCode(wasteForm.data.detected_location_code)
  }, [locations, wasteForm.data.detected_location_code])
  const resolvedQuarantineLocation = useMemo(() => {
    return resolveLocationByCode(wasteForm.data.quarantine_location_code)
  }, [locations, wasteForm.data.quarantine_location_code])
  const selectedInstantDestination = useMemo(() => {
    return locations.find((location) => String(location.id) === String(instantForm.data.destination_location_id)) || null
  }, [locations, instantForm.data.destination_location_id])

  const formatPositionLabel = (position) => {
    const locationLabel = position.location?.path_code || position.location?.codigo || 'Sin ubicación'
    const quantity = Number(position.quantity).toLocaleString('es-CL', { maximumFractionDigits: 4 })

    return `${locationLabel} · ${quantity}${position.lot_code ? ` · ${position.lot_code}` : ''}`
  }

  const spatialPositionLabel = (unit) => {
    const parts = []
    if (unit?.spatial_prefix) parts.push(`Prefijo ${unit.spatial_prefix}`)
    if (unit?.spatial_column) parts.push(`Col ${unit.spatial_column}`)
    if (unit?.spatial_row) parts.push(`Fila ${unit.spatial_row}`)
    return parts.length ? parts.join(' · ') : null
  }

  const requestMaterialOptions = useMemo(() => {
    if (!selectedRequest?.items) return []
    return selectedRequest.items
      .filter((item) => item?.material?.id)
      .map((item) => ({
        value: String(item.material.id),
        label: `${item.material.codigo} · ${item.material.nombre}`,
      }))
  }, [selectedRequest])

  const searchLpnByMaterial = async (materialId) => {
    if (!materialId) return

    setLpnSearch((prev) => ({ ...prev, loading: true, error: null }))

    try {
      const response = await window.axios.get(route('inventory.workflows.lpn-search'), {
        params: { material_id: materialId },
      })
      setLpnSearch((prev) => ({ ...prev, loading: false, results: response.data.units || [] }))
    } catch (error) {
      setLpnSearch((prev) => ({
        ...prev,
        loading: false,
        error: error?.response?.data?.errors
          ? Object.values(error.response.data.errors).flat()[0]
          : 'Error al buscar LPNs.',
      }))
    }
  }

  const addSearchedLpn = async (unit) => {
    const code = unit.license_plate_number
    if (instantForm.data.logistic_unit_codes.includes(code)) {
      toast.info('Este LPN ya fue agregado.')
      return
    }

    setInstantInput(code)
    setInstantLookup({ loading: true, error: null })

    try {
      const response = await window.axios.get(route('inventory.workflows.transfer-reference'), {
        params: { logistic_unit_code: code },
      })

      const found = response.data?.unit || null
      const unitCode = found?.license_plate_number || code
      const positions = Array.isArray(found?.positions) ? found.positions : []
      const selectedPosition = positions.length === 1 ? positions[0] : null

      setInstantReferences((current) => ({
        ...current,
        [unitCode]: { unit: found, error: null },
      }))

      instantForm.setData((current) => ({
        ...current,
        logistic_unit_codes: current.logistic_unit_codes.includes(unitCode)
          ? current.logistic_unit_codes
          : [...current.logistic_unit_codes, unitCode],
        transfer_items: [
          ...current.transfer_items.filter((item) => item.logistic_unit_code !== unitCode),
          {
            logistic_unit_code: unitCode,
            position_id: selectedPosition ? String(selectedPosition.id) : '',
            quantity: selectedPosition ? String(selectedPosition.quantity) : (positions.length ? '' : String(found?.available_quantity || '')),
          },
        ],
      }))

      setInstantInput('')
      setInstantLookup({ loading: false, error: null })
      setLpnSearch((prev) => ({ ...prev, query: '', results: [] }))
      toast.success(`${unitCode} agregado al traslado.`)
    } catch (error) {
      setInstantLookup({
        loading: false,
        error: error?.response?.data?.errors
          ? Object.values(error.response.data.errors).flat()[0]
          : 'No fue posible resolver el pallet/LPN.',
      })
    }
  }

  const printTransferPickList = () => {
    const codes = instantForm.data.logistic_unit_codes
    if (!codes.length) {
      toast.error('No hay pallets en el traslado para imprimir.')
      return
    }

    const win = window.open('', '_blank', 'width=700,height=500')
    if (!win) return

    const requestLabel = selectedRequest?.codigo || instantForm.data.material_request_id || '-'

    const rows = codes.map((code) => {
      const ref = instantReferences[code]
      const unit = ref?.unit
      const item = instantForm.data.transfer_items.find((i) => i.logistic_unit_code === code) || {}
      const spatial = spatialPositionLabel(unit)
      return `<tr>
        <td style="padding:6px 8px;border:1px solid #999">${requestLabel}</td>
        <td style="padding:6px 8px;border:1px solid #999">${unit?.license_plate_number || code}</td>
        <td style="padding:6px 8px;border:1px solid #999">${unit?.material ? `${unit.material.codigo} · ${unit.material.nombre}` : '-'}</td>
        <td style="padding:6px 8px;border:1px solid #999">${spatial || '-'}</td>
        <td style="padding:6px 8px;border:1px solid #999;text-align:right">${Number(item.quantity || unit?.available_quantity || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })}</td>
        <td style="padding:6px 8px;border:1px solid #999">${unit?.location?.path_code || unit?.location?.codigo || '-'}</td>
      </tr>`
    }).join('')

    win.document.open()
    win.document.write(`<!doctype html>
<html><head><title>Picking - Traslado</title>
<style>
  body { font-family: Arial, sans-serif; font-size: 13px; padding: 20px; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  .sub { color: #666; font-size: 11px; margin-bottom: 16px; }
  table { width: 100%; border-collapse: collapse; }
  th { background: #eee; padding: 6px 8px; border: 1px solid #999; text-align: left; font-size: 11px; }
  td { padding: 6px 8px; border: 1px solid #999; }
  .footer { margin-top: 20px; font-size: 10px; color: #999; text-align: center; }
</style></head><body>
<h1>Lista de Picking - Traslado Inmediato</h1>
<div class="sub">${new Date().toLocaleString()} · ${codes.length} pallet(s)</div>
<table>
<thead><tr>
  <th>Solicitud</th>
  <th>LPN</th>
  <th>Material</th>
  <th>Posición Espacial</th>
  <th style="text-align:right">Cantidad</th>
  <th>Ubicación</th>
</tr></thead>
<tbody>${rows}</tbody>
</table>
<div class="footer">Documento generado desde Escaneo Operativo</div>
</body></html>`)
    win.document.close()
    win.focus()
    win.setTimeout(() => win.print(), 300)
  }

  const setWasteLocationCode = (field, option) => {
    const location = locations.find((item) => String(item.id) === String(option?.value || ''))
    wasteForm.setData(field, locationSubmissionCode(location))
  }

  useEffect(() => {
    let cancelled = false

    const loadWasteReference = async () => {
      const detectedLocationCode = wasteForm.data.detected_location_code.trim()
      const logisticUnitCode = wasteForm.data.logistic_unit_code.trim()
      const materialId = wasteForm.data.material_id

      if (!detectedLocationCode || (!logisticUnitCode && !materialId)) {
        setWasteReference({ positions: [], loading: false, error: null })
        if (wasteForm.data.position_id) {
          wasteForm.setData('position_id', '')
        }
        return
      }

      setWasteReference((current) => ({ ...current, loading: true, error: null }))

      try {
        const response = await window.axios.get(route('inventory.workflows.waste-reference'), {
          params: {
            detected_location_code: detectedLocationCode,
            logistic_unit_code: logisticUnitCode || undefined,
            material_id: materialId || undefined,
          },
        })

        if (cancelled) {
          return
        }

        const nextMaterialId = response.data.material_id ? String(response.data.material_id) : ''
        if (nextMaterialId && !wasteForm.data.material_id) {
          wasteForm.setData('material_id', nextMaterialId)
        }

        const nextPositions = Array.isArray(response.data.positions) ? response.data.positions : []
        const hasSelectedPosition = nextPositions.some((position) => String(position.id) === String(wasteForm.data.position_id))
        if (!hasSelectedPosition && wasteForm.data.position_id) {
          wasteForm.setData('position_id', '')
        }

        setWasteReference({
          positions: nextPositions,
          loading: false,
          error: null,
        })
      } catch (error) {
        if (cancelled) {
          return
        }

        wasteForm.setData('position_id', '')
        setWasteReference({
          positions: [],
          loading: false,
          error: error?.response?.data?.errors
            ? Object.values(error.response.data.errors).flat()[0]
            : 'No fue posible consultar posiciones elegibles para la merma.',
        })
      }
    }

    loadWasteReference()

    return () => {
      cancelled = true
    }
  }, [wasteForm.data.detected_location_code, wasteForm.data.logistic_unit_code, wasteForm.data.material_id])

  const addTransferUnitCode = async () => {
    if (transferUnitLookup.loading) {
      return
    }

    const code = transferUnitInput.replace(/'/g, '-').trim()
    if (!code) {
      return
    }

    if (transferForm.data.logistic_unit_codes.includes(code)) {
      setTransferUnitInput('')
      return
    }

    setTransferUnitLookup({ loading: true, error: null })
    transferForm.clearErrors('logistic_unit_codes', 'transfer_items')

    try {
      const response = await window.axios.get(route('inventory.workflows.transfer-reference'), {
        params: {
          logistic_unit_code: code,
        },
      })

      const unit = response.data?.unit || null
      const unitCode = unit?.license_plate_number || code
      const positions = Array.isArray(unit?.positions) ? unit.positions : []
      const selectedPosition = positions.length === 1 ? positions[0] : null

      setTransferReferenceByCode((current) => ({
        ...current,
        [unitCode]: { unit, error: null },
      }))

      transferForm.setData((current) => ({
        ...current,
        logistic_unit_codes: current.logistic_unit_codes.includes(unitCode)
          ? current.logistic_unit_codes
          : [...current.logistic_unit_codes, unitCode],
        transfer_items: [
          ...current.transfer_items.filter((item) => item.logistic_unit_code !== unitCode),
          {
            logistic_unit_code: unitCode,
            position_id: selectedPosition ? String(selectedPosition.id) : '',
            quantity: selectedPosition ? String(selectedPosition.quantity) : (positions.length ? '' : String(unit?.available_quantity || '')),
          },
        ],
      }))

      setTransferUnitInput('')
      setTransferUnitLookup({ loading: false, error: null })
    } catch (error) {
      setTransferUnitLookup({
        loading: false,
        error: error?.response?.data?.errors
          ? Object.values(error.response.data.errors).flat()[0]
          : 'No fue posible resolver el pallet/LPN informado.',
      })
    }
  }

  const resetTransferForm = () => {
    transferForm.reset()
    transferForm.clearErrors()
    setTransferUnitInput('')
    setTransferUnitLookup({ loading: false, error: null })
    setTransferReferenceByCode({})
  }

  const resetInstantForm = () => {
    instantForm.reset()
    instantForm.clearErrors()
    setInstantInput('')
    setInstantLookup({ loading: false, error: null })
    setInstantReferences({})
  }

  const resetWasteForm = () => {
    wasteForm.reset()
    wasteForm.clearErrors()
    setWasteReference({ positions: [], loading: false, error: null })
  }

  const removeTransferUnitCode = (code) => {
    transferForm.setData((current) => ({
      ...current,
      logistic_unit_codes: current.logistic_unit_codes.filter((item) => item !== code),
      transfer_items: current.transfer_items.filter((item) => item.logistic_unit_code !== code),
    }))
    setTransferReferenceByCode((current) => {
      const next = { ...current }
      delete next[code]
      return next
    })
  }

  const updateTransferItem = (code, patch) => {
    transferForm.setData((current) => ({
      ...current,
      transfer_items: current.transfer_items.map((item) => (
        item.logistic_unit_code === code ? { ...item, ...patch } : item
      )),
    }))
  }

  const submitTransfer = (event) => {
    event.preventDefault()

    if (!transferForm.data.destination_location_id) {
      transferForm.setError('destination_location_id', 'Debes seleccionar la ubicación destino.')
      return
    }

    const missingPositionCode = transferForm.data.logistic_unit_codes.find((code) => {
      const positions = transferReferenceByCode[code]?.unit?.positions || []
      const item = transferForm.data.transfer_items.find((current) => current.logistic_unit_code === code)

      return positions.length > 1 && !item?.position_id
    })

    if (missingPositionCode) {
      transferForm.setError('transfer_items', `Debes seleccionar la posición que estás moviendo para el pallet ${missingPositionCode}.`)
      return
    }

    transferForm.clearErrors('destination_location_id', 'transfer_items')
    transferForm.post(route('inventory.workflows.transfer'), {
      preserveScroll: true,
      onSuccess: () => toast.success('Traslado registrado correctamente.'),
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al realizar el traslado.')
      },
    })
  }

  const addInstantCode = async () => {
    if (instantLookup.loading) return

    const code = instantInput.replace(/'/g, '-').trim()
    if (!code) return

    if (instantForm.data.logistic_unit_codes.includes(code)) {
      setInstantInput('')
      return
    }

    setInstantLookup({ loading: true, error: null })
    instantForm.clearErrors('logistic_unit_codes', 'transfer_items')

    try {
      const response = await window.axios.get(route('inventory.workflows.transfer-reference'), {
        params: { logistic_unit_code: code },
      })

      const unit = response.data?.unit || null
      const unitCode = unit?.license_plate_number || code
      const positions = Array.isArray(unit?.positions) ? unit.positions : []
      const selectedPosition = positions.length === 1 ? positions[0] : null

      setInstantReferences((current) => ({
        ...current,
        [unitCode]: { unit, error: null },
      }))

      instantForm.setData((current) => ({
        ...current,
        logistic_unit_codes: current.logistic_unit_codes.includes(unitCode)
          ? current.logistic_unit_codes
          : [...current.logistic_unit_codes, unitCode],
        transfer_items: [
          ...current.transfer_items.filter((item) => item.logistic_unit_code !== unitCode),
          {
            logistic_unit_code: unitCode,
            position_id: selectedPosition ? String(selectedPosition.id) : '',
            quantity: selectedPosition ? String(selectedPosition.quantity) : (positions.length ? '' : String(unit?.available_quantity || '')),
          },
        ],
      }))

      setInstantInput('')
      setInstantLookup({ loading: false, error: null })
    } catch (error) {
      setInstantLookup({
        loading: false,
        error: error?.response?.data?.errors
          ? Object.values(error.response.data.errors).flat()[0]
          : 'No fue posible resolver el pallet/LPN informado.',
      })
    }
  }

  const removeInstantCode = (code) => {
    instantForm.setData((current) => ({
      ...current,
      logistic_unit_codes: current.logistic_unit_codes.filter((item) => item !== code),
      transfer_items: current.transfer_items.filter((item) => item.logistic_unit_code !== code),
    }))
    setInstantReferences((current) => {
      const next = { ...current }
      delete next[code]
      return next
    })
  }

  const updateInstantItem = (code, patch) => {
    instantForm.setData((current) => ({
      ...current,
      transfer_items: current.transfer_items.map((item) => (
        item.logistic_unit_code === code ? { ...item, ...patch } : item
      )),
    }))
  }

  const submitInstantTransfer = (event) => {
    event.preventDefault()

    if (!instantForm.data.material_request_id) {
      instantForm.setError('material_request_id', 'Debes seleccionar una solicitud aprobada.')
      return
    }

    if (!instantForm.data.destination_location_id) {
      instantForm.setError('destination_location_id', 'Debes seleccionar la ubicación destino.')
      return
    }

    const missingPositionCode = instantForm.data.logistic_unit_codes.find((code) => {
      const positions = instantReferences[code]?.unit?.positions || []
      const item = instantForm.data.transfer_items.find((i) => i.logistic_unit_code === code)
      return positions.length > 1 && !item?.position_id
    })

    if (missingPositionCode) {
      instantForm.setError('transfer_items', `Debes seleccionar la posición que estás moviendo para el pallet ${missingPositionCode}.`)
      return
    }

    instantForm.clearErrors('destination_location_id', 'material_request_id', 'transfer_items')
    instantForm.post(route('inventory.workflows.transfer'), {
      preserveScroll: true,
      onSuccess: () => {
        resetInstantForm()
        toast.success('Traslado inmediato registrado correctamente.')
      },
      onError: (errors) => {
        const msg = Object.values(errors).join(', ')
        toast.error(msg || 'Error al realizar el traslado.')
      },
    })
  }

  const submitWaste = (event) => {
    event.preventDefault()

    if (wasteReference.positions.length > 0 && !wasteForm.data.position_id) {
      wasteForm.setError('position_id', 'Debes seleccionar una posición de stock para registrar la merma.')
      return
    }

    wasteForm.clearErrors('position_id')
    wasteForm.post(route('inventory.workflows.waste'), { preserveScroll: true })
  }

  return (
    <div className="container mx-auto py-10 space-y-4">
      <div className="flex flex-wrap gap-2">
        <Button type="button" variant={mode === 'transfer' ? 'default' : 'outline'} onClick={() => setMode('transfer')}>Traslado</Button>
        <Button type="button" variant={mode === 'waste' ? 'default' : 'outline'} onClick={() => setMode('waste')}>Merma</Button>
      </div>

      {props?.flash?.success && <div className="rounded border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">{props.flash.success}</div>}
      {props?.flash?.error && <div className="rounded border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{props.flash.error}</div>}
      <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
        Usa este módulo para registrar eventos rápidos por escaneo. El traslado consolida pallets por ubicación origen; la merma valida posiciones cuando el stock de la ubicación origen ya está distribuido por posición.
      </div>

      {mode === 'transfer' ? (
        <Card>
          <CardHeader>
            <CardTitle>Flujo rápido · Traslado</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submitTransfer} className="space-y-4">
              <div className="rounded border bg-white p-4 space-y-4">
                <div>
                  <div className="font-medium text-slate-900">Captura de traslado vinculado a solicitud</div>
                  <div className="text-xs text-slate-500">Selecciona primero la solicitud aprobada. La ubicación destino se completará automáticamente.</div>
                </div>

                <div className="space-y-2">
                  <Label>Solicitud de Materiales (Aprobada)</Label>
                  <div className="flex gap-2">
                    <div className="flex-1">
                      <SearchableSelect
                        options={requestOptions}
                        value={requestOptions.find(o => o.value === transferForm.data.material_request_id)}
                        onChange={handleRequestChange}
                        placeholder="Busca y selecciona folio de solicitud..."
                      />
                    </div>
                    {transferForm.data.material_request_id && (
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        onClick={() => setShowRequestDetail(true)}
                        title="Ver detalle de solicitud"
                      >
                        <Eye className="w-4 h-4" />
                      </Button>
                    )}
                  </div>
                  {transferForm.errors.material_request_id && <p className="text-sm text-red-600 font-medium">{transferForm.errors.material_request_id}</p>}
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label>Paso 1 · Escanea pallet y agrégalo al traslado</Label>
                    <div className="flex gap-2">
                      <Input
                        autoFocus
                        value={transferUnitInput}
                        onChange={(e) => setTransferUnitInput(e.target.value.replace(/'/g, '-'))}
                        disabled={!transferForm.data.material_request_id}
                        onKeyDown={(event) => {
                          if (event.key === 'Enter') {
                            event.preventDefault()
                            addTransferUnitCode()
                          }
                        }}
                        placeholder="Código pallet / LPN"
                      />
                      <Button type="button" variant="outline" onClick={addTransferUnitCode} disabled={transferUnitLookup.loading || !transferForm.data.material_request_id}>
                        {transferUnitLookup.loading ? 'Buscando...' : 'Agregar'}
                      </Button>
                    </div>
                    {!transferForm.data.material_request_id && <div className="mt-1 text-xs text-amber-600 font-bold uppercase italic">Selecciona una solicitud primero</div>}
                    <div className="mt-1 text-xs text-gray-500">Al agregar un pallet se muestran sus posiciones. Si tiene más de una, selecciona la posición que moverás.</div>
                    {transferUnitLookup.error ? <div className="mt-1 text-sm text-red-600">{transferUnitLookup.error}</div> : null}
                    {transferForm.errors.logistic_unit_codes && <div className="mt-1 text-sm text-red-600">{transferForm.errors.logistic_unit_codes}</div>}
                  </div>
                  <div>
                    <Label>Paso 2 · Ubicación destino (Auto-completado)</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={locationOptions.find((item) => item.value === String(transferForm.data.destination_location_id)) || null}
                      onChange={(option) => transferForm.setData('destination_location_id', option?.value || '')}
                      placeholder="Selecciona ubicación destino"
                      isDisabled={true}
                    />
                    {transferForm.errors.destination_location_id && <div className="mt-1 text-sm text-red-600">{transferForm.errors.destination_location_id}</div>}
                  </div>
                </div>
              </div>

              <div className="rounded border bg-gray-50 p-3">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-medium">Pallets listos para traslado</div>
                  <div className="text-xs text-gray-500">{transferForm.data.logistic_unit_codes.length} escaneado(s)</div>
                </div>
                {transferForm.data.logistic_unit_codes.length ? (
                  <div className="mt-3 space-y-3">
                    {transferForm.data.logistic_unit_codes.map((code) => {
                      const reference = transferReferenceByCode[code]
                      const unit = reference?.unit
                      const positions = unit?.positions || []
                      const transferItem = transferForm.data.transfer_items.find((item) => item.logistic_unit_code === code) || {}
                      const positionOptions = positions.map((position) => ({
                        value: String(position.id),
                        label: formatPositionLabel(position),
                      }))

                      return (
                        <div key={code} className="rounded border bg-white p-3 text-sm">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <div className="font-medium text-slate-900">{unit?.license_plate_number || code}</div>
                              <div className="mt-1 text-xs text-slate-500">
                                {unit?.material ? `${unit.material.codigo} · ${unit.material.nombre}` : 'Material no informado'} ·
                                {' '}Origen: {unit?.location?.path_code || unit?.location?.codigo || 'Sin ubicación'} ·
                                {' '}Disponible: {Number(unit?.available_quantity || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                              </div>
                            </div>
                            <Button type="button" variant="outline" size="sm" onClick={() => removeTransferUnitCode(code)}>Quitar</Button>
                          </div>

                          {positions.length ? (
                            <div className="mt-3 grid gap-3 md:grid-cols-2">
                              <div>
                                <Label>{positions.length > 1 ? 'Posición a mover' : 'Posición detectada'}</Label>
                                <SearchableSelect
                                  options={positionOptions}
                                  value={positionOptions.find((item) => item.value === String(transferItem.position_id)) || null}
                                  onChange={(option) => {
                                    const selectedPosition = positions.find((position) => String(position.id) === String(option?.value || ''))
                                    updateTransferItem(code, {
                                      position_id: option?.value || '',
                                      quantity: selectedPosition ? String(selectedPosition.quantity) : '',
                                    })
                                  }}
                                  placeholder={positions.length > 1 ? 'Selecciona posición' : 'Posición única'}
                                  isDisabled={positions.length === 1}
                                  isClearable={positions.length > 1}
                                />
                                {positions.length > 1 ? <div className="mt-1 text-xs font-medium text-amber-700">Este pallet está distribuido en varias posiciones.</div> : null}
                              </div>
                              <div>
                                <Label>Cantidad a mover</Label>
                                <Input value={transferItem.quantity || ''} disabled />
                              </div>
                            </div>
                          ) : (
                            <div className="mt-3 rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                              El pallet no tiene posiciones activas; se trasladará el disponible completo registrado en el LPN.
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <div className="mt-3 text-sm text-gray-500">Escanea uno o más pallets antes de confirmar el traslado.</div>
                )}
                {transferForm.errors.transfer_items && <div className="mt-2 text-sm text-red-600">{transferForm.errors.transfer_items}</div>}
                {transferForm.errors.workflow && <div className="mt-2 text-sm text-red-600">{transferForm.errors.workflow}</div>}
              </div>
              <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Al confirmar, los pallets quedan en tránsito, el stock sale de la ubicación origen y la ubicación destino recibe una notificación para confirmar pallet por pallet con trazabilidad por posiciones.
              </div>
              <div>
                <Label>Observación</Label>
                <Input value={transferForm.data.observacion} onChange={(e) => transferForm.setData('observacion', e.target.value)} placeholder="Opcional" />
                {transferForm.errors.observacion && <div className="mt-1 text-sm text-red-600">{transferForm.errors.observacion}</div>}
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={resetTransferForm}>Limpiar</Button>
                <Button type="submit" disabled={transferForm.processing}>{transferForm.processing ? 'Registrando...' : 'Confirmar traslado'}</Button>
              </div>
            </form>

            <form onSubmit={submitInstantTransfer} className="mt-6 rounded border bg-white p-4 space-y-4">
              <div>
                <div className="font-medium text-slate-900">Traslado inmediato por pallet/LPN o posición</div>
                <div className="text-xs text-slate-500">Escanea uno o más pallets/LPNs, selecciona posición y cantidad parcial por cada uno.</div>
              </div>

              <div className="space-y-2">
                <Label>Solicitud de Materiales (Aprobada)</Label>
                <div className="flex gap-2">
                  <div className="flex-1">
                    <SearchableSelect
                      options={requestOptions}
                      value={requestOptions.find(o => o.value === instantForm.data.material_request_id)}
                      onChange={handleInstantRequestChange}
                      placeholder="Busca folio de solicitud..."
                    />
                  </div>
                  {instantForm.data.material_request_id && (
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={() => setShowRequestDetail(true)}
                      title="Ver detalle de solicitud"
                    >
                      <Eye className="w-4 h-4" />
                    </Button>
                  )}
                </div>
                {instantForm.errors.material_request_id && <p className="text-sm text-red-600 font-medium">{instantForm.errors.material_request_id}</p>}
              </div>

              <div className="grid gap-4 md:grid-cols-2">
                <div>
                  <Label>Escanea pallet y agrégalo al traslado</Label>
                  <div className="flex gap-2">
                    <Input
                      value={instantInput}
                      onChange={(e) => setInstantInput(e.target.value.replace(/'/g, '-'))}
                      disabled={!instantForm.data.material_request_id}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter') {
                          event.preventDefault()
                          addInstantCode()
                        }
                      }}
                      placeholder="Código pallet / LPN"
                    />
                    <Button type="button" variant="outline" onClick={addInstantCode} disabled={instantLookup.loading || !instantForm.data.material_request_id}>
                      {instantLookup.loading ? 'Buscando...' : 'Agregar'}
                    </Button>
                  </div>
                  {!instantForm.data.material_request_id && <div className="mt-1 text-xs text-amber-600 font-bold uppercase italic">Selecciona una solicitud primero</div>}
                  <div className="mt-1 text-xs text-gray-500">Al agregar un pallet se muestran sus posiciones. Si tiene más de una, selecciona la posición que moverás.</div>
                  {instantLookup.error ? <div className="mt-1 text-sm text-red-600">{instantLookup.error}</div> : null}
                  {instantForm.errors.logistic_unit_codes && <div className="mt-1 text-sm text-red-600">{instantForm.errors.logistic_unit_codes}</div>}
                </div>
                <div>
                  <Label>Ubicación destino (Auto-completado)</Label>
                  <SearchableSelect
                    options={locationOptions}
                    value={locationOptions.find((item) => item.value === String(instantForm.data.destination_location_id)) || null}
                    onChange={(option) => instantForm.setData('destination_location_id', option?.value || '')}
                    placeholder="Selecciona ubicación destino"
                    isDisabled={true}
                  />
                  {instantForm.errors.destination_location_id ? <div className="mt-1 text-sm text-red-600">{instantForm.errors.destination_location_id}</div> : null}
                  {selectedInstantDestination ? <div className="mt-1 text-xs text-slate-500">Destino: {selectedInstantDestination.path_code || selectedInstantDestination.codigo} · {selectedInstantDestination.nombre}</div> : null}
                </div>
              </div>

              <div className="rounded border bg-white p-3">
                <div className="text-sm font-medium mb-2">Buscar LPN por material de la solicitud</div>
                <SearchableSelect
                  options={requestMaterialOptions}
                  value={requestMaterialOptions.find((o) => o.value === selectedSearchMaterial) || null}
                  onChange={(option) => {
                    const materialId = option?.value || null
                    setSelectedSearchMaterial(materialId)
                    if (materialId) {
                      searchLpnByMaterial(materialId)
                    } else {
                      setLpnSearch((p) => ({ ...p, results: [], error: null }))
                    }
                  }}
                  placeholder="Selecciona un material de la solicitud..."
                  isDisabled={!selectedRequest}
                  isClearable
                />
                {!selectedRequest ? <div className="mt-1 text-xs text-amber-600 font-bold uppercase italic">Selecciona una solicitud primero</div> : null}
                {lpnSearch.error ? <div className="mt-1 text-sm text-red-600">{lpnSearch.error}</div> : null}
                {lpnSearch.results.length > 0 ? (
                  <div className="mt-2 space-y-2 max-h-60 overflow-y-auto">
                    <div className="text-xs text-slate-500">{lpnSearch.results.length} LPN(s) disponibles</div>
                    {lpnSearch.results.map((unit) => {
                      const spatial = spatialPositionLabel(unit)
                      return (
                        <div key={unit.id} className="flex items-start justify-between gap-3 rounded border bg-slate-50 p-2 text-xs">
                          <div className="min-w-0 flex-1">
                            <div className="font-medium text-slate-900">{unit.license_plate_number}</div>
                            <div className="text-slate-500">
                              Disp: {Number(unit.available_quantity || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                              {spatial ? ` · ${spatial}` : ''}
                            </div>
                          </div>
                          <Button type="button" size="sm" variant="outline" onClick={() => addSearchedLpn(unit)}>
                            Agregar
                          </Button>
                        </div>
                      )
                    })}
                  </div>
                ) : null}
                {selectedSearchMaterial && !lpnSearch.loading && !lpnSearch.results.length && !lpnSearch.error ? (
                  <div className="mt-1 text-xs text-slate-500">Sin LPNs activos para este material en la ubicación de origen.</div>
                ) : null}
              </div>

              <div className="rounded border bg-gray-50 p-3">
                <div className="flex items-center justify-between">
                  <div className="text-sm font-medium">Pallets listos para traslado inmediato</div>
                  <div className="text-xs text-gray-500">{instantForm.data.logistic_unit_codes.length} escaneado(s)</div>
                </div>
                {instantForm.data.logistic_unit_codes.length ? (
                  <div className="mt-3 space-y-3">
                    {instantForm.data.logistic_unit_codes.map((code) => {
                      const reference = instantReferences[code]
                      const unit = reference?.unit
                      const positions = unit?.positions || []
                      const item = instantForm.data.transfer_items.find((i) => i.logistic_unit_code === code) || {}
                      const positionOptions = positions.map((position) => ({
                        value: String(position.id),
                        label: formatPositionLabel(position),
                      }))

                      return (
                        <div key={code} className="rounded border bg-white p-3 text-sm">
                          <div className="flex items-start justify-between gap-3">
                            <div>
                              <div className="font-medium text-slate-900">{unit?.license_plate_number || code}</div>
                              <div className="mt-1 text-xs text-slate-500">
                                {unit?.material ? `${unit.material.codigo} · ${unit.material.nombre}` : 'Material no informado'} ·
                                {' '}Origen: {unit?.location?.path_code || unit?.location?.codigo || 'Sin ubicación'} ·
                                {' '}Disponible: {Number(unit?.available_quantity || 0).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                              </div>
                            </div>
                            {spatialPositionLabel(unit) ? <div className="text-xs text-slate-500 mt-1">{spatialPositionLabel(unit)}</div> : null}
                            <Button type="button" variant="outline" size="sm" onClick={() => removeInstantCode(code)}>Quitar</Button>
                          </div>

                          {positions.length ? (
                            <div className="mt-3 grid gap-3 md:grid-cols-2">
                              <div>
                                <Label>{positions.length > 1 ? 'Posición a mover' : 'Posición detectada'}</Label>
                                <SearchableSelect
                                  options={positionOptions}
                                  value={positionOptions.find((p) => p.value === String(item.position_id)) || null}
                                  onChange={(option) => {
                                    const selectedPosition = positions.find((p) => String(p.id) === String(option?.value || ''))
                                    updateInstantItem(code, {
                                      position_id: option?.value || '',
                                      quantity: selectedPosition ? String(selectedPosition.quantity) : '',
                                    })
                                  }}
                                  placeholder={positions.length > 1 ? 'Selecciona posición' : 'Posición única'}
                                  isDisabled={positions.length === 1}
                                  isClearable={positions.length > 1}
                                />
                                {positions.length > 1 ? <div className="mt-1 text-xs font-medium text-amber-700">Este pallet está distribuido en varias posiciones.</div> : null}
                              </div>
                              <div>
                                <Label>Cantidad a trasladar (parcial)</Label>
                                <Input
                                  type="number"
                                  step="0.0001"
                                  value={item.quantity || ''}
                                  onChange={(e) => updateInstantItem(code, { quantity: e.target.value })}
                                />
                              </div>
                            </div>
                          ) : (
                            <div className="mt-3 grid gap-3 md:grid-cols-2">
                              <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900 md:col-span-2">
                                El pallet no tiene posiciones activas; se trasladará el disponible completo registrado en el LPN.
                              </div>
                            </div>
                          )}
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <div className="mt-3 text-sm text-gray-500">Escanea uno o más pallets antes de confirmar.</div>
                )}
                {instantForm.errors.transfer_items && <div className="mt-2 text-sm text-red-600">{instantForm.errors.transfer_items}</div>}
              </div>

              <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                Los traslados inmediatos se registran y quedan en tránsito para recepción en destino. Puedes mover varios pallets a la vez.
              </div>
              <div>
                <Label>Observación</Label>
                <Input value={instantForm.data.observacion} onChange={(e) => instantForm.setData('observacion', e.target.value)} placeholder="Opcional" />
                {instantForm.errors.observacion && <div className="mt-1 text-sm text-red-600">{instantForm.errors.observacion}</div>}
              </div>
              <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={resetInstantForm}>Limpiar</Button>
                <Button type="button" variant="secondary" onClick={printTransferPickList} disabled={!instantForm.data.logistic_unit_codes.length}>
                  Imprimir picking
                </Button>
                <Button type="submit" disabled={instantForm.processing}>{instantForm.processing ? 'Registrando...' : 'Confirmar traslado inmediato'}</Button>
              </div>
            </form>
          </CardContent>
        </Card>
      ) : (
        <Card>
          <CardHeader>
            <CardTitle>Flujo rápido · Merma por ubicación</CardTitle>
          </CardHeader>
          <CardContent>
            <form onSubmit={submitWaste} className="space-y-4">
              <div className="rounded border bg-white p-4 space-y-4">
                <div>
                  <div className="font-medium text-slate-900">Captura de merma</div>
                  <div className="text-xs text-slate-500">Define primero la ubicación detectada y luego el origen físico de la merma: pallet/LPN, material y posición cuando aplique.</div>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                  <div>
                    <Label>Paso 1 · Ubicación donde ocurre la merma</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={resolvedDetectedLocation ? locationOptions.find((item) => item.value === String(resolvedDetectedLocation.id)) || null : null}
                      onChange={(option) => setWasteLocationCode('detected_location_code', option)}
                      placeholder="Selecciona ubicación"
                    />
                  </div>
                  <div>
                    <Label>Paso 2 · Pallet / LPN origen</Label>
                    <Input value={wasteForm.data.logistic_unit_code} onChange={(e) => wasteForm.setData('logistic_unit_code', e.target.value.replace(/'/g, '-'))} placeholder="Opcional si registras por material" />
                    {wasteForm.errors.logistic_unit_code && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.logistic_unit_code}</div>}
                  </div>
                  <div>
                    <Label>Material</Label>
                    <SearchableSelect
                      options={materialOptions}
                      value={materialOptions.find((item) => item.value === String(wasteForm.data.material_id)) || null}
                      onChange={(option) => wasteForm.setData('material_id', option?.value || '')}
                      placeholder="Selecciona material si no informas pallet"
                    />
                    <div className="mt-1 text-xs text-gray-500">Usa pallet/LPN o material. Si informas pallet/LPN, el material se resuelve automáticamente para buscar posiciones elegibles.</div>
                    {wasteForm.errors.material_id && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.material_id}</div>}
                  </div>
                  <div>
                    <Label>Posición</Label>
                    <SearchableSelect
                      options={wasteReference.positions.map((position) => ({
                        value: String(position.id),
                        label: `${position.logistic_unit?.license_plate_number || 'Sin LPN'} · ${position.location?.codigo || 'Sin ubicación'} · ${Number(position.quantity).toLocaleString('es-CL', { maximumFractionDigits: 4 })}${position.lot_code ? ` · ${position.lot_code}` : ''}`,
                      }))}
                      value={wasteReference.positions.map((position) => ({
                        value: String(position.id),
                        label: `${position.logistic_unit?.license_plate_number || 'Sin LPN'} · ${position.location?.codigo || 'Sin ubicación'} · ${Number(position.quantity).toLocaleString('es-CL', { maximumFractionDigits: 4 })}${position.lot_code ? ` · ${position.lot_code}` : ''}`,
                      })).find((item) => item.value === String(wasteForm.data.position_id)) || null}
                      onChange={(option) => wasteForm.setData('position_id', option?.value || '')}
                      placeholder={wasteReference.positions.length ? 'Selecciona posición' : 'Sin posiciones específicas'}
                      isDisabled={wasteReference.loading || !wasteReference.positions.length}
                    />
                    {wasteReference.loading ? <div className="mt-1 text-xs text-gray-500">Buscando posiciones elegibles...</div> : null}
                    {!wasteReference.loading && wasteReference.positions.length ? <div className="mt-1 text-xs font-medium text-amber-700">Obligatoria cuando existe stock posicionado en la ubicación detectada.</div> : null}
                    {wasteReference.error ? <div className="mt-1 text-sm text-red-600">{wasteReference.error}</div> : null}
                    {wasteForm.errors.position_id ? <div className="mt-1 text-sm text-red-600">{wasteForm.errors.position_id}</div> : null}
                  </div>
                  <div>
                    <Label>Cantidad</Label>
                    <Input type="number" step="0.0001" value={wasteForm.data.quantity} onChange={(e) => wasteForm.setData('quantity', e.target.value)} />
                    {wasteForm.errors.quantity && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.quantity}</div>}
                  </div>
                  <div>
                    <Label>Motivo de Merma</Label>
                    <SearchableSelect
                      options={wasteReasonOptions}
                      value={wasteReasonOptions.find((item) => item.value === String(wasteForm.data.waste_reason_id)) || null}
                      onChange={(option) => wasteForm.setData('waste_reason_id', option?.value || '')}
                      placeholder="Selecciona motivo"
                    />
                    {wasteForm.errors.waste_reason_id && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.waste_reason_id}</div>}
                  </div>
                  <div>
                    <Label>Tipo de Merma</Label>
                    <SearchableSelect
                      options={wasteTypeOptions}
                      value={wasteTypeOptions.find((item) => item.value === String(wasteForm.data.waste_type_id)) || null}
                      onChange={(option) => wasteForm.setData('waste_type_id', option?.value || '')}
                      placeholder="Selecciona tipo de merma"
                    />
                    {wasteForm.errors.waste_type_id && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.waste_type_id}</div>}
                  </div>
                  <div className="flex items-center space-x-2 pt-6">
                    <Switch
                      id="is_waste_pallet"
                      checked={wasteForm.data.is_waste_pallet}
                      onCheckedChange={(checked) => wasteForm.setData('is_waste_pallet', checked)}
                    />
                    <Label htmlFor="is_waste_pallet">Crear pallet de merma (LPN MERMA-...)</Label>
                  </div>
                  <div>
                    <Label>Ubicación cuarentena</Label>
                    <SearchableSelect
                      options={locationOptions}
                      value={resolvedQuarantineLocation ? locationOptions.find((item) => item.value === String(resolvedQuarantineLocation.id)) || null : null}
                      onChange={(option) => setWasteLocationCode('quarantine_location_code', option)}
                      placeholder="Opcional"
                    />
                    {wasteForm.errors.quarantine_location_code && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.quarantine_location_code}</div>}
                  </div>
                  <div>
                    <Label>Notas</Label>
                    <Input value={wasteForm.data.notes} onChange={(e) => wasteForm.setData('notes', e.target.value)} placeholder="Detalle breve" />
                    {wasteForm.errors.notes && <div className="mt-1 text-sm text-red-600">{wasteForm.errors.notes}</div>}
                  </div>
                </div>
              </div>
              {resolvedDetectedLocation ? (
                <div className="rounded border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                  Ubicación resuelta: {resolvedDetectedLocation.path_code || resolvedDetectedLocation.codigo} · {resolvedDetectedLocation.nombre}
                </div>
              ) : null}
              {!resolvedDetectedLocation && wasteForm.data.detected_location_code ? (
                <div className="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                  La ubicación solo se valida cuando exista un código reconocible. Si el código no coincide, no se podrán resolver posiciones elegibles.
                </div>
              ) : null}
              {wasteForm.errors.detected_location_code && <div className="text-sm text-red-600">{wasteForm.errors.detected_location_code}</div>}
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={resetWasteForm}>Limpiar</Button>
                <Button type="submit" disabled={wasteForm.processing || wasteReference.loading}>{wasteForm.processing ? 'Registrando...' : 'Registrar merma'}</Button>
              </div>
            </form>
          </CardContent>
        </Card>
      )}

      <Dialog open={showRequestDetail} onOpenChange={setShowRequestDetail}>
        <DialogContent className="max-w-3xl max-h-[75vh] overflow-y-auto">
          <DialogHeader>
            <DialogTitle>Detalle de Solicitud Seleccionada</DialogTitle>
            <DialogDescription>
              Materiales y cantidades requeridas para este movimiento.
            </DialogDescription>
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
                        <TableCell className="font-medium">
                          {item.material?.codigo} · {item.material?.nombre}
                        </TableCell>
                        <TableCell className="text-right">
                          {Number(item.cantidad_solicitada).toLocaleString('es-CL', { maximumFractionDigits: 4 })}
                        </TableCell>
                        <TableCell className="text-sm text-slate-600 italic">
                          {item.notas || '-'}
                        </TableCell>
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

InventoryScanWorkflow.layout = (page) => (
  <AuthenticatedLayout
    children={page}
    header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Inventario · Escaneo operativo</h2>}
  />
)
