<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Enums\IntegrationRunRecordStatus;
use App\Models\IntegrationRun;
use App\Models\IntegrationRunRecord;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RunRecordController extends Controller
{
    public function index(Request $request, IntegrationRun $run)
    {
        $filters = $request->only(['estado', 'q']);

        $records = IntegrationRunRecord::where('run_id', $run->id)
            ->when($filters['estado'] ?? null, fn ($q, $v) => $q->where('estado', $v))
            ->when($filters['q'] ?? null, fn ($q, $v) => $q->where(function ($q) use ($v) {
                $q->where('source_identifier', 'like', "%{$v}%")
                    ->orWhere('idempotency_key', 'like', "%{$v}%");
            }))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($r) => [
                'id' => $r->id,
                'source_identifier' => $r->source_identifier,
                'estado' => $r->estado?->value,
                'estado_label' => $r->estado?->label(),
                'errores' => $r->errores,
                'advertencias' => $r->advertencias,
                'intentos' => $r->intentos,
                'duracion_ms' => $r->duracion_ms,
                'processed_at' => $r->processed_at?->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Integrations/Runs/Records/Index', [
            'run_id' => $run->id,
            'records' => $records,
            'filters' => $filters,
            'statuses' => collect(IntegrationRunRecordStatus::cases())->map(fn ($s) => [
                'value' => $s->value,
                'label' => $s->label(),
            ]),
        ]);
    }

    public function show(IntegrationRun $run, IntegrationRunRecord $record)
    {
        $record->load(['run', 'rulesTrace', 'mappingsTrace']);

        return Inertia::render('Integrations/Runs/Records/Show', [
            'record' => [
                'id' => $record->id,
                'run_id' => $record->run_id,
                'source_identifier' => $record->source_identifier,
                'idempotency_key' => $record->idempotency_key,
                'estado' => $record->estado?->value,
                'estado_label' => $record->estado?->label(),
                'input_original' => $record->input_original,
                'input_normalizado' => $record->input_normalizado,
                'output_generado' => $record->output_generado,
                'errores' => $record->errores,
                'advertencias' => $record->advertencias,
                'intentos' => $record->intentos,
                'duracion_ms' => $record->duracion_ms,
                'processed_at' => $record->processed_at?->format('Y-m-d H:i:s'),
                'rules_trace' => $record->rulesTrace->map(fn ($t) => [
                    'rule_id' => $t->rule_id,
                    'rule_name' => $t->rule_name,
                    'rule_type' => $t->rule_type,
                    'estado' => $t->estado,
                    'input_values' => $t->input_values,
                    'output_values' => $t->output_values,
                    'error' => $t->error,
                    'duracion_ms' => $t->duracion_ms,
                ]),
                'mappings_trace' => $record->mappingsTrace->map(fn ($m) => [
                    'mapping_set_version_id' => $m->mapping_set_version_id,
                    'mapping_set_name' => $m->mapping_set_name,
                    'input_keys' => $m->input_keys,
                    'output_values' => $m->output_values,
                    'fallback_usado' => $m->fallback_usado,
                ]),
            ],
        ]);
    }
}
