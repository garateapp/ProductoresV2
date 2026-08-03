<?php

namespace App\Models;

use App\Enums\IntegrationMappingFallbackStrategy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationMappingSetVersion extends Model
{
    protected $fillable = [
        'mapping_set_id', 'version', 'estado', 'inmutable',
        'estrategia_fallback', 'valor_defecto', 'prioridad',
        'sensible_mayusculas', 'tratamiento_espacios', 'config_normalizacion',
        'fecha_inicio_vigencia', 'fecha_fin_vigencia', 'descripcion',
        'published_by', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inmutable' => 'boolean',
            'sensible_mayusculas' => 'boolean',
            'config_normalizacion' => 'array',
            'estrategia_fallback' => IntegrationMappingFallbackStrategy::class,
            'fecha_inicio_vigencia' => 'date',
            'fecha_fin_vigencia' => 'date',
            'published_at' => 'datetime',
        ];
    }

    public function mappingSet(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingSet::class, 'mapping_set_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(IntegrationMappingItem::class, 'mapping_set_version_id');
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublicado($query)
    {
        return $query->where('estado', 'publicado');
    }

    public function scopeVigente($query, string $date)
    {
        return $query->where(function ($q) use ($date) {
            $q->whereNull('fecha_inicio_vigencia')
                ->orWhere('fecha_inicio_vigencia', '<=', $date);
        })->where(function ($q) use ($date) {
            $q->whereNull('fecha_fin_vigencia')
                ->orWhere('fecha_fin_vigencia', '>=', $date);
        });
    }

    public function activeItems()
    {
        return $this->items()->where('activo', true);
    }
}
