<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recepciones con informe tardio</title>
</head>
<body>
    <p>
        Resumen de recepciones con informe enviado con retraso mayor a
        {{ $thresholdHours }} horas. Se consideran recepciones recibidas en las
        ultimas {{ $lookbackHours }} horas ({{ $since->format('Y-m-d H:i') }}
        a {{ $now->format('Y-m-d H:i') }}).
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th>Numero recepcion</th>
                <th>Recepcion ID</th>
                <th>Productor</th>
                <th>Fecha recepcion</th>
                <th>Fecha envio</th>
                <th>Retraso (horas)</th>
                <th>Tipo</th>
                <th>Destinatario</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['numero_g_recepcion'] ?? 'N/A' }}</td>
                    <td>{{ $row['recepcion_id'] ?? 'N/A' }}</td>
                    <td>{{ $row['producer'] ?? 'N/A' }}</td>
                    <td>{{ $row['received_at'] ?? 'N/A' }}</td>
                    <td>{{ $row['sent_at'] ?? 'N/A' }}</td>
                    <td>{{ $row['delay_hours'] ?? 'N/A' }}</td>
                    <td>{{ $row['notification_type'] ?? 'N/A' }}</td>
                    <td>{{ $row['recipient'] ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
