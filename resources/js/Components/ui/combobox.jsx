import React from 'react'
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
  const selected = options.find(o => String(o.value) === String(value))

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
          <CommandInput placeholder={searchPlaceholder} />
          <CommandEmpty>{emptyMessage}</CommandEmpty>
          <CommandGroup>
            {options.map(opt => (
              <CommandItem key={opt.value} value={String(opt.label)} onSelect={() => onChange(String(opt.value))}>
                <Check className={`mr-2 h-4 w-4 ${String(value) === String(opt.value) ? 'opacity-100' : 'opacity-0'}`} />
                {opt.label}
              </CommandItem>
            ))}
          </CommandGroup>
        </Command>
      </PopoverContent>
    </Popover>
  )
}

