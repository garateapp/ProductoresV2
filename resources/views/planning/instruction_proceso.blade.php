<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructivo - Proceso #{{ $process->id }}</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --border:#e5e7eb; --bg:#ffffff; --head:#f3f4f6; }
        html, body { background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .wrap { max-width: 1200px; margin: 18px auto; padding: 0 14px; }
        .top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        .title { font-size: 18px; font-weight: 800; margin:0; letter-spacing: .2px; }
        .meta { margin-top:8px; font-size: 12px; line-height: 1.35; color: var(--muted); }
        .meta strong { color: var(--ink); }
        .btns { display:flex; gap:8px; }
        .btn { border:1px solid var(--border); padding:10px 12px; border-radius:8px; background:#f9fafb; cursor:pointer; font-weight:700; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; border:1px solid var(--border); background:#fff; color: var(--ink); }

        .sheet { margin-top: 14px; border:1px solid var(--border); border-radius: 10px; overflow:hidden; }
        .sheet-header { background: var(--head); padding:10px 12px; border-bottom:1px solid var(--border); }
        .sheet-header .line { font-size: 13px; font-weight: 800; }
        .sheet-header .sub { margin-top: 4px; display:flex; flex-wrap:wrap; gap:10px; font-size: 11px; color: var(--muted); }

        table { width:100%; border-collapse: collapse; }
        th, td { padding:6px 6px; border-bottom:1px solid var(--border); font-size: 10.5px; vertical-align: top; }
        th { text-align:left; color:#374151; background:#fafafa; font-size: 10px; letter-spacing:.2px; text-transform: uppercase; }
        .right { text-align:right; white-space:nowrap; }
        .muted { color: var(--muted); }
        .small { font-size: 10px; }
        .nowrap { white-space:nowrap; }
        .wrap-any { word-break: break-word; }
        .audit { margin-top: 3px; font-size: 9.5px; color:#7c2d12; }
        .audit strong { color:#7c2d12; }

        @page { margin: 10mm; }
        @media print {
            .btns { display:none; }
            .wrap { margin: 0; max-width: none; }
            .sheet { break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <h1 class="title">INSTRUCTIVO DE PROCESO</h1>
            <div class="meta">
                <div>
                    <strong>Especie:</strong> {{ $process->especie }}
                    &nbsp;·&nbsp; <strong>Fecha Proceso:</strong> {{ optional($process->fecha)->format('d-m-Y') }}
                    &nbsp;·&nbsp; <strong>Turno:</strong> {{ $process->shift?->codigo }} {{ $process->shift?->nombre ? '· '.$process->shift?->nombre : '' }} ({{ $process->shift?->horas }}h)
                    &nbsp;·&nbsp; <strong>Versión:</strong> {{ optional($process->updated_at)->format('d-m H:i') }}
                    &nbsp;·&nbsp; <strong>Estado:</strong> <span class="badge">{{ $process->estado?->value ?? $process->estado }}</span>
                </div>

            </div>
        </div>
        <div class="btns">
            <button class="btn" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @foreach($groupedLots as $lineName => $lots)
        <div class="sheet">
            <div class="sheet-header">
                <div class="line">{{ $lineName }}</div>
                <div class="sub">
                    <span><strong>Proceso #</strong> {{ $process->id }}</span>
                    <span><strong>Lotes</strong> {{ $lots->count() }}</span>
                    <span><strong>Total bins</strong> {{ number_format((int) $lots->sum('cantidad_bins'), 0, ',', '.') }}</span>
                    <span><strong>Total kg</strong> {{ number_format((float) $lots->sum(fn($l) => (float) ($l->peso_neto ?? 0)), 0, ',', '.') }}</span>
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th class="nowrap" style="width:70px;">Hr inicio</th>
                    <th class="nowrap" style="width:70px;">Hr fin</th>
                    <th class="nowrap" style="width:70px;">Lote</th>
                    <th style="width:85px;">Tipo</th>
                    <th style="width:95px;">Variedad</th>
                    <th style="width:140px;">Productor</th>
                    <th class="nowrap" style="width:65px;">CSG</th>
                    <th style="width:90px;">Categoría</th>
                    <th class="nowrap" style="width:80px;">F. recepción</th>
                    <th class="right nowrap" style="width:55px;">Bins</th>
                    <th class="right nowrap" style="width:65px;">Kilos</th>
                    <th class="nowrap" style="width:55px;">SDP</th>
                    <th style="width:150px;">Embalaje</th>
                    <th class="nowrap" style="width:70px;">Nota cal.</th>
                    <th style="width:90px;">Color</th>
                    <th class="nowrap" style="width:55px;">Brix</th>
                    <th class="nowrap" style="width:55px;">Calibre</th>
                    <th class="nowrap" style="width:55px;">CP2</th>
                    <th style="width:190px;">Observaciones</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lots as $lot)
                    <tr>
                        <td class="nowrap">{{ $lot->inicio_estimado ? $lot->inicio_estimado->format('H:i') : '-' }}</td>
                        <td class="nowrap">{{ $lot->fin_estimado ? $lot->fin_estimado->format('H:i') : '-' }}</td>
                        <td class="nowrap">
                            <strong>{{ $lot->n_g_recepcion }}</strong>
                            @if(($lot->split_index ?? 1) > 1)
                                <div class="muted">Parte {{ $lot->split_index }}</div>
                            @endif
                        </td>
                        <td class="wrap-any">{{ $lot->descripcion_tipo ?? 'Normal' }}</td>
                        <td class="wrap-any"><strong>{{ $lot->n_variedad ?? '-' }}</strong></td>
                        <td class="wrap-any">{{ $lot->n_productor ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->csg_productor ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->setup_color ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->fecha_recepcion ? \Carbon\Carbon::parse($lot->fecha_recepcion)->format('d-m-Y') : '-' }}</td>
                        <td class="right nowrap">{{ number_format((int) $lot->cantidad_bins, 0, ',', '.') }}</td>
                        <td class="right nowrap">{{ $lot->peso_neto !== null ? number_format((float) $lot->peso_neto, 0, ',', '.') : '-' }}</td>
                        <td class="nowrap">{{ $lot->sdp_centrocosto ?? '-' }}</td>
                        <td class="wrap-any">
                            <div><strong>{{ $lot->c_embalaje ?? '-' }}</strong> <span class="muted">{{ $lot->n_embalaje ?? '' }}</span></div>
                            <div class="muted">CP2: {{ $lot->cp2_cajas_por_pallet ?? '-' }}</div>
                            @if($lot->lastPackagingChange)
                                <div class="audit">
                                    <strong>Editado</strong> {{ optional($lot->lastPackagingChange->created_at)->format('d-m H:i') }}
                                    @if($lot->lastPackagingChange->user)
                                        · {{ $lot->lastPackagingChange->user->name }}
                                    @endif
                                </div>
                            @endif
                        </td>
                        <td class="nowrap">{{ $lot->setup_nota_calidad ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->setup_color ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->brix ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->setup_calibre ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->cp2_cajas_por_pallet ?? '-' }}</td>
                        <td class="wrap-any">
                            @if($lot->lastPackagingChange)
                                <div><strong>Motivo:</strong> {{ $lot->lastPackagingChange->reason }}</div>
                            @else
                                <span class="muted">-</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
</body>
</html>

