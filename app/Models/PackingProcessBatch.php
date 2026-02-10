<?php

namespace App\Models;

use App\Enums\PlanningProcessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingProcessBatch extends Model
{
    protected $table = 'process_batches';

    protected $fillable = [
        'especie',
        'week_start',
        'week_end',
        'shift_id',
        'estado',
        'creado_por',
        'included_packing_line_ids',
    ];

    protected $casts = [
        'week_start' => 'date',
        'week_end' => 'date',
        'estado' => PlanningProcessStatus::class,
        'included_packing_line_ids' => 'array',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function processes(): HasMany
    {
        return $this->hasMany(PackingProcess::class, 'process_batch_id')->orderBy('fecha');
    }
}

