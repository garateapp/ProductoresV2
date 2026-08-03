<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryProduction extends Model
{
    protected $fillable = [
        'fecha',
        'turno',
        'linea',
        'especie',
        'variedad',
        'packaging_id',
        'cantidad_cajas',
        'cantidad_pallets',
        'referencia_tipo',
        'referencia_id',
        'observacion',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'fecha' => 'date',
        'cantidad_cajas' => 'decimal:4',
        'cantidad_pallets' => 'decimal:4',
        'metadata' => 'array',
    ];

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(InventoryPackaging::class, 'packaging_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
