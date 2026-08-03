<?php

namespace App\Contracts\Integrations;

interface OutputExporterInterface
{
    public function key(): string;

    public function label(): string;

    public function validateConfiguration(array $configuration): void;

    public function initialize(array $configuration, array $headers): string;

    public function writeHeaders(array $headers): void;

    public function writeRecord(array $record): void;

    public function finalize(): string;

    public function cancel(): void;
}
