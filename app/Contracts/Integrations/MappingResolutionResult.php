<?php

namespace App\Contracts\Integrations;

class MappingResolutionResult
{
    public function __construct(
        public readonly bool $found,
        public readonly mixed $value,
        public readonly ?int $mappingSetVersionId = null,
        public readonly ?string $mappingSetName = null,
        public readonly ?array $inputKeys = null,
        public readonly ?array $outputValues = null,
        public readonly ?string $fallbackUsed = null,
    ) {}

    public static function found(
        mixed $value,
        int $mappingSetVersionId,
        string $mappingSetName,
        array $inputKeys,
        array $outputValues = null,
    ): self {
        return new self(
            found: true,
            value: $value,
            mappingSetVersionId: $mappingSetVersionId,
            mappingSetName: $mappingSetName,
            inputKeys: $inputKeys,
            outputValues: $outputValues,
        );
    }

    public static function notFound(?string $fallbackUsed = null): self
    {
        return new self(
            found: false,
            value: null,
            fallbackUsed: $fallbackUsed,
        );
    }
}
