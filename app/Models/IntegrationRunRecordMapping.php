<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationRunRecordMapping extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'run_record_id', 'mapping_set_version_id', 'mapping_set_name',
        'input_keys', 'output_values', 'fallback_usado',
    ];

    protected function casts(): array
    {
        return [
            'input_keys' => 'array',
            'output_values' => 'array',
        ];
    }

    public function runRecord(): BelongsTo
    {
        return $this->belongsTo(IntegrationRunRecord::class, 'run_record_id');
    }

    public function mappingSetVersion(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingSetVersion::class, 'mapping_set_version_id');
    }
}
