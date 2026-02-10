<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructivo - Proceso #{{ $process->id }}</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --border:#e5e7eb; --bg:#ffffff; }
        html, body { background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .wrap { max-width: 1100px; margin: 24px auto; padding: 0 16px; }
        .top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        .title { font-size: 20px; font-weight: 700; margin:0; }
        .meta { margin-top:6px; color: var(--muted); font-size: 12px; line-height: 1.3; }
        .btns { display:flex; gap:8px; }
        .btn { border:1px solid var(--border); padding:10px 12px; border-radius:8px; background:#f9fafb; cursor:pointer; font-weight:600; }
        .section { margin-top: 18px; border:1px solid var(--border); border-radius: 10px; overflow:hidden; }
        .section h2 { margin:0; padding:12px 14px; font-size:14px; background:#f3f4f6; border-bottom:1px solid var(--border); }
        table { width:100%; border-collapse: collapse; }
        th, td { padding:10px 10px; border-bottom:1px solid var(--border); font-size: 12px; vertical-align: top; }
        th { text-align:left; color:#374151; background:#fafafa; }
        .muted { color: var(--muted); }
        .right { text-align:right; white-space:nowrap; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; border:1px solid var(--border); background:#fff; }
        @media print {
            .btns { display:none; }
            .wrap { margin: 0; max-width: none; }
            .section { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 class="title">Instructivo de Proceso #{{ $process->id }}</h1>
            <div class="meta">
                <div><strong>Especie:</strong> {{ $process->especie }} &nbsp;·&nbsp; <strong>Fecha:</strong> {{ optional($process->fecha)->format('d-m-Y') }}</div>
                <div>
                    <strong>Turno:</strong> {{ $process->shift?->codigo }} - {{ $process->shift?->nombre }}
                    ({{ $process->shift?->horas }}h @if((float)($process->extra_horas ?? 0) > 0)+ {{ $process->extra_horas }}h extra @endif)
                    &nbsp;·&nbsp; <strong>Estado:</strong> <span class="badge">{{ $process->estado?->value ?? $process->estado }}</span>
                </div>
            </div>
        </div>
        <div class="btns">
            <button class="btn" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @foreach($groupedLots as $lineName => $lots)
        <div class="section">
            <h2>{{ $lineName }}</h2>
            <table>
                <thead>
                <tr>
                    <th style="width:120px;">Recepción</th>
                    <th>Variedad / Setup</th>
                    <th>Embalaje</th>
                    <th class="right">Bins</th>
                    <th class="right">Peso Neto</th>
                    <th class="right">Inicio</th>
                    <th class="right">Fin</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lots as $lot)
                    <tr>
                        <td>
                            <div><strong>{{ $lot->n_g_recepcion }}</strong></div>
                            @if(($lot->split_index ?? 1) > 1)
                                <div class="muted">Parte {{ $lot->split_index }}</div>
                            @endif
                        </td>
                        <td>
                            <div><strong>{{ $lot->n_variedad ?? '-' }}</strong></div>
                            <div class="muted">
                                NC: {{ $lot->setup_nota_calidad ?? '-' }} ·
                                Cal: {{ $lot->setup_calibre ?? '-' }} ·
                                Color: {{ $lot->setup_color ?? '-' }} ·
                                Brix: {{ $lot->brix ?? '-' }}
                            </div>
                        </td>
                        <td>
                            <div><strong>{{ $lot->c_embalaje ?? '-' }}</strong> <span class="muted">{{ $lot->n_embalaje ?? '' }}</span></div>
                            <div class="muted">CP2: {{ $lot->cp2_cajas_por_pallet ?? '-' }}</div>
                        </td>
                        <td class="right">{{ number_format((int) $lot->cantidad_bins, 0, ',', '.') }}</td>
                        <td class="right">{{ $lot->peso_neto !== null ? number_format((float) $lot->peso_neto, 3, ',', '.') : '-' }}</td>
                        <td class="right">{{ $lot->inicio_estimado ? $lot->inicio_estimado->format('H:i') : '-' }}</td>
                        <td class="right">{{ $lot->fin_estimado ? $lot->fin_estimado->format('H:i') : '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
</body>
</html>
