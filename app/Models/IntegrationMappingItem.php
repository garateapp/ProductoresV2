<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationMappingItem extends Model
{
    protected $fillable = [
        'mapping_set_version_id', 'valor_salida',
        'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'prioridad',
        'activo', 'observacion', 'origen', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_inicio_vigencia' => 'date',
            'fecha_fin_vigencia' => 'date',
        ];
    }

    public function mappingSetVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingSetVersion::class, 'mapping_set_version_id');
    }

    public function inputs(): HasMany
    {
        return $this->hasMany(IntegrationMappingItemInput::class, 'mapping_item_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
