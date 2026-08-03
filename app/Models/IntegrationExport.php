<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationExport extends Model
{
    protected $fillable = [
        'run_id', 'tipo', 'archivo', 'disk', 'mime_type',
        'tamano_bytes', 'total_registros', 'hash', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tamano_bytes' => 'integer',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(IntegrationRun::class, 'run_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
