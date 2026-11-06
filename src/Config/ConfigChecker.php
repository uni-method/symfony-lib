<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Config;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ManagerRegistry;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use UniMethod\Frame\Config\{
    Action,
    ActionType,
    Attribute,
    Config,
    Model,
    ModelLocatorResolver,
    Relation,
    RelationWriteMode,
};
use UniMethod\SymfonyLib\Constraint\{OwnedByCurrentUser, ScopedToCurrentUser};
use UniMethod\SymfonyLib\Integration\{
    DoctrineLocator,
    DoctrineRemover,
    DoctrineSaver,
};

/**
 * Static reconciliation of the loaded Config with the application classes: what
 * the framework and the bundle would otherwise detect only on a specific request to a specific
 * model. Repeats the runtime rules (MappingToObject, MappingFromObject,
 * AttributeAccessor, ActionValidator, PropertyPathReader, Doctrine*).
 *
 * Статическая сверка загруженного Config с классами приложения: то, что
 * фреймворк и бандл иначе обнаружат только на конкретном запросе к конкретной
 * модели. Повторяет правила рантайма (MappingToObject, MappingFromObject,
 * AttributeAccessor, ActionValidator, PropertyPathReader, Doctrine*).
 */
final readonly class ConfigChecker
{
    public function __construct(private ?ManagerRegistry $doctrine = null)
    {
    }

    /**
     * @return list<string>
     */
    public function check(Config $config): array
    {
        /** @var array<string, Model> $models */
        $models = [];
        foreach ($config->models as $model) {
            $models[$model->type] = $model;
        }

        $issues = [];
        /** @var array<string, ReflectionClass<object>> $classes */
        $classes = [];

        foreach ($models as $model) {
            if (!class_exists($model->class)) {
                $issues[] = sprintf(
                    "Model '%s': class '%s' does not exist",
                    $model->type,
                    $model->class,
                );
                continue;
            }

            $classes[$model->type] = new ReflectionClass($model->class);
        }

        [$written, $instantiated, $writtenRelations, $referenced] =
            $this->collectWrites($config, $models, $issues);

        foreach ($classes as $type => $class) {
            $model = $models[$type];

            foreach ($model->attributes as $attribute) {
                $this->checkGetter($model, $class, $attribute, $issues);

                if (
                    isset($written[$type]) &&
                    !$attribute->constructorParam
                ) {
                    $this->checkAttributeSetter(
                        $model,
                        $class,
                        $attribute,
                        $issues,
                    );
                }
            }

            foreach ($model->relations as $relation) {
                $this->checkGetter($model, $class, $relation, $issues);

                if (
                    isset($writtenRelations[$type][$relation->name]) &&
                    !$relation->constructorParam
                ) {
                    $this->checkRelationSetter(
                        $model,
                        $class,
                        $relation,
                        $issues,
                    );
                }
            }

            if (isset($instantiated[$type])) {
                $this->checkConstructor($model, $class, $issues);
            }

            foreach ($this->constraints($class, OwnedByCurrentUser::class) as $owned) {
                $this->checkPath(
                    OwnedByCurrentUser::class,
                    $class,
                    $owned->path,
                    "path",
                    $issues,
                );
            }
        }

        $this->checkDtoReferences($config, $referenced, $issues);
        $this->checkDoctrine($config, $classes, $issues);
        $this->checkListRequests($config, $models, $issues);

        return $issues;
    }

    /**
     * Which models are written (create/update and nested creates), which
     * are instantiated by the mapper, which relations are written and which lead by reference.
     *
     * Какие модели пишутся (create/update и вложенные create), какие
     * инстанцируются маппером, какие связи пишутся и какие ведут по ссылке.
     *
     * @param array<string, Model> $models
     * @param list<string> $issues
     * @return array{
     *     array<string, true>,
     *     array<string, true>,
     *     array<string, array<string, true>>,
     *     list<array{Action, string, Model}>,
     * }
     */
    private function collectWrites(
        Config $config,
        array $models,
        array &$issues,
    ): array {
        $written = [];
        $instantiated = [];
        $writtenRelations = [];
        $referenced = [];

        foreach ($config->actions as $action) {
            if (
                $action->type !== ActionType::Create &&
                $action->type !== ActionType::Update
            ) {
                continue;
            }

            $written[$action->model] = true;

            if ($action->type === ActionType::Create) {
                $instantiated[$action->model] = true;
            }

            foreach ($action->writable ?? [] as $path => $writable) {
                $mode = $writable->mode;
                $current = $models[$action->model] ?? null;
                $segments = explode(".", (string) $path);

                foreach ($segments as $index => $segment) {
                    if ($current === null) {
                        break;
                    }

                    $relation = $this->findRelation($current, $segment);
                    if (!$relation instanceof Relation) {
                        $issues[] = sprintf(
                            "Action '%s' on model '%s': writable path '%s' — relation '%s' not found in model '%s'",
                            $action->type->value,
                            $action->model,
                            $path,
                            $segment,
                            $current->type,
                        );
                        break;
                    }

                    $writtenRelations[$current->type][$relation->name] = true;
                    $target = $models[$relation->targetModel] ?? null;
                    $isLast = $index === count($segments) - 1;

                    if ($target === null) {
                        break;
                    }

                    if (
                        !$isLast ||
                        $mode === RelationWriteMode::Create ||
                        $mode === RelationWriteMode::ReferenceOrCreate
                    ) {
                        $written[$target->type] = true;
                        $instantiated[$target->type] = true;
                    }

                    if (
                        $isLast &&
                        (
                            $mode === RelationWriteMode::Reference ||
                            $mode === RelationWriteMode::ReferenceOrCreate
                        )
                    ) {
                        $referenced[] = [$action, (string) $path, $target];
                    }

                    $current = $target;
                }
            }
        }

        return [$written, $instantiated, $writtenRelations, $referenced];
    }

    /**
     * Repeats MappingFromObject::resolveGetter + getAttributeValue.
     *
     * Повторяет MappingFromObject::resolveGetter + getAttributeValue.
     *
     * @param ReflectionClass<object> $class
     * @param list<string> $issues
     */
    private function checkGetter(
        Model $model,
        ReflectionClass $class,
        Attribute|Relation $field,
        array &$issues,
    ): void {
        if ($field->getter === false) {
            return;
        }

        $kind = $field instanceof Relation ? "relation" : "attribute";

        if (is_string($field->getter)) {
            if (
                !$this->callableMethod($class, $field->getter, 0) instanceof \ReflectionMethod &&
                !$this->publicProperty($class, $field->getter) instanceof \ReflectionProperty
            ) {
                $issues[] = sprintf(
                    "Model '%s' %s '%s': getter '%s' is neither a public method callable without arguments nor a " .
                    "public property of %s",
                    $model->type,
                    $kind,
                    $field->name,
                    $field->getter,
                    $class->getName(),
                );
            }

            return;
        }

        if (!$this->publicProperty($class, $field->name) instanceof \ReflectionProperty) {
            $issues[] = sprintf(
                "Model '%s' %s '%s': getter is not set and %s has no public property '%s' — the value is silently " .
                "omitted from responses. Set 'getter' or 'getter: false'",
                $model->type,
                $kind,
                $field->name,
                $class->getName(),
                $field->name,
            );
        }
    }

    /**
     * Repeats AttributeAccessor::resolveSetter + setValue.
     *
     * Повторяет AttributeAccessor::resolveSetter + setValue.
     *
     * @param ReflectionClass<object> $class
     * @param list<string> $issues
     */
    private function checkAttributeSetter(
        Model $model,
        ReflectionClass $class,
        Attribute $attribute,
        array &$issues,
    ): void {
        if ($attribute->setter === false) {
            return;
        }

        if (is_string($attribute->setter)) {
            if (!$this->isWritableMember($class, $attribute->setter)) {
                $issues[] = sprintf(
                    "Model '%s' attribute '%s': setter '%s' is neither a public method taking one argument nor a " .
                    "public writable property of %s",
                    $model->type,
                    $attribute->name,
                    $attribute->setter,
                    $class->getName(),
                );
            }

            return;
        }

        if (!$this->isWritableProperty($class, $attribute->name)) {
            $issues[] = sprintf(
                "Model '%s' attribute '%s': setter is not set and %s has no public writable property '%s' — the " .
                "value is silently not written. Set 'setter' or 'setter: false'",
                $model->type,
                $attribute->name,
                $class->getName(),
                $attribute->name,
            );
        }
    }

    /**
     * @param ReflectionClass<object> $class
     * @param list<string> $issues
     */
    private function checkRelationSetter(
        Model $model,
        ReflectionClass $class,
        Relation $relation,
        array &$issues,
    ): void {
        if ($relation->setter === false && $relation->add === false) {
            return;
        }

        if (
            is_string($relation->setter) &&
            !$this->isWritableMember($class, $relation->setter)
        ) {
            $issues[] = sprintf(
                "Model '%s' relation '%s': setter '%s' is neither a public method taking one argument nor a public " .
                "writable property of %s",
                $model->type,
                $relation->name,
                $relation->setter,
                $class->getName(),
            );
        }

        if (
            is_string($relation->add) &&
            !$this->callableMethod($class, $relation->add, 1) instanceof \ReflectionMethod
        ) {
            $issues[] = sprintf(
                "Model '%s' relation '%s': add '%s' is not a public method taking one argument of %s",
                $model->type,
                $relation->name,
                $relation->add,
                $class->getName(),
            );
        }

        if (
            $relation->setter === null &&
            !is_string($relation->add) &&
            !$this->isWritableProperty($class, $relation->name)
        ) {
            $issues[] = sprintf(
                "Model '%s' relation '%s': writable, but neither setter nor add is set and %s has no public writable " .
                "property '%s' — the value is silently not written",
                $model->type,
                $relation->name,
                $class->getName(),
                $relation->name,
            );
        }
    }

    /**
     * Repeats the instantiation in MappingToObject: without constructorParam —
     * `new Class()`, otherwise named arguments for the constructorParam fields.
     *
     * Повторяет инстанцирование MappingToObject: без constructorParam —
     * `new Class()`, иначе именованные аргументы по constructorParam-полям.
     *
     * @param ReflectionClass<object> $class
     * @param list<string> $issues
     */
    private function checkConstructor(
        Model $model,
        ReflectionClass $class,
        array &$issues,
    ): void {
        if (!$class->isInstantiable()) {
            $issues[] = sprintf(
                "Model '%s': class %s is not instantiable (abstract, interface or non-public constructor)",
                $model->type,
                $class->getName(),
            );
            return;
        }

        $passed = [];
        foreach ([...$model->attributes, ...$model->relations] as $field) {
            if ($field->constructorParam) {
                $passed[$field->name] = true;
            }
        }

        $constructor = $class->getConstructor();
        $params = $constructor?->getParameters() ?? [];
        $variadic = false;
        $names = [];

        foreach ($params as $param) {
            $names[$param->getName()] = true;
            $variadic = $variadic || $param->isVariadic();

            if (
                !$param->isOptional() &&
                !isset($passed[$param->getName()])
            ) {
                $issues[] = sprintf(
                    "Model '%s': constructor of %s requires '$%s', but it is not declared as constructorParam",
                    $model->type,
                    $class->getName(),
                    $param->getName(),
                );
            }
        }

        if ($variadic) {
            return;
        }

        foreach (array_keys($passed) as $name) {
            if (!isset($names[$name])) {
                $issues[] = sprintf(
                    "Model '%s': '%s' is declared as constructorParam, but the constructor of %s has no such parameter",
                    $model->type,
                    $name,
                    $class->getName(),
                );
            }
        }
    }

    /**
     * Repeats ActionValidator::validateRelationExistenceWithLocators: the locator
     * of the target model is Config::locatorForModel (its own, the read action's, or
     * impl.defaultLocator).
     *
     * Повторяет ActionValidator::validateRelationExistenceWithLocators: локатор
     * целевой модели — Config::locatorForModel (свой, read-действия или
     * impl.defaultLocator).
     *
     * @param list<array{Action, string, Model}> $referenced
     * @param list<string> $issues
     */
    private function checkDtoReferences(
        Config $config,
        array $referenced,
        array &$issues,
    ): void {
        foreach ($referenced as [$action, $path, $target]) {
            if ($config->locatorForModel($target->type) === null) {
                $issues[] = sprintf(
                    "Action '%s' on model '%s': writable path '%s' references model '%s', which has no entity " .
                    "locator. %s",
                    $action->type->value,
                    $action->model,
                    $path,
                    $target->type,
                    ModelLocatorResolver::hint($target->type),
                );
            }
        }
    }

    /**
     * Models served by the Doctrine locator/saver/remover must be
     * mapped in Doctrine.
     *
     * Модели, которые обслуживают Doctrine-locator/saver/remover, должны быть
     * замаплены в Doctrine.
     *
     * @param array<string, ReflectionClass<object>> $classes
     * @param list<string> $issues
     */
    private function checkDoctrine(
        Config $config,
        array $classes,
        array &$issues,
    ): void {
        $doctrineAliases = [
            "locator" => array_keys($config->locators, DoctrineLocator::class, true),
            "saver" => array_keys($config->savers, DoctrineSaver::class, true),
            "remover" => array_keys($config->removers, DoctrineRemover::class, true),
        ];

        if (array_merge(...array_values($doctrineAliases)) === []) {
            return;
        }

        if (!$this->doctrine instanceof ManagerRegistry) {
            $issues[] = "Doctrine locator/saver/remover is configured, but Doctrine ManagerRegistry is not available";
            return;
        }

        $checked = [];
        foreach ($config->actions as $action) {
            $uses =
                in_array($action->entityLocator, $doctrineAliases["locator"], true) ||
                in_array($action->entitySaver, $doctrineAliases["saver"], true) ||
                in_array($action->entityRemover, $doctrineAliases["remover"], true);

            if (
                !$uses ||
                isset($checked[$action->model]) ||
                !isset($classes[$action->model])
            ) {
                continue;
            }

            $checked[$action->model] = true;
            $class = $classes[$action->model]->getName();

            if (!$this->doctrine->getManagerForClass($class) instanceof ObjectManager) {
                $issues[] = sprintf(
                    "Model '%s': class %s is served by a Doctrine locator/saver/remover, but no entity manager " .
                    "handles it",
                    $action->model,
                    $class,
                );
            }
        }
    }

    /**
     * ScopedToCurrentUser on list request DTOs: path — on the DTO, identityPath —
     * on the user model class (if it is set in the config).
     *
     * ScopedToCurrentUser на DTO list-запросов: path — на DTO, identityPath —
     * на классе модели пользователя (если она задана в конфиге).
     *
     * @param array<string, Model> $models
     * @param list<string> $issues
     */
    private function checkListRequests(
        Config $config,
        array $models,
        array &$issues,
    ): void {
        $userClass = null;
        if ($config->userModel !== null) {
            $userModelClass = $models[$config->userModel]->class ?? null;
            if ($userModelClass !== null && class_exists($userModelClass)) {
                $userClass = new ReflectionClass($userModelClass);
            }
        }

        foreach ($config->actions as $action) {
            $dtoClass = $action->config?->class;
            if (
                $action->type !== ActionType::List ||
                $dtoClass === null ||
                !class_exists($dtoClass)
            ) {
                continue;
            }

            $dto = new ReflectionClass($dtoClass);

            foreach ($this->constraints($dto, ScopedToCurrentUser::class) as $scoped) {
                $this->checkPath(
                    ScopedToCurrentUser::class,
                    $dto,
                    $scoped->path,
                    "path",
                    $issues,
                );

                if ($scoped->identityPath !== null && $userClass instanceof \ReflectionClass) {
                    $this->checkPath(
                        ScopedToCurrentUser::class,
                        $userClass,
                        $scoped->identityPath,
                        "identityPath",
                        $issues,
                    );
                }
            }
        }
    }

    /**
     * Repeats PropertyPathReader: public property → getX() → isX() → hasX()
     * → x(). The type of the next segment is taken from the declared type; if the type is not
     * a single class (no type, mixed, union) — the path is not checked further.
     *
     * Повторяет PropertyPathReader: public-свойство → getX() → isX() → hasX()
     * → x(). Тип следующего сегмента берётся из объявленного типа; если тип не
     * один класс (нет типа, mixed, union) — дальше путь не проверяется.
     *
     * @param ReflectionClass<object> $class
     * @param list<string> $issues
     */
    private function checkPath(
        string $constraint,
        ReflectionClass $class,
        string $path,
        string $option,
        array &$issues,
    ): void {
        $current = $class;
        $segments = explode(".", $path);

        foreach ($segments as $index => $segment) {
            $type = null;
            $found = false;

            $property = $this->publicProperty($current, $segment);
            if ($property instanceof \ReflectionProperty) {
                $found = true;
                $type = $property->getType();
            } else {
                foreach (["get", "is", "has", ""] as $prefix) {
                    $name = $prefix === "" ? $segment : $prefix . ucfirst($segment);
                    $method = $this->callableMethod($current, $name, 0);
                    if ($method instanceof \ReflectionMethod) {
                        $found = true;
                        $type = $method->getReturnType();
                        break;
                    }
                }
            }

            if (!$found) {
                $issues[] = sprintf(
                    "%s on %s: %s '%s' — segment '%s' is not readable on %s",
                    $constraint,
                    $class->getName(),
                    $option,
                    $path,
                    $segment,
                    $current->getName(),
                );
                return;
            }

            if ($index === count($segments) - 1) {
                return;
            }

            if (!$type instanceof ReflectionNamedType) {
                return;
            }

            $typeName = $type->getName();
            if ($typeName === "self" || $typeName === "static") {
                continue;
            }

            if ($type->isBuiltin()) {
                if (in_array($typeName, ["mixed", "object"], true)) {
                    return;
                }

                $issues[] = sprintf(
                    "%s on %s: %s '%s' — segment '%s' on %s is of type %s, the rest of the path is unreachable",
                    $constraint,
                    $class->getName(),
                    $option,
                    $path,
                    $segment,
                    $current->getName(),
                    $typeName,
                );
                return;
            }

            if (!class_exists($typeName) && !interface_exists($typeName)) {
                return;
            }

            $current = new ReflectionClass($typeName);
        }
    }

    /**
     * Constraint attributes of the class and its parents (Symfony Validator
     * inherits the parent's constraints).
     *
     * Атрибуты-констрейнты класса и его родителей (Symfony Validator
     * наследует констрейнты родителя).
     *
     * @template T of object
     * @param ReflectionClass<object> $class
     * @param class-string<T> $constraint
     * @return list<T>
     */
    private function constraints(ReflectionClass $class, string $constraint): array
    {
        $result = [];

        for ($current = $class; $current !== false; $current = $current->getParentClass()) {
            foreach (
                $current->getAttributes($constraint, ReflectionAttribute::IS_INSTANCEOF) as $attribute
            ) {
                $result[] = $attribute->newInstance();
            }
        }

        return $result;
    }

    private function findRelation(Model $model, string $name): ?Relation
    {
        foreach ($model->relations as $relation) {
            if ($relation->name === $name) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * A public non-static method that can be called with $arguments
     * arguments.
     *
     * Public нестатический метод, который можно вызвать с $arguments
     * аргументами.
     *
     * @param ReflectionClass<object> $class
     */
    private function callableMethod(
        ReflectionClass $class,
        string $name,
        int $arguments,
    ): ?ReflectionMethod {
        if (!$class->hasMethod($name)) {
            return null;
        }

        $method = $class->getMethod($name);

        if (
            !$method->isPublic() ||
            $method->isStatic() ||
            $method->getNumberOfRequiredParameters() > $arguments ||
            ($method->getNumberOfParameters() < $arguments && !$method->isVariadic())
        ) {
            return null;
        }

        return $method;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function publicProperty(
        ReflectionClass $class,
        string $name,
    ): ?ReflectionProperty {
        if (!$class->hasProperty($name)) {
            return null;
        }

        $property = $class->getProperty($name);

        return $property->isPublic() && !$property->isStatic() ? $property : null;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function isWritableProperty(ReflectionClass $class, string $name): bool
    {
        $property = $this->publicProperty($class, $name);

        return $property instanceof \ReflectionProperty && !$property->isReadOnly();
    }

    /**
     * Repeats AttributeAccessor::setValue: first the method, then the property.
     *
     * Повторяет AttributeAccessor::setValue: сначала метод, затем свойство.
     *
     * @param ReflectionClass<object> $class
     */
    private function isWritableMember(ReflectionClass $class, string $name): bool
    {
        if ($class->hasMethod($name)) {
            return $this->callableMethod($class, $name, 1) instanceof \ReflectionMethod;
        }

        return $this->isWritableProperty($class, $name);
    }
}
