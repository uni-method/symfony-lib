<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use UniMethod\SymfonyLib\DependencyInjection\SymfonyLibExtension;
use UniMethod\SymfonyLib\Exception\MissingDependencyException;

final class SymfonyLibExtensionTest extends TestCase
{
    /**
     * `rate_limit` with the default limiter without symfony/rate-limiter —
     * MissingDependencyException with the path to the setting.
     *
     * `rate_limit` с лимитером по умолчанию без symfony/rate-limiter —
     * MissingDependencyException с путём до настройки.
     */
    public function testRateLimitWithoutRateLimiterPackageThrows(): void
    {
        $extension = new class () extends SymfonyLibExtension {
            protected function isRateLimiterAvailable(): bool
            {
                return false;
            }
        };

        $this->expectException(MissingDependencyException::class);
        $this->expectExceptionMessage("symfony_lib.apis.main.rate_limit");

        $extension->load(
            [[
                "apis" => [
                    "main" => [
                        "config_path" => "/tmp/api.json",
                        "prefix" => "/api",
                        "rate_limit" => [
                            "anonymous" => [
                                "user" => [
                                    "list" => [
                                        "policy" => "fixed_window",
                                        "limit" => 1,
                                        "interval" => "1 minute",
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ]],
            new ContainerBuilder(),
        );
    }
}
