<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructivo - Turno</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --border:#d1d5db; --bg:#ffffff; --head:#f3f4f6; }
        html, body { background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .wrap { max-width: none; margin: 12px; padding: 0 10px; }
        .top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        .logo { height: 34px; width:auto; object-fit: contain; }
        .title { font-size: 18px; font-weight: 900; margin:0; letter-spacing: .2px; }
        .meta { margin-top:8px; font-size: 12px; line-height: 1.35; color: var(--muted); }
        .meta strong { color: var(--ink); }
        .btns { display:flex; gap:8px; }
        .btn { border:1px solid var(--border); padding:10px 12px; border-radius:8px; background:#f9fafb; cursor:pointer; font-weight:800; }

        .sheet { margin-top: 12px; border:1px solid var(--border); border-radius: 10px; overflow:hidden; }
        .sheet-header { background: var(--head); padding:10px 12px; border-bottom:1px solid var(--border); }
        .sheet-grid { display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; }
        .cell { font-size: 12px; color: var(--muted); }
        .cell strong { color: var(--ink); }

        table { width:100%; border-collapse: collapse; }
        th, td { padding:6px 6px; border:1px solid var(--border); font-size: 10.5px; vertical-align: top; }
        th { text-align:left; color:#374151; background:#fafafa; font-size: 10px; letter-spacing:.2px; text-transform: none; }
        .right { text-align:right; white-space:nowrap; }
        .nowrap { white-space:nowrap; }
        .wrap-any { word-break: break-word; }
        .muted { color: var(--muted); }

        .section-title { padding:10px 12px; border-top:1px solid var(--border); background:#fafafa; font-size:11px; font-weight:900; }
        .comments { padding:10px 12px; border-top:1px solid var(--border); font-size:11px; }

        @page { margin: 10mm; size: A4 landscape; }
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
            <img class="logo" src="{{ asset('img/logogreenex.png') }}" alt="Greenex" />
            <h1 class="title">INSTRUCTIVO DE EMBALAJE HAND PACK</h1>
        </div>
        <div class="btns">
            <button class="btn" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    @php
        $version = \Carbon\Carbon::now();
    @endphp

    @foreach($lineSheets as $sheet)
        @php
            $kilos = (float) ($sheet['kilos'] ?? 0);
            $lineName = (string) ($sheet['lineName'] ?? '-');
            $speciesLabel = (string) ($sheet['speciesLabel'] ?? '-');
            $exportadoraLabel = (string) ($sheet['exportadoraLabel'] ?? '-');
            $pedidosLabel = (string) ($sheet['pedidosLabel'] ?? '-');
            $lots = $sheet['lots'] ?? [];
            $packagingSummary = $sheet['packagingSummary'] ?? [];
        @endphp

        <div class="sheet">
            <div class="sheet-header">
                <div class="sheet-grid">
                    <div class="cell"><strong>Especie</strong> {{ $speciesLabel }}</div>
                    <div class="cell"><strong>Exportadora</strong> {{ $exportadoraLabel }}</div>
                    <div class="cell"><strong>Fecha Proceso</strong> {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</div>
                    <div class="cell"><strong>Versión</strong> {{ $version->format('d-m H:i') }}</div>
                    <div class="cell"><strong>Turno</strong> {{ $shift?->codigo }} {{ $shift?->nombre ? '· '.$shift->nombre : '' }}</div>
                    <div class="cell"><strong>Línea Proceso</strong> {{ $lineName }}</div>
                    <div class="cell"><strong>Kilos</strong> {{ number_format($kilos, 0, ',', '.') }}</div>
                </div>
                <div class="cell" style="margin-top:6px;">
                    <strong>Pedidos</strong> {{ $pedidosLabel }}
                </div>
                <div class="cell" style="margin-top:6px;">
                    <strong>CAMARA</strong> {{ $lineName }}
                </div>
            </div>

            <table>
                <thead>
                <tr>
                    <th class="nowrap" style="width:85px;">Hr Inicio Proceso</th>
                    <th class="nowrap" style="width:70px;">N° Proceso</th>
                    <th class="nowrap" style="width:70px;">Lote</th>
                    <th style="width:90px;">Tipo Proceso</th>
                    <th style="width:120px;">Variedad Original</th>
                    <th style="width:170px;">Productor Real</th>
                    <th class="nowrap" style="width:70px;">CSG</th>
                    <th style="width:85px;">Categoria</th>
                    <th class="nowrap" style="width:95px;">Fecha Recepción</th>
                    <th class="right nowrap" style="width:60px;">Cantidad</th>
                    <th class="right nowrap" style="width:65px;">Kilos</th>
                    <th class="nowrap" style="width:75px;">SDP</th>
                    <th style="width:120px;">Envase</th>
                    <th class="nowrap" style="width:70px;">Nota Calidad</th>
                    <th class="nowrap" style="width:60px;">% Expor</th>
                    <th style="width:130px;">Exportadora/Cliente</th>
                    <th style="width:120px;">Variedad Rotulada</th>
                    <th style="width:18px;"></th>
                    <th class="nowrap" style="width:105px;">Hrs Estimadas de proceso</th>
                </tr>
                </thead>
                <tbody>
                @foreach($lots as $lot)
                    @php
                        $start = $lot->instruction_inicio ?? null;
                        $end = $lot->instruction_fin ?? null;
                        $duration = ($start && $end) ? $start->diffInMinutes($end) : null;
                        $durTxt = $duration !== null ? sprintf('%d:%02d', intdiv($duration, 60), $duration % 60) : '-';
                    @endphp
                    <tr>
                        <td class="nowrap">{{ $start ? $start->format('H:i:s') : '-' }}</td>
                        <td class="nowrap">{{ $lot->process_id }}</td>
                        <td class="nowrap">
                            <strong>{{ $lot->n_g_recepcion }}</strong>
                            @if(($lot->split_index ?? 1) > 1)
                                <div class="muted">Parte {{ $lot->split_index }}</div>
                            @endif
                        </td>
                        <td class="wrap-any">{{ $lot->tipo_proceso ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->variedad_original ?? ($lot->n_variedad ?? '-') }}</td>
                        <td class="wrap-any">{{ $lot->productor_real ?? ($lot->n_productor ?? '-') }}</td>
                        <td class="nowrap">{{ $lot->csg_productor ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->categoria_origen ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->fecha_recepcion ? \Carbon\Carbon::parse($lot->fecha_recepcion)->format('d-m-Y') : '-' }}</td>
                        <td class="right nowrap">{{ number_format((int) $lot->cantidad_bins, 0, ',', '.') }}</td>
                        <td class="right nowrap">{{ $lot->peso_neto !== null ? number_format((float) $lot->peso_neto, 0, ',', '.') : '-' }}</td>
                        <td class="nowrap">{{ $lot->sdp_centrocosto ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->envase_origen ?? '-' }}</td>
                        <td class="nowrap">{{ $lot->setup_nota_calidad ?? '-' }}</td>
                        <td class="nowrap">-</td>
                        <td class="wrap-any">{{ $lot->exportadora ?? $exportadoraLabel ?? '-' }}</td>
                        <td class="wrap-any">{{ $lot->n_variedad ?? '-' }}</td>
                        <td></td>
                        <td class="nowrap">{{ $durTxt }}</td>
                    </tr>
                @endforeach

                {{-- Totales (similar a XLSX) --}}
                <tr>
                    <td colspan="9"></td>
                    <td class="right nowrap">0</td>
                    <td class="right nowrap">{{ number_format((float) $kilos, 0, ',', '.') }}</td>
                    <td colspan="8"></td>
                </tr>
                </tbody>
            </table>

            @if(!empty($packagingSummary))
                <div class="section-title">Destino + Embalajes</div>
                <table>
                    <thead>
                    <tr>
                        <th style="width:80px;">Destino</th>
                        <th style="width:105px;">Código Embalaje</th>
                        <th style="width:260px;">Descripcion de Embalaje</th>
                        <th style="width:90px;">Etiqueta</th>
                        <th class="right nowrap" style="width:85px;">Peso Estandar</th>
                        <th class="right nowrap" style="width:95px;">Envases/Pallet</th>
                        <th style="width:70px;">Altura</th>
                        <th style="width:220px;">Calibres</th>
                        <th style="width:70px;">Nota</th>
                        <th style="width:300px;">Observaciones</th>
                        <th style="width:90px;">count</th>
                        <th style="width:90px;">Pedido</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($packagingSummary as $row)
                        @php
                            $rule = $row['rule'] ?? null;
                            $desc = $rule?->desc_embalaje ?? ($row['n_item'] ?? '');
                            $peso = $rule?->peso_caja ?? null;
                            $allowed = is_array($rule?->allowed_calibres) ? $rule->allowed_calibres : [];

                            // Resumen tipo "36 AL 56" cuando hay calibres numéricos.
                            $calibresResumen = '-';
                            $numeric = [];
                            foreach ($allowed as $a) {
                                $a = trim((string) $a);
                                if ($a === '') continue;
                                if (is_numeric($a)) $numeric[] = (float) $a;
                            }
                            if (!empty($numeric)) {
                                $calibresResumen = ((int) min($numeric)).' AL '.((int) max($numeric));
                            } elseif (!empty($allowed)) {
                                $calibresResumen = implode(', ', array_map('strval', $allowed));
                            }

                            $nota = $rule?->nota ?? null;
                            $obs = trim((string) ($rule?->calibres_note ?? ''));
                            $sobre = trim((string) ($rule?->sobre_calibre_note ?? ''));
                            if ($sobre !== '') {
                                $obs = ($obs !== '' ? ($obs.' · ') : '').$sobre;
                            }
                        @endphp
                        <tr>
                            <td class="nowrap">{{ $row['destino'] ?? '-' }}</td>
                            <td class="nowrap"><strong>{{ $row['c_item'] ?? '-' }}</strong></td>
                            <td class="wrap-any">{{ $desc ?: '-' }}</td>
                            <td class="wrap-any">-</td>
                            <td class="right nowrap">{{ $peso !== null ? number_format((float) $peso, 1, ',', '.') : '-' }}</td>
                            <td class="right nowrap">{{ $row['cp2'] ?? '-' }}</td>
                            <td class="nowrap">{{ $row['altura'] ?? '-' }}</td>
                            <td class="wrap-any">{{ $calibresResumen }}</td>
                            <td class="nowrap">{{ $nota ?: '-' }}</td>
                            <td class="wrap-any">{{ $obs !== '' ? $obs : '-' }}</td>
                            <td class="wrap-any">-</td>
                            <td class="wrap-any">-</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif

            <div class="comments"><strong>Comentarios:</strong> Camara {{ $lineName }}/</div>
        </div>
    @endforeach
</div>
</body>
</html>
