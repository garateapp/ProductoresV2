<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Detalle extends Model
{
    protected $fillable = [
        'calidad_id',
        'parametro_id',
        'valor_id',
        'cantidad',
        'exportable',
        'temperatura',
        'valor_ss',
        'tipo_item',
        'detalle_item',
        'estado',
        'fecha',
        'categoria',
        'tipo_detalle',
        'porcentaje_muestra',
    ];

    public function calidad(): BelongsTo
    {
        return $this->belongsTo(Calidad::class);
    }

    public function parametro(): BelongsTo
    {
        return $this->belongsTo(Parametro::class, 'parametro_id');
    }

    public function valor(): BelongsTo
    {
        return $this->belongsTo(Valor::class, 'valor_id');
    }
}
