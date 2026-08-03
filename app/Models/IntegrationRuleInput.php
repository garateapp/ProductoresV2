<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IntegrationRuleInput extends Model
{
    public $timestamps = false;

    protected $fillable = ['rule_id', 'input_field_id', 'clave_origen', 'alias'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(IntegrationRule::class, 'rule_id');
    }

    public function inputField(): BelongsTo
    {
        return $this->belongsTo(IntegrationInputField::class, 'input_field_id');
    }
}
