<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Fixtures\App\Security;

use Symfony\Component\Security\Core\User\UserInterface as SecurityUserInterface;
use UniMethod\Frame\Integration\UserInterface;

/**
 * A user both for Symfony Security and for the framework.
 *
 * Пользователь и для Symfony Security, и для фреймворка.
 */
final class User implements UserInterface, SecurityUserInterface
{
    public function __construct(private readonly string $id)
    {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUserIdentifier(): string
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return ["ROLE_USER"];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * Required in the Symfony 7.x UserInterface; in 8.0 the method was removed from the interface.
     *
     * Обязателен в UserInterface Symfony 7.x; в 8.0 метод из интерфейса убран.
     */
    public function eraseCredentials(): void
    {
    }
}
