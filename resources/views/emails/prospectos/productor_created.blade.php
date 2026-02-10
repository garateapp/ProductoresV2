<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Nuevo prospecto de productor</title>
</head>
<body>
    <p>Se ha creado un nuevo prospecto de productor para revisi&oacute;n.</p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <tbody>
            <tr>
                <th align="left">Raz&oacute;n social</th>
                <td>{{ $prospecto->razon_social ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th align="left">RUT</th>
                <td>{{ $prospecto->rut ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th align="left">Email</th>
                <td>{{ $prospecto->email ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th align="left">GGN</th>
                <td>{{ $prospecto->ggn ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th align="left">Fecha</th>
                <td>{{ optional($prospecto->created_at)->format('Y-m-d H:i') }}</td>
            </tr>
        </tbody>
    </table>

    <p>
        Revisa el prospecto en el portal para validarlo y crear el productor.
    </p>
</body>
</html>
