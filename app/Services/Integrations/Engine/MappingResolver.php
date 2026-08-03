<?php

namespace App\Services\Integrations\Engine;

use App\Contracts\Integrations\MappingResolutionResult;
use App\Contracts\Integrations\MappingResolverInterface;
use App\Models\IntegrationMappingItem;
use App\Models\IntegrationMappingSetVersion;

class MappingResolver implements MappingResolverInterface
{
    public function resolve(
        IntegrationMappingSetVersion $mappingSetVersion,
        array $inputValues,
        string $referenceDate = null
    ): MappingResolutionResult {
        $date = $referenceDate ?? now()->format('Y-m-d');
        $sensitive = $mappingSetVersion->sensible_mayusculas;
        $spaceTreatment = $mappingSetVersion->tratamiento_espacios ?? 'trim';
        $normalization = $mappingSetVersion->config_normalizacion ?? [];

        $normalizedInput = $this->normalizeInputValues($inputValues, $sensitive, $spaceTreatment);

        $items = $mappingSetVersion->activeItems()
            ->with(['inputs', 'mappingSetVersion'])
            ->get();

        $sorted = $items->sortByDesc('prioridad');

        foreach ($sorted as $item) {
            $itemInputs = $item->inputs->keyBy('clave');

            $allMatch = true;
            $matchedKeys = [];

            foreach ($normalizedInput as $key => $value) {
                $input = $itemInputs->get($key);

                if (!$input) {
                    $allMatch = false;
                    break;
                }

                $itemValue = $input->valor_entrada;
                $itemValue = $spaceTreatment === 'trim' ? trim($itemValue) : $itemValue;
                $itemValue = $spaceTreatment === 'normalize' ? preg_replace('/\s+/', ' ', trim($itemValue)) : $itemValue;
                $itemValue = $sensitive ? $itemValue : mb_strtolower($itemValue);

                $compareValue = $sensitive ? $value : mb_strtolower($value);

                if ($itemValue !== $compareValue) {
                    $allMatch = false;
                    break;
                }

                $matchedKeys[$key] = $value;
            }

            if (!$allMatch) {
                continue;
            }

            if (!$this->isItemVigent($item, $date)) {
                continue;
            }

            $outputValues = $item->inputs->pluck('pivot.valor_salida', 'clave')->toArray();

            return MappingResolutionResult::found(
                value: $item->valor_salida,
                mappingSetVersionId: $mappingSetVersion->id,
                mappingSetName: $mappingSetVersion->mappingSet?->nombre ?? 'Unknown',
                inputKeys: $matchedKeys,
                outputValues: [$item->valor_salida],
            );
        }

        return MappingResolutionResult::notFound();
    }

    public function resolveFallback(
        IntegrationMappingSetVersion $mappingSetVersion,
        array $inputValues,
        string $referenceDate = null
    ): mixed {
        $strategy = $mappingSetVersion->estrategia_fallback?->value ?? 'error';

        return match ($strategy) {
            'default' => $mappingSetVersion->valor_defecto,
            'keep_original' => reset($inputValues) ?: null,
            'null' => null,
            default => null,
        };
    }

    private function normalizeInputValues(array $values, bool $sensitive, string $spaceTreatment): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            if (!is_string($value)) {
                $value = (string) $value;
            }

            $value = match ($spaceTreatment) {
                'trim' => trim($value),
                'normalize' => preg_replace('/\s+/', ' ', trim($value)),
                default => $value,
            };

            $value = $sensitive ? $value : mb_strtolower($value);

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function isItemVigent(IntegrationMappingItem $item, string $date): bool
    {
        $start = $item->fecha_inicio_vigencia;
        $end = $item->fecha_fin_vigencia;

        if ($start && $start->format('Y-m-d') > $date) {
            return false;
        }

        if ($end && $end->format('Y-m-d') < $date) {
            return false;
        }

        return true;
    }
}
