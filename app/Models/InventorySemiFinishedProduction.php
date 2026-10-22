<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventorySemiFinishedProduction extends Model
{
    protected $table = "inventory_semi_finished_productions";

    protected $fillable = [
        "logistic_unit_id",
        "technical_sheet_id",
        "location_id",
        "created_by",
        "quantity",
        "status",
        "reversed_at",
        "reversed_by_id",
        "reversal_reason",
        "metadata",
    ];

    protected $casts = [
        "quantity" => "decimal:4",
        "reversed_at" => "datetime",
        "metadata" => "array",
    ];

    public function logisticUnit(): BelongsTo
    {
        return $this->belongsTo(InventoryLogisticUnit::class, "logistic_unit_id");
    }

    public function technicalSheet(): BelongsTo
    {
        return $this->belongsTo(InventoryTechnicalSheet::class, "technical_sheet_id");
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, "location_id");
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, "created_by");
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, "reversed_by_id");
    }
}

