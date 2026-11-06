<?php

declare(strict_types=1);

namespace UniMethod\SymfonyLib\Integration;

use UniMethod\Frame\Integration\{ConstraintCheckerInterface, ConstraintContext, Error};

/**
 * A stub for a build without symfony/validator: ApiService requires
 * ConstraintCheckerInterface as a required argument. It gives no violations —
 * constraints fall entirely on the savers.
 *
 * Заглушка для сборки без symfony/validator: ApiService требует
 * ConstraintCheckerInterface обязательным аргументом. Нарушений не даёт —
 * ограничения целиком ложатся на сейверы.
 */
final readonly class NullConstraintChecker implements ConstraintCheckerInterface
{
    /**
     * @return array<Error>
     */
    public function check(object $object, ConstraintContext $context): array
    {
        return [];
    }
}
