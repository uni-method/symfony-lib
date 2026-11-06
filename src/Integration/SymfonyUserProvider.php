<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use UniMethod\Frame\Config\{AccessConfig, Action};
use UniMethod\Frame\Integration\{UserInterface, UserProviderInterface};

/**
 * Implementation of UserProviderInterface for Symfony Security.
 *
 * Реализация UserProviderInterface для Symfony Security.
 */
final readonly class SymfonyUserProvider implements UserProviderInterface
{
    public function __construct(
        private Security $security,
        private RoleHierarchyInterface $roleHierarchy,
    ) {
    }

    public function getCurrentUser(): ?UserInterface
    {
        $user = $this->security->getUser();

        return $user instanceof UserInterface ? $user : null;
    }

    public function canAccessAction(Action $action): bool
    {
        return !$action->access instanceof AccessConfig || $this->canAccess($action->access);
    }

    public function canAccess(AccessConfig $config): bool
    {
        $user = $this->getCurrentUser();

        if ($config->authRequired && !$user instanceof UserInterface) {
            // Authorization is required, but the user is not logged in
            //
            // Требуется авторизация, но пользователь не вошёл
            return false;
        }

        // If no roles are specified, it is enough to simply be authorized
        //
        // Если роли не указаны — значит, достаточно просто быть авторизованным
        if ($config->roles === null) {
            return true;
        }

        if (!$user instanceof UserInterface) {
            return false;
        }

        return $this->userHasAnyRole($user, $config->roles);
    }

    public function userHasAnyRole(
        UserInterface $user,
        array $requiredRoles,
    ): bool {
        // the user's roles + all inherited roles
        //
        // роли пользователя + все наследуемые роли
        $reachableRoles = $this->roleHierarchy->getReachableRoleNames(
            $user->getRoles(),
        );

        foreach ($requiredRoles as $role) {
            if (in_array($role, $reachableRoles, true)) {
                return true;
            }
        }

        return false;
    }
}
