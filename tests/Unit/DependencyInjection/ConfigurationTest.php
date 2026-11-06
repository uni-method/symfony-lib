<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use UniMethod\SymfonyLib\DependencyInjection\Configuration;

final class ConfigurationTest extends TestCase
{
    public function testMinimalConfigIsNormalized(): void
    {
        self::assertSame(
            ["apis" => ["main" => ["config_path" => "/app/api.yaml", "prefix" => "/api"]]],
            $this->process(["config_path" => "/app/api.yaml", "prefix" => "/api"]),
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideInvalidApis(): iterable
    {
        yield "пустой prefix" => [["config_path" => "/app/api.yaml", "prefix" => ""], "cannot contain an empty value"];
        yield "prefix без /" => [["config_path" => "/app/api.yaml", "prefix" => "api"], 'prefix must start with "/"'];
        yield "пустой config_path" => [["config_path" => "", "prefix" => "/api"], "cannot contain an empty value"];
    }

    /**
     * @param array<string, mixed> $api
     */
    #[DataProvider("provideInvalidApis")]
    public function testInvalidApiIsRejected(array $api, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->process($api);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function provideIntervals(): iterable
    {
        yield "30 seconds" => ["30 seconds", true];
        yield "1 minute" => ["1 minute", true];
        yield "2 hours" => ["2 hours", true];
        yield "пустой" => ["", false];
        yield "abc" => ["abc", false];
        yield "-1 minute" => ["-1 minute", false];
        yield "0 seconds" => ["0 seconds", false];
    }

    /**
     * The window interval and the rate interval are checked the same way.
     *
     * Интервал окна и интервал rate проверяются одинаково.
     */
    #[DataProvider("provideIntervals")]
    public function testInterval(string $interval, bool $valid): void
    {
        $rules = [
            "fixed_window" => ["policy" => "fixed_window", "limit" => 1, "interval" => $interval],
            "token_bucket" => ["policy" => "token_bucket", "limit" => 1, "rate" => ["interval" => $interval]],
        ];

        foreach ($rules as $policy => $rule) {
            try {
                $this->process($this->withRule($rule));
                self::assertTrue($valid, $policy . ": интервал должен быть отклонён");
            } catch (InvalidConfigurationException $exception) {
                self::assertFalse($valid, $policy . ": " . $exception->getMessage());
                self::assertStringContainsString("is not a positive relative interval", $exception->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function provideIncompleteRules(): iterable
    {
        yield "fixed_window без interval" => [["policy" => "fixed_window", "limit" => 1], 'require "limit" and "interval"'];
        yield "sliding_window без limit" => [
            ["policy" => "sliding_window", "interval" => "1 minute"],
            'require "limit" and "interval"',
        ];
        yield "token_bucket без rate" => [["policy" => "token_bucket", "limit" => 1], 'requires "limit" and "rate"'];
        yield "limit 0" => [["policy" => "fixed_window", "limit" => 0, "interval" => "1 minute"], "limit"];
    }

    /**
     * @param array<string, mixed> $rule
     */
    #[DataProvider("provideIncompleteRules")]
    public function testIncompleteRuleIsRejected(array $rule, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->process($this->withRule($rule));
    }

    /**
     * @param array<string, mixed> $rule
     * @return array<string, mixed>
     */
    private function withRule(array $rule): array
    {
        return [
            "config_path" => "/app/api.yaml",
            "prefix" => "/api",
            "rate_limit" => ["anonymous" => ["post" => ["list" => $rule]]],
        ];
    }

    /**
     * @param array<string, mixed> $api
     * @return array<string, mixed>
     */
    private function process(array $api): array
    {
        return (new Processor())->processConfiguration(new Configuration(), [["apis" => ["main" => $api]]]);
    }
}
