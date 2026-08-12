@php
    $producerName = $producer->name ?: 'Productor';
@endphp

<p>Hola {{ $producerName }},</p>

<p>
    Ya esta disponible el informe de recepcion {{ $recepcion->numero_g_recepcion }}.
    @if (!empty($recepcion->n_especie))
        Especie: {{ $recepcion->n_especie }}.
    @endif
    @if (!empty($recepcion->n_variedad))
        Variedad: {{ $recepcion->n_variedad }}.
    @endif
    @if (!empty($formattedDate))
        Fecha de recepcion: {{ $formattedDate }}.
    @endif
</p>

@if (!empty($reportUrl))
    <p>Puedes acceder al documento desde el siguiente enlace: <a href="{{ $reportUrl }}">{{ $reportUrl }}</a></p>
@else
    <p>Adjuntamos el documento en este correo para tu referencia.</p>
@endif

<p>Saludos cordiales,<br>Equipo Gárate Hermanos</p>
