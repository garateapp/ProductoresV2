<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class GarcesCodeMapping extends Model
{
    public const CACHE_VERSION_KEY = 'garces_code_dict_version';

    protected $table = 'garces_code_mappings';

    protected $fillable = [
        'tipo',
        'clave_interna',
        'clave_garces',
        'vigencia_desde',
        'vigencia_hasta',
        'prioridad',
        'activo',
        'notas',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'vigencia_desde' => 'date',
            'vigencia_hasta' => 'date',
            'prioridad' => 'integer',
            'activo' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => static::flushCache());
        static::deleted(fn () => static::flushCache());
    }

    public static function cacheVersion(): int
    {
        return (int) Cache::get(self::CACHE_VERSION_KEY, 1);
    }

    public static function flushCache(): void
    {
        Cache::forever(self::CACHE_VERSION_KEY, self::cacheVersion() + 1);
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
