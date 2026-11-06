<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Unit\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use UniMethod\Frame\Config\{AccessConfig, Action, ActionType};
use UniMethod\SymfonyLib\Integration\SymfonyUserProvider;
use UniMethod\SymfonyLib\Tests\Fixtures\App\Security\User;

final class SymfonyUserProviderTest extends TestCase
{
    /**
     * @return iterable<string, array{AccessConfig, bool, bool}>
     */
    public static function provideAccess(): iterable
    {
        yield "authRequired, пользователя нет" => [new AccessConfig(authRequired: true), false, false];
        yield "authRequired, пользователь есть" => [new AccessConfig(authRequired: true), true, true];
        yield "роль через иерархию ROLE_ADMIN → ROLE_USER" => [new AccessConfig(roles: ["ROLE_ADMIN"]), true, false];
        yield "роль пользователя" => [new AccessConfig(roles: ["ROLE_USER"]), true, true];
        yield "роль есть в иерархии ниже" => [new AccessConfig(roles: ["ROLE_GUEST"]), true, true];
        yield "роли без пользователя" => [new AccessConfig(roles: ["ROLE_USER"]), false, false];
    }

    /**
     * canAccessAction and canAccess give the same result for the same AccessConfig.
     *
     * canAccessAction и canAccess дают одно и то же для одного AccessConfig.
     */
    #[DataProvider("provideAccess")]
    public function testActionAccessMatchesAccess(AccessConfig $access, bool $loggedIn, bool $expected): void
    {
        $provider = $this->provider($loggedIn);

        self::assertSame($expected, $provider->canAccess($access));
        self::assertSame(
            $expected,
            $provider->canAccessAction(new Action(type: ActionType::Read, model: "post", access: $access)),
        );
    }

    public function testActionWithoutAccessIsAllowed(): void
    {
        self::assertTrue(
            $this->provider(false)->canAccessAction(new Action(type: ActionType::Read, model: "post")),
        );
    }

    private function provider(bool $loggedIn): SymfonyUserProvider
    {
        $security = $this->createStub(Security::class);
        $security->method("getUser")->willReturn($loggedIn ? new User("1") : null);

        // The user has ROLE_USER; ROLE_USER includes ROLE_GUEST
        //
        // У пользователя ROLE_USER; ROLE_USER включает ROLE_GUEST
        return new SymfonyUserProvider(
            $security,
            new RoleHierarchy(["ROLE_ADMIN" => ["ROLE_USER"], "ROLE_USER" => ["ROLE_GUEST"]]),
        );
    }
}
