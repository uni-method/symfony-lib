<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Exception;

use RuntimeException;
use UniMethod\Frame\Exception\FrameConfigurationException;
use UniMethod\SymfonyLib\RateLimit\RateLimiterInterface;

/**
 * The service from symfony_lib.apis.<name>.rate_limit.limiter does not implement
 * RateLimiterInterface.
 *
 * Сервис из symfony_lib.apis.<name>.rate_limit.limiter не реализует
 * RateLimiterInterface.
 */
final class RateLimiterTypeMismatchException extends RuntimeException implements
    FrameConfigurationException
{
    public function __construct(string $apiName, mixed $service)
    {
        parent::__construct(
            sprintf(
                'symfony_lib.apis.%s.rate_limit.limiter: service must implement %s, %s given.',
                $apiName,
                RateLimiterInterface::class,
                get_debug_type($service),
            ),
        );
    }
}
