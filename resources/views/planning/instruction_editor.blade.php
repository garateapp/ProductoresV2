<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Instructivo</title>
    <style>
        :root { --ink:#0f172a; --muted:#64748b; --border:#e2e8f0; --bg:#ffffff; --head:#f8fafc; --soft:#f1f5f9; --danger:#b91c1c; --ok:#065f46; }
        html, body { background: var(--bg); color: var(--ink); font-family: Arial, Helvetica, sans-serif; }
        .wrap { max-width: 1280px; margin: 14px auto; padding: 0 12px; }
        .top { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; }
        .brand { display:flex; align-items:center; gap:10px; }
        .logo { height: 34px; width:auto; object-fit: contain; }
        .title { font-size: 18px; font-weight: 900; margin:0; letter-spacing: .2px; }
        .meta { margin-top:6px; font-size: 12px; line-height: 1.35; color: var(--muted); }
        .meta strong { color: var(--ink); }
        .btns { display:flex; gap:8px; flex-wrap: wrap; justify-content:flex-end; }
        .btn { border:1px solid var(--border); padding:10px 12px; border-radius:10px; background: var(--head); cursor:pointer; font-weight:900; text-decoration:none; color:inherit; display:inline-block; }
        .btn.primary { background:#111827; color:#fff; border-color:#111827; }
        .btn.danger { background:#fef2f2; color:var(--danger); border-color:#fecaca; }
        .btn.danger:hover { background:#fee2e2; }

        .card { margin-top: 12px; border:1px solid var(--border); border-radius: 12px; overflow:hidden; box-shadow: 0 1px 0 rgba(15, 23, 42, 0.03); }
        .card-h { background: linear-gradient(90deg,#ffffff,var(--head)); padding:12px 12px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; gap:12px; }
        .card-h .sub { font-size: 12px; color: var(--muted); font-weight: 700; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:10px; padding:12px; }
        .field label { display:block; font-size: 12px; font-weight: 900; margin-bottom:6px; }
        .field textarea, .field input, .field select { width:100%; border:1px solid var(--border); border-radius:10px; padding:10px; font-size: 12px; }
        .hint { margin-top:6px; font-size: 11px; color: var(--muted); }
        .error { color: var(--danger); font-size: 12px; font-weight: 800; }
        .ok { color: var(--ok); font-size: 12px; font-weight: 800; }

        table { width:100%; border-collapse: collapse; }
        th, td { padding:8px 8px; border:1px solid var(--border); font-size: 11px; vertical-align: top; }
        th { text-align:left; color:#334155; background:#fbfdff; font-size: 10.5px; letter-spacing:.2px; position: sticky; top: 0; z-index: 2; }
        tbody tr:nth-child(odd) td { background: #ffffff; }
        tbody tr:nth-child(even) td { background: #fcfdff; }
        .right { text-align:right; white-space:nowrap; }
        .wrap-any { word-break: break-word; }
        .muted { color: var(--muted); }

        .sticky-actions { position: sticky; bottom: 0; z-index: 5; background: rgba(255,255,255,.92); backdrop-filter: blur(6px); border-top:1px solid var(--border); padding:10px 12px; display:flex; justify-content:space-between; gap:10px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:11px; background: var(--soft); border:1px solid var(--border); font-weight:900; }
        .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace; font-size: 11px; }

        tr.deleted td { opacity: 0.35; text-decoration: line-through; }
        tr.deleted input, tr.deleted textarea, tr.deleted select { pointer-events: none; }
        .del-btn { cursor:pointer; border:1px solid var(--border); background:#fef2f2; color:var(--danger); border-radius:6px; padding:3px 8px; font-size:11px; font-weight:800; }
        .del-btn:hover { background:#fee2e2; border-color:#fecaca; }
        .del-btn.active { background:var(--danger); color:#fff; border-color:var(--danger); }

        @media (max-width: 980px) {
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="top">
        <div>
            <div class="brand">
                <img class="logo" src="{{ asset('img/logogreenex.png') }}" alt="Greenex" />
                <div>
                    <h1 class="title">Editar instructivo · {{ $sheet['lineName'] ?? '-' }}</h1>
                    <div class="meta">
                        <div><strong>Fecha:</strong> {{ \Carbon\Carbon::parse($date)->format('d-m-Y') }}</div>
                    </div>
                </div>
            </div>
        </div>
        <div class="btns">
            <a class="btn" href="{{ route('planning.processes.instruction', ['process' => $process->id, 'line_id' => $lineId]) }}">Ver HTML</a>
            <a class="btn" href="{{ $downloadUrl }}">Descargar PDF</a>
        </div>
    </div>

    <div class="card">
        <div class="card-h">
            <div>
                <div style="font-weight:900;">Guardar cambios</div>
                <div class="sub">Edita Turno, Observaciones, Calibres, Pedido y embalajes para esta línea. Al guardar se crea una nueva versión del instructivo.</div>
            </div>
            <div class="sub">
                Proceso #{{ $process->id }}
                <span class="badge">Línea #{{ $lineId }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('planning.processes.instruction.update', ['process' => $process->id]) }}" id="editorForm">
            @csrf
            <input type="hidden" name="line_id" value="{{ $lineId }}">

            <div class="grid">
                <div class="field">
                    <label>Turno</label>
                    <select name="shift_id">
                        @foreach($shifts as $s)
                            <option value="{{ $s->id }}" {{ (int)$s->id === (int)$currentShiftId ? 'selected' : '' }}>
                                {{ $s->codigo }}{{ $s->nombre ? ' · '.$s->nombre : '' }} ({{ $s->horas }}h)
                            </option>
                        @endforeach
                    </select>
                    @error('shift_id') <div class="error">{{ $message }}</div> @enderror
                </div>
                <div class="field">
                    <label>Motivo del cambio (obligatorio)</label>
                    <textarea name="change_reason" rows="3" placeholder="Ej: Ajuste de calibres por indicación comercial">{{ old('change_reason') }}</textarea>
                    @error('change_reason') <div class="error">{{ $message }}</div> @enderror
                    <div class="hint">Este motivo quedará registrado en la versión del instructivo.</div>
                </div>
            </div>

            <div class="grid" style="padding-top:0;">
                <div class="field">
                    <label>Especie (filtro)</label>
                    <input type="text" name="species" value="{{ $sheet['speciesLabel'] ?? '' }}" placeholder="Todas" readonly>
                    <div class="hint">La especie se define automáticamente según el contexto del proceso.</div>
                </div>
                <div class="field">
                    @if(session('success')) <div class="ok">{{ session('success') }}</div> @endif
                    @if(session('error')) <div class="error">{{ session('error') }}</div> @endif
                </div>
            </div>

            @php
                $packagingSummary = $sheet['packagingSummary'] ?? [];
            @endphp
            <div style="padding:12px;">
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:8px;">
                    <div style="font-weight:900;">Destino + Embalajes (editables)</div>
                    <div class="muted" style="font-size:11px;">
                        <span id="rowCount">{{ count($packagingSummary) }}</span> fila(s) visibles
                    </div>
                </div>
                <table>
                    <thead>
                    <tr>
                        <th style="width:40px;">#</th>
                        <th style="width:80px;">Destino</th>
                        <th style="width:105px;">Código</th>
                        <th style="width:260px;">Descripción</th>
                        <th style="width:180px;">Calibres</th>
                        <th style="width:280px;">Observaciones</th>
                        <th style="width:200px;">Pedido</th>
                        <th style="width:60px;">Acción</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($packagingSummary as $i => $row)
                        @php
                            $key = (string) ($row['key'] ?? '');
                            $deletedKey = "rows.$i._deleted";
                            $isDeleted = old($deletedKey, '0');
                            $calValue = old("rows.$i.calibres", $row['calibres'] ?? '-');
                            $obsValue = old("rows.$i.observaciones", $row['observaciones'] ?? '');
                            $pedValue = old("rows.$i.pedido", $row['pedido'] ?? '');
                        @endphp
                        <tr data-row-index="{{ $i }}" class="{{ $isDeleted === '1' ? 'deleted' : '' }}">
                            <td class="right muted">{{ $i + 1 }}</td>
                            <td class="nowrap"><strong>{{ $row['destino'] ?? '-' }}</strong></td>
                            <td class="nowrap"><strong class="mono">{{ $row['c_item'] ?? '-' }}</strong></td>
                            <td class="wrap-any">{{ $row['desc_embalaje'] ?? '-' }}</td>
                            <td>
                                <input type="hidden" name="rows[{{ $i }}][key]" value="{{ $key }}">
                                <input type="hidden" name="rows[{{ $i }}][_deleted]" value="{{ $isDeleted }}" class="deleted-flag">
                                <input name="rows[{{ $i }}][calibres]" value="{{ $calValue }}" placeholder="Ej: 36 AL 56 o L, XL, 2J">
                                @error("rows.$i.calibres") <div class="error">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <textarea name="rows[{{ $i }}][observaciones]" rows="2" placeholder="Observaciones...">{{ $obsValue }}</textarea>
                                @error("rows.$i.observaciones") <div class="error">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <input name="rows[{{ $i }}][pedido]" value="{{ $pedValue }}" placeholder="Ej: Pedido 123 / Cliente X">
                                @error("rows.$i.pedido") <div class="error">{{ $message }}</div> @enderror
                            </td>
                            <td>
                                <button type="button" class="del-btn" data-row="{{ $i }}" onclick="toggleDelete(this, {{ $i }})" title="Eliminar esta fila">
                                    ✕
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="muted">No hay embalajes asignados todavía para esta línea.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div class="sticky-actions">
                <div class="muted" style="font-size:12px;">
                    Guardar crea una nueva versión para la línea.
                </div>
                <div class="btns" style="margin:0;">
                    <a class="btn" href="{{ route('planning.processes.instruction', ['process' => $process->id, 'line_id' => $lineId]) }}">Ver instructivo</a>
                    <button type="submit" class="btn primary">Guardar (nueva versión)</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDelete(btn, index) {
    const tr = btn.closest('tr');
    const flag = tr.querySelector('.deleted-flag');
    const isDeleted = flag.value === '1';

    if (isDeleted) {
        flag.value = '0';
        tr.classList.remove('deleted');
        btn.classList.remove('active');
        btn.title = 'Eliminar esta fila';
    } else {
        flag.value = '1';
        tr.classList.add('deleted');
        btn.classList.add('active');
        btn.title = 'Restaurar esta fila';
    }
    updateRowCount();
}

function updateRowCount() {
    const rows = document.querySelectorAll('tbody tr[data-row-index]');
    let count = 0;
    rows.forEach(function(row) {
        if (!row.classList.contains('deleted')) count++;
    });
    const el = document.getElementById('rowCount');
    if (el) el.textContent = count;
}
</script>
</body>
</html>
