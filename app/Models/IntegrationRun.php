<?php

namespace App\Models;

use App\Enums\IntegrationRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationRun extends Model
{
    protected $fillable = [
        'profile_id', 'profile_version_id', 'estado', 'user_id',
        'started_at', 'finished_at', 'total_registros', 'procesados',
        'exitosos', 'pendientes', 'fallidos', 'archivo_generado',
        'batch_id', 'duracion_segundos', 'metricas', 'errores',
        'nota', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'estado' => IntegrationRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metricas' => 'array',
            'errores' => 'array',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class, 'profile_id');
    }

    public function profileVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfileVersion::class, 'profile_version_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function records(): HasMany
    {
        return $this->hasMany(IntegrationRunRecord::class, 'run_id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(IntegrationExport::class, 'run_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
