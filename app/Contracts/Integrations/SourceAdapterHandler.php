<?php

namespace App\Contracts\Integrations;

interface SourceAdapterHandler
{
    public function validateConfiguration(array $config): void;

    public function getSchema(array $config): array;

    public function count(array $config): int;

    public function getRecords(array $config): \Generator;

    public function getExamples(array $config): array;

    public function getStableIdentifier(array $record): string;
}
