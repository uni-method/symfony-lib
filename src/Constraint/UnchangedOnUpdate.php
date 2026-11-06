<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Constraint;

use Attribute;
use Symfony\Component\Validator\Constraint;

/**
 * The property value cannot be changed after creation. It is compared with the same
 * property of the "before" snapshot (ConstraintContext::$original); outside update and without
 * a snapshot — skip. A violation — 422.
 *
 * At the same time the property remains in `writable` of update — otherwise the client gets
 * the generic "Relation ... is not writable" instead of the domain text.
 *
 *   #[UnchangedOnUpdate(message: 'A zone cannot be moved to another garden', groups: ['update'])]
 *
 * Значение свойства нельзя менять после создания. Сравнивается с тем же
 * свойством снимка «до» (ConstraintContext::$original); вне update и без
 * снимка — пропуск. Нарушение — 422.
 *
 * Свойство при этом остаётся в `writable` у update — иначе клиент получит
 * общее «Relation ... is not writable» вместо доменного текста.
 *
 *   #[UnchangedOnUpdate(message: 'Зону нельзя перенести в другой огород', groups: ['update'])]
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class UnchangedOnUpdate extends Constraint
{
    public string $message = "This value cannot be changed.";

    /**
     * @param list<string>|null $groups
     */
    public function __construct(
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(groups: $groups, payload: $payload);

        if ($message !== null) {
            $this->message = $message;
        }
    }
}
