<?php

namespace App\Contracts\Integrations;

use App\Models\IntegrationProfileVersion;

interface TransformationEngineInterface
{
    public function transform(
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        array $mappingSetVersions = []
    ): TransformationResult;

    public function simulate(
        IntegrationProfileVersion $profileVersion,
        array $inputData,
        array $mappingSetVersions = []
    ): TransformationResult;

    public function validateProfile(IntegrationProfileVersion $profileVersion): array;
}
