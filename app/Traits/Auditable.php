<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;

trait Auditable
{
    protected static function bootAuditable()
    {
        static::created(function ($model) {
            $model->logAudit('created', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $oldValues = array_intersect_key($model->getOriginal(), $model->getDirty());
            $newValues = $model->getDirty();
            $model->logAudit('updated', $oldValues, $newValues);
        });

        static::deleted(function ($model) {
            $model->logAudit('deleted', $model->getAttributes(), null);
        });
    }

    public function logAudit(string $event, ?array $oldValues, ?array $newValues)
    {
        DB::table('audit_logs')->insert([
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => get_class($this),
            'auditable_id' => $this->id,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
