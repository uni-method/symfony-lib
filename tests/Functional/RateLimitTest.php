<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Functional;

use UniMethod\SymfonyLib\Exception\RateLimiterTypeMismatchException;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Security\HeaderAuthenticator;

/**
 * In TestKernel the anonymous list of posts is limited to two requests per minute.
 *
 * В TestKernel анонимный list постов ограничен двумя запросами в минуту.
 */
final class RateLimitTest extends KernelTestCase
{
    public function testRequestOverLimitReturns429(): void
    {
        self::assertSame(200, $this->request("GET", "/api/post")->getStatusCode());
        self::assertSame(200, $this->request("GET", "/api/post")->getStatusCode());

        $response = $this->request("GET", "/api/post");

        self::assertSame(429, $response->getStatusCode());
        self::assertGreaterThanOrEqual(0, (int) $response->headers->get("Retry-After"));
    }

    /**
     * The rule is set only for anonymous users: an authenticated one is not limited.
     *
     * Правило задано только для анонимов: аутентифицированный не ограничен.
     */
    public function testAuthenticatedUserIsNotLimited(): void
    {
        $headers = [HeaderAuthenticator::HEADER => "42"];

        for ($i = 0; $i < 3; $i++) {
            self::assertSame(200, $this->request("GET", "/api/post", headers: $headers)->getStatusCode());
        }
    }

    public function testLimiterOfWrongTypeIsConfigurationError(): void
    {
        $this->bootKernel("bad_limiter");

        $this->expectException(RateLimiterTypeMismatchException::class);

        $this->request("GET", "/api/post", catch: false);
    }
}
