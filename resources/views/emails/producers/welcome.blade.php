<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Bienvenido al Portal de Productores Greenex</title>
</head>
<body>
    <p style="text-align: center;">
        <img src="{{ asset('img/logogreenex.png') }}" alt="Greenex" style="height: 60px;">
    </p>

    <p>Hola {{ $producer->name ?? 'Productor' }},</p>

    <p>
        Te damos la bienvenida al Portal de Productores Greenex. Nos alegra contar contigo.
        Desde el portal podras revisar tus recepciones, procesos y documentos de manera
        rapida y segura.
    </p>

    <p><strong>Acceso al portal</strong></p>
    <p>URL: <a href="{{ $portalUrl }}">{{ $portalUrl }}</a></p>
    <p>Usuario: {{ $username }}</p>
    <p>Password: {{ $defaultPassword }}</p>

    <p>
        Si tienes dudas o necesitas apoyo, no dudes en contactarnos.
    </p>

    <p>Saludos cordiales,<br>Equipo Greenex</p>
</body>
</html>
