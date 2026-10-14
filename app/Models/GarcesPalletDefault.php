<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GarcesPalletDefault extends Model
{
    protected $table = 'garces_pallet_defaults';

    public const IS_EXPORT_DEFAULT = 1;

    protected $fillable = [
        'code_subsidiary',
        'code_season',
        'subsidiary_garces',
        'season_garces',
        'tipo_tarja',
        'tipo_fruta',
        'tipo_pallet',
        'is_export',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'is_export' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function scopePlantaTemporada($query, string $subsidiary, string $season)
    {
        return $query->where('code_subsidiary', $subsidiary)->where('code_season', $season);
    }

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }
}
