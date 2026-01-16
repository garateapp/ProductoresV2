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

$delayCron = config('reports.reception_delay_cron');
$delayTime = config('reports.reception_delay_time');
$delaySchedule = Schedule::command('reports:reception-delay-summary')
    ->description('Envia resumen de recepciones con envio de informe tardio');

if ($delayCron) {
    $delaySchedule->cron($delayCron);
} elseif ($delayTime) {
    $delaySchedule->dailyAt($delayTime);
}

Schedule::command('tinker --execute="Artisan::call(\'reports:process-daily-summary\')"')
    ->dailyAt('12:00')
    ->description('Envia resumen de procesos enviados (12:00).');

Schedule::command('tinker --execute="Artisan::call(\'reports:process-daily-summary\')"')
    ->dailyAt('15:00')
    ->description('Envia resumen de procesos enviados (12:00).');

Schedule::command('reports:process-pending-summary')
    ->dailyAt('16:00')
    ->description('Envia resumen de procesos sin informe (>24h) a las 16:00.');

Schedule::command('tinker --execute="Artisan::call(\'reports:reception-daily-summary\')"')
    ->dailyAt('12:00')
    ->description('Envia resumen de recepciones enviadas (12:00).');

Schedule::command('tinker --execute="Artisan::call(\'reports:reception-daily-summary\')"')
    ->dailyAt('15:00')
    ->description('Envia resumen de recepciones enviadas (15:00).');
