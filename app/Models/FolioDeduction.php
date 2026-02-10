<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FolioDeduction extends Model
{
    protected $fillable = [
        'process_id',
        'folio',
        'user_id',
        'quantity',
        'scanned_at',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
        'quantity' => 'integer',
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(PackingProcess::class, 'process_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
