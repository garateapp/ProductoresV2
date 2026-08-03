<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Acta de Destrucción - {{ $act->folio }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 30px; }
        .info-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .info-table th, .info-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .footer { margin-top: 50px; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Acta de Destrucción de Merma</h1>
        <p>Folio: <strong>{{ $act->folio }}</strong></p>
    </div>

    <table class="info-table">
        <tr><th>Fecha</th><td>{{ $act->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><th>Material</th><td>{{ $record->material->codigo }} · {{ $record->material->nombre }}</td></tr>
        <tr><th>Cantidad</th><td>{{ number_format($record->quantity, 4, ',', '.') }}</td></tr>
        <tr><th>Ubicación</th><td>{{ $record->detectedLocation->nombre }}</td></tr>
        <tr><th>Motivo</th><td>{{ $record->reason->nombre }}</td></tr>
        <tr><th>Observaciones</th><td>{{ $act->observaciones }}</td></tr>
    </table>

    <div class="footer">
        <p>___________________________</p>
        <p>Firma de Responsable</p>
        <p>{{ $act->user->name }}</p>
    </div>
</body>
</html>
