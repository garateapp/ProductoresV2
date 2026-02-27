<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Cuadratura para aprobación</title>
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
    @endphp

    <p>Hola Jefe de Planta,</p>
    <p>
        {{ $senderName }} ha enviado {{ count($items) }} proceso(s) para aprobación de cuadratura.
    </p>

    <table cellpadding="6" cellspacing="0" border="1" style="border-collapse: collapse;">
        <thead>
            <tr>
                <th>N° Proceso</th>
                <th>Productor</th>
                <th>Especie</th>
                <th>Variedad</th>
                <th>Informe</th>
                <th>Revisión</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($items as $item)
                <tr>
                    <td>{{ $item['n_proceso'] ?? '-' }}</td>
                    <td>{{ $item['agricola'] ?? '-' }}</td>
                    <td>{{ $item['especie'] ?? '-' }}</td>
                    <td>{{ $item['variedad'] ?? '-' }}</td>
                    <td>
                        @php($reportUrl = $toAbsoluteUrl($item['report_url'] ?? null))
                        @if ($reportUrl)
                            <a href="{{ $reportUrl }}">Abrir informe</a>
                        @else
                            No disponible
                        @endif
                    </td>
                    <td>
                        @php($reviewUrl = $toAbsoluteUrl($item['review_url'] ?? null))
                        @if ($reviewUrl)
                            <a href="{{ $reviewUrl }}">Revisar / Aprobar</a>
                        @else
                            No disponible
                        @endif
                    </td>
                </tr>
                <tr>
                    <td colspan="6" style="text-align: center;">
                        @php($listadoUrl = $toAbsoluteUrl($item['ver_listado_para_aprobar'] ?? null))
                        @if ($listadoUrl)
                            <a href="{{ $listadoUrl }}">Ver listado completo para aprobación</a>
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p>Saludos,<br>Portal Greenex</p>
</body>
</html>
