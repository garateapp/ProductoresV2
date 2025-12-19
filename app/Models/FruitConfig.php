<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FruitConfig extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'species',
        'variety',
        'tottes_per_bin',
        'status',
    ];

    protected $casts = [
        'tottes_per_bin' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
