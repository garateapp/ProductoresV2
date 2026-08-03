<?php

namespace App\Http\Controllers\Integrations;

use App\Http\Controllers\Controller;
use App\Models\IntegrationMappingSet;
use App\Models\IntegrationMappingItem;
use App\Services\Integrations\Audit\IntegrationAuditService;
use Illuminate\Http\Request;

class MappingItemController extends Controller
{
    public function store(Request $request, IntegrationMappingSet $mappingSet, IntegrationAuditService $audit)
    {
        $version = $mappingSet->currentVersion;

        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar ítems en una versión inmutable.');
        }

        $data = $request->validate([
            'valor_interno' => "required|string|max:200|unique:integration_mapping_items,valor_interno,null,null,mapping_set_version_id,{$version->id}",
            'valor_externo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:500',
            'orden' => 'integer|min:0',
        ]);

        $item = IntegrationMappingItem::create([
            ...$data,
            'mapping_set_version_id' => $version->id,
            'activo' => true,
        ]);

        $audit->mappingItemCreated($mappingSet->id, $mappingSet->nombre, $data);

        return back()->with('success', 'Ítem agregado.');
    }

    public function update(Request $request, IntegrationMappingSet $mappingSet, IntegrationMappingItem $item)
    {
        $version = $mappingSet->currentVersion;

        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden modificar ítems en una versión inmutable.');
        }

        $data = $request->validate([
            'valor_interno' => "required|string|max:200|unique:integration_mapping_items,valor_interno,{$item->id},id,mapping_set_version_id,{$version->id}",
            'valor_externo' => 'required|string|max:200',
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
            'orden' => 'integer|min:0',
        ]);

        $item->update($data);

        return back()->with('success', 'Ítem actualizado.');
    }

    public function destroy(IntegrationMappingSet $mappingSet, IntegrationMappingItem $item)
    {
        $version = $mappingSet->currentVersion;

        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden eliminar ítems en una versión inmutable.');
        }

        $item->delete();

        return back()->with('success', 'Ítem eliminado.');
    }

    public function importBulk(Request $request, IntegrationMappingSet $mappingSet)
    {
        $version = $mappingSet->currentVersion;

        if (!$version || $version->inmutable) {
            return back()->with('error', 'No se pueden importar ítems en una versión inmutable.');
        }

        $data = $request->validate([
            'items' => 'required|array',
            'items.*.valor_interno' => 'required|string|max:200',
            'items.*.valor_externo' => 'required|string|max:200',
            'items.*.descripcion' => 'nullable|string|max:500',
        ]);

        $count = 0;
        foreach ($data['items'] as $item) {
            IntegrationMappingItem::updateOrCreate(
                [
                    'mapping_set_version_id' => $version->id,
                    'valor_interno' => $item['valor_interno'],
                ],
                [
                    'valor_externo' => $item['valor_externo'],
                    'descripcion' => $item['descripcion'] ?? null,
                    'activo' => true,
                ]
            );
            $count++;
        }

        return back()->with('success', "{$count} ítems importados correctamente.");
    }
}
