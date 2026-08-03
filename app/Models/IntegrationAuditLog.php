<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationAuditLog extends Model
{
    protected $fillable = [
        'user_id', 'evento', 'entidad_tipo', 'entidad_id', 'entidad_nombre',
        'valores_previos', 'valores_nuevos', 'ip_address', 'motivo', 'run_id',
    ];

    protected function casts(): array
    {
        return [
            'valores_previos' => 'array',
            'valores_nuevos' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(IntegrationRun::class, 'run_id');
    }
}
