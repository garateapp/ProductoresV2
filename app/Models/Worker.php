<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Worker extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'national_id',
        'full_name',
        'role',
        'status',
        'contractor_id',
        'crew_id',
    ];

    protected $casts = [
        'national_id' => 'string',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }

    public function credentialLinks(): HasMany
    {
        return $this->hasMany(WorkerCredentialLink::class);
    }

    public function activeCredentialLink(): HasOne
    {
        return $this->hasOne(WorkerCredentialLink::class)
            ->whereNull('unassigned_at')
            ->latestOfMany('assigned_at');
    }
}
