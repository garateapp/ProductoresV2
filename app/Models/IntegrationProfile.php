<?php

namespace App\Models;

use App\Enums\IntegrationProfileStatus;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class IntegrationProfile extends Model
{
    use Auditable;

    protected $fillable = [
        'client_id', 'codigo', 'nombre', 'descripcion', 'direccion',
        'estado', 'tipo_salida', 'source_adapter', 'exporter',
        'zona_horaria', 'source_adapter_config', 'error_config', 'idempotency_config', 'retencion_config',
        'activo', 'current_version_id', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => IntegrationProfileStatus::class,
            'activo' => 'boolean',
            'source_adapter_config' => 'array',
            'error_config' => 'array',
            'idempotency_config' => 'array',
            'retencion_config' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'client_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfileVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(IntegrationProfileVersion::class, 'profile_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(IntegrationRun::class, 'profile_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function publishedVersions(): HasMany
    {
        return $this->hasMany(IntegrationProfileVersion::class, 'profile_id')
            ->where('estado', 'publicado');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(IntegrationProfileVersion::class, 'profile_id')
            ->latestOfMany('version');
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeByEstado($query, IntegrationProfileStatus $status)
    {
        return $query->where('estado', $status);
    }
}
