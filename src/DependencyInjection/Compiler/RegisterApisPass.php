<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\DependencyInjection\Compiler;

use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\{
    CompilerPassInterface,
    ServiceLocatorTagPass,
};
use Symfony\Component\DependencyInjection\{
    ContainerBuilder,
    Definition,
    Reference,
};
use UniMethod\Frame\Config\Config;
use UniMethod\Frame\Integration\{
    ApiService,
    ConstraintCheckerInterface,
    DomainExceptionObserverInterface,
    UserProviderInterface,
};
use UniMethod\Frame\Json\{ConvertToJson, ConvertToStdClass};
use UniMethod\Frame\Mapping\{
    AttributeAccessor,
    MappingFromObject,
    MappingToListAction,
    MappingToObject,
};
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
use UniMethod\Frame\Relation\TreeBuilder;
use UniMethod\Frame\Type\{Caster, TypeSpecParser};
use UniMethod\Frame\Validator\ActionValidator;
use UniMethod\SymfonyLib\Command\ValidateConfigCommand;
use UniMethod\SymfonyLib\Config\{ConfigLoaderResolver, SerializedConfig};
use UniMethod\SymfonyLib\Controller\ApiController;
use UniMethod\SymfonyLib\DependencyInjection\ImplServiceFactory;
use UniMethod\SymfonyLib\Exception\{
    ImplServiceNotFoundException,
    ImplServiceTypeMismatchException,
};
use UniMethod\SymfonyLib\Integration\{ApiHandler, ApiRoutingLoader};

/**
 * Services of each API from `symfony_lib.apis`. Order: config → impl services
 * (locators/savers/removers/mutators) → mappers and validator → ApiService →
 * routing and consumers (controller, check command).
 *
 * The config is loaded at build time: an error in it, a missing impl service or
 * a service of the wrong interface break the container build, not the first request. At
 * runtime Config is restored from the container (SerializedConfig), the file
 * is not read again; FileResource rebuilds the container when it changes.
 *
 * Сервисы каждого API из `symfony_lib.apis`. Порядок: конфиг → сервисы impl
 * (locators/savers/removers/mutators) → мапперы и валидатор → ApiService →
 * роутинг и потребители (контроллер, команда проверки).
 *
 * Конфиг загружается при сборке: ошибка в нём, отсутствующий сервис impl или
 * сервис не того интерфейса ломают сборку контейнера, а не первый запрос. В
 * рантайме Config восстанавливается из контейнера (SerializedConfig), файл
 * повторно не читается; FileResource пересобирает контейнер при его изменении.
 */
final class RegisterApisPass implements CompilerPassInterface
{
    /**
     * Above the FrameworkBundle passes with priority 0.
     *
     * Выше пассов FrameworkBundle с приоритетом 0.
     */
    public const int PRIORITY = 10;

    private const array IMPL_SECTIONS = [
        "locators" => EntityLocatorInterface::class,
        "savers" => EntitySaverInterface::class,
        "removers" => EntityRemoverInterface::class,
        "mutators" => AttributeMutatorInterface::class,
        "relationMutators" => RelationMutatorInterface::class,
    ];

    public function process(ContainerBuilder $container): void
    {
        /** @var array<string, array{config_path: string, prefix: string}> $apis */
        $apis = $container->hasParameter("symfony_lib.apis")
            ? $container->getParameter("symfony_lib.apis")
            : [];

        $apiServices = [];
        $configs = [];
        $handlers = [];

        foreach ($apis as $name => $api) {
            $name = (string) $name;

            $config = $this->registerConfig($container, $name, $api["config_path"]);
            $this->registerImplServices($container, $name, $config);
            $this->registerMappingAndValidator($container, $name);
            $this->registerApiService($container, $name);
            $this->registerApiHandler($container, $name, $api["prefix"]);

            $apiServices[$name] = new Reference($this->id("api_service", $name));
            $configs[$name] = new Reference($this->id("config", $name));
            $handlers[$name] = new Reference($this->id("api_handler", $name));
        }

        $this->registerConsumers($container, $apiServices, $configs, $handlers);
    }

    private function id(string $kind, string $apiName): string
    {
        return sprintf("symfony_lib.%s.%s", $kind, $apiName);
    }

    private function registerConfig(ContainerBuilder $container, string $name, string $configPath): Config
    {
        $loaderClass = ConfigLoaderResolver::resolve($configPath);
        $resolvedPath = $container->getParameterBag()->resolveValue($configPath);
        $resolvedPath = is_string($resolvedPath) ? $resolvedPath : $configPath;

        // Rebuild the container when the file changes
        //
        // Пересборка контейнера при изменении файла
        $container->addResource(new FileResource($resolvedPath));
        $config = (new $loaderClass())->load($resolvedPath);

        $container
            ->register($this->id("config", $name), Config::class)
            ->setFactory([SerializedConfig::class, "restore"])
            ->setArguments([SerializedConfig::dump($config)]);

        return $config;
    }

    private function registerImplServices(ContainerBuilder $container, string $name, Config $config): void
    {
        $references = [];

        foreach (self::IMPL_SECTIONS as $section => $interface) {
            /** @var array<string, string> $aliasToClass */
            $aliasToClass = $config->{$section};

            foreach ($aliasToClass as $alias => $serviceId) {
                $this->assertImplService($container, $section, $alias, $serviceId, $interface);
                $references[$serviceId] = new Reference($serviceId);
            }
        }

        $services = ServiceLocatorTagPass::register($container, $references);
        $configRef = new Reference($this->id("config", $name));

        $container
            ->register($this->id("list_locators", $name), ListLocators::class)
            ->setFactory([ImplServiceFactory::class, "createLocators"])
            ->setArguments([$configRef, $services]);

        $container
            ->register($this->id("list_savers", $name), ListSavers::class)
            ->setFactory([ImplServiceFactory::class, "createSavers"])
            ->setArguments([$configRef, $services]);

        $container
            ->register($this->id("list_removers", $name), ListRemovers::class)
            ->setFactory([ImplServiceFactory::class, "createRemovers"])
            ->setArguments([$configRef, $services]);

        $container
            ->register($this->id("mutator_registry", $name), MutatorRegistry::class)
            ->setFactory([ImplServiceFactory::class, "createMutators"])
            ->setArguments([$configRef, $services, new Reference(AttributeAccessor::class)]);
    }

    /**
     * @param class-string $interface
     * @throws ImplServiceNotFoundException|ImplServiceTypeMismatchException
     */
    private function assertImplService(
        ContainerBuilder $container,
        string $section,
        string $alias,
        string $serviceId,
        string $interface,
    ): void {
        if (!$container->has($serviceId)) {
            throw new ImplServiceNotFoundException($section, $alias, $serviceId);
        }

        $class = $container->getParameterBag()->resolveValue(
            $container->findDefinition($serviceId)->getClass() ?? $serviceId,
        );

        // A class that cannot be reflected (a factory without a class, etc.)
        // is checked by the factory on the instance
        //
        // Класс, который нельзя отразить (фабрика без класса и т.п.),
        // проверяется фабрикой на экземпляре
        $reflection = is_string($class) ? $container->getReflectionClass($class, false) : null;

        if ($reflection instanceof \ReflectionClass && !$reflection->implementsInterface($interface)) {
            throw new ImplServiceTypeMismatchException(
                $section,
                $alias,
                $serviceId,
                $interface,
                $reflection->getName(),
            );
        }
    }

    private function registerMappingAndValidator(ContainerBuilder $container, string $name): void
    {
        $configRef = new Reference($this->id("config", $name));
        $locatorsRef = new Reference($this->id("list_locators", $name));

        $container
            ->register($this->id("mapping_from_object", $name), MappingFromObject::class)
            ->setArguments([
                new Reference($this->id("mutator_registry", $name)),
                new Reference(TypeSpecParser::class),
                new Reference(Caster::class),
                $configRef,
            ]);

        $container
            ->register($this->id("mapping_to_object", $name), MappingToObject::class)
            ->setArguments([
                new Reference(TreeBuilder::class),
                new Reference(TypeSpecParser::class),
                new Reference(Caster::class),
                $locatorsRef,
                new Reference(AttributeAccessor::class),
                $configRef,
            ]);

        $container
            ->register($this->id("mapping_to_list_action", $name), MappingToListAction::class)
            ->setArguments([
                new Reference(TypeSpecParser::class),
                $locatorsRef,
                new Reference(Caster::class),
                $configRef,
            ]);

        $container
            ->register($this->id("action_validator", $name), ActionValidator::class)
            ->setArguments([new Reference(TreeBuilder::class), $configRef]);
    }

    private function registerApiService(ContainerBuilder $container, string $name): void
    {
        $definition = new Definition(ApiService::class);
        $definition->setArguments([
            '$constraintChecker' => new Reference(ConstraintCheckerInterface::class),
            '$config' => new Reference($this->id("config", $name)),
            '$treeBuilder' => new Reference(TreeBuilder::class),
            '$convertToStdClass' => new Reference(ConvertToStdClass::class),
            '$convertToJson' => new Reference(ConvertToJson::class),
            '$mappingFromObject' => new Reference($this->id("mapping_from_object", $name)),
            '$mappingToObject' => new Reference($this->id("mapping_to_object", $name)),
            '$mappingToListAction' => new Reference($this->id("mapping_to_list_action", $name)),
            '$actionValidator' => new Reference($this->id("action_validator", $name)),
            '$locators' => new Reference($this->id("list_locators", $name)),
            '$savers' => new Reference($this->id("list_savers", $name)),
            '$removers' => new Reference($this->id("list_removers", $name)),
            '$manipulators' => new Reference($this->id("mutator_registry", $name)),
            '$repositories' => new TaggedIteratorArgument("symfony_lib.repository.collection"),
        ]);

        // A user provider exists only with symfony/security-bundle,
        // an observer — if the application has declared one; otherwise the arguments
        // stay at the default null
        //
        // Провайдер пользователя есть только с symfony/security-bundle,
        // наблюдатель — если приложение его объявило; иначе аргументы
        // остаются дефолтными null
        if ($container->has(UserProviderInterface::class)) {
            $definition->setArgument('$userProvider', new Reference(UserProviderInterface::class));
        }

        if ($container->has(DomainExceptionObserverInterface::class)) {
            $definition->setArgument(
                '$domainExceptionObserver',
                new Reference(DomainExceptionObserverInterface::class),
            );
        }

        $container->setDefinition($this->id("api_service", $name), $definition);
    }

    private function registerApiHandler(ContainerBuilder $container, string $name, string $prefix): void
    {
        $container
            ->register($this->id("api_handler", $name), ApiHandler::class)
            ->setArguments([
                '$apiPrefix' => $prefix,
                '$config' => new Reference($this->id("config", $name)),
                '$apiName' => $name,
            ]);
    }

    /**
     * @param array<string, Reference> $apiServices
     * @param array<string, Reference> $configs
     * @param array<string, Reference> $handlers
     */
    private function registerConsumers(
        ContainerBuilder $container,
        array $apiServices,
        array $configs,
        array $handlers,
    ): void {
        if ($container->hasDefinition(ApiController::class)) {
            $container
                ->getDefinition(ApiController::class)
                ->setArgument('$apiServices', ServiceLocatorTagPass::register($container, $apiServices));
        }

        if ($container->hasDefinition(ValidateConfigCommand::class)) {
            $container
                ->getDefinition(ValidateConfigCommand::class)
                ->setArgument('$configs', ServiceLocatorTagPass::register($container, $configs))
                ->setArgument('$apiServices', ServiceLocatorTagPass::register($container, $apiServices));
        }

        if ($handlers === []) {
            return;
        }

        $container
            ->register("symfony_lib.api_routing_loader", ApiRoutingLoader::class)
            ->setArguments([
                '$handlers' => ServiceLocatorTagPass::register($container, $handlers),
                '$apiNames' => array_keys($handlers),
            ])
            ->addTag("routing.loader");
    }
}
