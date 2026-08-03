<?php

namespace App\Contracts\Integrations;

class RuleResult
{
    public function __construct(
        public readonly bool $success,
        public readonly array $output = [],
        public readonly ?string $error = null,
        public readonly ?string $warning = null,
        public readonly bool $stopRecord = false,
        public readonly bool $markPending = false,
        public readonly ?string $pendingField = null,
        public readonly ?string $pendingValue = null,
        public readonly ?array $mappingUsed = null,
        public readonly int $durationMs = 0,
    ) {}

    public static function ok(array $output, int $durationMs = 0, ?array $mappingUsed = null): self
    {
        return new self(success: true, output: $output, durationMs: $durationMs, mappingUsed: $mappingUsed);
    }

    public static function error(string $error, bool $stopRecord = true): self
    {
        return new self(success: false, error: $error, stopRecord: $stopRecord);
    }

    public static function warning(string $warning, array $output = []): self
    {
        return new self(success: true, output: $output, warning: $warning);
    }

    public static function pending(string $field, string $value): self
    {
        return new self(success: false, markPending: true, pendingField: $field, pendingValue: $value);
    }

    public static function skip(): self
    {
        return new self(success: true);
    }
}
