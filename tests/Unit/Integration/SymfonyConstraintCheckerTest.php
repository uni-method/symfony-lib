<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Symfony\Component\Validator\{ConstraintViolation, ConstraintViolationList};
use Symfony\Component\Validator\Validator\ValidatorInterface;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\ConstraintContextHolder;
use UniMethod\SymfonyLib\Integration\SymfonyConstraintChecker;

final class SymfonyConstraintCheckerTest extends TestCase
{
    public function testPassesGroupsAndExposesContextDuringValidation(): void
    {
        $holder = new ConstraintContextHolder();
        $context = ConstraintContext::afterInput("post", ActionType::Update, null, 7);
        $object = new stdClass();

        $validator = $this->createMock(ValidatorInterface::class);
        $validator
            ->expects($this->once())
            ->method("validate")
            ->with($object, null, ["Default", "update"])
            ->willReturnCallback(function () use ($holder, $context): ConstraintViolationList {
                self::assertTrue($holder->has());
                self::assertSame($context, $holder->get());

                return new ConstraintViolationList();
            });

        $checker = new SymfonyConstraintChecker($validator, $holder);

        self::assertSame([], $checker->check($object, $context));
        self::assertFalse($holder->has());
    }

    public function testMapsViolationsTo422ErrorsWithPointer(): void
    {
        $object = new stdClass();
        $violations = new ConstraintViolationList([
            new ConstraintViolation("Name is required", null, [], $object, "name", null),
            new ConstraintViolation("Too long", null, [], $object, "tags[1]", "x"),
        ]);

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method("validate")->willReturn($violations);

        $checker = new SymfonyConstraintChecker($validator, new ConstraintContextHolder());
        $errors = $checker->check($object, ConstraintContext::listQuery("post"));

        self::assertCount(2, $errors);
        self::assertSame(422, $errors[0]->status);
        self::assertSame("Name is required", $errors[0]->detail);
        self::assertSame("Validation errors", $errors[0]->title);
        self::assertSame(["pointer" => "name"], $errors[0]->source);
        self::assertSame(["pointer" => "tags[1]"], $errors[1]->source);
    }

    public function testContextIsClearedWhenValidatorThrows(): void
    {
        $holder = new ConstraintContextHolder();
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method("validate")->willThrowException(new RuntimeException("boom"));

        $checker = new SymfonyConstraintChecker($validator, $holder);

        try {
            $checker->check(new stdClass(), ConstraintContext::listQuery("post"));
            self::fail("Exception expected");
        } catch (RuntimeException) {
        }

        self::assertFalse($holder->has());
    }

    /**
     * A check started inside another check does not overwrite the outer context.
     *
     * Проверка, запущенная внутри другой проверки, не затирает внешний контекст.
     */
    public function testNestedCheckKeepsOuterContext(): void
    {
        $holder = new ConstraintContextHolder();
        $outer = ConstraintContext::afterInput("post", ActionType::Update, null, 7);
        $inner = ConstraintContext::afterInput("comment", ActionType::Create);
        $checker = null;

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method("validate")->willReturnCallback(
            function (object $object) use (&$checker, $holder, $outer, $inner): ConstraintViolationList {
                if ($holder->get() === $outer) {
                    $checker->check(new stdClass(), $inner);
                    self::assertSame($outer, $holder->get());
                }

                return new ConstraintViolationList();
            },
        );

        $checker = new SymfonyConstraintChecker($validator, $holder);
        $checker->check(new stdClass(), $outer);

        self::assertFalse($holder->has());
    }
}
