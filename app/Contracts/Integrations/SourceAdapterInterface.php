<?php

namespace App\Contracts\Integrations;

interface SourceAdapterInterface
{
    public function key(): string;

    public function label(): string;

    public function validateConfiguration(array $configuration): void;

    public function getSchema(array $configuration): array;

    public function count(array $configuration): int;

    public function getRecords(array $configuration): \Generator;

    public function getStableIdentifier(array $record): string;

    public function applyFilters(array $configuration, array $filters): array;

    public function getExamples(array $configuration): array;
}
