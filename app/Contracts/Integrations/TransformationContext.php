<?php

namespace App\Contracts\Integrations;

use App\Models\IntegrationProfileVersion;

class TransformationContext
{
    public function __construct(
        public readonly IntegrationProfileVersion $profileVersion,
        public readonly array $inputData,
        public readonly array $normalizedData,
        public array $currentOutput,
        public readonly array $mappingSetVersions = [],
        public readonly array $resolvedMappings = [],
    ) {}

    public function getInput(string $key, mixed $default = null): mixed
    {
        return data_get($this->inputData, $key, $default);
    }

    public function getNormalized(string $key, mixed $default = null): mixed
    {
        return data_get($this->normalizedData, $key, $default);
    }

    public function getOutput(string $key, mixed $default = null): mixed
    {
        return data_get($this->currentOutput, $key, $default);
    }

    public function setOutput(string $key, mixed $value): void
    {
        data_set($this->currentOutput, $key, $value);
    }

    public function hasMappingSet(int $mappingSetVersionId): bool
    {
        return isset($this->mappingSetVersions[$mappingSetVersionId]);
    }

    public function getMappingSet(int $mappingSetVersionId): mixed
    {
        return $this->mappingSetVersions[$mappingSetVersionId] ?? null;
    }
}
