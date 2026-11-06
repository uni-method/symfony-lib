<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use LogicException;
use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\ConstraintContextHolder;
use UniMethod\SymfonyLib\Exception\ConstraintContextNotSetException;

final class ConstraintContextHolderTest extends TestCase
{
    public function testGetWithoutContextThrows(): void
    {
        $holder = new ConstraintContextHolder();

        self::assertFalse($holder->has());

        $this->expectException(ConstraintContextNotSetException::class);
        $holder->get();
    }

    /**
     * A nested check sees its own context, after it the outer one is visible again.
     *
     * Вложенная проверка видит свой контекст, после неё снова виден внешний.
     */
    public function testNestedPushPopRestoresOuterContext(): void
    {
        $holder = new ConstraintContextHolder();
        $outer = ConstraintContext::listQuery("post");
        $inner = ConstraintContext::afterLocate("post", ActionType::Read, 1);

        $holder->push($outer);
        $holder->push($inner);
        self::assertSame($inner, $holder->get());

        $holder->pop();
        self::assertSame($outer, $holder->get());

        $holder->pop();
        self::assertFalse($holder->has());
    }

    public function testPopWithoutPushThrows(): void
    {
        $this->expectException(LogicException::class);

        (new ConstraintContextHolder())->pop();
    }
}
