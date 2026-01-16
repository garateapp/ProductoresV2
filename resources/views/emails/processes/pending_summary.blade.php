<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Procesos sin informe</title>
</head>
<body>
    <p>
        Resumen de procesos sin informe por mas de {{ $thresholdHours }} horas.
        Corte: {{ $cutoff->format('Y-m-d H:i') }}. Generado: {{ $now->format('Y-m-d H:i') }}.
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th>Numero proceso</th>
                <th>Productor</th>
                <th>Lote recepcion</th>
                <th>Especie</th>
                <th>Variedad</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['n_proceso'] ?? 'N/A' }}</td>
                    <td>{{ $row['producer'] ?? 'N/A' }}</td>
                    <td>{{ $row['lote_recepcion'] ?? 'N/A' }}</td>
                    <td>{{ $row['especie'] ?? 'N/A' }}</td>
                    <td>{{ $row['variedad'] ?? 'N/A' }}</td>
                    <td>{{ $row['fecha'] ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
