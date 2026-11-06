<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Config;

use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;
use UniMethod\Frame\Config\{Action, ActionType, Attribute, Config, Model};
use UniMethod\Frame\Mapping\{AttributeAccessor, MappingFromObject, MappingToObject};
use UniMethod\Frame\Mutator\MutatorRegistry;
use UniMethod\Frame\Persistence\ListLocators;
use UniMethod\Frame\Relation\TreeBuilder;
use UniMethod\Frame\Type\{Caster, TypeSpecParser};
use UniMethod\SymfonyLib\Config\ConfigChecker;
use UniMethod\SymfonyLib\Tests\Fixtures\Entity\{Post, Sealed, Strict};

/**
 * ConfigChecker and the framework runtime are consistent: on the same model
 * the checker reports an error exactly when the framework mapper fails.
 *
 * ConfigChecker и рантайм фреймворка согласованы: на одной и той же модели
 * чекер сообщает об ошибке ровно тогда, когда маппер фреймворка падает.
 */
final class ConfigCheckerRuntimeSyncTest extends TestCase
{
    /**
     * @return iterable<string, array{Model, ActionType, Closure(Config): mixed, bool}>
     */
    public static function provideCases(): iterable
    {
        $id = new Attribute(name: "id", type: "string|null");

        yield "getter: публичное свойство" => [
            new Model(class: Post::class, type: "m", attributes: [$id, new Attribute(name: "title", type: "string|null")]),
            ActionType::Read,
            static fn (Config $config): mixed => self::render($config, new Post()),
            false,
        ];
        yield "getter: приватный метод" => [
            new Model(class: Sealed::class, type: "m", attributes: [$id, new Attribute(name: "secret", type: "string", getter: "getSecret")]),
            ActionType::Read,
            static fn (Config $config): mixed => self::render($config, new Sealed()),
            true,
        ];
        yield "setter: публичное свойство" => [
            new Model(class: Post::class, type: "m", attributes: [$id, new Attribute(name: "title", type: "string|null")]),
            ActionType::Create,
            static fn (Config $config): mixed => self::input($config, (object) ["title" => "x"]),
            false,
        ];
        yield "setter: readonly-свойство" => [
            new Model(class: Sealed::class, type: "m", attributes: [$id, new Attribute(name: "code", type: "string", getter: false)]),
            ActionType::Create,
            static fn (Config $config): mixed => self::input($config, (object) ["code" => "x"]),
            true,
        ];
        yield "конструктор: обязательный параметр без constructorParam" => [
            new Model(class: Strict::class, type: "m", attributes: [$id, new Attribute(name: "name", type: "string")]),
            ActionType::Create,
            static fn (Config $config): mixed => self::input($config, (object) ["name" => "x"]),
            true,
        ];
        yield "конструктор: параметр объявлен constructorParam" => [
            new Model(class: Strict::class, type: "m", attributes: [$id, new Attribute(name: "name", type: "string", constructorParam: true)]),
            ActionType::Create,
            static fn (Config $config): mixed => self::input($config, (object) ["name" => "x"]),
            false,
        ];
    }

    /**
     * @param Closure(Config): mixed $runtime
     */
    #[DataProvider("provideCases")]
    public function testCheckerAgreesWithRuntime(Model $model, ActionType $type, Closure $runtime, bool $broken): void
    {
        $config = new Config(models: [$model], actions: [new Action(type: $type, model: "m")]);

        $issues = (new ConfigChecker())->check($config);

        try {
            $runtime($config);
            $failed = false;
        } catch (Throwable) {
            $failed = true;
        }

        self::assertSame($broken, $issues !== [], "ConfigChecker: " . implode("; ", $issues));
        self::assertSame($broken, $failed, "рантайм фреймворка");
    }

    private static function render(Config $config, object $entity): object
    {
        $mutators = new MutatorRegistry([], new AttributeAccessor(), [], $config);

        return (new MappingFromObject($mutators, new TypeSpecParser(), new Caster(), $config))
            ->map("m", $entity, [], ActionType::Read);
    }

    private static function input(Config $config, object $body): object
    {
        return (new MappingToObject(
            new TreeBuilder(),
            new TypeSpecParser(),
            new Caster(),
            new ListLocators([]),
            new AttributeAccessor(),
            $config,
        ))->map("m", $body, ActionType::Create, "");
    }
}
