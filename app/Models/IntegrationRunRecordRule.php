<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationRunRecordRule extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'run_record_id', 'rule_id', 'rule_name', 'rule_type',
        'estado', 'duracion_ms', 'input_values', 'output_values', 'error',
    ];

    protected function casts(): array
    {
        return [
            'input_values' => 'array',
            'output_values' => 'array',
            'error' => 'array',
        ];
    }

    public function runRecord(): BelongsTo
    {
        return $this->belongsTo(IntegrationRunRecord::class, 'run_record_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IntegrationRule::class, 'rule_id');
    }
}
