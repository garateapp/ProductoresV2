<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryManualConsumption extends Model
{
    public const TIPO_REEMBALAJE = 'reembalaje';
    public const TIPO_REPROCESO = 'reproceso';
    public const TIPO_COMPLETAR_SALDOS = 'completar_saldos';

    protected $table = 'inventory_manual_consumptions';

    protected $fillable = [
        'tipo_accion',
        'material_id',
        'id_g_produccion',
        'semielaborado_material_id',
        'cantidad',
        'fecha',
        'location_id',
        'movement_id',
        'folio_nuevo',
        'folios',
        'estado',
        'detalle_estado',
        'observacion',
        'created_by',
    ];

    protected $casts = [
        'cantidad' => 'decimal:4',
        'fecha' => 'date',
        'id_g_produccion' => 'integer',
        'semielaborado_material_id' => 'integer',
        'folios' => 'array',
    ];

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function semielaboradoMaterial(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'semielaborado_material_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(InventoryManualConsumptionDetail::class, 'manual_consumption_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function accionLabel(string $tipo): string
    {
        return match ($tipo) {
            self::TIPO_REEMBALAJE => 'Reembalaje',
            self::TIPO_REPROCESO => 'Reproceso',
            self::TIPO_COMPLETAR_SALDOS => 'Completar saldos',
            default => $tipo,
        };
    }
}
