<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryAutoConsumptionFolio extends Model
{
    protected $table = 'inventory_auto_consumption_folios';

    protected $fillable = [
        'id_g_produccion',
        'folio',
        'es_temporal',
        'numero_g_produccion',
        'c_embalaje',
        'n_embalaje',
        'cantidad',
        'peso_neto',
        'n_linea_proceso',
        'n_turno',
        'n_calibre',
        'n_especie',
        'n_variedad',
        'fecha_produccion',
        'packaging_id',
        'production_id',
        'movement_id',
        'origin_location_id',
        'estado',
        'detalle_estado',
        'raw_payload',
        'processed_at',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'peso_neto' => 'decimal:4',
        'es_temporal' => 'boolean',
        'fecha_produccion' => 'date',
        'raw_payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(InventoryPackaging::class, 'packaging_id');
    }

    public function production(): BelongsTo
    {
        return $this->belongsTo(InventoryProduction::class, 'production_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function originLocation(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'origin_location_id');
    }
}