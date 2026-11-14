<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GarcesProduccionEnvio extends Model
{
    protected $table = 'garces_produccion_envios';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'folio',
        'numero_proceso',
        'fecha_proceso',
        'records',
        'cajas',
        'kilos',
        'status',
        'attempts',
        'last_error',
        'user_id',
        'sent_at',
        'json_enviado',
        'respuesta_api',
    ];

    protected $casts = [
        'fecha_proceso' => 'datetime',
        'sent_at' => 'datetime',
        'records' => 'integer',
        'attempts' => 'integer',
        'cajas' => 'float',
        'kilos' => 'float',
        'json_enviado' => 'array',
        'respuesta_api' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
