<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationAudit extends Model
{
    use HasFactory;

    protected $table = 'estimation_audits';

    protected $fillable = [
        'estimation_version_id',
        'estimation_row_id',
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
        return $this->belongsTo(EstimationVersion::class, 'estimation_version_id');
    }

    public function row()
    {
        return $this->belongsTo(EstimationRow::class, 'estimation_row_id');
    }

    public function changer()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}