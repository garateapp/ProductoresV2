<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Note: Class-based console commands in app/Console/Commands are auto-discovered in this Laravel version.
// No explicit registration is needed here.

Schedule::command(
    'tinker --execute="app()->call([App\\Http\\Controllers\\RecepcionController::class, \'recepction_sync\'])"'
)->hourly()->description('Sincroniza recepciones cada 60 minutos mediante RecepcionController@recepction_sync');

$delayTime = config('reports.reception_delay_time');
if ($delayTime) {
    Schedule::command('reports:reception-delay-summary')
        ->dailyAt($delayTime)
        ->description('Envia resumen de recepciones con envio de informe tardio');
}
