<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Credential extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'qr_uid',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function links(): HasMany
    {
        return $this->hasMany(WorkerCredentialLink::class);
    }

    public function activeLink(): HasOne
    {
        return $this->hasOne(WorkerCredentialLink::class)
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }
}
