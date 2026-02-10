<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanningInstructionVersion extends Model
{
    protected $table = 'planning_instruction_versions';

    protected $fillable = [
        'fecha',
        'shift_id',
        'packing_line_id',
        'version',
        'html',
        'overrides',
        'reason',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'version' => 'integer',
        'changed_at' => 'datetime',
        'overrides' => 'array',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function packingLine(): BelongsTo
    {
        return $this->belongsTo(PackingLine::class, 'packing_line_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
