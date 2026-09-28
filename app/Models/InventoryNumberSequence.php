<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryNumberSequence extends Model
{
    protected $table = 'inventory_number_sequences';

    protected $fillable = [
        'key',
        'value',
    ];

    protected $casts = [
        'value' => 'integer',
    ];
}
