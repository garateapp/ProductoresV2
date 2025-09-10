<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Note: Class-based console commands in app/Console/Commands are auto-discovered in this Laravel version.
// No explicit registration is needed here.
