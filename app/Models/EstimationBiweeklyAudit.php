<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationBiweeklyAudit extends Model
{
    use HasFactory;

    protected $table = 'estimation_biweekly_audits';

    protected $fillable = [
        'estimation_biweekly_version_id',
        'estimation_biweekly_row_id',
        'field_name',
        'action',
        'source',
        'old_value',
        'new_value',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function version()
    {
        return $this->belongsTo(EstimationBiweeklyVersion::class, 'estimation_biweekly_version_id');
    }

    public function row()
    {
        return $this->belongsTo(EstimationBiweeklyRow::class, 'estimation_biweekly_row_id');
    }
}
