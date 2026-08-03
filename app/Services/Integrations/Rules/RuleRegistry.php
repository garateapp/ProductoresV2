<?php

namespace App\Services\Integrations\Rules;

use App\Contracts\Integrations\CustomRuleInterface;
use RuntimeException;

class RuleRegistry
{
    private static array $customRules = [];

    public static function register(CustomRuleInterface $rule): void
    {
        $key = $rule->key();

        if (isset(self::$customRules[$key])) {
            throw new RuntimeException("Custom rule '{$key}' is already registered.");
        }

        self::$customRules[$key] = $rule;
    }

    public static function get(string $key): ?CustomRuleInterface
    {
        return self::$customRules[$key] ?? null;
    }

    public static function all(): array
    {
        return self::$customRules;
    }

    public static function keys(): array
    {
        return array_keys(self::$customRules);
    }

    public static function clear(): void
    {
        self::$customRules = [];
    }
}
