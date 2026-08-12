<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cuadratura rechazada</title>
</head>
<body>
    @php
        $toAbsoluteUrl = function ($value) {
            $url = trim((string) $value);
            if ($url === '') {
                return null;
            }
            if (preg_match('/^https?:\/\//i', $url) === 1) {
                return $url;
            }

            return rtrim((string) config('app.url'), '/') . '/' . ltrim($url, '/');
        };

        $safeReviewUrl = $toAbsoluteUrl($reviewUrl ?? null);
        $safeReportUrl = $toAbsoluteUrl($reportUrl ?? null);
    @endphp

    <p>Hola,</p>

    <p>
        El Jefe de Planta <strong>{{ $chiefName }}</strong> rechazó la cuadratura del proceso
        <strong>{{ $proceso->n_proceso }}</strong>.
    </p>

    <p>
        <strong>Motivo del rechazo:</strong><br>
        {{ $comment }}
    </p>

    @if ($safeReviewUrl)
        <p>
            <a href="{{ $safeReviewUrl }}">Revisar proceso en módulo de cuadratura</a>
        </p>
    @endif

    @if ($safeReportUrl)
        <p>
            <a href="{{ $safeReportUrl }}">Abrir informe del proceso</a>
        </p>
    @endif

    <p>Saludos,<br>Portal Gárate Hermanos</p>
</body>
</html>
