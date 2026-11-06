<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\RateLimit;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * An adapter over symfony/rate-limiter. Without a LockFactory, parallel requests
 * of one client may be consumed without locking.
 *
 * Адаптер над symfony/rate-limiter. Без LockFactory параллельные запросы
 * одного клиента могут списываться без блокировки.
 */
final class SymfonyRateLimiter implements RateLimiterInterface
{
    /** @var array<string, RateLimiterFactory> */
    private array $factories = [];

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly ?LockFactory $lockFactory = null,
    ) {
    }

    public function consume(RateLimitRule $rule, string $key): RateLimitResult
    {
        $rateLimit = $this->factory($rule)->create($key)->consume();

        return new RateLimitResult(
            $rateLimit->isAccepted(),
            $rateLimit->getRetryAfter(),
        );
    }

    private function factory(RateLimitRule $rule): RateLimiterFactory
    {
        if (isset($this->factories[$rule->id])) {
            return $this->factories[$rule->id];
        }

        $config = [
            "id" => $rule->id,
            "policy" => $rule->policy->value,
        ];

        if ($rule->limit !== null) {
            $config["limit"] = $rule->limit;
        }

        if ($rule->interval !== null) {
            $config["interval"] = $rule->interval;
        }

        if ($rule->rateInterval !== null) {
            $config["rate"] = [
                "interval" => $rule->rateInterval,
                "amount" => $rule->rateAmount,
            ];
        }

        return $this->factories[$rule->id] = new RateLimiterFactory(
            $config,
            new CacheStorage($this->cache),
            $this->lockFactory,
        );
    }
}
