<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use UniMethod\Frame\Integration\{UserInterface, UserProviderInterface};

/**
 * Role check taking the hierarchy into account if there is a UserProviderInterface
 * (symfony/security-bundle); without it — by UserInterface::hasRole().
 *
 * Проверка ролей с учётом иерархии, если есть UserProviderInterface
 * (symfony/security-bundle); без него — по UserInterface::hasRole().
 */
final readonly class RoleChecker
{
    public function __construct(private ?UserProviderInterface $userProvider = null)
    {
    }

    /**
     * @param list<string> $roles
     */
    public function hasAny(UserInterface $user, array $roles): bool
    {
        if ($roles === []) {
            return false;
        }

        if ($this->userProvider instanceof UserProviderInterface) {
            return $this->userProvider->userHasAnyRole($user, $roles);
        }

        foreach ($roles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }

        return false;
    }
}
