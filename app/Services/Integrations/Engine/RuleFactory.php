<?php

namespace App\Services\Integrations\Engine;

use App\Services\Integrations\Rules\BaseRule;
use App\Services\Integrations\Rules\CompositeMappingRule;
use App\Services\Integrations\Rules\ConcatenationRule;
use App\Services\Integrations\Rules\ConditionalRule;
use App\Services\Integrations\Rules\ConstantRule;
use App\Services\Integrations\Rules\DirectRule;
use App\Services\Integrations\Rules\FormatRule;
use App\Services\Integrations\Rules\MappingRule;
use App\Services\Integrations\Rules\MathRule;
use App\Services\Integrations\Rules\MultiOutputMappingRule;
use App\Services\Integrations\Rules\RuleRegistry;
use InvalidArgumentException;

class RuleFactory
{
    private static ?array $builtInRules = null;

    public static function create(string $type): BaseRule
    {
        return match ($type) {
            'direct' => app(DirectRule::class),
            'constant' => app(ConstantRule::class),
            'mapping' => app(MappingRule::class),
            'composite_mapping' => app(CompositeMappingRule::class),
            'multi_output_mapping' => app(MultiOutputMappingRule::class),
            'concatenation' => app(ConcatenationRule::class),
            'math' => app(MathRule::class),
            'conditional' => app(ConditionalRule::class),
            'format' => app(FormatRule::class),
            default => self::resolveCustom($type),
        };
    }

    public static function availableTypes(): array
    {
        if (self::$builtInRules === null) {
            self::$builtInRules = [
                'direct' => 'Directa',
                'constant' => 'Constante',
                'mapping' => 'Homologación simple',
                'composite_mapping' => 'Homologación compuesta',
                'multi_output_mapping' => 'Homologación multi-salida',
                'concatenation' => 'Concatenación',
                'math' => 'Operación matemática',
                'conditional' => 'Condicional',
                'format' => 'Formato',
                'related_field' => 'Campo relacionado',
                'custom' => 'Regla personalizada',
            ];
        }

        return self::$builtInRules;
    }

    private static function resolveCustom(string $type): BaseRule
    {
        $customRule = RuleRegistry::get($type);
        if (!$customRule) {
            throw new InvalidArgumentException("Tipo de regla no soportado: {$type}");
        }

        return new class($customRule) extends BaseRule {
            public function __construct(private $customRule) {}

            protected function getType(): string
            {
                return $this->customRule->key();
            }

            protected function doTransform(array $config, TransformationContext $context): RuleResult
            {
                $result = $this->customRule->transform(
                    $context->inputData,
                    $config,
                    $context
                );

                return $result;
            }
        };
    }
}
