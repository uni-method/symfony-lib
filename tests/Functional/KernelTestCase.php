<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\{Request, Response};
use UniMethod\SymfonyLib\Tests\Fixtures\App\TestKernel;

/**
 * Boots TestKernel and performs requests via HttpKernel without browser-kit.
 *
 * Поднимает TestKernel и выполняет запросы через HttpKernel без browser-kit.
 */
abstract class KernelTestCase extends TestCase
{
    private ?TestKernel $kernel = null;

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    protected function bootKernel(string $variant = "default"): TestKernel
    {
        $this->kernel?->shutdown();
        $this->kernel = new TestKernel($variant);
        (new Filesystem())->remove($this->kernel->getAppCacheDir());
        $this->kernel->boot();

        return $this->kernel;
    }

    /**
     * @param array<string, string> $headers
     */
    protected function request(
        string $method,
        string $uri,
        ?string $body = null,
        array $headers = [],
        bool $catch = true,
    ): Response {
        $kernel = $this->kernel ?? $this->bootKernel();
        $server = [];
        foreach ($headers as $name => $value) {
            $server["HTTP_" . strtoupper(str_replace("-", "_", $name))] = $value;
        }

        return $kernel->handle(
            Request::create($uri, $method, [], [], [], $server, $body),
            catch: $catch,
        );
    }
}
