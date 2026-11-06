<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use UniMethod\SymfonyLib\Controller\ApiController;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\{Route, RouteCollection};
use UniMethod\Frame\Config\{ActionType, Config};
use UniMethod\SymfonyLib\Exception\RoutingMisconfiguredException;

class ApiHandler extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private readonly string $apiPrefix,
        private readonly Config $config,
        private readonly string $apiName,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): mixed
    {
        if ($this->loaded) {
            throw new RoutingMisconfiguredException(
                sprintf('ApiHandler for API "%s" already loaded.', $this->apiName),
            );
        }

        $routes = new RouteCollection();

        foreach ($this->config->actions as $action) {
            [$method, $suffix, $requirements] = $this->route($action->type);

            $routes->add(
                sprintf(
                    "symfony_lib_api_%s_%s_%s",
                    $this->apiName,
                    $action->model,
                    $action->type->value,
                ),
                new Route(
                    rtrim($this->apiPrefix, "/") . "/" . $action->model . $suffix,
                    [
                        "_controller" => ApiController::class . "::handle",
                        "_model" => $action->model,
                        "_actionType" => $action->type->value,
                        "_api_name" => $this->apiName,
                    ],
                    $requirements,
                    methods: [$method],
                ),
            );
        }

        $this->loaded = true;

        return $routes;
    }

    /**
     * Action type → [HTTP method, path suffix, requirements]. Routes with the same
     * path differ by method, so the order of routes does not affect matching.
     *
     * Тип действия → [HTTP-метод, суффикс пути, requirements]. Роуты с одним
     * путём различаются методом, поэтому порядок роутов на матчинг не влияет.
     *
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private function route(ActionType $type): array
    {
        return match ($type) {
            ActionType::Create => ["POST", "", []],
            ActionType::List => ["GET", "", []],
            ActionType::Read => ["GET", "/{id}", ["id" => "[^/]+"]],
            ActionType::Update => ["PATCH", "/{id}", ["id" => "[^/]+"]],
            ActionType::Delete => ["DELETE", "/{id}", ["id" => "[^/]+"]],
        };
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === "symfony_lib_api";
    }
}
