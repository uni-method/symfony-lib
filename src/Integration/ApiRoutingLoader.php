<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Psr\Container\ContainerInterface;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;
use UniMethod\SymfonyLib\Exception\RoutingMisconfiguredException;

class ApiRoutingLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param ContainerInterface $handlers ServiceLocator: API name → ApiHandler
     * @param string[] $apiNames
     */
    public function __construct(
        private readonly ContainerInterface $handlers,
        private readonly array $apiNames,
        ?string $env = null,
    ) {
        parent::__construct($env);
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RoutingMisconfiguredException(
                'ApiRoutingLoader already loaded: resource of type "symfony_lib_multi_api" is imported more than once.',
            );
        }

        $collection = new RouteCollection();

        foreach ($this->apiNames as $apiName) {
            $handlerServiceId = sprintf("symfony_lib.api_handler.%s", $apiName);

            if (!$this->handlers->has($apiName)) {
                throw new RoutingMisconfiguredException(
                    sprintf(
                        'ApiHandler service "%s" not found.',
                        $handlerServiceId,
                    ),
                );
            }

            $handler = $this->handlers->get($apiName);

            if (!$handler instanceof ApiHandler) {
                throw new RoutingMisconfiguredException(
                    sprintf(
                        'Service "%s" must be an instance of %s.',
                        $handlerServiceId,
                        ApiHandler::class,
                    ),
                );
            }

            $apiRoutes = $handler->load($apiName, "symfony_lib_api");

            if (!$apiRoutes instanceof RouteCollection) {
                throw new RoutingMisconfiguredException(
                    sprintf(
                        'ApiHandler "%s" returned no route collection.',
                        $handlerServiceId,
                    ),
                );
            }

            $collection->addCollection($apiRoutes);
        }

        $this->loaded = true;
        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === "symfony_lib_multi_api";
    }
}
