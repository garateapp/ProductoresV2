<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationSourceAdapter extends Model
{
    use Auditable;

    protected $fillable = [
        'key', 'nombre', 'descripcion', 'tipo_conexion',
        'configuracion', 'esquema_entrada', 'activo',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'configuracion' => 'array',
            'esquema_entrada' => 'array',
            'activo' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(IntegrationProfile::class, 'source_adapter', 'key');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
