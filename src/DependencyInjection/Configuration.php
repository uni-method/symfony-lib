<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\DependencyInjection;

use DateInterval;
use DateTimeImmutable;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\ScalarNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Throwable;
use UniMethod\Frame\Config\ActionType;
use UniMethod\SymfonyLib\RateLimit\{
    RateLimiterInterface,
    RateLimitListener,
    RateLimitPolicy,
};

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder("symfony_lib");
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
            ->arrayNode("apis")
            ->info("Multiple API configurations")
            ->defaultValue([])
            ->useAttributeAsKey("name")
            ->arrayPrototype()
            ->children()
            ->scalarNode("config_path")
            ->isRequired()
            ->cannotBeEmpty()
            ->info("Path to api.json configuration file")
            ->end()
            ->scalarNode("prefix")
            ->isRequired()
            ->cannotBeEmpty()
            ->info("API route prefix (e.g., /api/v1, /api/internal)")
            ->validate()
            ->ifTrue(static fn(mixed $prefix): bool => !is_string($prefix) || !str_starts_with($prefix, "/"))
            ->thenInvalid('prefix must start with "/", %s given')
            ->end()
            ->end()
            ->append($this->rateLimitNode())
            ->end()
            ->end()
            ->end()
            ->end();

        return $treeBuilder;
    }

    private function rateLimitNode(): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition("rate_limit");

        $node
            ->info("Rate limits: user type → model → action → rule. No rule — no limit")
            ->children()
            ->scalarNode("limiter")
            ->defaultNull()
            ->info(
                "Service id implementing " .
                    RateLimiterInterface::class .
                    "; null — SymfonyRateLimiter (symfony/rate-limiter)",
            )
            ->end()
            ->append($this->userTypeNode(RateLimitListener::ANONYMOUS))
            ->append($this->userTypeNode(RateLimitListener::AUTHENTICATED))
            ->end();

        return $node;
    }

    private function userTypeNode(string $name): ArrayNodeDefinition
    {
        $node = new ArrayNodeDefinition($name);

        $actions = $node
            ->useAttributeAsKey("model")
            ->normalizeKeys(false)
            ->arrayPrototype()
            ->children();

        foreach (ActionType::cases() as $actionType) {
            $actions->append($this->ruleNode($actionType->value));
        }

        return $node;
    }

    private function ruleNode(string $name): ArrayNodeDefinition
    {
        $windowPolicies = [
            RateLimitPolicy::FixedWindow->value,
            RateLimitPolicy::SlidingWindow->value,
        ];

        $node = new ArrayNodeDefinition($name);

        $node
            ->children()
            ->enumNode("policy")
            ->values(
                array_map(
                    static fn(RateLimitPolicy $policy): string => $policy->value,
                    RateLimitPolicy::cases(),
                ),
            )
            ->isRequired()
            ->end()
            ->integerNode("limit")
            ->min(1)
            ->end()
            ->append($this->intervalNode())
            ->arrayNode("rate")
            ->children()
            ->append($this->intervalNode()->isRequired())
            ->integerNode("amount")
            ->min(1)
            ->defaultValue(1)
            ->end()
            ->end()
            ->end()
            ->end();

        $node
            ->validate()
            ->ifTrue(
                static fn(array $rule): bool => in_array(
                    $rule["policy"],
                    $windowPolicies,
                    true,
                ) && (!isset($rule["limit"]) || !isset($rule["interval"])),
            )
            ->thenInvalid('Policies "fixed_window" and "sliding_window" require "limit" and "interval": %s')
            ->end()
            ->validate()
            ->ifTrue(
                static fn(array $rule): bool =>
                    $rule["policy"] === RateLimitPolicy::TokenBucket->value &&
                    (!isset($rule["limit"]) || !isset($rule["rate"])),
            )
            ->thenInvalid('Policy "token_bucket" requires "limit" and "rate": %s')
            ->end();

        return $node;
    }

    /**
     * An interval in the PHP relative format (`'30 seconds'`, `'1 minute'`) —
     * the same parsing as in symfony/rate-limiter; it is checked when the container is built,
     * not on the first request to the rate-limited route.
     *
     * Интервал в относительном формате PHP (`'30 seconds'`, `'1 minute'`) —
     * тот же разбор, что у symfony/rate-limiter; проверяется при сборке
     * контейнера, а не на первом запросе к лимитируемому роуту.
     */
    private function intervalNode(): ScalarNodeDefinition
    {
        $node = new ScalarNodeDefinition("interval");

        $node
            ->validate()
            ->ifTrue(static fn(mixed $interval): bool => !self::isPositiveInterval($interval))
            ->thenInvalid('Interval %s is not a positive relative interval like "30 seconds" or "1 minute"')
            ->end();

        return $node;
    }

    private static function isPositiveInterval(mixed $interval): bool
    {
        if (!is_string($interval) || trim($interval) === "") {
            return false;
        }

        try {
            // Before PHP 8.3 — false with a warning, since 8.3 — an exception
            //
            // До PHP 8.3 — false с предупреждением, с 8.3 — исключение
            /** @var DateInterval|false $parsed */
            $parsed = @DateInterval::createFromDateString($interval);
        } catch (Throwable) {
            return false;
        }

        if (!$parsed instanceof DateInterval) {
            return false;
        }

        $zero = new DateTimeImmutable("@0");

        return $zero->add($parsed) > $zero;
    }
}
