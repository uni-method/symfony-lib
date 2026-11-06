<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The object is available only to the user at the path `path` (to User, not to id)
 * and to users with a role from `exceptRoles`. A violation — 403 (interrupts).
 *
 * It is put on the class with the groups of actions where the object already exists:
 *   #[OwnedByCurrentUser(path: 'author', exceptRoles: ['ROLE_ADMIN'], groups: ['read', 'update', 'delete'])]
 *
 * null on the path — skip (being required is a separate NotNull).
 *
 * Объект доступен только пользователю по пути `path` (до User, не до id)
 * и пользователям с ролью из `exceptRoles`. Нарушение — 403 (прерывает).
 *
 * Вешается на класс с группами действий, где объект уже существует:
 *   #[OwnedByCurrentUser(path: 'author', exceptRoles: ['ROLE_ADMIN'], groups: ['read', 'update', 'delete'])]
 *
 * null на пути — пропуск (обязательность — отдельный NotNull).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class OwnedByCurrentUser extends Constraint
{
    public string $message = "Access denied.";

    /**
     * @param list<string> $exceptRoles
     * @param list<string>|null $groups
     */
    public function __construct(
        public string $path,
        public array $exceptRoles = [],
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
