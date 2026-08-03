<?php

namespace App\Contracts\Integrations;

use App\Models\IntegrationMappingSetVersion;

interface MappingResolverInterface
{
    public function resolve(
        IntegrationMappingSetVersion $mappingSetVersion,
        array $inputValues,
        string $referenceDate = null
    ): MappingResolutionResult;

    public function resolveFallback(
        IntegrationMappingSetVersion $mappingSetVersion,
        array $inputValues,
        string $referenceDate = null
    ): mixed;
}
