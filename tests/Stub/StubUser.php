<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Tests\Stub;

use UniMethod\Frame\Integration\UserInterface;

final class StubUser implements UserInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        private readonly string|int $id,
        private readonly array $roles = [],
        public ?StubCompany $company = null,
    ) {
    }

    public function getId(): string|int
    {
        return $this->id;
    }

    public function getUserIdentifier(): string|int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }
}
