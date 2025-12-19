<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Contractor extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'name',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function crews(): HasMany
    {
        return $this->hasMany(Crew::class);
    }

    public function workers(): HasMany
    {
        return $this->hasMany(Worker::class);
    }
}
