<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

/**
 * API request limiter. The default implementation is SymfonyRateLimiter
 * (symfony/rate-limiter); a custom one is connected with the option
 * symfony_lib.apis.<name>.rate_limit.limiter.
 *
 * Лимитер запросов API. Реализация по умолчанию — SymfonyRateLimiter
 * (symfony/rate-limiter); своя подключается опцией
 * symfony_lib.apis.<name>.rate_limit.limiter.
 */
interface RateLimiterInterface
{
    /**
     * Consumes one request of the client with the key $key according to the rule $rule.
     *
     * Списывает один запрос клиента с ключом $key по правилу $rule.
     */
    public function consume(RateLimitRule $rule, string $key): RateLimitResult;
}
