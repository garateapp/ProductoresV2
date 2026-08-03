<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationMappingSet extends Model
{
    use Auditable;

    protected $fillable = [
        'client_id', 'codigo', 'nombre', 'descripcion', 'estado',
        'current_version_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'string',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'client_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingSetVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(IntegrationMappingSetVersion::class, 'mapping_set_id');
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
        return $query->where('estado', '!=', 'archivado');
    }
}
