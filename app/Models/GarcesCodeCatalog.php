<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GarcesCodeCatalog extends Model
{
    protected $table = 'garces_code_catalogs';

    protected $fillable = [
        'tipo',
        'codigo_interno',
        'nombre',
        'activo',
        'notas',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
