<?php

namespace App\Models;

use App\Enums\EstimationType;
use App\Enums\EstimationVersionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimationVersion extends Model
{
    use HasFactory;

    protected $table = 'estimation_versions';

    protected $fillable = [
        'season_id',
        'type',
        'period_start_week',
        'period_end_week',
        'source',
        'uploaded_by',
        'status',
        'file_name',
        'file_path',
        'notes',
    ];

    protected $casts = [
        'type' => EstimationType::class,
        'status' => EstimationVersionStatus::class,
        'period_start_week' => 'integer',
        'period_end_week' => 'integer',
    ];

    public function season()
    {
        return $this->belongsTo(EstimationSeason::class, 'season_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function rows()
    {
        return $this->hasMany(EstimationRow::class, 'estimation_version_id');
    }

    public function audits()
    {
        return $this->hasMany(EstimationAudit::class, 'estimation_version_id');
    }
}