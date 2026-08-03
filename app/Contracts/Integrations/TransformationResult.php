<?php

namespace App\Contracts\Integrations;

class TransformationResult
{
    public function __construct(
        public readonly array $output,
        public readonly ?array $normalizedInput = null,
        public readonly array $rulesExecuted = [],
        public readonly array $errors = [],
        public readonly array $warnings = [],
        public readonly array $mappingsUsed = [],
        public readonly array $pendingFields = [],
        public readonly array $ruleTimings = [],
        public readonly bool $success = true,
    ) {}

    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    public function hasWarnings(): bool
    {
        return ! empty($this->warnings);
    }

    public function hasPendingFields(): bool
    {
        return ! empty($this->pendingFields);
    }

    public function merge(TransformationResult $other): self
    {
        return new self(
            output: array_merge($this->output, $other->output),
            normalizedInput: $this->normalizedInput ?? $other->normalizedInput,
            rulesExecuted: array_merge($this->rulesExecuted, $other->rulesExecuted),
            errors: array_merge($this->errors, $other->errors),
            warnings: array_merge($this->warnings, $other->warnings),
            mappingsUsed: array_merge($this->mappingsUsed, $other->mappingsUsed),
            pendingFields: array_merge($this->pendingFields, $other->pendingFields),
            ruleTimings: array_merge($this->ruleTimings, $other->ruleTimings),
            success: $this->success && $other->success,
        );
    }
}
