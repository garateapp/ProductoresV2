<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTechnicalSheet extends Model
{
    protected $fillable = [
        'packaging_id',
        'material_id',
        'es_semielaborado',
        'nombre',
        'version',
        'fecha_vigencia_desde',
        'fecha_vigencia_hasta',
        'activo',
        'observacion',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'fecha_vigencia_desde' => 'date',
        'fecha_vigencia_hasta' => 'date',
        'activo' => 'boolean',
        'es_semielaborado' => 'boolean',
        'metadata' => 'array',
    ];

    public function packaging(): BelongsTo
    {
        return $this->belongsTo(InventoryPackaging::class, 'packaging_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(InventoryMaterial::class, 'material_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function unitItems(): HasMany
    {
        return $this->hasMany(InventoryTechnicalSheetUnitItem::class, 'technical_sheet_id');
    }

    public function palletItems(): HasMany
    {
        return $this->hasMany(InventoryTechnicalSheetPalletItem::class, 'technical_sheet_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(InventoryTechnicalSheetImage::class, 'technical_sheet_id')->orderBy('orden');
    }
}
