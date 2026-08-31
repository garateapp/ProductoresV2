<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PreCoolingAuditLog extends Model
{
    protected $table = 'pre_cooling_audit_logs';

    protected $fillable = [
        'load_id',
        'folio',
        'usuario_id',
        'accion',
        'datos_antes',
        'datos_despues',
        'ip',
    ];

    protected $casts = [
        'datos_antes' => 'array',
        'datos_despues' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function carga(): BelongsTo
    {
        return $this->belongsTo(PreCoolingLoad::class, 'load_id');
    }
}
