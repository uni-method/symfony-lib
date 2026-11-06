<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Constraint;

use PHPUnit\Framework\TestCase;
use UniMethod\Frame\Integration\UserProviderInterface;
use UniMethod\SymfonyLib\Constraint\RoleChecker;
use UniMethod\SymfonyLib\Tests\Stub\StubUser;

final class RoleCheckerTest extends TestCase
{
    public function testEmptyRolesNeverMatch(): void
    {
        $checker = new RoleChecker();

        self::assertFalse($checker->hasAny(new StubUser(1, ["ROLE_ADMIN"]), []));
    }

    public function testWithoutProviderUsesUserHasRole(): void
    {
        $checker = new RoleChecker();
        $user = new StubUser(1, ["ROLE_USER"]);

        self::assertTrue($checker->hasAny($user, ["ROLE_ADMIN", "ROLE_USER"]));
        self::assertFalse($checker->hasAny($user, ["ROLE_ADMIN"]));
    }

    public function testWithProviderDelegatesHierarchyAwareCheck(): void
    {
        $user = new StubUser(1, ["ROLE_SUPER_ADMIN"]);
        $provider = $this->createMock(UserProviderInterface::class);
        $provider
            ->expects($this->once())
            ->method("userHasAnyRole")
            ->with($user, ["ROLE_ADMIN"])
            ->willReturn(true);

        $checker = new RoleChecker($provider);

        // the user himself does not have ROLE_ADMIN — it is given by the provider's hierarchy
        //
        // сам пользователь ROLE_ADMIN не имеет — её даёт иерархия провайдера
        self::assertTrue($checker->hasAny($user, ["ROLE_ADMIN"]));
    }
}
