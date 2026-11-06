<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use ArrayIterator;
use Doctrine\Common\Collections\ArrayCollection;
use Iterator;
use IteratorAggregate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UniMethod\SymfonyLib\Constraint\PropertyPathReader;

final class PathItem
{
    public function __construct(public string $name)
    {
    }
}

final class PropertyPathReaderTest extends TestCase
{
    private PropertyPathReader $reader;

    protected function setUp(): void
    {
        $this->reader = new PropertyPathReader();
    }

    public function testPublicProperty(): void
    {
        $object = new class {
            public string $name = "n";
        };

        self::assertSame([true, "n"], $this->reader->read($object, "name"));
    }

    public function testGetterIsserHasserAndBareMethod(): void
    {
        $object = new class {
            public function getName(): string
            {
                return "n";
            }

            public function isActive(): bool
            {
                return true;
            }

            public function hasChildren(): bool
            {
                return false;
            }

            public function total(): int
            {
                return 7;
            }
        };

        self::assertSame([true, "n"], $this->reader->read($object, "name"));
        self::assertSame([true, true], $this->reader->read($object, "active"));
        self::assertSame([true, false], $this->reader->read($object, "children"));
        self::assertSame([true, 7], $this->reader->read($object, "total"));
    }

    public function testPublicPropertyWinsOverGetter(): void
    {
        $object = new class {
            public string $name = "property";

            public function getName(): string
            {
                return "getter";
            }
        };

        self::assertSame([true, "property"], $this->reader->read($object, "name"));
    }

    public function testNestedPath(): void
    {
        $leaf = new class {
            public int $id = 42;
        };
        $mid = new class ($leaf) {
            public function __construct(public object $user)
            {
            }
        };
        $root = new class ($mid) {
            public function __construct(private readonly object $garden)
            {
            }

            public function getGarden(): object
            {
                return $this->garden;
            }
        };

        self::assertSame([true, 42], $this->reader->read($root, "garden.user.id"));
    }

    public function testNullMidwayIsReachableNull(): void
    {
        $root = new class {
            public ?object $garden = null;
        };

        self::assertSame([true, null], $this->reader->read($root, "garden.user"));
    }

    public function testMissingSegmentIsUnreachable(): void
    {
        $root = new class {
            public string $name = "n";
        };

        self::assertSame([false, null], $this->reader->read($root, "missing"));
        self::assertSame([false, null], $this->reader->read($root, "name.deeper"));
    }

    /**
     * An uninitialized public property exists: the path is reachable,
     * there is no value. Differs from a missing segment.
     *
     * Неинициализированное публичное свойство существует: путь достижим,
     * значения нет. Отличается от отсутствующего сегмента.
     */
    public function testUninitializedTypedPropertyIsReachableNull(): void
    {
        $root = new class {
            public string $name;
        };

        self::assertSame([true, null], $this->reader->read($root, "name"));
    }

    /**
     * A method that cannot be called from outside without arguments is not an accessor.
     *
     * Метод, который нельзя вызвать снаружи без аргументов, — не аксессор.
     */
    public function testInaccessibleMethodsAreUnreachable(): void
    {
        $root = new class {
            private function getSecret(): string
            {
                return "s";
            }

            protected function getHidden(): string
            {
                return "h";
            }

            public static function getShared(): string
            {
                return "x";
            }

            public function getScoped(string $scope): string
            {
                return $scope;
            }
        };

        self::assertSame([false, null], $this->reader->read($root, "secret"));
        self::assertSame([false, null], $this->reader->read($root, "hidden"));
        self::assertSame([false, null], $this->reader->read($root, "shared"));
        self::assertSame([false, null], $this->reader->read($root, "scoped"));
    }

    public function testPrivatePropertyIsReadThroughGetter(): void
    {
        $root = new class {
            private string $name = "n";

            public function getName(): string
            {
                return $this->name;
            }
        };

        self::assertSame([true, "n"], $this->reader->read($root, "name"));
    }

    /**
     * @return iterable<string, array{iterable<int, object>}>
     */
    public static function provideCollections(): iterable
    {
        $items = [new PathItem("a"), new PathItem("b")];

        yield "array" => [$items];
        yield "ArrayCollection" => [new ArrayCollection($items)];
        yield "ArrayIterator" => [new ArrayIterator($items)];
        yield "Traversable" => [new class ($items) implements IteratorAggregate {
            /** @param list<PathItem> $items */
            public function __construct(private readonly array $items)
            {
            }

            public function getIterator(): Iterator
            {
                return new ArrayIterator($this->items);
            }
        }];
    }

    /**
     * @param iterable<int, object> $items
     */
    #[DataProvider("provideCollections")]
    public function testIndexedSegment(iterable $items): void
    {
        $root = new class ($items) {
            /** @param iterable<int, object> $items */
            public function __construct(public iterable $items)
            {
            }
        };

        self::assertSame([true, "b"], $this->reader->read($root, "items[1].name"));
        self::assertSame([true, null], $this->reader->read($root, "items[9].name"));
    }

    public function testIndexOnScalarIsUnreachable(): void
    {
        $root = new class {
            public string $items = "abc";
        };

        self::assertSame([false, null], $this->reader->read($root, "items[0]"));
    }
}
