<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comunicado - {{ $serviceName }}</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2933; line-height: 1.6;">
    <p><img src="https://appgreenex.cl/img/logogreenex.png" alt="{{ $serviceName }}" style="display: block; margin: 0 auto; max-width: 100%; height: auto;"></p>
    <p><strong>Comunicado - {{ $serviceName }}</strong>
    <p>{!! nl2br(e($messageBody)) !!}</p>

    <p style="margin-top: 2rem;">Saludos cordiales,<br>Greenex</p>
</body>
</html>
