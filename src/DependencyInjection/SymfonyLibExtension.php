<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\DependencyInjection;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Console\Command\Command as ConsoleCommand;
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition};
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface as SymfonyValidatorInterface;
use UniMethod\Frame\Integration\UserProviderInterface;
use UniMethod\Frame\Persistence\EntityCollectionInterface;
use UniMethod\SymfonyLib\Exception\MissingDependencyException;
use UniMethod\SymfonyLib\RateLimit\{RateLimitListener, SymfonyRateLimiter};

/**
 * Bundle parameters, services from Resources/config and rate limit. Services of each
 * API are registered by RegisterApisPass.
 *
 * Параметры бандла, сервисы из Resources/config и rate limit. Сервисы каждого
 * API регистрирует RegisterApisPass.
 */
class SymfonyLibExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Save the configurations of all APIs
        //
        // Сохраняем конфигурации всех API
        $container->setParameter("symfony_lib.apis", $config["apis"]);

        // Repositories of list actions: the tag is set by ResolveInstanceofConditionalsPass
        // for services with autoconfigure
        //
        // Репозитории list-действий: тег ставит ResolveInstanceofConditionalsPass
        // для сервисов с autoconfigure
        $container
            ->registerForAutoconfiguration(EntityCollectionInterface::class)
            ->addTag("symfony_lib.repository.collection");

        // Load services
        //
        // Загружаем сервисы
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . "/../Resources/config"),
        );
        $loader->load("services.yaml");

        // Optional integrations: the file is loaded only if the library is installed
        //
        // Опциональные интеграции: файл грузится только если библиотека стоит
        if (interface_exists(ManagerRegistry::class)) {
            $loader->load("doctrine.yaml");
        }

        $loader->load(
            interface_exists(SymfonyValidatorInterface::class)
                ? "validator.yaml"
                : "null_validator.yaml",
        );

        if (class_exists(Security::class)) {
            $loader->load("security.yaml");
        }

        if (class_exists(ConsoleCommand::class)) {
            $loader->load("console.yaml");
        }

        if ($this->isRateLimiterAvailable()) {
            $loader->load("rate_limiter.yaml");
        }

        $this->registerRateLimit($container, $config["apis"]);

        // Save the API names for use in routing
        //
        // Сохраняем имена API для использования в роутинге
        $container->setParameter(
            "symfony_lib.api_names",
            array_map(
                static fn(int|string $v): string => (string) $v,
                array_keys($config["apis"]),
            ),
        );
    }

    /**
     * @param array<array-key, array{rate_limit?: array{
     *     limiter: ?string,
     *     anonymous: array<string, array<string, array<string, mixed>>>,
     *     authenticated: array<string, array<string, array<string, mixed>>>,
     * }}> $apis
     */
    private function registerRateLimit(
        ContainerBuilder $container,
        array $apis,
    ): void {
        $rules = [];
        $limiters = [];

        foreach ($apis as $name => $apiConfig) {
            $name = (string) $name;
            $rateLimit = $apiConfig["rate_limit"] ?? null;

            if ($rateLimit === null) {
                continue;
            }

            $apiRules = array_filter([
                RateLimitListener::ANONYMOUS =>
                    $rateLimit[RateLimitListener::ANONYMOUS],
                RateLimitListener::AUTHENTICATED =>
                    $rateLimit[RateLimitListener::AUTHENTICATED],
            ]);

            if ($apiRules === []) {
                continue;
            }

            $limiterId = $rateLimit["limiter"];

            if ($limiterId === null) {
                if (!$this->isRateLimiterAvailable()) {
                    throw MissingDependencyException::rateLimiter($name);
                }

                $limiterId = SymfonyRateLimiter::class;
            }

            $rules[$name] = $apiRules;
            $limiters[$name] = new Reference($limiterId);
        }

        if ($rules === []) {
            return;
        }

        $definition = new Definition(RateLimitListener::class);
        $definition->setArgument('$rules', $rules);
        $definition->setArgument(
            '$limiters',
            ServiceLocatorTagPass::register($container, $limiters),
        );
        $definition->setArgument(
            '$userProvider',
            new Reference(
                UserProviderInterface::class,
                ContainerBuilder::NULL_ON_INVALID_REFERENCE,
            ),
        );
        $definition->addTag("kernel.event_listener", [
            "event" => KernelEvents::REQUEST,
            "method" => "__invoke",
            "priority" => RateLimitListener::PRIORITY,
        ]);

        $container->setDefinition(
            "symfony_lib.rate_limit_listener",
            $definition,
        );
    }

    /**
     * Whether symfony/rate-limiter is present; extracted so that a test can emulate
     * the absence of the package.
     *
     * Наличие symfony/rate-limiter; вынесено, чтобы тест мог эмулировать
     * отсутствие пакета.
     */
    protected function isRateLimiterAvailable(): bool
    {
        return class_exists(RateLimiterFactory::class);
    }

    public function getAlias(): string
    {
        return "symfony_lib";
    }
}
