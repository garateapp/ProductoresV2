<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationProfileVersion extends Model
{
    protected $fillable = [
        'profile_id', 'version', 'estado', 'inmutable', 'descripcion',
        'snapshot_config', 'published_by', 'published_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'inmutable' => 'boolean',
            'snapshot_config' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class, 'profile_id');
    }

    public function inputFields(): HasMany
    {
        return $this->hasMany(IntegrationInputField::class, 'profile_version_id');
    }

    public function outputFields(): HasMany
    {
        return $this->hasMany(IntegrationOutputField::class, 'profile_version_id');
    }

    public function rules(): HasMany
    {
        return $this->hasMany(IntegrationRule::class, 'profile_version_id')->orderBy('orden');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(IntegrationRun::class, 'profile_version_id');
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
}
