<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingLineMonitor extends Model
{
    protected $fillable = [
        'packing_line_id',
        'fecha',
        'shift_id',
        'sqlsrv_production_id',
        'sqlsrv_production_number',
        'linked_by',
        'linked_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'linked_at' => 'datetime',
        'sqlsrv_production_id' => 'integer',
    ];

    public function packingLine(): BelongsTo
    {
        return $this->belongsTo(PackingLine::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function linkedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'linked_by');
    }
}

