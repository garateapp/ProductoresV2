<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reservation extends Model
{
    protected $fillable = [
        'n_g_recepcion',
        'source_type',
        'source_key',
        'process_id',
        'estado',
        'reserved_bins',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(PackingProcess::class, 'process_id');
    }
}
