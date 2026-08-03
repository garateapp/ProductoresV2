<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationRuleOutput extends Model
{
    public $timestamps = false;

    protected $fillable = ['rule_id', 'output_field_id', 'clave_destino'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IntegrationRule::class, 'rule_id');
    }

    public function outputField(): BelongsTo
    {
        return $this->belongsTo(IntegrationOutputField::class, 'output_field_id');
    }
}
