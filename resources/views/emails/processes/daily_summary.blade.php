<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Resumen de procesos enviados</title>
</head>
<body>
    <p>
        Resumen de procesos enviados hoy ({{ $since->format('Y-m-d H:i') }}
        a {{ $now->format('Y-m-d H:i') }}).
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th>Numero proceso</th>
                <th>Fecha</th>
                <th>CSG</th>
                <th>Productor</th>
                <th>Tipo</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['n_proceso'] ?? 'N/A' }}</td>
                    <td>{{ $row['fecha'] ?? 'N/A' }}</td>
                    <td>{{ $row['csg'] ?? 'N/A' }}</td>
                    <td>{{ $row['producer'] ?? 'N/A' }}</td>
                    <td>{{ $row['type'] ?? 'N/A' }}</td>
                    <td>{{ $row['estado'] ?? 'N/A' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
