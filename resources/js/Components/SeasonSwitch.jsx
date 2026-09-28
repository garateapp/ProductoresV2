import React, { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import { Switch } from '@/Components/ui/switch';

/**
 * Switch de temporada para las lecturas en SQL Server.
 *
 * - 'actual'   -> conexión 'sqlsrv'             (temporada en curso)
 * - 'anterior' -> conexión 'temporada_anterior' (temporada anterior)
 *
 * Al cambiarlo recarga la página actual con `?temporada=…` (el backend lo resuelve
 * y lo persiste en sesión), conservando los filtros que ya venir en la URL.
 */
export default function SeasonSwitch({ temporada = 'actual', database = null, params = {} }) {
  const page = usePage();
  const [switching, setSwitching] = useState(false);
  const isPrevious = temporada === 'anterior';
  const currentLabel = isPrevious ? 'Temporada anterior' : 'Temporada actual';

  const changeSeason = (checked) => {
    const value = checked ? 'anterior' : 'actual';
    if (value === temporada) return;

    setSwitching(true);

    const url = new URL(page.url || window.location.pathname, window.location.origin);
    Object.entries(params || {}).forEach(([key, value]) => {
      if (value === '' || value === null || value === undefined) {
        url.searchParams.delete(key);
      } else {
        url.searchParams.set(key, String(value));
      }
    });
    url.searchParams.set('temporada', value);

    router.get(`${url.pathname}${url.search}`, {
      preserveScroll: true,
      preserveState: false,
      replace: true,
      onFinish: () => setSwitching(false),
      onError: () => setSwitching(false),
    });
  };

  return (
    <div
      className="flex items-center gap-2 rounded-md border border-gray-200 bg-white px-3 py-1.5"
      title={`Leyendo de ${currentLabel} — base SQL Server: ${database || 'sin configurar'}. Actual: 'sqlsrv' · Anterior: 'temporada_anterior'.`}
    >
      <span className="text-xs text-gray-600">Temporada</span>
      <span className={`text-xs font-semibold ${isPrevious ? 'text-gray-400' : 'text-emerald-700'}`}>Actual</span>
      <Switch
        checked={isPrevious}
        disabled={switching}
        onCheckedChange={changeSeason}
        aria-label="Usar temporada anterior"
      />
      <span className={`text-xs font-semibold ${isPrevious ? 'text-amber-700' : 'text-gray-400'}`}>Anterior</span>
      <span className="max-w-[9rem] truncate text-[11px] text-gray-400" title={database || ''}>
        {database || '—'}
      </span>
    </div>
  );
}
