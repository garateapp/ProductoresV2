<?php

namespace App\Models;

use App\Enums\IntegrationRunRecordStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationRunRecord extends Model
{
    protected $fillable = [
        'run_id', 'reprocess_of_id', 'source_identifier', 'idempotency_key',
        'estado', 'input_original', 'input_normalizado', 'output_generado',
        'errores', 'advertencias', 'intentos', 'duracion_ms', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'estado' => IntegrationRunRecordStatus::class,
            'input_original' => 'array',
            'input_normalizado' => 'array',
            'output_generado' => 'array',
            'errores' => 'array',
            'advertencias' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(IntegrationRun::class, 'run_id');
    }

    public function reprocessOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reprocess_of_id');
    }

    public function reprocesses(): HasMany
    {
        return $this->hasMany(self::class, 'reprocess_of_id');
    }

    public function rulesTrace(): HasMany
    {
        return $this->hasMany(IntegrationRunRecordRule::class, 'run_record_id');
    }

    public function mappingsTrace(): HasMany
    {
        return $this->hasMany(IntegrationRunRecordMapping::class, 'run_record_id');
    }
}
