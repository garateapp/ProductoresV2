@php
    $producerName = $producer->name ?: 'Productor';
@endphp

<p>Hola {{ $producerName }},</p>

<p>
    Ya esta disponible el informe del proceso {{ $proceso->n_proceso }}.
    @if (!empty($proceso->especie))
        Especie: {{ $proceso->especie }}.
    @endif
    @if (!empty($proceso->variedad))
        Variedad: {{ $proceso->variedad }}.
    @endif
    @if (!empty($formattedDate))
        Fecha de proceso: {{ $formattedDate }}.
    @endif
</p>

@if ($reportUrl)
    <p>Puedes acceder al documento desde el siguiente enlace: <a href="{{ $reportUrl }}">{{ $reportUrl }}</a></p>
@else
    <p>No pudimos generar un enlace de descarga automatico. El documento va adjunto en este correo.</p>
@endif

<p>Saludos cordiales,<br>Equipo Gárate Hermanos</p>
