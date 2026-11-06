<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App;

use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\DependencyInjection\Compiler\{CompilerPassInterface, PassConfig};
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use UniMethod\Frame\Config\Config;
use UniMethod\Frame\Mapping\{MappingFromObject, MappingToListAction, MappingToObject};
use UniMethod\Frame\Mutator\MutatorRegistry;
use UniMethod\Frame\Persistence\{
    EntityLocatorContainerInterface,
    ListLocators,
    ListRemovers,
    ListSavers,
};
use UniMethod\Frame\Validator\ActionValidator;
use UniMethod\SymfonyLib\SymfonyLibBundle;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Log\MemoryLogger;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Persistence\{
    InMemoryLocator,
    InMemoryRemover,
    InMemorySaver,
};
use UniMethod\SymfonyLib\Tests\Fixtures\App\RateLimit\NotALimiter;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Repository\PostRepository;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Security\HeaderAuthenticator;

/**
 * A minimal application with the bundle. Configuration variants:
 *
 * - `default` — API `main` (YAML, /api, rate limit on the anonymous list of posts)
 *   and `second` (JSON, /internal, Doctrine adapters without an entity manager);
 * - `swapped` — the same APIs as in `default`, in reverse order;
 * - `broken` — APIs `main` and `broken`, whose locator is not registered as a service;
 * - `mismatch` — API `main`, whose locator is registered with the saver class;
 * - `bad_limiter` — API `main` with a limiter not implementing RateLimiterInterface;
 * - `detached` — API `main` with a config from a copy in a temporary directory, which
 *   the test may delete after the kernel is loaded.
 *
 * Application services are private. Before unused services are removed, what
 * is not visible after compilation is written into parameters:
 * `test.tagged.<tag>` — ids with a tag from RECORDED_TAGS, `test.service_ids` — ids
 * with the `symfony_lib.` prefix, `test.has` — presence of ids from RECORDED_IDS.
 *
 * Минимальное приложение с бандлом. Варианты конфигурации:
 *
 * - `default` — API `main` (YAML, /api, rate limit на анонимный list постов)
 *   и `second` (JSON, /internal, Doctrine-адаптеры без entity manager);
 * - `swapped` — те же API, что в `default`, в обратном порядке;
 * - `broken` — API `main` и `broken`, чей локатор не зарегистрирован сервисом;
 * - `mismatch` — API `main`, чей локатор зарегистрирован классом сейвера;
 * - `bad_limiter` — API `main` с лимитером, не реализующим RateLimiterInterface;
 * - `detached` — API `main` с конфигом из копии во временном каталоге, которую
 *   тест может удалить после загрузки ядра.
 *
 * Сервисы приложения приватные. До удаления неиспользуемых сервисов в
 * параметры записывается то, что после компиляции не видно:
 * `test.tagged.<тег>` — id с тегом из RECORDED_TAGS, `test.service_ids` — id
 * с префиксом `symfony_lib.`, `test.has` — наличие id из RECORDED_IDS.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public const array RECORDED_TAGS = ["symfony_lib.repository.collection"];

    /**
     * Ids of "global" services of the first API that must not exist.
     *
     * Id «глобальных» сервисов первого API, которых быть не должно.
     */
    public const array RECORDED_IDS = [
        Config::class,
        EntityLocatorContainerInterface::class,
        MappingFromObject::class,
        MappingToObject::class,
        MappingToListAction::class,
        ActionValidator::class,
        ListLocators::class,
        ListSavers::class,
        ListRemovers::class,
        MutatorRegistry::class,
    ];

    public function __construct(private readonly string $variant = "default")
    {
        parent::__construct("test", true);

        if ($variant === "detached") {
            @mkdir($this->getProjectDir(), 0o777, true);
            copy(self::MAIN_CONFIG, $this->getDetachedConfigPath());
        }
    }

    public const string MAIN_CONFIG = __DIR__ . "/config/api_main.yaml";

    public function getDetachedConfigPath(): string
    {
        return $this->getProjectDir() . "/api_main.yaml";
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new SymfonyLibBundle();
    }

    /**
     * Outside the repository: the kernel writes generated files into the project directory.
     *
     * Вне репозитория: ядро пишет в каталог проекта сгенерированные файлы.
     */
    public function getProjectDir(): string
    {
        return sys_get_temp_dir() . "/symfony-lib-tests/" . getmypid() . "/" . $this->variant;
    }

    /**
     * The cache.app storage (limiter state); tests clear it before starting the kernel.
     *
     * Хранилище cache.app (состояние лимитера); тесты чистят его перед запуском ядра.
     */
    public function getAppCacheDir(): string
    {
        return $this->getProjectDir() . "/var/app-cache";
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . "/var/cache";
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . "/var/log";
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension("framework", [
            "secret" => "test",
            "test" => true,
            "http_method_override" => false,
            "validation" => ["enabled" => true],
            // Not array: services_resetter clears it between requests, and
            // the limiter state would not survive until the next request
            //
            // Не array: services_resetter очищает его между запросами, и
            // состояние лимитера не доживает до следующего запроса
            "cache" => [
                "app" => "cache.adapter.filesystem",
                "directory" => "%kernel.project_dir%/var/app-cache",
            ],
        ]);
        $container->extension("security", [
            "providers" => ["none" => ["memory" => null]],
            "firewalls" => [
                "main" => [
                    "stateless" => true,
                    "provider" => "none",
                    "custom_authenticators" => [HeaderAuthenticator::class],
                ],
            ],
        ]);
        $container->extension("symfony_lib", ["apis" => $this->apis()]);

        $services = $container->services();
        // There is no DoctrineBundle in the tests: the registry is substituted in the test
        //
        // DoctrineBundle в тестах нет: реестр подставляется в тесте
        $services->set(ManagerRegistry::class)->synthetic()->public();
        // Registration by a single class, without resource: the class is not loaded
        // until AutowirePass
        //
        // Регистрация по одному классу, без resource: класс не загружается
        // до AutowirePass
        $services->set(PostRepository::class)->autoconfigure();
        $services->set(InMemoryLocator::class, $this->variant === "mismatch" ? InMemorySaver::class : null);
        $services->set(InMemorySaver::class);
        $services->set(InMemoryRemover::class);
        $services->set(HeaderAuthenticator::class);
        $services->set(NotALimiter::class);
        $services->set("logger", MemoryLogger::class)->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(".", "symfony_lib_multi_api");
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(
            new class () implements CompilerPassInterface {
                public function process(ContainerBuilder $container): void
                {
                    foreach (TestKernel::RECORDED_TAGS as $tag) {
                        $container->setParameter(
                            "test.tagged." . $tag,
                            array_keys($container->findTaggedServiceIds($tag)),
                        );
                    }

                    $ids = array_merge(
                        array_keys($container->getDefinitions()),
                        array_keys($container->getAliases()),
                    );
                    $ids = array_values(array_filter(
                        $ids,
                        static fn (string $id): bool => str_starts_with($id, "symfony_lib."),
                    ));
                    sort($ids);
                    $container->setParameter("test.service_ids", $ids);

                    $has = [];
                    foreach (TestKernel::RECORDED_IDS as $id) {
                        $has[$id] = $container->has($id);
                    }

                    $container->setParameter("test.has", $has);
                }
            },
            PassConfig::TYPE_BEFORE_REMOVING,
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function apis(): array
    {
        $main = [
            "config_path" => $this->variant === "detached"
                ? $this->getDetachedConfigPath()
                : self::MAIN_CONFIG,
            "prefix" => "/api",
            "rate_limit" => [
                "anonymous" => [
                    "post" => [
                        "list" => [
                            "policy" => "fixed_window",
                            "limit" => 2,
                            "interval" => "1 minute",
                        ],
                    ],
                ],
            ],
        ];

        $second = [
            "config_path" => __DIR__ . "/config/api_second.json",
            "prefix" => "/internal",
        ];

        return match ($this->variant) {
            "swapped" => ["second" => $second, "main" => $main],
            "mismatch", "detached" => ["main" => $main],
            "broken" => [
                "main" => $main,
                "broken" => [
                    "config_path" => __DIR__ . "/config/api_broken.json",
                    "prefix" => "/broken",
                ],
            ],
            "bad_limiter" => [
                "main" => array_replace_recursive(
                    $main,
                    ["rate_limit" => ["limiter" => NotALimiter::class]],
                ),
            ],
            default => ["main" => $main, "second" => $second],
        };
    }
}
