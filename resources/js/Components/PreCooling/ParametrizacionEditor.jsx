import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Plus, X } from 'lucide-react'

export default function ParametrizacionEditor({ dimensions, values, onChange, disabled = false }) {
  const addValue = (dim) => {
    onChange({
      ...values,
      [dim]: [...(values[dim] || []), ''],
    })
  }

  const setValue = (dim, index, value) => {
    const next = [...(values[dim] || [])]
    next[index] = value
    onChange({ ...values, [dim]: next })
  }

  const removeValue = (dim, index) => {
    onChange({
      ...values,
      [dim]: (values[dim] || []).filter((_, i) => i !== index),
    })
  }

  return (
    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
      {dimensions.map(({ key, label }) => (
        <div key={key} className="rounded-md border border-gray-200 p-3">
          <p className="mb-2 text-sm font-semibold text-gray-700">{label}</p>
          <div className="space-y-2">
            {(values[key] || []).map((valor, index) => (
              <div key={index} className="flex items-center gap-2">
                <Input
                  value={valor}
                  onChange={(e) => setValue(key, index, e.target.value)}
                  disabled={disabled}
                  placeholder={`Valor ${index + 1}`}
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  disabled={disabled}
                  onClick={() => removeValue(key, index)}
                  aria-label={`Quitar ${label}`}
                >
                  <X className="h-4 w-4" />
                </Button>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" disabled={disabled} onClick={() => addValue(key)}>
              <Plus className="mr-1 h-4 w-4" /> Agregar
            </Button>
          </div>
        </div>
      ))}
    </div>
  )
}
