<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SapSyncState extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key): ?string
    {
        return static::query()->where('key', $key)->value('value');
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }
}
