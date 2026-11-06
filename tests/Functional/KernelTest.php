<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Functional;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use Symfony\Component\DependencyInjection\ContainerInterface;
use UniMethod\SymfonyLib\Config\YamlConfigLoader;
use UniMethod\SymfonyLib\Exception\{
    ImplServiceNotFoundException,
    ImplServiceTypeMismatchException,
};
use UniMethod\SymfonyLib\Tests\Fixtures\App\Repository\PostRepository;
use UniMethod\SymfonyLib\Tests\Fixtures\App\TestKernel;

final class KernelTest extends KernelTestCase
{
    /**
     * A repository with autoconfigure gets the `symfony_lib.repository.collection` tag,
     * even if its class is not loaded before the container is built.
     *
     * Репозиторий с autoconfigure получает тег `symfony_lib.repository.collection`,
     * даже если его класс до сборки контейнера не загружен.
     */
    #[RunInSeparateProcess]
    public function testAutoconfiguredRepositoryIsTagged(): void
    {
        self::assertFalse(class_exists(PostRepository::class, false));

        $kernel = $this->bootKernel();

        self::assertContains(
            PostRepository::class,
            $kernel->getContainer()->getParameter("test.tagged.symfony_lib.repository.collection"),
        );
    }

    /**
     * Bundle services — a set for each API from symfony_lib.apis, plus
     * the route loader and the rate limit listener.
     *
     * Сервисы бандла — по набору на каждый API из symfony_lib.apis, плюс
     * загрузчик роутов и слушатель rate limit.
     */
    public function testServiceSet(): void
    {
        $ids = [];
        foreach (["main", "second"] as $api) {
            foreach (self::PER_API_SERVICES as $kind) {
                $ids[] = "symfony_lib." . $kind . "." . $api;
            }
        }

        $ids[] = "symfony_lib.api_routing_loader";
        $ids[] = "symfony_lib.rate_limit_listener";
        sort($ids);

        self::assertSame($ids, $this->bootKernel()->getContainer()->getParameter("test.service_ids"));
    }

    /**
     * The set of services does not depend on the order of APIs in the config.
     *
     * Набор сервисов не зависит от порядка API в конфиге.
     */
    public function testServiceSetDoesNotDependOnApiOrder(): void
    {
        $default = $this->bootKernel()->getContainer()->getParameter("test.service_ids");
        $swapped = $this->bootKernel("swapped")->getContainer()->getParameter("test.service_ids");

        self::assertSame($default, $swapped);
    }

    /**
     * There are no services and parameters bound to the first API.
     *
     * Нет сервисов и параметров, привязанных к первому API.
     */
    public function testNoServicesOfFirstApi(): void
    {
        $container = $this->bootKernel()->getContainer();

        self::assertSame(
            [],
            array_keys(array_filter((array) $container->getParameter("test.has"))),
        );
        self::assertFalse($container->hasParameter("symfony_lib.api_config_path"));
        self::assertFalse($container->hasParameter("symfony_lib.api_prefix"));
    }

    /**
     * A service from impl that is not registered in the container is a build error.
     *
     * Сервис из impl, не зарегистрированный в контейнере, — ошибка сборки.
     */
    public function testUnregisteredImplServiceFailsContainerBuild(): void
    {
        $this->expectException(ImplServiceNotFoundException::class);
        $this->expectExceptionMessage('impl.locators alias "unregistered"');

        $this->bootKernel("broken");
    }

    /**
     * A service from impl with a class of the wrong interface is a build error.
     *
     * Сервис из impl с классом не того интерфейса — ошибка сборки.
     */
    public function testImplServiceOfWrongTypeFailsContainerBuild(): void
    {
        $this->expectException(ImplServiceTypeMismatchException::class);
        $this->expectExceptionMessage('impl.locators alias "memory"');

        $this->bootKernel("mismatch");
    }

    /**
     * At runtime the config is taken from the container: the file is not read after the build,
     * and the restored Config equals the result of the loader.
     *
     * В рантайме конфиг берётся из контейнера: файл после сборки не читается,
     * а восстановленный Config равен результату загрузчика.
     */
    public function testConfigIsNotReadFromFileAtRuntime(): void
    {
        $kernel = $this->bootKernel("detached");
        unlink($kernel->getDetachedConfigPath());

        self::assertSame(200, $this->request("GET", "/api/post/1")->getStatusCode());

        $testContainer = $kernel->getContainer()->get("test.service_container");
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        self::assertEquals(
            (new YamlConfigLoader())->load(TestKernel::MAIN_CONFIG),
            $testContainer->get("symfony_lib.config.main"),
        );
    }

    private const array PER_API_SERVICES = [
        "action_validator",
        "api_handler",
        "api_service",
        "config",
        "list_locators",
        "list_removers",
        "list_savers",
        "mapping_from_object",
        "mapping_to_list_action",
        "mapping_to_object",
        "mutator_registry",
    ];
}
