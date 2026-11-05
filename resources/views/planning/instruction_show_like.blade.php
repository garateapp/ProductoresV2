<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Instructivo</title>
    <style>
        :root { --ink:#111827; --muted:#6b7280; --border:#e5e7eb; --bg:#ffffff; --white:#ffffff; --slate:#f8fafc; --green:#166534; }
        * { box-sizing: border-box; }
        html, body { margin:0; padding:0; background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
        .page { padding: 12px; }
        .head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:10px; }
        .head-title { font-size:16px; font-weight: 900; margin:0; }
        .head-sub { margin-top:4px; font-size: 13px; color: var(--muted); }
        .status { margin-top:8px; display:inline-flex; border:1px solid var(--border); border-radius:999px; padding:4px 10px; font-size:11px; font-weight:700; background:#f8fafc; color:#334155; }

        .card { border:1px solid var(--border); border-radius:10px; background: var(--white); margin-top:12px; overflow:hidden; }
        .card-head { padding:10px 14px; border-bottom:1px solid var(--border); background: #f8fafc; }
        .card-title { font-size:12px; font-weight:800; margin:0; color: var(--ink); }
        .card-sub { margin-top:4px; font-size:10px; color: var(--muted); }
        .card-body { padding:10px 14px 14px 14px; }

        .instructionDoc h1 { font-size:16px; margin:0 0 8px 0; font-weight:900; color: var(--ink); }
        .instructionDoc h2 { font-size:13px; margin:16px 0 6px 0; font-weight:900; color: var(--ink); }
        .table-wrap { overflow:auto; border:1px solid var(--border); border-radius:8px; padding:8px; background:#fff; }
        table { border-collapse:collapse; width:max-content; min-width:100%; }
        th, td { border:1px solid var(--border); padding:5px 8px; vertical-align:top; white-space:pre-wrap; word-break:break-word; overflow-wrap:anywhere; font-size:10.5px; }
        th { position:sticky; top:0; background:#e5e7eb; font-weight:800; color: var(--ink); }
        tr:nth-child(even) td { background:#fafafa; }
        .meta-grid { display:grid; grid-template-columns:repeat(6,minmax(0,1fr)); gap:8px; margin:10px 0; }
        .meta-box { border:1px solid var(--border); border-radius:8px; padding:8px 10px; background:#fff; }
        .meta-label { font-size:10px; color:var(--muted); margin-bottom:2px; font-weight:700; }
        .meta-value { font-weight:900; color:var(--ink); font-size:11px; }
        .small { font-size:10px; color:#4b5563; margin-top:2px; }
        .comments-title { font-weight:700; }
        .comments-line { margin-top:4px; font-size:10px; color:#374151; }
        .font-bold { font-weight:700; }

        .status-borrador { background:#f1f5f9; color:#1e293b; border-color:#e2e8f0; }
        .status-conflicto { background:#fef2f2; color:#991b1b; border-color:#fecaca; }
        .status-confirmado { background:#f0fdf4; color:#166534; border-color:#bbf7d0; }
        .status-en-proceso { background:#eff6ff; color:#1e40af; border-color:#bfdbfe; }
        .status-cerrado { background:#e2e8f0; color:#0f172a; border-color:#cbd5e1; }
        .status-default { background:#f8fafc; color:#334155; border-color:#e2e8f0; }

        @media (max-width: 900px) { .meta-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @page { margin: 8mm; size: A4 landscape; }
        @media print {
            html, body { background:#fff; }
            .page { padding:0; }
            .head { margin-bottom: 6px; }
            .head-title { font-size: 12px; }
            .head-sub { font-size: 10px; }
            .status { margin-top: 4px; padding: 2px 8px; font-size: 9px; }
            .card { overflow:visible; margin-top:8px; }
            .card-head { padding:8px 10px; background:#e5e7eb; }
            .card-title { font-size: 11px; }
            .card-body { padding:8px 10px 10px 10px; }
            .table-wrap { overflow:visible !important; padding:4px; border:none; }
            .instructionDoc h1 { font-size: 13px; margin:0 0 6px 0; }
            .instructionDoc h2 { font-size: 11.5px; margin:12px 0 5px 0; }
            table { width:100% !important; min-width:0 !important; table-layout:fixed; }
            th { background:#2f631e !important; color:#fff !important; position:static !important; }
            th, td { font-size:9px; line-height:1.15; padding:3px 5px; white-space:normal; word-break:break-word; overflow-wrap:anywhere; }
            tr:nth-child(even) td { background:#fff; }
            .meta-box { font-size:9px; }
            .meta-label { font-size:8.5px; }
            .meta-value { font-size:9.5px; }
        }
    </style>
</head>
<body>
@php
    $fmtDate = function ($value): string {
        if (! is_string($value) || trim($value) === '') return '-';
        try {
            return \Carbon\Carbon::parse($value)->format('d-m-Y');
        } catch (\Throwable) {
            return '-';
        }
    };

    $fmtTime = function ($value): string {
        if (! is_string($value) || trim($value) === '') return '';
        try {
            return \Carbon\Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return '';
        }
    };

    $fmtPercent = function ($value): string {
        if ($value === null || $value === '') return '';
        $n = (float) $value;
        $text = number_format($n, 2, ',', '.');
        $text = rtrim(rtrim($text, '0'), ',');
        return $text.'%';
    };

    $fmtPesoCaja = function ($value): string {
        if ($value === null || $value === '') return '';
        $n = (float) $value;
        $text = number_format($n, 1, ',', '.');
        return rtrim(rtrim($text, '0'), ',');
    };

    $defectSummaryText = function ($rows): string {
        if (! is_array($rows) || count($rows) === 0) return '';
        $parts = [];
        foreach ($rows as $d) {
            if (! is_array($d)) continue;
            $name = trim((string) ($d['detalle_item'] ?? '-'));
            $value = (float) ($d['porcentaje_muestra'] ?? 0);
            $txt = number_format($value, 2, ',', '.');
            $txt = rtrim(rtrim($txt, '0'), ',');
            $parts[] = ($name !== '' ? $name : '-').': '.$txt.'%';
        }
        return implode(', ', $parts);
    };

    $commentsByLots = function ($lots) use ($defectSummaryText): array {
        $lines = [];
        if (! is_array($lots)) return $lines;

        foreach ($lots as $lot) {
            if (! is_array($lot)) continue;
            $sourceType = strtolower(trim((string) ($lot['source_type'] ?? '')));
            $lote = $sourceType === 'reembalaje'
                ? trim((string) ($lot['source_key'] ?? ($lot['n_g_recepcion'] ?? '')))
                : trim((string) ($lot['n_g_recepcion'] ?? ''));
            if ($lote === '') continue;

            $cal = $defectSummaryText($lot['defectos_calidad'] ?? []);
            $con = $defectSummaryText($lot['defectos_condicion'] ?? []);
            if ($cal === '' && $con === '') continue;

            $text = $lote.': ';
            if ($cal !== '') $text .= 'Calidad ['.$cal.']';
            if ($cal !== '' && $con !== '') $text .= ' · ';
            if ($con !== '') $text .= 'Condición ['.$con.']';
            $lines[] = $text;
        }

        return $lines;
    };

    $sheetList = is_array($lineSheets ?? null) ? $lineSheets : [];
    $status = (string) ($process['estado'] ?? '-');
    $statusToneClass = match (strtoupper(trim($status))) {
        'BORRADOR' => 'status-borrador',
        'CONFLICTO' => 'status-conflicto',
        'CONFIRMADO' => 'status-confirmado',
        'EN_PROCESO' => 'status-en-proceso',
        'CERRADO' => 'status-cerrado',
        default => 'status-default',
    };
    $shiftLabel = '-';
    if (is_array($shift ?? null)) {
        $shiftLabel = trim((string) ($shift['codigo'] ?? '').((isset($shift['nombre']) && trim((string) $shift['nombre']) !== '') ? (' · '.trim((string) $shift['nombre'])) : ''));
        if ($shiftLabel === '') $shiftLabel = '-';
    }
@endphp

<div class="page">
    <div class="head">
        <div>
            <h1 class="head-title">Instructivo</h1>

            <div class="head-sub">{{ $fmtDate((string) ($process['fecha'] ?? '')) }} · {{ $shiftLabel }}</div>
            <div class="status {{ $statusToneClass }}">{{ $status !== '' ? $status : '-' }}</div>
        </div>
    </div>

    @foreach($sheetList as $s)
        @php
            $lineId = (int) ($s['lineId'] ?? 0);
            $lineName = (string) ($s['lineName'] ?? 'Línea');
            $speciesLabel = (string) ($s['speciesLabel'] ?? '');
            $lots = is_array($s['lots'] ?? null) ? $s['lots'] : [];
            $packRows = is_array($s['packagingSummary'] ?? null) ? $s['packagingSummary'] : [];
            $meta = (is_array($metaByLineId ?? null) && $lineId > 0 && isset($metaByLineId[$lineId]) && is_array($metaByLineId[$lineId])) ? $metaByLineId[$lineId] : null;
            $version = $meta && isset($meta['version']) ? (int) $meta['version'] : null;
            $sumBins = collect($lots)->sum(fn ($r) => (int) ($r['cantidad_bins'] ?? 0));
            $sumTotes = collect($lots)->sum(fn ($r) => (int) ($r['cantidad_totes'] ?? 0));
            $sumKgs = collect($lots)->sum(fn ($r) => (float) ($r['peso_neto'] ?? 0));
            $promedioEnvase = $sumTotes > 0 ? $sumKgs / $sumTotes : null;
            $comments = $commentsByLots($lots);
        @endphp

        <div class="card">
            <div class="card-head">
                <h2 class="card-title">{{ $lineName }}</h2>
                <div class="card-sub">
                    {{ $speciesLabel !== '' ? 'Especie: '.$speciesLabel : '' }}
                    @if($version) · Versión {{ $version }} @endif
                </div>
                @if($meta && !empty($meta['reason']))
                    <div class="card-sub"><strong>Motivo:</strong> {{ (string) $meta['reason'] }}</div>
                @endif
            </div>
            <div class="card-body">
                <div class="instructionDoc">
                    <h1>INSTRUCTIVO DE EMBALAJE</h1>
                    <div class="meta-grid">
                        <div class="meta-box">
                            <div class="meta-label">Especie</div>
                            <div class="meta-value">{{ $speciesLabel !== '' ? $speciesLabel : 'VARIAS' }}</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Fecha Proceso</div>
                            <div class="meta-value">{{ $fmtDate((string) ($process['fecha'] ?? '')) }}</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Versión</div>
                            <div class="meta-value">{{ $version ?: '-' }}</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Turno</div>
                            <div class="meta-value">
                                @if(is_array($shift ?? null))
                                    {{ trim((string) ($shift['nombre'] ?? '')) !== '' ? (' · '.trim((string) $shift['nombre'])) : '' }}
                                @else
                                    -
                                @endif
                            </div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Línea Proceso</div>
                            <div class="meta-value">{{ $lineName }}</div>
                        </div>
                        <div class="meta-box">
                            <div class="meta-label">Kilos</div>
                            <div class="meta-value">{{ number_format((float) ($s['kilos'] ?? 0), 0, ',', '.') }}</div>
                        </div>
                        <div class="meta-box" style="grid-column: span 6;">
                            <div class="meta-label">Pedidos</div>
                            <div class="meta-value">{{ (string) (($s['pedidosLabel'] ?? '') !== '' ? $s['pedidosLabel'] : '-') }}</div>
                        </div>
                    </div>
                    <h2>Procesos / lotes</h2>
                    <div class="table-wrap">
                        @php
                            $hasMexico = collect($lots)->first(function ($r) {
                                return strtoupper(trim((string) ($r['destino'] ?? ''))) === 'MEXICO';
                            }) !== null;
                        @endphp
                        <table class="process-lots-table" style="table-layout:fixed; width:100%">
                            <thead>
                            <tr>
                                <th style="width:4%">Hr Inicio Proceso</th>
                                <th style="width:4%">Hr Término Proceso</th>
                                <th style="width:5%">N° Proceso</th>
                                <th style="width:6%">Lote</th>
                                <th style="width:4%">Tipo Proceso</th>
                                <th style="width:3%">Variedad Original</th>
                                <th style="width:6%">Productor Real</th>
                                <th style="width:4%">CSG</th>
                                <th style="width:4.5%">Categoria</th>
                                @if($hasMexico) <th style="width:3.5%">Huerto</th> @endif
                                <th style="width:4%">Pulpa</th>
                                <th style="width:6%">Fecha Recepción</th>
                                <th style="width:4%">% Exp</th>
                                <th style="width:3%">Cantidad (bins)</th>
                                <th style="width:3%">Totes</th>
                                <th style="width:4%">Kilos</th>
                                <th style="width:7%">Peso prom. env.</th>
                                <th style="width:4%">SDP</th>
                                <th style="width:4%">Nota Calidad</th>
                                <th style="width:7%">Exportadora/Cliente</th>
                                <th style="width:3%">Variedad<br>Rotulada</th>
                                <th style="width:8%">Hrs Estimadas</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($lots as $r)
                                @php
                                    $sourceType = strtolower(trim((string) ($r['source_type'] ?? '')));
                                    $isRepack = $sourceType === 'reembalaje';
                                    $lote = $isRepack
                                        ? trim((string) (($r['source_key'] ?? '') !== '' ? $r['source_key'] : ($r['n_g_recepcion'] ?? '')))
                                        : trim((string) ($r['n_g_recepcion'] ?? ''));
                                @endphp
                                <tr>
                                    <td>{{ $fmtTime((string) ($r['inicio'] ?? '')) }}</td>
                                    <td>{{ $fmtTime((string) ($r['fin'] ?? '')) }}</td>
                                    <td>{{ (string) ($r['numproceso'] ?? '') }}</td>
                                    <td>
                                        @if($isRepack)
                                            <strong>Folio {{ $lote }}</strong>
                                            <div class="small">N° Proceso {{ (string) ($r['source_n_g_proceso'] ?? '-') }} · Lote {{ (string) (($r['source_lote'] ?? '') !== '' ? $r['source_lote'] : ($r['n_g_recepcion'] ?? '-')) }}</div>
                                        @else
                                            {{ $lote }}
                                        @endif
                                    </td>
                                    <td>{{ (string) (($r['tipo_proceso'] ?? '') !== '' ? $r['tipo_proceso'] : 'Normal') }}</td>
                                    <td>{{ (string) ($r['variedad_original'] ?? '') }}</td>
                                    <td>{{ (string) (($r['productor_real'] ?? '') !== '' ? $r['productor_real'] : ($r['n_productor'] ?? '')) }}</td>
                                    <td>{{ (string) ($r['csg_productor'] ?? '') }}</td>
                                    <td>{{ (string) (($r['categoria_origen'] ?? '') !== '' ? $r['categoria_origen'] : 'Cat 1') }}</td>
                                    @if($hasMexico)
                                        <td>{{ strtoupper(trim((string) ($r['destino'] ?? ''))) === 'MEXICO' ? (string) ($r['huerto'] ?? '') : '' }}</td>
                                    @endif
                                    <td>{{ (string) ($r['pulpa'] ?? '') }}</td>
                                    <td>
                                        @php
                                            $fechaRec = (string) ($r['fecha_recepcion'] ?? '');
                                            $fechaRec10 = strlen($fechaRec) >= 10 ? substr($fechaRec, 0, 10) : $fechaRec;
                                        @endphp
                                        {{ $fmtDate($fechaRec10) !== '-' ? $fmtDate($fechaRec10) : '' }}
                                    </td>
                                    <td>{{ $fmtPercent($r['porcentaje_exportacion'] ?? null) }}</td>
                                    <td>{{ !empty($r['cantidad_bins']) ? number_format((float) ($r['cantidad_bins'] ?? 0), 0, ',', '.') : '' }}</td>
                                    <td>{{ !empty($r['cantidad_totes']) ? number_format((float) ($r['cantidad_totes'] ?? 0), 0, ',', '.') : '' }}</td>
                                    <td>{{ !empty($r['peso_neto']) ? number_format(round((float) ($r['peso_neto'] ?? 0)), 0, ',', '.') : '' }}</td>
                                    <td>{{ !empty($r['peso_promedio_envase']) ? number_format((float) ($r['peso_promedio_envase'] ?? 0), 3, ',', '.') : '' }}</td>
                                    <td>{{ (string) ($r['sdp_centrocosto'] ?? '') }}</td>
                                    <td>{{ (string) ($r['nota_calidad'] ?? '') }}</td>
                                    <td>{{ (string) ($r['exportadora'] ?? '') }}</td>
                                    <td>{{ (string) ($r['n_variedad'] ?? '') }}</td>
                                    <td>{{ ($fmtTime((string) ($r['inicio'] ?? '')) && $fmtTime((string) ($r['fin'] ?? ''))) ? '' : '' }}</td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="{{ $hasMexico ? 13 : 12 }}"><strong>TOTAL</strong></td>
                                <td><strong>{{ $sumBins ? number_format((float) $sumBins, 0, ',', '.') : '' }}</strong></td>
                                <td><strong>{{ $sumTotes ? number_format((float) $sumTotes, 0, ',', '.') : '' }}</strong></td>
                                <td><strong>{{ $sumKgs ? number_format(round((float) $sumKgs), 0, ',', '.') : '' }}</strong></td>
                                <td><strong>{{ $promedioEnvase ? number_format((float) $promedioEnvase, 3, ',', '.') : '' }}</strong></td>
                                <td colspan="5"></td>
                            </tr>
                            </tbody>
                        </table>
                    </div>

                    <h2>Destino + Embalajes</h2>
                    <div class="table-wrap">
                        <table class="packaging-table">
                            <colgroup>
                                <col style="width:6%">
                                <col style="width:8%">
                                <col style="width:16%">
                                <col style="width:8%">
                                <col style="width:4%">
                                <col style="width:5%">
                                <col style="width:4%">
                                <col style="width:8%">
                                <col style="width:10%">
                                <col style="width:14%">
                                <col style="width:9%">
                                <col style="width:8%">
                            </colgroup>

                            <thead>
                            <tr style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Destino</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Código Embalaje</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Descripcion de Embalaje</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Etiqueta</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Peso Estandar</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Envases/Pallet</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Altura</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Calibres</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Indicaciones</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Observaciones</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">count</th>
                                <th style="position:static; top:0; background:#2f631e; font-weight:600;color:#fff;">Pedido</th>
                            </tr>
                            </thead>
                            <tbody>
                            @if(count($packRows) > 0)
                                @foreach($packRows as $r)
                                    <tr>
                                        <td>{{ (string) ($r['destino'] ?? '') }}</td>
                                        <td class="font-bold">{{ (string) ($r['c_item'] ?? '') }}</td>
                                        <td>{{ (string) ($r['desc_embalaje'] ?? '') }}</td>
                                        <td>{{ (string) ($r['etiqueta'] ?? '') }}</td>
                                        <td>{{ $fmtPesoCaja($r['peso_caja'] ?? null) }}</td>
                                        <td>{{ (string) ($r['cp2'] ?? '') }}</td>
                                        <td>{{ (string) ($r['altura'] ?? '') }}</td>
                                        <td>{{ (string) ($r['calibres'] ?? '') }}</td>
                                        <td>{{ (string) ($r['indications'] ?? '') }}</td>
                                        <td>{{ (string) ($r['observaciones'] ?? '') }}</td>
                                        <td>{{ (string) ($r['count'] ?? '') }}</td>
                                        <td>{{ (string) ($r['pedido'] ?? '') }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="12">Sin embalajes sugeridos (falta destino y/o embalaje en los lotes).</td>
                                </tr>
                            @endif
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:12px;">
                        <span class="comments-title">Comentarios:</span>
                        @if(count($comments) > 0)
                            @foreach($comments as $line)
                                <div class="comments-line">{{ $line }}</div>
                            @endforeach
                        @else
                            <span> Camara {{ $lineName }}/</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    @if(count($sheetList) === 0)
        <div class="card">
            <div class="card-body">No hay datos para generar el instructivo.</div>
        </div>
    @endif
</div>
</body>
</html>
