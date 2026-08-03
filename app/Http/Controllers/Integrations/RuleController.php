<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationProfile;
use App\Models\IntegrationRule;
use App\Enums\IntegrationRuleType;
use App\Enums\IntegrationRuleErrorPolicy;
use Illuminate\Http\Request;

class RuleController extends Controller
{
    public function store(Request $request, IntegrationProfile $profile)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar reglas en una versión inmutable.');
        }

        $data = $request->validate([
            'tipo' => 'required|string',
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'config' => 'nullable|array',
            'orden' => 'integer|min:0',
            'obligatoria' => 'boolean',
            'politica_error' => 'nullable|string',
            'activo' => 'boolean',
            'inputs' => 'nullable|array',
            'inputs.*.clave_origen' => 'required|string|max:100',
            'inputs.*.alias' => 'nullable|string|max:100',
            'outputs' => 'nullable|array',
            'outputs.*.clave_destino' => 'required|string|max:100',
        ]);

        $data['profile_version_id'] = $version->id;
        $data['activo'] = $data['activo'] ?? true;

        $rule = IntegrationRule::create($data);

        if ($request->has('inputs')) {
            foreach ($request->inputs as $input) {
                $rule->inputs()->create($input);
            }
        }

        if ($request->has('outputs')) {
            foreach ($request->outputs as $output) {
                $rule->outputs()->create($output);
            }
        }

        return back()->with('success', 'Regla agregada correctamente.');
    }

    public function update(Request $request, IntegrationProfile $profile, IntegrationRule $rule)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar reglas en una versión inmutable.');
        }

        $data = $request->validate([
            'tipo' => 'required|string',
            'nombre' => 'required|string|max:200',
            'descripcion' => 'nullable|string',
            'config' => 'nullable|array',
            'orden' => 'integer|min:0',
            'obligatoria' => 'boolean',
            'politica_error' => 'nullable|string',
            'activo' => 'boolean',
            'inputs' => 'nullable|array',
            'inputs.*.clave_origen' => 'required|string|max:100',
            'inputs.*.alias' => 'nullable|string|max:100',
            'outputs' => 'nullable|array',
            'outputs.*.clave_destino' => 'required|string|max:100',
        ]);

        $rule->update($data);

        if ($request->has('inputs')) {
            $rule->inputs()->delete();
            foreach ($request->inputs as $input) {
                $rule->inputs()->create($input);
            }
        }

        if ($request->has('outputs')) {
            $rule->outputs()->delete();
            foreach ($request->outputs as $output) {
                $rule->outputs()->create($output);
            }
        }

        return back()->with('success', 'Regla actualizada correctamente.');
    }

    public function destroy(IntegrationProfile $profile, IntegrationRule $rule)
    {
        $version = $profile->currentVersion;
        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden eliminar reglas en una versión inmutable.');
        }

        $rule->delete();

        return back()->with('success', 'Regla eliminada.');
    }

    public function reorder(Request $request, IntegrationProfile $profile)
    {
        $request->validate([
            'rules' => 'required|array',
            'rules.*.id' => 'required|exists:integration_rules,id',
            'rules.*.orden' => 'required|integer|min:0',
        ]);

        foreach ($request->rules as $rule) {
            IntegrationRule::where('id', $rule['id'])->update(['orden' => $rule['orden']]);
        }

        return back()->with('success', 'Orden de reglas actualizado.');
    }
}
