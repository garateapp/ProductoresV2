<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PackingProcessLotPackagingChange extends Model
{
    protected $table = 'process_lot_packaging_changes';

    public $timestamps = false;

    protected $fillable = [
        'process_lot_id',
        'process_id',
        'user_id',
        'from_c_embalaje',
        'from_n_embalaje',
        'to_c_embalaje',
        'to_n_embalaje',
        'reason',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'process_lot_id' => 'integer',
        'process_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(PackingProcessLot::class, 'process_lot_id');
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(PackingProcess::class, 'process_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}

