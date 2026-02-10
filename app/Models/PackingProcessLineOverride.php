<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingProcessLineOverride extends Model
{
    protected $table = 'process_line_overrides';

    protected $fillable = [
        'process_id',
        'packing_line_id',
        'extra_horas',
    ];

    protected $casts = [
        'extra_horas' => 'decimal:2',
        'process_id' => 'integer',
        'packing_line_id' => 'integer',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(PackingProcess::class, 'process_id');
    }

    public function packingLine(): BelongsTo
    {
        return $this->belongsTo(PackingLine::class, 'packing_line_id');
    }
}

