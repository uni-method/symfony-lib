<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use ArrayObject;
use PHPUnit\Framework\Attributes\DataProvider;
use UniMethod\Frame\Config\ActionType;
use UniMethod\Frame\Integration\ConstraintContext;
use UniMethod\SymfonyLib\Constraint\HasRole;
use UniMethod\SymfonyLib\Exception\AccessDeniedException;
use UniMethod\SymfonyLib\Tests\Stub\StubUser;

final class RoleGuardedDto
{
    #[HasRole(["ROLE_MANAGER", "ROLE_ADMIN"], message: "Managers only")]
    public ?string $discount = null;

    #[HasRole("ROLE_ADMIN")]
    public array $flags = [];
}

final class AnyValueDto
{
    #[HasRole("ROLE_ADMIN")]
    public mixed $value = null;
}

final class HasRoleTest extends ConstraintTestCase
{
    public function testEmptyValuesNeedNoRole(): void
    {
        $dto = new RoleGuardedDto();

        $errors = $this->check($dto, ConstraintContext::afterInput("dto", ActionType::Create, new StubUser(1)));

        self::assertSame([], $errors);
    }

    public function testFilledValueWithRolePasses(): void
    {
        $dto = new RoleGuardedDto();
        $dto->discount = "10%";

        $errors = $this->check($dto, ConstraintContext::afterInput("dto", ActionType::Create, new StubUser(1, ["ROLE_MANAGER"])));

        self::assertSame([], $errors);
    }

    public function testFilledValueWithoutRoleIsDenied(): void
    {
        $dto = new RoleGuardedDto();
        $dto->discount = "10%";

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessage("Managers only");

        $this->check($dto, ConstraintContext::afterInput("dto", ActionType::Create, new StubUser(1, ["ROLE_USER"])));
    }

    public function testStringRoleFormAndAnonymous(): void
    {
        $dto = new RoleGuardedDto();
        $dto->flags = ["x"];

        $this->expectException(AccessDeniedException::class);

        $this->check($dto, ConstraintContext::afterInput("dto", ActionType::Create));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function provideValues(): iterable
    {
        yield "null" => [null, true];
        yield "''" => ["", true];
        yield "[]" => [[], true];
        yield "false" => [false, false];
        yield "0" => [0, false];
        yield "'0'" => ["0", false];
        yield "0.0" => [0.0, false];
        yield "пустой ArrayObject" => [new ArrayObject(), false];
    }

    /**
     * Empty values are only null, '' and []; everything else requires the role.
     *
     * Пустые значения — только null, '' и []; остальное требует роли.
     */
    #[DataProvider("provideValues")]
    public function testEmptiness(mixed $value, bool $empty): void
    {
        $dto = new AnyValueDto();
        $dto->value = $value;

        if (!$empty) {
            $this->expectException(AccessDeniedException::class);
        }

        $errors = $this->check($dto, ConstraintContext::afterInput("dto", ActionType::Create, new StubUser(1)));

        self::assertSame([], $errors);
    }
}
