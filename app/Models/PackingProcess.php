<?php

namespace App\Models;

use App\Enums\PlanningProcessStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PackingProcess extends Model
{
    protected $table = 'processes';

    protected $fillable = [
        'process_batch_id',
        'especie',
        'exportadora',
        'fecha',
        'shift_id',
        'extra_horas',
        'estado',
        'creado_por',
        'included_packing_line_ids',
        'pedidos',
    ];

    protected $casts = [
        'fecha' => 'date',
        'estado' => PlanningProcessStatus::class,
        'included_packing_line_ids' => 'array',
        'extra_horas' => 'decimal:2',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PackingProcessBatch::class, 'process_batch_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function lots(): HasMany
    {
        return $this->hasMany(PackingProcessLot::class, 'process_id')->orderBy('orden');
    }

    public function lineOverrides(): HasMany
    {
        return $this->hasMany(PackingProcessLineOverride::class, 'process_id');
    }
}
