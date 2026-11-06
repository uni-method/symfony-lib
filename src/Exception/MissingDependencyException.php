<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use LogicException;
use UniMethod\Frame\Exception\FrameConfigurationException;
use UniMethod\SymfonyLib\RateLimit\RateLimiterInterface;

/**
 * The bundle config requires a package that is not installed.
 *
 * Конфиг бандла требует пакет, который не установлен.
 */
final class MissingDependencyException extends LogicException implements
    FrameConfigurationException
{
    public static function rateLimiter(string $apiName): self
    {
        return new self(
            sprintf(
                'symfony_lib.apis.%s.rate_limit uses the default limiter, which requires symfony/rate-limiter. Run ' .
                '"composer require symfony/rate-limiter" or set "limiter" to a service implementing %s.',
                $apiName,
                RateLimiterInterface::class,
            ),
        );
    }
}
