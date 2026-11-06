<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use ArrayAccess;
use ReflectionMethod;
use ReflectionProperty;
use Traversable;

/**
 * Reading a value by a dotted path (`garden.user`, `items[1].name`) without
 * symfony/property-access: public non-static property → getX() → isX()
 * → hasX() → x(); the method is public, non-static, without required
 * parameters. The index `[i]` is read from an array, ArrayAccess or Traversable.
 *
 * The result contract `[whether the path is reachable, value]`:
 * - the segment does not exist or is inaccessible (private, static, a method with parameters) —
 *   `[false, null]`;
 * - the value at the path is null, a public typed property is not yet
 *   initialized, or the index is not in the collection — `[true, null]`.
 *
 * Чтение значения по точечному пути (`garden.user`, `items[1].name`) без
 * symfony/property-access: публичное нестатическое свойство → getX() → isX()
 * → hasX() → x(); метод — публичный, нестатический, без обязательных
 * параметров. Индекс `[i]` читается из array, ArrayAccess или Traversable.
 *
 * Контракт результата `[достижим ли путь, значение]`:
 * - сегмента нет или он недоступен (private, static, метод с параметрами) —
 *   `[false, null]`;
 * - значение по пути null, публичное типизированное свойство ещё не
 *   инициализировано или индекса нет в коллекции — `[true, null]`.
 */
final class PropertyPathReader
{
    private const string SEGMENT = '/^([^\[\]]+)((?:\[[^\[\]]*\])*)$/';

    /**
     * @return array{0: bool, 1: mixed} [whether the whole path is reachable, value]
     */
    public function read(object $root, string $path): array
    {
        $current = $root;

        foreach (explode(".", $path) as $segment) {
            if (!is_object($current) || preg_match(self::SEGMENT, $segment, $match) !== 1) {
                return [false, null];
            }

            $accessor = $this->resolve($current, $match[1]);

            if ($accessor === null) {
                return [false, null];
            }

            $current = $accessor($current);

            if ($match[2] !== "") {
                preg_match_all('/\[([^\[\]]*)\]/', $match[2], $indexes);

                foreach ($indexes[1] as $index) {
                    if ($current === null) {
                        return [true, null];
                    }

                    [$indexable, $current] = $this->element($current, $index);

                    if (!$indexable) {
                        return [false, null];
                    }
                }
            }

            if ($current === null) {
                return [true, null];
            }
        }

        return [true, $current];
    }

    /**
     * @return (callable(object): mixed)|null
     */
    private function resolve(object $object, string $segment): ?callable
    {
        if (property_exists($object, $segment)) {
            $property = new ReflectionProperty($object, $segment);

            if ($property->isPublic() && !$property->isStatic()) {
                return $property->isInitialized($object)
                    ? static fn (object $o): mixed => $o->{$segment}
                    : static fn (object $o): mixed => null;
            }
        }

        $candidates = [
            "get" . ucfirst($segment),
            "is" . ucfirst($segment),
            "has" . ucfirst($segment),
            $segment,
        ];

        foreach ($candidates as $name) {
            if (!method_exists($object, $name)) {
                continue;
            }

            $method = new ReflectionMethod($object, $name);

            if ($method->isPublic() && !$method->isStatic() && $method->getNumberOfRequiredParameters() === 0) {
                return static fn (object $o): mixed => $o->{$name}();
            }
        }

        return null;
    }

    /**
     * @return array{0: bool, 1: mixed} [whether the value is indexable, element or null]
     */
    private function element(mixed $collection, string $index): array
    {
        $key = ctype_digit($index) ? (int) $index : $index;

        if (is_array($collection)) {
            return [true, $collection[$key] ?? null];
        }

        if ($collection instanceof ArrayAccess) {
            return [true, $collection->offsetExists($key) ? $collection->offsetGet($key) : null];
        }

        if ($collection instanceof Traversable) {
            foreach ($collection as $itemKey => $item) {
                if ($itemKey === $key) {
                    return [true, $item];
                }
            }

            return [true, null];
        }

        return [false, null];
    }
}
