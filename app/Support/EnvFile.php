<?php

namespace App\Support;

class EnvFile
{
    public function update(string $key, string|int $value): bool
    {
        $path = app()->environmentFilePath();

        if (! is_file($path) || ! is_writable($path)) {
            return false;
        }

        $line = "{$key}={$value}";
        $content = (string) file_get_contents($path);

        if (preg_match("/^{$key}=.*$/m", $content)) {
            $content = (string) preg_replace("/^{$key}=.*$/m", $line, $content);
        } else {
            $content .= PHP_EOL.$line.PHP_EOL;
        }

        return file_put_contents($path, $content) !== false;
    }

    public function value(string $key, string|int|null $default = null): string|int|null
    {
        return env($key, $default);
    }
}
