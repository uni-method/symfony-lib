<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Functional;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RouterInterface;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Log\MemoryLogger;

final class ApiRoutingTest extends KernelTestCase
{
    /**
     * One route per action for each API, with its own prefix.
     *
     * По роуту на действие для каждого API, со своим префиксом.
     */
    public function testRoutesOfTwoApis(): void
    {
        $router = $this->bootKernel()->getContainer()->get("router");
        self::assertInstanceOf(RouterInterface::class, $router);

        $routes = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (str_starts_with($name, "symfony_lib_api_")) {
                $routes[$name] = [$route->getMethods(), $route->getPath()];
                self::assertSame(
                    ["_controller", "_model", "_actionType", "_api_name"],
                    array_keys($route->getDefaults()),
                    $name,
                );
            }
        }

        ksort($routes);

        self::assertSame(
            [
                "symfony_lib_api_main_post_create" => [["POST"], "/api/post"],
                "symfony_lib_api_main_post_delete" => [["DELETE"], "/api/post/{id}"],
                "symfony_lib_api_main_post_list" => [["GET"], "/api/post"],
                "symfony_lib_api_main_post_read" => [["GET"], "/api/post/{id}"],
                "symfony_lib_api_main_post_update" => [["PATCH"], "/api/post/{id}"],
                "symfony_lib_api_second_ghost_create" => [["POST"], "/internal/ghost"],
                "symfony_lib_api_second_ghost_delete" => [["DELETE"], "/internal/ghost/{id}"],
                "symfony_lib_api_second_ghost_list" => [["GET"], "/internal/ghost"],
                "symfony_lib_api_second_ghost_read" => [["GET"], "/internal/ghost/{id}"],
                "symfony_lib_api_second_ghost_update" => [["PATCH"], "/internal/ghost/{id}"],
            ],
            $routes,
        );
    }

    public function testReadReturnsEntity(): void
    {
        $response = $this->request("GET", "/api/post/1");

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ["id" => "1", "title" => "First"],
            json_decode((string) $response->getContent(), true),
        );
    }

    public function testReadOfMissingEntityReturns404(): void
    {
        self::assertSame(404, $this->request("GET", "/api/post/2")->getStatusCode());
    }

    public function testUpdateWithInvalidBodyReturns422(): void
    {
        $response = $this->request("PATCH", "/api/post/1", '{"unknown": 1}');

        self::assertSame(422, $response->getStatusCode());
    }

    /**
     * An entity without an entity manager is a configuration error: 500 and the entry
     * "API configuration error" in the log.
     *
     * Сущность без entity manager — ошибка конфигурации: 500 и запись
     * «API configuration error» в лог.
     */
    public function testUnmappedEntityIsConfigurationError(): void
    {
        $container = $this->bootKernel()->getContainer();
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn(null);
        $container->set(ManagerRegistry::class, $registry);

        $response = $this->request("GET", "/internal/ghost/1");

        self::assertSame(500, $response->getStatusCode());
        $logger = $container->get("logger");
        self::assertInstanceOf(MemoryLogger::class, $logger);
        self::assertContains(
            "API configuration error",
            array_column($logger->records, "message"),
        );
    }
}
