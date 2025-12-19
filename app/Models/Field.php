<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Models\User;

class Field extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'producer_id',
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

    public function producer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'producer_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }
}
