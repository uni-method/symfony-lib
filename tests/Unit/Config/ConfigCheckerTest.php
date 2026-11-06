<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Config;

use Doctrine\Persistence\{ManagerRegistry, ObjectManager};
use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Config\{
    Action,
    ActionType,
    Attribute,
    Config,
    Model,
    Relation,
    RelationWriteMode,
    WritableConfig,
};
use UniMethod\Frame\Config\ListAction\ListActionConfig;
use UniMethod\SymfonyLib\Config\ConfigChecker;
use UniMethod\SymfonyLib\Constraint\{OwnedByCurrentUser, ScopedToCurrentUser};
use UniMethod\SymfonyLib\Integration\{DoctrineLocator, DoctrineSaver};
use UniMethod\SymfonyLib\Tests\Fixtures\Entity\{
    AbstractModel,
    Account,
    Guarded,
    Post,
    PostListRequest,
    ScalarGuarded,
    Sealed,
    Strict,
    Writer,
};

/**
 * One test per ConfigChecker message. Config is assembled directly, without
 * the loader: the framework loader would reject some of the cases earlier.
 *
 * По тесту на каждое сообщение ConfigChecker. Config собирается напрямую, без
 * загрузчика: часть случаев загрузчик фреймворка отклонил бы раньше.
 */
final class ConfigCheckerTest extends TestCase
{
    public function testValidConfigHasNoIssues(): void
    {
        $config = new Config(
            models: [$this->post(), $this->writer(), $this->account()],
            actions: [
                new Action(
                    type: ActionType::Create,
                    model: "post",
                    writable: ["author" => new WritableConfig(RelationWriteMode::Reference)],
                ),
                new Action(type: ActionType::Read, model: "post", entityLocator: "loc"),
                new Action(type: ActionType::Read, model: "writer", entityLocator: "loc"),
            ],
            userModel: "account",
        );

        self::assertSame([], $this->check($config));
    }

    public function testMissingModelClass(): void
    {
        $config = new Config(models: [new Model(class: "App\\Missing", type: "ghost")]);

        self::assertSame(["Model 'ghost': class 'App\\Missing' does not exist"], $this->check($config));
    }

    public function testGetterIsNotCallable(): void
    {
        $config = $this->single($this->sealed([new Attribute(name: "secret", type: "string", getter: "getSecret")]));

        self::assertSame(
            ["Model 'sealed' attribute 'secret': getter 'getSecret' is neither a public method callable without arguments nor a public property of " . Sealed::class],
            $this->check($config),
        );
    }

    public function testGetterIsNotSetAndNoPublicProperty(): void
    {
        $config = $this->single($this->sealed([new Attribute(name: "hidden", type: "string")]));

        self::assertSame(
            ["Model 'sealed' attribute 'hidden': getter is not set and " . Sealed::class . " has no public property 'hidden' — the value is silently omitted from responses. Set 'getter' or 'getter: false'"],
            $this->check($config),
        );
    }

    public function testAttributeSetterIsNotWritable(): void
    {
        $config = $this->written($this->sealed([new Attribute(name: "code", type: "string", setter: "code")]));

        self::assertSame(
            ["Model 'sealed' attribute 'code': setter 'code' is neither a public method taking one argument nor a public writable property of " . Sealed::class],
            $this->check($config),
        );
    }

    public function testAttributeSetterIsNotSetAndPropertyIsReadonly(): void
    {
        $config = $this->written($this->sealed([new Attribute(name: "code", type: "string")]));

        self::assertSame(
            ["Model 'sealed' attribute 'code': setter is not set and " . Sealed::class . " has no public writable property 'code' — the value is silently not written. Set 'setter' or 'setter: false'"],
            $this->check($config),
        );
    }

    public function testRelationSetterIsNotWritable(): void
    {
        $config = $this->sealedWithWriters(new Relation(name: "writers", type: Relation::ONE_TO_MANY, targetModel: "writer", getter: false, setter: "writers"));

        self::assertSame(
            ["Model 'sealed' relation 'writers': setter 'writers' is neither a public method taking one argument nor a public writable property of " . Sealed::class],
            $this->check($config),
        );
    }

    public function testRelationAddIsNotCallable(): void
    {
        $config = $this->sealedWithWriters(new Relation(name: "writers", type: Relation::ONE_TO_MANY, targetModel: "writer", getter: false, setter: false, add: "addWriter"));

        self::assertSame(
            ["Model 'sealed' relation 'writers': add 'addWriter' is not a public method taking one argument of " . Sealed::class],
            $this->check($config),
        );
    }

    public function testRelationHasNoWayToBeWritten(): void
    {
        $config = $this->sealedWithWriters(new Relation(name: "writers", type: Relation::ONE_TO_MANY, targetModel: "writer", getter: false));

        self::assertSame(
            ["Model 'sealed' relation 'writers': writable, but neither setter nor add is set and " . Sealed::class . " has no public writable property 'writers' — the value is silently not written"],
            $this->check($config),
        );
    }

    public function testModelIsNotInstantiable(): void
    {
        $model = new Model(class: AbstractModel::class, type: "abstract", attributes: [new Attribute(name: "id", type: "string|null")]);

        self::assertSame(
            ["Model 'abstract': class " . AbstractModel::class . " is not instantiable (abstract, interface or non-public constructor)"],
            $this->check($this->written($model)),
        );
    }

    public function testRequiredConstructorParameterIsNotConstructorParam(): void
    {
        $model = new Model(class: Strict::class, type: "strict", attributes: [
            new Attribute(name: "id", type: "string|null"),
            new Attribute(name: "name", type: "string"),
        ]);

        self::assertSame(
            ["Model 'strict': constructor of " . Strict::class . " requires '\$name', but it is not declared as constructorParam"],
            $this->check($this->written($model)),
        );
    }

    public function testConstructorParamWithoutConstructorParameter(): void
    {
        $model = new Model(class: Post::class, type: "post", attributes: [
            new Attribute(name: "id", type: "string|null"),
            new Attribute(name: "title", type: "string|null", constructorParam: true),
        ]);

        self::assertSame(
            ["Model 'post': 'title' is declared as constructorParam, but the constructor of " . Post::class . " has no such parameter"],
            $this->check($this->written($model)),
        );
    }

    public function testWritablePathToUnknownRelation(): void
    {
        $config = new Config(
            models: [$this->post(), $this->writer(), $this->account()],
            actions: [
                new Action(
                    type: ActionType::Create,
                    model: "post",
                    writable: ["editor" => new WritableConfig(RelationWriteMode::Create)],
                ),
            ],
        );

        self::assertSame(
            ["Action 'create' on model 'post': writable path 'editor' — relation 'editor' not found in model 'post'"],
            $this->check($config),
        );
    }

    public function testReferenceToModelWithoutLocator(): void
    {
        $config = new Config(
            models: [$this->post(), $this->writer(), $this->account()],
            actions: [
                new Action(
                    type: ActionType::Create,
                    model: "post",
                    writable: ["author" => new WritableConfig(RelationWriteMode::Reference)],
                ),
            ],
        );

        self::assertSame(
            ["Action 'create' on model 'post': writable path 'author' references model 'writer', which has no entity locator. Set impl.models[writer].entityLocator, a read action with entityLocator or impl.defaultLocator"],
            $this->check($config),
        );
    }

    /**
     * The target model's locator without a read action: its own or impl.defaultLocator.
     *
     * Локатор целевой модели без read-действия: свой или impl.defaultLocator.
     */
    public function testReferenceToModelWithModelOrDefaultLocator(): void
    {
        $actions = [
            new Action(
                type: ActionType::Create,
                model: "post",
                writable: ["author" => new WritableConfig(RelationWriteMode::Reference)],
            ),
        ];
        $writer = $this->writer();

        $withModelLocator = new Config(
            models: [
                $this->post(),
                new Model(
                    class: $writer->class,
                    type: $writer->type,
                    attributes: $writer->attributes,
                    relations: $writer->relations,
                    entityLocator: "loc",
                ),
                $this->account(),
            ],
            actions: $actions,
        );
        $withDefault = new Config(
            models: [$this->post(), $writer, $this->account()],
            actions: $actions,
            defaultLocator: "loc",
        );

        self::assertSame([], $this->check($withModelLocator));
        self::assertSame([], $this->check($withDefault));
    }

    public function testDoctrineWithoutManagerRegistry(): void
    {
        self::assertSame(
            ["Doctrine locator/saver/remover is configured, but Doctrine ManagerRegistry is not available"],
            $this->check($this->doctrinePost()),
        );
    }

    public function testDoctrineModelWithoutEntityManager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn(null);

        self::assertSame(
            ["Model 'post': class " . Post::class . " is served by a Doctrine locator/saver/remover, but no entity manager handles it"],
            $this->check($this->doctrinePost(), $registry),
        );
    }

    public function testDoctrineModelWithEntityManager(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method("getManagerForClass")->willReturn($this->createStub(ObjectManager::class));

        self::assertSame([], $this->check($this->doctrinePost(), $registry));
    }

    /**
     * The path is checked by the getter's type; a path past a scalar type is unreachable.
     *
     * Путь проверяется по типу геттера; путь за скалярным типом недостижим.
     */
    public function testOwnedByCurrentUserPathSegmentIsNotReadable(): void
    {
        $model = new Model(class: Guarded::class, type: "guarded", attributes: [
            new Attribute(name: "id", type: "string|null"),
        ]);

        self::assertSame(
            [OwnedByCurrentUser::class . " on " . Guarded::class . ": path 'author.owner' — segment 'owner' is not readable on " . Writer::class],
            $this->check($this->single($model)),
        );
    }

    public function testOwnedByCurrentUserPathContinuesPastScalar(): void
    {
        $model = new Model(class: ScalarGuarded::class, type: "scalar", attributes: [
            new Attribute(name: "id", type: "string|null"),
            new Attribute(name: "title", type: "string"),
        ]);

        self::assertSame(
            [OwnedByCurrentUser::class . " on " . ScalarGuarded::class . ": path 'title.length' — segment 'title' on " . ScalarGuarded::class . " is of type string, the rest of the path is unreachable"],
            $this->check($this->single($model)),
        );
    }

    public function testScopedToCurrentUserIdentityPathOnUserModel(): void
    {
        $config = new Config(
            models: [$this->post(), $this->writer(), $this->account()],
            actions: [
                new Action(
                    type: ActionType::List,
                    model: "post",
                    config: new ListActionConfig(class: PostListRequest::class),
                ),
            ],
            userModel: "account",
        );

        self::assertSame(
            [ScopedToCurrentUser::class . " on " . Account::class . ": identityPath 'uuid' — segment 'uuid' is not readable on " . Account::class],
            $this->check($config),
        );
    }

    /**
     * @return list<string>
     */
    private function check(Config $config, ?ManagerRegistry $doctrine = null): array
    {
        return (new ConfigChecker($doctrine))->check($config);
    }

    private function post(): Model
    {
        return new Model(
            class: Post::class,
            type: "post",
            attributes: [
                new Attribute(name: "id", type: "string|null"),
                new Attribute(name: "title", type: "string|null"),
            ],
            relations: [new Relation(name: "author", type: Relation::MANY_TO_ONE, targetModel: "writer")],
        );
    }

    private function writer(): Model
    {
        return new Model(
            class: Writer::class,
            type: "writer",
            attributes: [
                new Attribute(name: "id", type: "string|null"),
                new Attribute(name: "name", type: "string|null"),
            ],
            relations: [new Relation(name: "user", type: Relation::MANY_TO_ONE, targetModel: "account")],
        );
    }

    private function account(): Model
    {
        return new Model(class: Account::class, type: "account", attributes: [new Attribute(name: "id", type: "string|null")]);
    }

    /**
     * @param list<Attribute> $attributes
     */
    private function sealed(array $attributes): Model
    {
        return new Model(
            class: Sealed::class,
            type: "sealed",
            attributes: [new Attribute(name: "id", type: "string|null"), ...$attributes],
        );
    }

    private function sealedWithWriters(Relation $relation): Config
    {
        return new Config(
            models: [
                new Model(
                    class: Sealed::class,
                    type: "sealed",
                    attributes: [new Attribute(name: "id", type: "string|null")],
                    relations: [$relation],
                ),
                $this->writer(),
                $this->account(),
            ],
            actions: [
                new Action(
                    type: ActionType::Update,
                    model: "sealed",
                    writable: ["writers" => new WritableConfig(RelationWriteMode::Reference)],
                ),
                new Action(type: ActionType::Read, model: "writer", entityLocator: "loc"),
            ],
        );
    }

    /**
     * The model is only read.
     *
     * Модель только читается.
     */
    private function single(Model $model): Config
    {
        return new Config(models: [$model], actions: [new Action(type: ActionType::Read, model: $model->type)]);
    }

    /**
     * The model is created: setters and the constructor are checked.
     *
     * Модель создаётся: проверяются сеттеры и конструктор.
     */
    private function written(Model $model): Config
    {
        return new Config(models: [$model], actions: [new Action(type: ActionType::Create, model: $model->type)]);
    }

    private function doctrinePost(): Config
    {
        return new Config(
            models: [$this->post(), $this->writer(), $this->account()],
            actions: [
                new Action(type: ActionType::Read, model: "post", entityLocator: "doctrine"),
                new Action(type: ActionType::Create, model: "post", entitySaver: "doctrine"),
            ],
            locators: ["doctrine" => DoctrineLocator::class],
            savers: ["doctrine" => DoctrineSaver::class],
        );
    }
}
