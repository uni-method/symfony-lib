<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Config;

use UniMethod\Frame\Config\{AbstractConfigLoader, JsonConfigLoader};

/**
 * API config loader by file extension: `.yaml`/`.yml` (case-insensitive)
 * — YAML, otherwise JSON.
 *
 * Загрузчик API-конфига по расширению файла: `.yaml`/`.yml` (без учёта
 * регистра) — YAML, иначе JSON.
 */
final class ConfigLoaderResolver
{
    /**
     * @return class-string<AbstractConfigLoader>
     */
    public static function resolve(string $configPath): string
    {
        return match (strtolower(pathinfo($configPath, PATHINFO_EXTENSION))) {
            "yaml", "yml" => YamlConfigLoader::class,
            default => JsonConfigLoader::class,
        };
    }
}
