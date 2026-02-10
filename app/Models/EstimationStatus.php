<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationStatus extends Model
{
    use HasFactory;

    protected $table = 'estimation_statuses';

    protected $fillable = [
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function rows()
    {
        return $this->hasMany(EstimationRow::class, 'status_id');
    }
}