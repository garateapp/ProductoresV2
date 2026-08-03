<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryScanEvent extends Model
{
    protected $fillable = [
        'scan_session_uuid',
        'movement_id',
        'step',
        'raw_code',
        'code_type',
        'resolved_entity_type',
        'resolved_entity_id',
        'success',
        'message',
        'user_id',
        'device_code',
        'scanned_at',
        'payload',
    ];

    protected $casts = [
        'success' => 'boolean',
        'scanned_at' => 'datetime',
        'payload' => 'array',
    ];

    public function movement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class, 'movement_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
