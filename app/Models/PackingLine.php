<?php

namespace App\Models;

use App\Enums\PackingLineType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class PackingLine extends Model
{
    protected $fillable = [
        'nombre',
        'tipo',
        // Campo legacy (primera especie). Mantener por compatibilidad/ordenamiento.
        'especie',
        // Nuevo: una línea/cámara puede atender más de una especie.
        'especies',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tipo' => PackingLineType::class,
        'especies' => 'array',
    ];

    public function capacities(): HasMany
    {
        return $this->hasMany(LineCapacity::class);
    }

    public function scopeForEspecie($query, string $especie)
    {
        // Compatibilidad: MySQL/PG soportan JSON_CONTAINS. SQLite depende de versión; usamos LIKE como fallback.
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->where(function ($q) use ($especie) {
                $q->where('especie', $especie)
                    ->orWhere('especies', 'like', '%"'.$especie.'"%');
            });
        }

        return $query->where(function ($q) use ($especie) {
            $q->where('especie', $especie)
                ->orWhereJsonContains('especies', $especie);
        });
    }

    public function processLots(): HasMany
    {
        return $this->hasMany(PackingProcessLot::class, 'packing_line_id');
    }
}
