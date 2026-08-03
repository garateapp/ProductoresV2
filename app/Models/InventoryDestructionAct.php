<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryDestructionAct extends Model
{
    protected $fillable = [
        'waste_record_id',
        'user_id',
        'folio',
        'observaciones',
    ];

    public function wasteRecord()
    {
        return $this->belongsTo(InventoryWasteRecord::class, 'waste_record_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
