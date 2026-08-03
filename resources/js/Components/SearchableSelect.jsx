import Select from 'react-select'

const styles = {
  control: (base, state) => ({
    ...base,
    minHeight: '40px',
    borderColor: state.isFocused ? '#6366f1' : '#d1d5db',
    boxShadow: state.isFocused ? '0 0 0 1px #6366f1' : 'none',
    '&:hover': {
      borderColor: state.isFocused ? '#6366f1' : '#9ca3af',
    },
  }),
  indicatorSeparator: (base) => ({
    ...base,
    display: 'none',
  }),
  menuPortal: (base) => ({
    ...base,
    zIndex: 60,
  }),
  placeholder: (base) => ({
    ...base,
    color: '#9ca3af',
  }),
}

export default function SearchableSelect({
  options = [],
  value = null,
  onChange,
  placeholder = 'Selecciona una opción',
  isClearable = true,
  isDisabled = false,
  menuPortalTarget,
}) {
  const resolvedMenuPortalTarget = menuPortalTarget === undefined
    ? (typeof document !== 'undefined' ? document.body : null)
    : menuPortalTarget

  return (
    <Select
      options={options}
      value={value}
      onChange={onChange}
      placeholder={placeholder}
      isClearable={isClearable}
      isDisabled={isDisabled}
      isSearchable
      className="mt-1 text-sm"
      classNamePrefix="react-select"
      menuPortalTarget={resolvedMenuPortalTarget}
      styles={styles}
      noOptionsMessage={() => 'Sin resultados'}
    />
  )
}
