<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingSaldo extends Model
{
    protected $table = 'pre_cooling_saldos';

    protected $fillable = [
        'camara_id',
        'load_folio_id',
        'banda',
        'fila',
        'columna',
        'altura',
        'nivel',
        'folio',
        'tipo_proceso_id',
        'cajas',
        'pallets',
        'especie',
        'variedad',
        'productor',
        'usuario_id',
    ];

    protected $casts = [
        'cajas' => 'integer',
        'pallets' => 'integer',
    ];

    public function camara(): BelongsTo
    {
        return $this->belongsTo(PreCoolingCamara::class, 'camara_id');
    }

    public function loadFolio(): BelongsTo
    {
        return $this->belongsTo(PreCoolingLoadFolio::class, 'load_folio_id');
    }

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTipoProceso::class, 'tipo_proceso_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
