<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationPendingMapping extends Model
{
    protected $table = 'integration_pending_mappings';

    protected $fillable = [
        'client_id', 'profile_id', 'mapping_set_id', 'run_record_id',
        'campo', 'valor_interno', 'frecuencia', 'valor_asignado',
        'resolved_by', 'resolved_at', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'frecuencia' => 'integer',
            'resolved_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(IntegrationClient::class, 'client_id');
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(IntegrationProfile::class, 'profile_id');
    }

    public function mappingSet(): BelongsTo
    {
        return $this->belongsTo(IntegrationMappingSet::class, 'mapping_set_id');
    }

    public function runRecord(): BelongsTo
    {
        return $this->belongsTo(IntegrationRunRecord::class, 'run_record_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeUnresolved($query)
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeByClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeByField($query, $campo)
    {
        return $query->where('campo', $campo);
    }
}
