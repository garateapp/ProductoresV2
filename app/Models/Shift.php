<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    protected $fillable = [
        'codigo',
        'nombre',
        'horas',
        'hora_inicio',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'horas' => 'decimal:2',
        // `time` column: keep as string like "07:00:00"
        'hora_inicio' => 'string',
    ];

    public function capacities(): HasMany
    {
        return $this->hasMany(LineCapacity::class);
    }

    public function processes(): HasMany
    {
        return $this->hasMany(PackingProcess::class, 'shift_id');
    }
}
