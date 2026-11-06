<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * A filled property value requires one of the roles; an empty one is skipped.
 * For fields that are available to different roles in one action (the coarse filter is
 * the action's access.roles). A violation — 403 (interrupts).
 *
 * Empty values are only `null`, `''` and `[]`; `false`, `0`, `'0'` and objects
 * are filled. For flags and counters — a nullable type with a `null` default, otherwise
 * the `false`/`0` default will require the role from everyone.
 *
 *   #[HasRole(['ROLE_ADMIN'], message: 'Administrator only')]
 *
 * Заполненное значение свойства требует одной из ролей; пустое пропускается.
 * Для полей, которые в одном действии доступны разным ролям (грубый фильтр —
 * access.roles действия). Нарушение — 403 (прерывает).
 *
 * Пустые значения — только `null`, `''` и `[]`; `false`, `0`, `'0'` и объекты
 * заполнены. Для флагов и счётчиков — nullable-тип с дефолтом `null`, иначе
 * дефолт `false`/`0` потребует роль у каждого.
 *
 *   #[HasRole(['ROLE_ADMIN'], message: 'Только администратор')]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class HasRole extends Constraint
{
    public string $message = "Access denied.";

    /** @var list<string> */
    public array $roles;

    /**
     * @param array<string>|string $roles
     * @param list<string>|null $groups
     */
    public function __construct(
        array|string $roles,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);

        $this->roles = is_string($roles) ? [$roles] : array_values($roles);

        if ($message !== null) {
            $this->message = $message;
        }
    }
}
