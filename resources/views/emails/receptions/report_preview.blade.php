@php
    $lote = $recepcion->numero_g_recepcion ?? 'Sin número';
    $productor = $recepcion->n_emisor ?? 'Productor no especificado';
    $fecha = $recepcion->fecha_g_recepcion
        ? \Carbon\Carbon::parse($recepcion->fecha_g_recepcion)->format('d-m-Y')
        : 'Fecha no disponible';
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Previsualización informe de recepción {{ $lote }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #111827;">
    <h1 style="font-size: 20px; margin-bottom: 16px;">Previsualización informe de recepción</h1>
    <p style="margin-bottom: 12px;">
        Se ha generado una previsualización del informe de la recepción <strong>{{ $lote }}</strong>.
    </p>
    <p style="margin-bottom: 12px;">
        <strong>Productor:</strong> {{ $productor }}<br>
        <strong>Fecha de recepción:</strong> {{ $fecha }}<br>
        <strong>Especie:</strong> {{ $recepcion->n_especie ?? 'N/A' }}<br>
        <strong>Variedad:</strong> {{ $recepcion->n_variedad ?? 'N/A' }}
    </p>
    @if($previewUrl)
        <p style="margin-bottom: 12px;">
            Puedes revisar el informe en línea en el siguiente enlace:<br>
            <a href="{{ $previewUrl }}" style="color: #1f2937;">{{ $previewUrl }}</a>
        </p>
    @endif
    <p style="margin-bottom: 12px;">
        Se adjunta una copia en PDF para revisión. Una vez validado, será aprobado desde la plataforma para iniciar su distribución oficial.
    </p>
    <p style="margin-top: 24px;">
        Saludos,<br>
        Equipo Gárate Hermanos
    </p>
</body>
</html>

