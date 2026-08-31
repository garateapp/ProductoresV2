<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduccionGarcesSyncState extends Model
{
    protected $table = 'produccion_garces_sync_states';

    protected $fillable = [
        'last_fecha_proceso',
        'last_numero_proceso',
        'records_sent',
        'records_failed',
        'status',
        'last_error',
        'last_run_at',
    ];

    protected $casts = [
        'last_fecha_proceso' => 'datetime',
        'last_run_at' => 'datetime',
        'records_sent' => 'integer',
        'records_failed' => 'integer',
    ];
}
