<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationWeekValue extends Model
{
    use HasFactory;

    protected $table = 'estimation_week_values';

    protected $fillable = [
        'estimation_row_id',
        'week_number',
        'kilos',
    ];

    protected $casts = [
        'week_number' => 'integer',
        'kilos' => 'decimal:3',
    ];

    public function row()
    {
        return $this->belongsTo(EstimationRow::class, 'estimation_row_id');
    }
}