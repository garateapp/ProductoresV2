<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingLoadFolio extends Model
{
    protected $table = 'pre_cooling_load_folios';

    protected $fillable = [
        'load_id',
        'tipo_proceso_id',
        'folio',
        'banda',
        'posicion',
        'altura',
        'nivel',
        'exportadora',
        'productor',
        'especie',
        'variedad',
        'embalaje',
        'categoria',
        'calibre',
        'cajas',
        'pallets',
        'temperatura_inicial',
        'metadata',
    ];

    protected $casts = [
        'cajas' => 'integer',
        'pallets' => 'integer',
        'temperatura_inicial' => 'decimal:2',
        'metadata' => 'array',
    ];

    public function carga(): BelongsTo
    {
        return $this->belongsTo(PreCoolingLoad::class, 'load_id');
    }

    public function tipoProceso(): BelongsTo
    {
        return $this->belongsTo(PreCoolingTipoProceso::class, 'tipo_proceso_id');
    }
}
