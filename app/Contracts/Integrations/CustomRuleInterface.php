<?php

namespace App\Contracts\Integrations;

interface CustomRuleInterface
{
    public function key(): string;

    public function label(): string;

    public function validateConfiguration(array $configuration): void;

    public function transform(
        array $input,
        array $configuration,
        TransformationContext $context
    ): RuleResult;
}
