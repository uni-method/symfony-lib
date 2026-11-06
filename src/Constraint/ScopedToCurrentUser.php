<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * Ownership on a list: the value at `path` (a filter by the user id) must
 * be filled and equal to the identifier of the current user. Users
 * with a role from `exceptRoles` are not restricted — they may not filter or
 * filter by any id.
 *
 *   #[ScopedToCurrentUser(path: 'filter.equal.userId', exceptRoles: ['ROLE_ADMIN'])]
 *   final class OrderListRequest { ... }
 *
 * An unfilled path — 422 (requiredMessage); someone else's id — 403 (interrupts).
 * `identityPath` — a path on the user object, if the comparison must be made not
 * with getUserIdentifier() (for example 'company.id').
 *
 * Владение на списке: значение по `path` (фильтр по id пользователя) обязано
 * быть заполнено и равно идентификатору текущего пользователя. Пользователи
 * с ролью из `exceptRoles` не ограничены — могут не фильтровать или
 * фильтровать по любому id.
 *
 *   #[ScopedToCurrentUser(path: 'filter.equal.userId', exceptRoles: ['ROLE_ADMIN'])]
 *   final class OrderListRequest { ... }
 *
 * Незаполненный путь — 422 (requiredMessage); чужой id — 403 (прерывает).
 * `identityPath` — путь на объекте пользователя, если сравнивать нужно не
 * с getUserIdentifier() (например 'company.id').
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ScopedToCurrentUser extends Constraint
{
    public string $requiredMessage = "This filter is required.";

    public string $message = "Access denied.";

    /**
     * @param list<string> $exceptRoles
     * @param list<string>|null $groups
     */
    public function __construct(
        public string $path,
        public array $exceptRoles = [],
        public ?string $identityPath = null,
        ?string $requiredMessage = null,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);

        if ($requiredMessage !== null) {
            $this->requiredMessage = $requiredMessage;
        }

        if ($message !== null) {
            $this->message = $message;
        }
    }

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
