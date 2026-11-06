<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Config\JsonConfigLoader;
use UniMethod\SymfonyLib\Config\{ConfigLoaderResolver, YamlConfigLoader};

final class ConfigLoaderResolverTest extends TestCase
{
    #[DataProvider("providePaths")]
    public function testResolvesLoaderByExtension(string $path, string $loader): void
    {
        self::assertSame($loader, ConfigLoaderResolver::resolve($path));
    }

    /**
     * @return iterable<string, array{string, class-string}>
     */
    public static function providePaths(): iterable
    {
        yield ".yaml" => ["/app/config/api.yaml", YamlConfigLoader::class];
        yield ".yml" => ["/app/config/api.yml", YamlConfigLoader::class];
        yield ".YAML" => ["/app/config/api.YAML", YamlConfigLoader::class];
        yield ".json" => ["/app/config/api.json", JsonConfigLoader::class];
        yield "без расширения" => ["/app/config/api", JsonConfigLoader::class];
    }
}
