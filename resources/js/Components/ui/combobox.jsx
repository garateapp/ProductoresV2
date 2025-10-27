import React, { useState } from 'react'
import { Button } from '@/Components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem } from '@/Components/ui/command'
import { ChevronsUpDown, Check } from 'lucide-react'

export default function Combobox({
  value,
  onChange,
  options = [],
  placeholder = 'Seleccione...',
  searchPlaceholder = 'Buscar...',
  emptyMessage = 'Sin resultados',
  className = 'w-56',
  disabled = false,
}) {
  const [searchTerm, setSearchTerm] = useState('')
  const selected = options.find(o => String(o.value) === String(value))
  const normalizedOptions = options.map(opt => ({
    ...opt,
    searchValue: String(opt.searchValue ?? opt.label ?? opt.value ?? ''),
  }))
  const normalizedValue = String(value)
  const filteredOptions = normalizedOptions.filter(opt =>
    opt.searchValue.toLowerCase().includes(searchTerm),
  )

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          type="button"
          variant="outline"
          role="combobox"
          className={`${className} justify-between`}
          disabled={disabled}
        >
          {selected ? selected.label : placeholder}
          <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
        </Button>
      </PopoverTrigger>
      <PopoverContent className={`${className} p-0`}>
        <Command>
          <CommandInput
            placeholder={searchPlaceholder}
            onValueChange={(val) => setSearchTerm(val.toLowerCase())}
          />
          <CommandEmpty>{emptyMessage}</CommandEmpty>
          <div className="max-h-60 overflow-y-auto">
            <CommandGroup>
              {filteredOptions.map(opt => (
                <CommandItem
                  key={opt.value}
                  value={opt.searchValue}
                  onSelect={() => {
                    onChange(String(opt.value))
                    setSearchTerm('')
                  }}
                >
                  <Check className={`mr-2 h-4 w-4 ${normalizedValue === String(opt.value) ? 'opacity-100' : 'opacity-0'}`} />
                  {opt.label}
                </CommandItem>
              ))}
            </CommandGroup>
          </div>
        </Command>
      </PopoverContent>
    </Popover>
  )
}
