<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Informe Proceso #{{ $proceso->n_proceso }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; color: #333; margin: 24px; font-size: 12px; }
        h1 { font-size: 20px; margin: 0 0 8px 0; color: #2c3e50; }
        h2 { font-size: 16px; margin: 16px 0 8px 0; color: #2c3e50; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; border-bottom: 2px solid #4CAF50; padding-bottom: 10px; margin-bottom: 16px; }
        .header-left { display: flex; align-items: center; gap: 12px; }
        .header-logo { height: 40px; width: auto; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 6px 16px; }
        .meta div { font-size: 12px; }
        .section { margin-top: 16px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: 600; }
        .muted { color: #6b7280; }
        .chip { display: inline-block; padding: 2px 6px; border-radius: 6px; font-size: 11px; background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
        .subtle { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; }
        .evaluation-card { page-break-inside: avoid; break-inside: avoid; margin-bottom: 12px; }
        .spacer { height: 10px; }
    </style>

</head>
<body>
    <div class="header">
        <div class="header-left">
            <img src="{{ asset('img/logo_garate.png') }}" alt="Gárate Hermanos" class="header-logo" />
            <h1>Informe de Producto Terminado</h1>
        </div>
        <div class="chip">Proceso #{{ $proceso->n_proceso }}</div>
    </div>

    <div class="section subtle">
        <h2>Información del Proceso</h2>
        <div class="meta">
            <div><strong>Agrícola:</strong> {{ $proceso->agricola ?? 'N/A' }}</div>
            <div><strong>Fecha:</strong> {{ $proceso->fecha ? \Carbon\Carbon::parse($proceso->fecha)->format('d/m/Y') : 'N/A' }}</div>
            <div><strong>Especie:</strong> {{ $proceso->especie }}</div>
            <div><strong>Variedad:</strong> {{ $proceso->variedad }}</div>
            <div><strong>Lote Recepción:</strong> {{ $proceso->lote_recepcion ?? 'N/A' }}</div>
            <div><strong>Kilos Netos:</strong> {{ number_format((float)($proceso->kilos_netos ?? 0), 0, ',', '.') }} kg</div>
            <div><strong>Temporada:</strong> 2025-2026</div>
        </div>
    </div>

    <div class="section">
        <h2>Evaluaciones Generadas</h2>
        @if(($proceso->processedFruitQualities ?? collect())->count() === 0)
            <p class="muted">No existen evaluaciones para este proceso.</p>
        @else
            @foreach($proceso->processedFruitQualities as $idx => $q)
                <div class="subtle evaluation-card">
                    <div class="meta" style="margin-bottom:6px;">
                        <div><strong>N° Caja:</strong> {{ $q->numero_de_caja ?? 'N/A' }}</div>
                        <div><strong>Fecha:</strong> {{ $q->fecha ? \Carbon\Carbon::parse($q->fecha)->format('d/m/Y') : ($q->created_at?->format('d/m/Y') ?? 'N/A') }}</div>
                        <div><strong>Responsable:</strong> {{ $q->responsable ?? 'N/A' }}</div>
                        <div><strong>Estado:</strong> {{ $q->estado ?? 'N/A' }}</div>
                        <div><strong>Categoría:</strong> {{ $q->categoria ?? 'N/A' }}</div>
                        <div><strong>Destino:</strong> {{ $q->destino ?? 'N/A' }}</div>
                        <div><strong>Calibre:</strong> {{ $q->calibre ?? 'N/A' }}</div>
                        <div><strong>Tamaño Muestra:</strong> {{ $q->t_muestra ?? 'N/A' }}</div>
                        <div><strong>Peso Exacto Caja (kg):</strong> {{ isset($q->peso_exacto_caja) ? number_format((float) $q->peso_exacto_caja, 2, ',', '.') : 'N/A' }}</div>
                        {{-- <div><strong>Color Cubrimiento:</strong> {{ $q->color_cubrimiento ?? 'N/A' }}</div> --}}
                        <div><strong>Color Fondo:</strong> {{ $q->color_fondo ?? 'N/A' }}</div>
                    </div>
                    @if(!empty($q->observaciones))
                        <div class="spacer"></div>
                        <div><strong>Observaciones:</strong> {{ $q->observaciones }}</div>
                    @endif

                    @php $details = $q->details ?? collect(); @endphp
                    @if($details->count() > 0)
                        <div class="spacer"></div>
                        <table>
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Detalle</th>
                                    <th>Cant. Muestra</th>
                                    <th>% Muestra</th>
                                    <th>Temperatura</th>
                                    <th>Valor SS / BRix</th>
                                    <th>Categoría</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($details as $d)
                                    <tr>
                                        <td>{{ $d->tipo_item ?? 'N/A' }}</td>
                                        <td>{{ $d->detalle_item ?? ($d->valor->nombre ?? 'N/A') }}</td>
                                        <td>{{ $d->cantidad_muestra ?? '-' }}</td>
                                        <td>{{ isset($d->porcentaje_muestra) ? number_format((float)$d->porcentaje_muestra, 2, ',', '.') . ' %' : '-' }}</td>
                                        <td>{{ $d->temperatura ?? '-' }}</td>
                                        <td>{{ $d->valor_ss ?? '-' }}</td>
                                        <td>{{ $d->categoria ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</body>
</html>
