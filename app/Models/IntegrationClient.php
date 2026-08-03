<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationClient extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo', 'nombre', 'rut', 'email', 'contacto', 'descripcion',
        'activo', 'metadata', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(IntegrationProfile::class, 'client_id');
    }

    public function mappingSets(): HasMany
    {
        return $this->hasMany(IntegrationMappingSet::class, 'client_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
