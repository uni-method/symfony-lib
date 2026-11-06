<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\DependencyInjection;

use Psr\Container\ContainerInterface;
use UniMethod\Frame\Config\Config;
use UniMethod\Frame\Mapping\AttributeAccessor;
use UniMethod\Frame\Mutator\{
    AttributeMutatorInterface,
    MutatorRegistry,
    RelationMutatorInterface,
};
use UniMethod\Frame\Persistence\{
    EntityLocatorInterface,
    EntityRemoverInterface,
    EntitySaverInterface,
    ListLocators,
    ListRemovers,
    ListSavers,
};
use UniMethod\SymfonyLib\Exception\{
    ImplServiceNotFoundException,
    ImplServiceTypeMismatchException,
};

/**
 * Factories of the API service containers from the `impl` dictionaries. `$services` is a
 * ServiceLocator with the services of these dictionaries by their id (class name); existence and
 * interface are already checked when the container is built, here the check is repeated
 * on the instance.
 *
 * Фабрики контейнеров сервисов API из словарей `impl`. `$services` —
 * ServiceLocator с сервисами этих словарей по их id (имени класса); наличие и
 * интерфейс уже проверены при сборке контейнера, здесь проверка повторяется
 * на экземпляре.
 */
final class ImplServiceFactory
{
    public static function createLocators(Config $config, ContainerInterface $services): ListLocators
    {
        /** @var array<string, EntityLocatorInterface> $locators */
        $locators = self::resolve($config->locators, $services, "locators", EntityLocatorInterface::class);

        return new ListLocators($locators);
    }

    public static function createSavers(Config $config, ContainerInterface $services): ListSavers
    {
        /** @var array<string, EntitySaverInterface> $savers */
        $savers = self::resolve($config->savers, $services, "savers", EntitySaverInterface::class);

        return new ListSavers($savers);
    }

    public static function createRemovers(Config $config, ContainerInterface $services): ListRemovers
    {
        /** @var array<string, EntityRemoverInterface> $removers */
        $removers = self::resolve($config->removers, $services, "removers", EntityRemoverInterface::class);

        return new ListRemovers($removers);
    }

    public static function createMutators(
        Config $config,
        ContainerInterface $services,
        AttributeAccessor $attributeAccessor,
    ): MutatorRegistry {
        /** @var array<string, AttributeMutatorInterface> $mutators */
        $mutators = self::resolve($config->mutators, $services, "mutators", AttributeMutatorInterface::class);

        /** @var array<string, RelationMutatorInterface> $relationMutators */
        $relationMutators = self::resolve(
            $config->relationMutators,
            $services,
            "relationMutators",
            RelationMutatorInterface::class,
        );

        return new MutatorRegistry($mutators, $attributeAccessor, $relationMutators, $config);
    }

    /**
     * @param array<string, string> $aliasToClass
     * @param class-string $expectedInterface
     * @return array<string, object>
     * @throws ImplServiceNotFoundException|ImplServiceTypeMismatchException
     */
    private static function resolve(
        array $aliasToClass,
        ContainerInterface $services,
        string $section,
        string $expectedInterface,
    ): array {
        $resolved = [];

        foreach ($aliasToClass as $alias => $class) {
            if (!$services->has($class)) {
                throw new ImplServiceNotFoundException($section, $alias, $class);
            }

            $service = $services->get($class);

            if (!is_object($service) || !$service instanceof $expectedInterface) {
                throw new ImplServiceTypeMismatchException(
                    $section,
                    $alias,
                    $class,
                    $expectedInterface,
                    get_debug_type($service),
                );
            }

            $resolved[$alias] = $service;
        }

        return $resolved;
    }
}
