<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Personal extends Model
{
    protected $table = 'personal';

    protected $fillable = [
        'nombre',
        'email',
        'cargo',
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(InventoryPersonDelivery::class, 'person_id');
    }
}
