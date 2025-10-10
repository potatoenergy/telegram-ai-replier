<?php

namespace App\Config;

use App\Exceptions\ConfigException;

class Config
{
    public static function loadAndValidate(): void
    {
        $requiredVars = [
            'BOT_TOKEN',
            'ADMIN_USER_ID',
            'TELEGRAM_WEBHOOK_URL',
            'AI_PROVIDER',
        ];

        $aiProvider = $_ENV['AI_PROVIDER'] ?? null;

        if ($aiProvider === 'openai') {
            $requiredVars[] = 'OPENAI_API_KEY';
            $baseUrl = $_ENV['OPENAI_BASE_URL'] ?? null;
            if ($baseUrl !== null) {
                 $requiredVars[] = 'OPENAI_BASE_URL';
            }
        } elseif ($aiProvider === 'ollama') {
        } else {
            throw new ConfigException("Unsupported AI_PROVIDER: '$aiProvider'. Must be 'openai' or 'ollama'.");
        }

        foreach ($requiredVars as $var) {
            if (!isset($_ENV[$var])) {
                throw new ConfigException("Required environment variable '$var' is not set.");
            }
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        return $_ENV[$key] ?? $default;
    }
}