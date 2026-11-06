<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\{RequestContext, RouteCollection};
use UniMethod\Frame\Config\{Action, ActionType, Attribute, Config, Model};
use UniMethod\SymfonyLib\Controller\ApiController;
use UniMethod\SymfonyLib\Exception\RoutingMisconfiguredException;
use UniMethod\SymfonyLib\Integration\ApiHandler;

final class ApiHandlerTest extends TestCase
{
    public function testRouteForEachAction(): void
    {
        $routes = $this->routes();

        $actual = [];
        foreach ($routes as $name => $route) {
            $actual[$name] = [$route->getMethods(), $route->getPath(), $route->getRequirements(), $route->getDefaults()];
        }

        ksort($actual);

        $defaults = static fn (string $type): array => [
            "_controller" => ApiController::class . "::handle",
            "_model" => "user",
            "_actionType" => $type,
            "_api_name" => "main",
        ];
        $id = ["id" => "[^/]+"];

        self::assertSame(
            [
                "symfony_lib_api_main_user_create" => [["POST"], "/api/user", [], $defaults("create")],
                "symfony_lib_api_main_user_delete" => [["DELETE"], "/api/user/{id}", $id, $defaults("delete")],
                "symfony_lib_api_main_user_list" => [["GET"], "/api/user", [], $defaults("list")],
                "symfony_lib_api_main_user_read" => [["GET"], "/api/user/{id}", $id, $defaults("read")],
                "symfony_lib_api_main_user_update" => [["PATCH"], "/api/user/{id}", $id, $defaults("update")],
            ],
            $actual,
        );
    }

    /**
     * Routes with the same path are distinguished by method: the order of routes does not matter.
     *
     * Роуты с одним путём различает метод: порядок роутов не важен.
     */
    public function testMatchingByPathAndMethod(): void
    {
        $routes = $this->routes();

        $patch = (new UrlMatcher($routes, new RequestContext(method: "PATCH")))->match("/api/user/42");
        self::assertSame("update", $patch["_actionType"]);
        self::assertSame("42", $patch["id"]);

        $get = (new UrlMatcher($routes, new RequestContext(method: "GET")))->match("/api/user");
        self::assertSame("list", $get["_actionType"]);
    }

    public function testSecondLoadIsRoutingMisconfiguration(): void
    {
        $handler = $this->handler();
        $handler->load("main", "symfony_lib_api");

        $this->expectException(RoutingMisconfiguredException::class);

        $handler->load("main", "symfony_lib_api");
    }

    private function routes(): RouteCollection
    {
        $routes = $this->handler()->load("main", "symfony_lib_api");
        self::assertInstanceOf(RouteCollection::class, $routes);

        return $routes;
    }

    private function handler(): ApiHandler
    {
        $config = new Config(
            models: [
                new Model(class: "App\\Entity\\User", type: "user", attributes: [new Attribute(name: "id", type: "int")]),
            ],
            actions: array_map(
                static fn (ActionType $type): Action => new Action(type: $type, model: "user"),
                ActionType::cases(),
            ),
        );

        return new ApiHandler("/api/", $config, "main");
    }
}
